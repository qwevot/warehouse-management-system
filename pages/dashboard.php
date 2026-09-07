<?php
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

require_once __DIR__ . '/../includes/auth.php';
require_login();

$totalQty = 0;
$available = 0;
$brandsInUse = 0;
$itemsInUse = 0;
$items = [];
$movements = [];
$dbError = '';

try {
    $pdo = db();

    // KPI counts (active = not deleted).
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(quantity), 0) 
        FROM inventory_list 
        WHERE deleted_at IS NULL
    ");

    $totalQty    = (int)$stmt->fetchColumn();
    $brandsInUse = (int)$pdo->query("SELECT COUNT(DISTINCT brand) FROM inventory_list WHERE deleted_at IS NULL AND brand IS NOT NULL AND brand <> ''")->fetchColumn();
    $itemsInUse  = (int)$pdo->query("SELECT COUNT(DISTINCT name) FROM inventory_list WHERE deleted_at IS NULL")->fetchColumn();

    // Items for the quick search (feeds window.STOCK_ITEMS).
    $items = $pdo->query("SELECT id, name, brand, serial, quantity, location, barcode, qr, status, photo FROM inventory_list WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();

    // Low stock items based on master items catalog (threshold <= 5 units across all active inventory)
    $lowStockThreshold = 5;
    $stmtLow = $pdo->prepare("
        SELECT mi.id, mi.product_code, mi.product_name, mi.brand, mi.product_class,
               (SELECT COALESCE(SUM(m.quantity), 0) 
                FROM inventory_list m 
                WHERE m.name = mi.product_name COLLATE utf8mb4_general_ci 
                  AND COALESCE(m.brand, '') = mi.brand COLLATE utf8mb4_general_ci 
                  AND m.deleted_at IS NULL 
                  AND m.status = 'available') AS total_qty
        FROM master_items mi
        WHERE mi.deleted_at IS NULL
        HAVING total_qty <= ?
        ORDER BY total_qty ASC, mi.product_name ASC
    ");
    $stmtLow->execute([$lowStockThreshold]);
    $lowStockItems = $stmtLow->fetchAll();
    $lowStockCount = count($lowStockItems);

    // Recent activity (limited to latest 30 movements for performance).
    $movements = fetch_movements($pdo, 30);
} catch (Exception $e) {
    $dbError = 'Could not load dashboard data from the database.';
}

$lowStockItems = $lowStockItems ?? [];
$lowStockCount = $lowStockCount ?? 0;
$lowStockThreshold = $lowStockThreshold ?? 5;

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

<div class="welcome-header">
    <h1>Welcome, <?php echo e($fullName); ?>!</h1>
</div>

<div class="kpi-grid">
    <a href="<?php echo BASE_URL; ?>/pages/brand.php" class="kpi-card kpi-link">
        <div class="kpi-label">Active Brands</div>
        <div class="kpi-value"><?php echo $brandsInUse; ?> Brands</div>
        <div class="kpi-hint">View Brands &rarr;</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/pages/items.php" class="kpi-card kpi-link">
        <div class="kpi-label">Active Items</div>
        <div class="kpi-value"><?php echo $itemsInUse; ?> Items</div>
        <div class="kpi-hint">View Items &rarr;</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/pages/index.php" class="kpi-card kpi-link">
        <div class="kpi-label">Total Items in Inventory</div>
        <div class="kpi-value"><?php echo $totalQty; ?> Units</div>
        <div class="kpi-hint">View Inventory List &rarr;</div>
    </a>
</div>

<div class="quick-actions-grid">
    <a href="javascript:void(0)" onclick="openScanModal()" class="quick-action-card green">
        <div class="quick-action-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="quick-action-content">
            <div class="quick-action-title">Check In</div>
            <div class="quick-action-desc">Scan item &amp; update custody location</div>
        </div>
        <div class="quick-action-arrow">&rarr;</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/pages/stock-in.php" class="quick-action-card blue">
        <div class="quick-action-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        </div>
        <div class="quick-action-content">
            <div class="quick-action-title">Stock In</div>
            <div class="quick-action-desc">Receive incoming items &amp; replenish stock</div>
        </div>
        <div class="quick-action-arrow">&rarr;</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/pages/stock-out.php" class="quick-action-card red">
        <div class="quick-action-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>
        </div>
        <div class="quick-action-content">
            <div class="quick-action-title">Stock Out</div>
            <div class="quick-action-desc">Issue, dispatch, or record outgoing items</div>
        </div>
        <div class="quick-action-arrow">&rarr;</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/pages/transfers.php" class="quick-action-card yellow">
        <div class="quick-action-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4"/></svg>
        </div>
        <div class="quick-action-content">
            <div class="quick-action-title">Transfers</div>
            <div class="quick-action-desc">Relocate items across shelves &amp; rows</div>
        </div>
        <div class="quick-action-arrow">&rarr;</div>
    </a>
</div>

<?php include __DIR__ . '/../includes/item-card-select.php'; ?>

<?php if (!empty($lowStockItems)): ?>
<section class="inventory-section low-stock-section" id="lowStockSection" style="margin-bottom: 24px; border: 1.5px solid var(--amber); background: #ffffff; border-radius: 12px; overflow: hidden; padding: 0; box-shadow: 0 4px 16px rgba(245, 158, 11, 0.15);">
    <button type="button" class="low-stock-toggle" id="lowStockToggle" onclick="toggleLowStockTable()" aria-expanded="false" style="width:100%; display:flex; justify-content:space-between; align-items:center; background:var(--amber); border:none; padding:14px 20px; cursor:pointer; text-align:left; font-family:inherit; color:#ffffff;">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <div style="display:flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:8px; background:rgba(255,255,255,0.25); color:#ffffff; flex-shrink:0;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:15px; font-weight:700; color:#ffffff;">Low Stock Alerts</span>
                <span style="font-size:12px; background:#ffffff; color:var(--amber-ink); padding:2px 8px; border-radius:999px; font-weight:800; box-shadow:0 1px 4px rgba(0,0,0,0.1);">
                    <?php echo $lowStockCount; ?>
                </span>
            </div>
            <span style="font-size:12.5px; color:rgba(255,255,255,0.9);">
                (&le; <?php echo $lowStockThreshold; ?> units remaining)
            </span>
        </div>
        <div style="display:flex; align-items:center; gap:6px; color:#ffffff; font-size:13px; font-weight:600;">
            <span id="lowStockToggleText">Show Details</span>
            <svg id="lowStockChevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.2s ease;">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </div>
    </button>

    <div class="table-container" id="lowStockTableContainer" style="display:none; border-top:1px solid rgba(0,0,0,0.08); background:#ffffff; margin:0; padding: 16px; 16px 16px; 16px">
        <table>
            <thead>
                <tr>
                    <th class="no-col">No.</th>
                    <th>Product Code</th>
                    <th>Product Name</th>
                    <th>Brand</th>
                    <th>Product Class</th>
                    <th>Total Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lowStockItems as $idx => $item): ?>
                    <tr>
                        <td class="no-col"><?php echo $idx + 1; ?></td>
                        <td><code><?php echo e($item['product_code']); ?></code></td>
                        <td><?php echo e($item['product_name']); ?></td>
                        <td><?php echo dash($item['brand']); ?></td>
                        <td><?php echo dash($item['product_class']); ?></td>
                        <td>
                            <strong style="color:<?php echo (int)$item['total_qty'] === 0 ? 'var(--red-ink)' : 'var(--amber-ink)'; ?>;">
                                <?php echo (int)$item['total_qty']; ?>
                            </strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

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
                    <th>User</th>
                    <th>Note</th>
                    <th class="sortable" data-sort="date" data-default-dir="desc" title="Sort by Date">
                        <div class="th-sort-inner">
                            <span>Date</span>
                            <svg class="sort-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody id="moveBody">
                <?php if (empty($movements)): ?>
                    <tr><td colspan="8" style="text-align:center; color:#9ca3af; padding:40px;">No activity yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($movements as $i => $m): ?>
                        <tr class="move-row" data-type="<?php echo e($m['type']); ?>" data-created="<?php echo strtotime($m['created_at']); ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <span class="status <?php echo e(movement_status_class($m['type'])); ?>">
                                    <?php echo e($typeLabels[$m['type']] ?? strtoupper((string)$m['type'])); ?>
                                </span>
                            </td>
                            <td><?php echo $m['item_name'] ? e($m['item_name']) : ('Item #' . (int)$m['item_id']); ?></td>
                            <td><?php echo movement_qty($m['type'], $m['quantity']); ?></td>
                            <td><?php echo dash($m['issued_to'] ?? null); ?></td>
                            <td><?php echo dash($m['modified_by'] ?? null); ?></td>
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
<?php include __DIR__ . '/../includes/checkin-modal.php'; ?>

