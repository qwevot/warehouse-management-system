<?php
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

require_once __DIR__ . '/../includes/auth.php';
require_login();

$currentUser = current_user();
$userRole    = current_role();
$userCompany = trim((string)($currentUser['company'] ?? ''));
$fullName    = $currentUser['full_name'] ?? $currentUser['username'] ?? 'User';

$totalQty = 0;
$available = 0;
$brandsInUse = 0;
$itemsInUse = 0;
$items = [];
$movements = [];
$dbError = '';

try {
    $pdo = db();

    // KPI counts (active in-warehouse inventory).
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory_units 
        WHERE deleted_at IS NULL
        AND status = 'available'
        AND (deployment_site = 'In Warehouse' OR deployment_site = '' OR deployment_site IS NULL)
    ");

    $totalQty    = (int)$stmt->fetchColumn();
    $brandsInUse = (int)$pdo->query("SELECT COUNT(DISTINCT mi.brand) FROM inventory_units u JOIN master_items mi ON mi.id = u.master_item_id WHERE u.deleted_at IS NULL AND u.status = 'available' AND mi.brand IS NOT NULL AND mi.brand <> '' AND (u.deployment_site = 'In Warehouse' OR u.deployment_site = '' OR u.deployment_site IS NULL)")->fetchColumn();
    $itemsInUse  = (int)$pdo->query("SELECT COUNT(DISTINCT mi.product_name) FROM inventory_units u JOIN master_items mi ON mi.id = u.master_item_id WHERE u.deleted_at IS NULL AND u.status = 'available' AND (u.deployment_site = 'In Warehouse' OR u.deployment_site = '' OR u.deployment_site IS NULL)")->fetchColumn();



    $userRole = current_role();
    $userCompany = trim((string)($currentUser['company'] ?? ''));

    // Low stock items based on master items catalog
    $defaultThreshold = 5;
    $lowStockItems = [];
    $lowStockCount = 0;

    if ($userRole !== 'customer') {
        $stmtLow = $pdo->prepare("
            SELECT mi.id, mi.product_code, mi.product_name, mi.brand, mi.product_class,
                COALESCE(ias.threshold, ?) AS effective_threshold,
                (SELECT COUNT(*) 
                    FROM inventory_units m 
                    WHERE m.master_item_id = mi.id 
                    AND m.deleted_at IS NULL 
                    AND m.status = 'available'
                    AND (m.deployment_site = 'In Warehouse' OR m.deployment_site = '' OR m.deployment_site IS NULL)) AS total_qty
            FROM master_items mi
            LEFT JOIN item_alert_settings ias ON ias.master_item_id = mi.id
            WHERE mi.deleted_at IS NULL
            AND ias.alert_enabled = 1
            HAVING total_qty <= effective_threshold
            ORDER BY total_qty ASC, mi.product_name ASC
        ");
        $stmtLow->execute([$defaultThreshold]);
        $lowStockItems = $stmtLow->fetchAll();
        $lowStockCount = count($lowStockItems);
    }

    if ($userRole === 'customer' && $userCompany !== '') {
        $stmtCust = $pdo->prepare("
            SELECT COUNT(*) 
            FROM inventory_units u
            WHERE u.deleted_at IS NULL 
            AND (
                u.location LIKE ? OR u.location = ? 
                OR u.deployment_site LIKE ? OR u.deployment_site = ?
                OR u.location LIKE ? OR u.deployment_site LIKE ?
            )
        ");
        $compPattern = $userCompany . ' - %';
        $compTransit = 'In Transit (' . $userCompany . '%';
        $stmtCust->execute([$compPattern, $userCompany, $compPattern, $userCompany, $compTransit, $compTransit]);
        $customerEquipmentCount = (int)$stmtCust->fetchColumn();
    }

    // Recent activity (limited to latest 30 movements for performance).
    if ($userRole === 'customer') {
        if ($userCompany !== '') {
            $companyPattern = $userCompany . ' - %';
            $stmtMoves = $pdo->prepare("
                SELECT m.*, COALESCE(mi.product_name, '') AS item_name
                FROM stock_movements m
                LEFT JOIN inventory_units u ON u.id = m.item_id
                LEFT JOIN master_items mi ON mi.id = u.master_item_id
                WHERE m.issued_to LIKE ? 
                   OR m.issued_to = ?
                   OR m.note LIKE ?
                   OR m.modified_by = ?
                   OR m.modified_by LIKE ?
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 30
            ");
            $companyPrefix = '%' . $userCompany . '%';
            $stmtMoves->execute([
                $companyPattern,
                $userCompany,
                $companyPrefix,
                $userCompany,
                $companyPrefix
            ]);
            $movements = $stmtMoves->fetchAll();
        } else {
            $movements = [];
        }
    } else {
        $movements = fetch_movements($pdo, 30);
    }
} catch (Exception $e) {
    $dbError = 'Could not load dashboard data from the database.';
}

$lowStockItems = $lowStockItems ?? [];
$lowStockCount = $lowStockCount ?? 0;
$lowStockThreshold = $lowStockThreshold ?? 5;
$customerEquipmentCount = $customerEquipmentCount ?? 0;
$userRole = $userRole ?? current_role();
$userCompany = $userCompany ?? trim((string)($currentUser['company'] ?? ''));

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

<?php if ($userRole === 'customer'): ?>
<div class="kpi-grid">
    <a href="<?php echo BASE_URL; ?>/pages/customer-assets.php" class="kpi-card kpi-link">
        <div class="kpi-label">Deployed Equipment</div>
        <div class="kpi-value"><?php echo $customerEquipmentCount; ?> Units</div>
        <div class="kpi-hint"><?php echo $userCompany ? e($userCompany) . ' Sites' : 'View Equipment'; ?> &rarr;</div>
    </a>
</div>

<div class="quick-actions-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
    <a href="javascript:void(0)" onclick="openScanModal()" class="quick-action-card green">
        <div class="quick-action-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="quick-action-content">
            <div class="quick-action-title">Check In Asset</div>
            <div class="quick-action-desc">Scan or enter unit serial to receive at your site</div>
        </div>
        <div class="quick-action-arrow">&rarr;</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/pages/customer-assets.php" class="quick-action-card blue">
        <div class="quick-action-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        </div>
        <div class="quick-action-content">
            <div class="quick-action-title">My Equipment</div>
            <div class="quick-action-desc">View and audit all hardware deployed at your sites</div>
        </div>
        <div class="quick-action-arrow">&rarr;</div>
    </a>
</div>
<?php else: ?>
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
        <div class="kpi-label">Units in Inventory</div>
        <div class="kpi-value"><?php echo $totalQty; ?> Units</div>
        <div class="kpi-hint">View Warehouse Lots &rarr;</div>
    </a>
</div>

<?php if (user_can('movement.manage')): ?>
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
    <a href="<?php echo BASE_URL; ?>/pages/stock-out.php" class="quick-action-card blue">
        <div class="quick-action-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>
        </div>
        <div class="quick-action-content">
            <div class="quick-action-title">Stock Out</div>
            <div class="quick-action-desc">Issue, dispatch, or record outgoing items</div>
        </div>
        <div class="quick-action-arrow">&rarr;</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/pages/transfers.php" class="quick-action-card blue">
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
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../includes/item-card-select.php'; ?>

<?php if ($userRole !== 'customer' && !empty($lowStockItems)): ?>
<section class="inventory-section low-stock-section" id="lowStockSection">
    <div class="low-stock-header">
        <div class="low-stock-header-left">
            <div style="display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:8px; background:#f59e0b; color:#ffffff; flex-shrink:0; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.35);">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
            <div style="min-width: 0;">
                <div class="low-stock-title-wrap">
                    <span style="font-size:15px; font-weight:700; color:#92400e;">Low Stock Alerts</span>
                    <span class="low-stock-badge-pill">
                        <?php echo $lowStockCount; ?>
                    </span>
                </div>
                <div style="font-size:12px; color:#b45309; margin-top:2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Check items with low stocks</div>
            </div>
        </div>
        <div class="low-stock-header-right" style="display:flex; align-items:center; gap:10px;">
            <button type="button" class="btn sm primary" id="lowStockCollapseBtn" onclick="toggleLowStockCollapse()" style="background:#f59e0b; border-color:#d97706; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                <span id="lowStockCollapseText">Show Details</span>
                <svg id="lowStockCollapseChevron" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.2s ease;">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Collapsible Body (Hidden by default with 'Show Details') -->
    <div id="lowStockCollapsibleBody" style="display:none;">
        <?php foreach ($lowStockItems as $idx => $item): 
            $isOos = ((int)$item['total_qty'] === 0);
            $cardBg = $isOos ? '#fff1f2' : '#fefce8';
            $cardBorder = $isOos ? '#fecdd3' : '#fef08a';
            $badgeBg = $isOos ? '#ef4444' : '#f59e0b';
            $badgeColor = '#ffffff';
            $isExtra = ($idx >= 5);
        ?>
            <div class="low-stock-card <?php echo $isExtra ? 'low-stock-extra' : ''; ?>" style="display: <?php echo $isExtra ? 'none' : 'flex'; ?>; background: <?php echo $cardBg; ?>; border: 1px solid <?php echo $cardBorder; ?>;">
                <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                    <div style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo $badgeBg; ?>; flex-shrink: 0;"></div>
                    <div style="min-width: 0;">
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span style="font-weight: 600; font-size: 13.5px; color: var(--ink);"><?php echo e($item['product_name']); ?></span>
                            <code style="font-size: 11px; background: rgba(0,0,0,0.05); padding: 1px 5px; border-radius: 4px; color: var(--text);"><?php echo e($item['product_code']); ?></code>
                        </div>
                        <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">
                            <?php if (!empty($item['brand'])): ?>
                                <span>Brand: <strong style="color: var(--text);"><?php echo e($item['brand']); ?></strong></span> &bull; 
                            <?php endif; ?>
                            <span>Threshold: &le; <?php echo (int)$item['effective_threshold']; ?> units</span>
                        </div>
                    </div>
                </div>

                <div class="low-stock-card-right" style="display: flex; align-items: center; gap: 14px; flex-shrink: 0;">
                    <div style="text-align: right;">
                        <div style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: <?php echo $isOos ? '#b91c1c' : '#b45309'; ?>;">
                            <?php echo $isOos ? 'Out of Stock' : 'Low Stock'; ?>
                        </div>
                        <div style="font-size: 15px; font-weight: 800; color: <?php echo $isOos ? '#dc2626' : '#d97706'; ?>;">
                            <?php echo (int)$item['total_qty']; ?> <span style="font-size: 11px; font-weight: 600; color: var(--muted);">units</span>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/pages/stock-in.php" title="Receive Stock" class="btn sm secondary" style="padding: 5px 10px; font-size: 12px;">
                        Add Stock
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($lowStockCount > 5): ?>
        <div style="padding: 10px 16px 14px; text-align: center; border-top: 1px solid #fef3c7; background: #fffdf5;">
            <button type="button" id="lowStockMoreBtn" onclick="toggleLowStockMore()" class="btn sm secondary" style="font-weight: 600; font-size: 12.5px; background: #ffffff; border-color: #fde68a; color: #b45309; display: inline-flex; align-items: center; gap: 6px;">
                <span id="lowStockMoreText">Show More (<?php echo $lowStockCount - 5; ?> more)</span>
                <svg id="lowStockMoreChevron" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="transition: transform 0.2s ease;">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </button>
        </div>
    <?php endif; ?>
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
    // A successful scan opens the Check-In modal to confirm/update location
    window.setScanHandler(function (item) {
        if (window.openCheckInModal) {
            window.openCheckInModal(item);
        } else {
            window.showItemCard(item);
        }
        return true;
    });

    // Toggle Low Stock Alert "Show Details" / "Hide Details" collapse
    window.toggleLowStockCollapse = function () {
        const body    = document.getElementById("lowStockCollapsibleBody");
        const btnText = document.getElementById("lowStockCollapseText");
        const chevron = document.getElementById("lowStockCollapseChevron");
        if (!body) return;

        const isHidden = body.style.display === "none" || body.style.display === "";

        if (isHidden) {
            body.style.display = "block";
            if (btnText) btnText.textContent = "Hide Details";
            if (chevron) chevron.style.transform = "rotate(180deg)";
        } else {
            body.style.display = "none";
            if (btnText) btnText.textContent = "Show Details";
            if (chevron) chevron.style.transform = "rotate(0deg)";
        }
    };

    // Toggle Low Stock Alert "Show More" items
    window.toggleLowStockMore = function () {
        const extraCards = document.querySelectorAll(".low-stock-extra");
        const btnText    = document.getElementById("lowStockMoreText");
        const chevron    = document.getElementById("lowStockMoreChevron");
        if (!extraCards.length) return;

        const isHidden = extraCards[0].style.display === "none" || extraCards[0].style.display === "";

        extraCards.forEach(function (card) {
            card.style.display = isHidden ? "flex" : "none";
        });

        if (isHidden) {
            if (btnText) btnText.textContent = "Show Less";
            if (chevron) chevron.style.transform = "rotate(180deg)";
        } else {
            const count = extraCards.length;
            if (btnText) btnText.textContent = "Show More (" + count + " more)";
            if (chevron) chevron.style.transform = "rotate(0deg)";
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
                        { value: 'check_in', label: 'CHECK IN' },
                        { value: 'return',   label: 'RETURN' },
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
