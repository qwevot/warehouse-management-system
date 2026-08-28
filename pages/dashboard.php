<?php
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

require_once __DIR__ . '/../includes/auth.php';
require_login();

$available = 0;
$brandsInUse = 0;
$itemsInUse = 0;
$items = [];
$movements = [];
$dbError = '';

try {
    $pdo = db();

    // KPI counts (active = not deleted).
    $available   = (int)$pdo->query("SELECT COUNT(*) FROM inventory_list WHERE deleted_at IS NULL AND status = 'available'")->fetchColumn();
    $brandsInUse = (int)$pdo->query("SELECT COUNT(DISTINCT brand) FROM inventory_list WHERE deleted_at IS NULL AND brand IS NOT NULL AND brand <> ''")->fetchColumn();
    $itemsInUse  = (int)$pdo->query("SELECT COUNT(DISTINCT name) FROM inventory_list WHERE deleted_at IS NULL")->fetchColumn();

    // Items for the quick search (feeds window.STOCK_ITEMS).
    $items = $pdo->query("SELECT id, name, brand, serial, quantity, location, barcode, qr, status, photo FROM inventory_list WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();

    // Recent activity (all movements; paginated client-side).
    $movements = fetch_movements($pdo);
} catch (Exception $e) {
    $dbError = 'Could not load dashboard data from the database.';
}

$typeLabels = movement_type_labels();

include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/data-toolbar.php';
require_once __DIR__ . '/../includes/data-pagination.php';

$moveSortOptions = [
    ['newest', 'Newest First'],
    ['oldest', 'Oldest First'],
];
$moveToolbar    = buildToolbar('move', $moveSortOptions);
$movePagination = buildPagination('move', ['pageSize' => 5]);
?>

<?php if (!empty($dbError)): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>

<div class="kpi-grid">
    <a href="<?php echo BASE_URL; ?>/pages/brand.php" class="kpi-card blue kpi-link">
        <div class="kpi-label">Brands in Use</div>
        <div class="kpi-value"><?php echo $brandsInUse; ?></div>
        <div class="kpi-hint">View Brands -></div>
    </a>
    <a href="<?php echo BASE_URL; ?>/pages/index.php" class="kpi-card red kpi-link">
        <div class="kpi-label">Items in Use</div>
        <div class="kpi-value"><?php echo $itemsInUse; ?></div>
        <div class="kpi-hint">View Items -></div>
    </a>
    <a href="<?php echo BASE_URL; ?>/pages/index.php" class="kpi-card amber kpi-link">
        <div class="kpi-label">Items in Inventory</div>
        <div class="kpi-value"><?php echo $available; ?></div>
        <div class="kpi-hint">View Inventory List -></div>
    </a>
    <button type="button" class="kpi-card green scan-link" onclick="openScanModal()">
        <div class="kpi-label">Scan Item</div>
        <div class="scan-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="28" height="28" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><line x1="7" y1="12" x2="17" y2="12"></line></svg>
        </div>
        <div class="kpi-hint">Scan an item to view details</div>
    </button>
</div>

<?php include __DIR__ . '/../includes/item-card-select.php'; ?>

<section class="inventory-section">
    <div class="section-header">
        <div class="section-title">Recent Activity</div>
    </div>

    <div class="toolbar-row"><?php echo $moveToolbar; ?></div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Issued To</th>
                    <th>Note</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody id="moveBody">
                <?php if (empty($movements)): ?>
                    <tr><td colspan="7" style="text-align:center; color:#9ca3af; padding:40px;">No activity yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($movements as $i => $m): ?>
                        <tr class="move-row" data-created="<?php echo strtotime($m['created_at']); ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <span class="status <?php echo e(movement_status_class($m['type'])); ?>">
                                    <?php echo e($typeLabels[$m['type']] ?? strtoupper((string)$m['type'])); ?>
                                </span>
                            </td>
                            <td><?php echo $m['item_name'] ? e($m['item_name']) : ('Item #' . (int)$m['item_id']); ?></td>
                            <td><?php echo movement_qty($m['type'], $m['quantity']); ?></td>
                            <td><?php echo dash($m['issued_to'] ?? null); ?></td>
                            <td><?php echo dash($m['note'] ?? null); ?></td>
                            <td class="registered-on"><?php echo e(fmt_datetime($m['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $movePagination; ?>
</section>

<?php include __DIR__ . '/../includes/scan-modal.php'; ?>

<script>
    <?php include __DIR__ . '/../includes/stock-items-data.php'; ?>

    // A successful scan shows the item's details in the shared item card.
    window.setScanHandler(function (item) {
        window.showItemCard(item);
        return true;
    });
</script>

<script>
    window.MOVE_DATA = <?php echo json_encode(array_map(function ($m) {
        return [
            'type'        => $m['type'],
            'item_name'   => $m['item_name'],
            'quantity'    => (int)$m['quantity'],
            'issued_to'   => $m['issued_to'] ?? '',
            'note'        => $m['note'] ?? '',
            'modified_by' => $m['modified_by'] ?? '',
            'created_at'  => $m['created_at'] ? date('Y-m-d H:i', strtotime($m['created_at'])) : '',
        ];
    }, $movements)); ?>;

    document.addEventListener("DOMContentLoaded", function () {
        initDataTable({
            searchInput:    '#moveSearchInput',
            sortSelect:     '#moveSortSelect',
            tbody:          '#moveBody',
            row:            '.move-row',
            filterWrap:     '#moveFilterWrap',
            filterBtn:      '#moveFilterBtn',
            filterPanel:    '#moveFilterPanel',
            filterBadge:    '#moveFilterBadge',
            pagePrev:       '#movePagePrev',
            pageNext:       '#movePageNext',
            pageNumbers:    '#movePageNumbers',
            pageRange:      '#movePageRange',
            pagination:     '#movePagination',
            pageSizeSelect: '#movePageSizeSelect',
            exportFn:       'moveExport',
            exportName:     'recent-activity',
            exportCols: [
                { key: 'type',       label: 'Type' },
                { key: 'item_name',  label: 'Item' },
                { key: 'quantity',   label: 'Quantity' },
                { key: 'issued_to',  label: 'Issued To' },
                { key: 'note',       label: 'Note' },
                { key: 'created_at', label: 'Date' },
            ],
            filterGroups: [],
            sorters: {
                'newest': function (a, b) { return (parseInt(b.dataset.created, 10) || 0) - (parseInt(a.dataset.created, 10) || 0); },
                'oldest': function (a, b) { return (parseInt(a.dataset.created, 10) || 0) - (parseInt(b.dataset.created, 10) || 0); },
            },
            data:     window.MOVE_DATA,
            dataText: function (r) { return r.item_name + ' ' + r.type + ' ' + (r.note || '') + ' ' + (r.issued_to || ''); },
        });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
