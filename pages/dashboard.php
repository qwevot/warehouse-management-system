<?php
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

require_once __DIR__ . '/../includes/auth.php';
require_login();

$totalItems = $available = $outItems = 0;
$items = [];
$movements = [];
$dbError = '';

try {
    $pdo = db();

    // KPI counts (active = not deleted).
    $totalItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory_list WHERE deleted_at IS NULL")->fetchColumn();
    $available  = (int)$pdo->query("SELECT COUNT(*) FROM inventory_list WHERE deleted_at IS NULL AND status = 'available'")->fetchColumn();
    $outItems   = (int)$pdo->query("SELECT COUNT(*) FROM inventory_list WHERE deleted_at IS NULL AND status = 'taken'")->fetchColumn();

    // Items for the quick search (feeds window.STOCK_ITEMS).
    $items = $pdo->query("SELECT id, name, brand, serial, quantity, location, barcode, qr, status, photo FROM inventory_list WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();

    // Recent activity (all movements; paginated client-side).
    $movements = $pdo->query(
        "SELECT m.*, COALESCE(i.name, '') AS item_name
         FROM stock_movements m
         LEFT JOIN inventory_list i ON i.id = m.item_id
         ORDER BY m.created_at DESC, m.id DESC"
    )->fetchAll();
} catch (Exception $e) {
    $dbError = 'Could not load dashboard data from the database.';
}

$typeLabels = [
    'in'       => 'INBOUND',
    'out'      => 'OUTBOUND',
    'transfer' => 'TRANSFER',
];

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
    <div class="kpi-card">
        <div class="kpi-label">Total Items</div>
        <div class="kpi-value"><?php echo $totalItems; ?></div>
    </div>
    <div class="kpi-card available">
        <div class="kpi-label">Available</div>
        <div class="kpi-value"><?php echo $available; ?></div>
    </div>
    <div class="kpi-card taken">
        <div class="kpi-label">Out of Warehouse</div>
        <div class="kpi-value"><?php echo $outItems; ?></div>
    </div>
</div>

<section class="inventory-section dashboard-find">
    <div class="section-header">
    <button type="button" class="btn primary find-scan" onclick="openScanModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><line x1="7" y1="12" x2="17" y2="12"></line></svg>
        Scan an Item
    </button>

    <?php include __DIR__ . '/../includes/item-card-select.php'; ?>
</section>

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
                                <span class="status <?php echo e($m['type'] === 'in' ? 'inbound' : ($m['type'] === 'out' ? 'outbound' : 'transfer')); ?>">
                                    <?php echo e($typeLabels[$m['type']] ?? strtoupper((string)$m['type'])); ?>
                                </span>
                            </td>
                            <td><?php echo $m['item_name'] ? e($m['item_name']) : ('Item #' . (int)$m['item_id']); ?></td>
                            <td>
                                <?php if ($m['type'] === 'in'): ?>+<?php echo (int)$m['quantity'];
                                elseif ($m['type'] === 'out'): ?>-<?php echo (int)$m['quantity'];
                                else: ?><span class="dash">—</span><?php endif; ?>
                            </td>
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

    // Dashboard quick-search (self-contained; the shared initStockSearch binds
    // to #stockItemSearch which this page intentionally does not use).
    (function () {
        const input = document.getElementById("dashSearch");
        const box = document.getElementById("dashSuggest");
        if (!input || !box) return;
        const items = window.STOCK_ITEMS || [];

        function closeBox() { box.innerHTML = ""; box.style.display = "none"; }

        function render(list) {
            box.innerHTML = "";
            list.forEach(function (it) {
                const d = document.createElement("div");
                d.className = "suggest-item";
                const label = document.createElement("div");
                label.className = "suggest-label";
                label.textContent = it.name + (it.brand ? "  ·  " + it.brand : "");
                const sub = document.createElement("div");
                sub.className = "suggest-sub";
                sub.textContent = (it.serial || "") + "  —  On hand: " + it.quantity;
                d.appendChild(label);
                d.appendChild(sub);
                d.addEventListener("mousedown", function (e) {
                    e.preventDefault();
                    input.value = it.name + (it.brand ? "  (" + it.brand + ")" : "");
                    window.showItemCard(it);
                    closeBox();
                });
                box.appendChild(d);
            });
            box.style.display = list.length ? "block" : "none";
        }

        function matches(q) {
            return items.filter(function (it) {
                return String(it.name || "").toLowerCase().indexOf(q) !== -1 ||
                       String(it.brand || "").toLowerCase().indexOf(q) !== -1 ||
                       String(it.serial || "").toLowerCase().indexOf(q) !== -1;
            }).slice(0, 8);
        }

        input.addEventListener("focus", function () {
            const q = input.value.trim().toLowerCase();
            render(q ? matches(q) : items.slice(0, 8));
        });

        input.addEventListener("input", function () {
            const q = input.value.trim().toLowerCase();
            if (!q) { closeBox(); return; }
            render(matches(q));
        });

        input.addEventListener("blur", function () { setTimeout(closeBox, 150); });

        document.addEventListener("click", function (e) {
            if (!input.contains(e.target) && !box.contains(e.target)) closeBox();
        });
    })();
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