<script>
    <?php include __DIR__ . '/../includes/stock-items-data.php'; ?>

    // A successful scan opens the Check-In modal to confirm/update location
    window.setScanHandler(function (item) {
        if (window.openCheckInModal) {
            window.openCheckInModal(item);
        } else {
            window.showItemCard(item);
        }
        return true;
    });

    // Toggle Low Stock Alert table visibility
    window.toggleLowStockTable = function (forceOpen) {
        const container = document.getElementById("lowStockTableContainer");
        const chevron   = document.getElementById("lowStockChevron");
        const toggleBtn = document.getElementById("lowStockToggle");
        const toggleTxt = document.getElementById("lowStockToggleText");
        if (!container) return;

        const isHidden = container.style.display === "none" || container.style.display === "";
        const open = (typeof forceOpen === "boolean") ? forceOpen : isHidden;

        if (open) {
            container.style.display = "block";
            if (chevron) chevron.style.transform = "rotate(180deg)";
            if (toggleTxt) toggleTxt.textContent = "Hide Details";
            if (toggleBtn) toggleBtn.setAttribute("aria-expanded", "true");
        } else {
            container.style.display = "none";
            if (chevron) chevron.style.transform = "rotate(0deg)";
            if (toggleTxt) toggleTxt.textContent = "Show Details";
            if (toggleBtn) toggleBtn.setAttribute("aria-expanded", "false");
        }
    };
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
            'created_ts'  => $m['created_at'] ? (int)strtotime($m['created_at']) : 0,
        ];
    }, $movements)); ?>;

    // Buckets a timestamp into the date-filter groups a row belongs to
    // (a row always belongs to 'all'; recent rows also match more specific
    // ranges, so selecting any combination works as a union).
    function moveDateBuckets(ts) {
        const t = parseInt(ts, 10);
        if (!t) return ['all'];
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime() / 1000;
        const out = ['all'];
        if (t >= start) out.push('today');
        if (t >= start - 6 * 86400) out.push('week');
        if (t >= start - 29 * 86400) out.push('month');
        return out;
    }

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
                { key: 'type',        label: 'Type' },
                { key: 'item_name',   label: 'Item' },
                { key: 'quantity',    label: 'Quantity' },
                { key: 'issued_to',   label: 'Issued To' },
                { key: 'modified_by', label: 'User' },
                { key: 'note',        label: 'Note' },
                { key: 'created_at',  label: 'Date' },
            ],
            filterGroups: [
                {
                    title: 'Type',
                    key: 'type',
                    dataValue: (r) => r.type,
                    options: [
                        { value: 'in',       label: 'INBOUND' },
                        { value: 'out',      label: 'OUTBOUND' },
                        { value: 'transfer', label: 'TRANSFER' },
                    ],
                },
                {
                    title: 'Date',
                    key: 'created',
                    value: (tr) => moveDateBuckets(tr.dataset.created),
                    dataValue: (r) => moveDateBuckets(r.created_ts),
                    options: [
                        { value: 'today', label: 'Today' },
                        { value: 'week',  label: 'Past Week' },
                        { value: 'month', label: 'Past Month' },
                        { value: 'all',   label: 'All' },
                    ],
                },
            ],
            defaultSortKey: 'date',
            defaultSortDir: 'desc',
            sorters: {
                'date_desc': function (a, b) { return (parseInt(b.dataset.created, 10) || 0) - (parseInt(a.dataset.created, 10) || 0); },
                'date_asc':  function (a, b) { return (parseInt(a.dataset.created, 10) || 0) - (parseInt(b.dataset.created, 10) || 0); },
                'newest':    function (a, b) { return (parseInt(b.dataset.created, 10) || 0) - (parseInt(a.dataset.created, 10) || 0); },
                'oldest':    function (a, b) { return (parseInt(a.dataset.created, 10) || 0) - (parseInt(b.dataset.created, 10) || 0); },
            },
            data:     window.MOVE_DATA,
            dataText: function (r) { return r.item_name + ' ' + r.type + ' ' + (r.note || '') + ' ' + (r.issued_to || ''); },
        });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
