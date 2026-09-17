<?php
$pageTitle = 'My Equipment';
$activeNav = 'customer-assets';

require_once __DIR__ . '/../includes/auth.php';
require_login();

$currentUser = current_user();
$role = current_role();
$company = trim((string)($currentUser['company'] ?? ''));

// Restrict this page to customer role, internal staff, and guest viewer
if ($role !== 'customer' && !in_array($role, ['super admin', 'admin', 'engineer', 'technician', 'guest'], true)) {
    redirect_to('dashboard', 'error', 'Access denied.');
}

$rows = [];
$total = 0;
$dbError = '';
$allCompanies = [];
$selectedCompany = trim((string)($_GET['company'] ?? ''));

try {
    $pdo = db();

    // Query units located at this customer's site(s) or in transit to them
    if ($role === 'customer') {
        $selectedCompany = $company;
        if ($company === '') {
            $dbError = 'Your account does not have a company assigned. Please contact an administrator.';
        } else {
            $stmt = $pdo->prepare("
                SELECT u.id, u.inventory_id, u.serial, u.qr, u.status, u.created_at, u.updated_at, u.modified_by,
                       COALESCE(NULLIF(u.deployment_site, ''), u.location, '') AS location,
                       u.bin_location, u.deployment_site,
                       mi.product_name AS name, mi.brand AS brand, COALESCE(u.photo, '') AS photo, u.barcode, 1 AS quantity
                FROM inventory_units u
                JOIN master_items mi ON mi.id = u.master_item_id
                WHERE u.deleted_at IS NULL 
                  AND (
                      u.deployment_site LIKE ? OR u.deployment_site = ? 
                      OR u.location LIKE ? OR u.location = ?
                      OR u.deployment_site LIKE ? OR u.location LIKE ?
                  )
                ORDER BY u.id DESC
            ");
            $companyPattern = $company . ' - %';
            $transitPattern = 'In Transit (' . $company . '%';
            $stmt->execute([$companyPattern, $company, $companyPattern, $company, $transitPattern, $transitPattern]);
            $rows = $stmt->fetchAll();
            $total = count($rows);
        }
    } else {
        // Super admin / admin view:
        // 1. Fetch distinct companies from users and deployed unit locations (including in-transit destinations)
        $userCompanies = $pdo->query("
            SELECT DISTINCT company 
            FROM users 
            WHERE role = 'customer' AND company IS NOT NULL AND company <> ''
        ")->fetchAll(PDO::FETCH_COLUMN);

        $locList = $pdo->query("
            SELECT DISTINCT deployment_site 
            FROM inventory_units 
            WHERE deleted_at IS NULL 
              AND deployment_site NOT IN ('In Warehouse', 'Main Office')
        ")->fetchAll(PDO::FETCH_COLUMN);

        $locCompanies = [];
        foreach ($locList as $l) {
            $lClean = trim((string)$l);
            if (preg_match('/^In Transit \((.+?)(?: - [^\)]+)?\)$/i', $lClean, $m)) {
                $locCompanies[] = trim($m[1]);
            } else {
                $parts = explode(' - ', $lClean, 2);
                if (!empty($parts[0])) {
                    $locCompanies[] = trim($parts[0]);
                }
            }
        }

        $allCompanies = array_values(array_unique(array_filter(array_merge($userCompanies, $locCompanies))));
        sort($allCompanies, SORT_NATURAL | SORT_FLAG_CASE);

        // 2. Query units: filtered by company if specified, or all customer-deployed & in-transit units
        if ($selectedCompany !== '' && in_array($selectedCompany, $allCompanies, true)) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.inventory_id, u.serial, u.qr, u.status, u.created_at, u.updated_at, u.modified_by,
                       COALESCE(NULLIF(u.deployment_site, ''), u.location, '') AS location,
                       u.bin_location, u.deployment_site,
                       mi.product_name AS name, mi.brand AS brand, COALESCE(u.photo, '') AS photo, u.barcode, 1 AS quantity
                FROM inventory_units u
                JOIN master_items mi ON mi.id = u.master_item_id
                WHERE u.deleted_at IS NULL 
                  AND (
                      u.deployment_site LIKE ? OR u.deployment_site = ? 
                      OR u.location LIKE ? OR u.location = ?
                      OR u.deployment_site LIKE ? OR u.location LIKE ?
                  )
                ORDER BY u.id DESC
            ");
            $companyPattern = $selectedCompany . ' - %';
            $transitPattern = 'In Transit (' . $selectedCompany . '%';
            $stmt->execute([$companyPattern, $selectedCompany, $companyPattern, $selectedCompany, $transitPattern, $transitPattern]);
            $rows = $stmt->fetchAll();
            $total = count($rows);
        } else {
            $selectedCompany = '';
            $stmt = $pdo->query("
                SELECT u.id, u.inventory_id, u.serial, u.qr, u.status, u.created_at, u.updated_at, u.modified_by,
                       COALESCE(NULLIF(u.deployment_site, ''), u.location, '') AS location,
                       u.bin_location, u.deployment_site,
                       mi.product_name AS name, mi.brand AS brand, COALESCE(u.photo, '') AS photo, u.barcode, 1 AS quantity
                FROM inventory_units u
                JOIN master_items mi ON mi.id = u.master_item_id
                WHERE u.deleted_at IS NULL 
                  AND (
                      (u.deployment_site NOT IN ('In Warehouse', 'Main Office') AND u.deployment_site LIKE '% - %')
                      OR (u.location LIKE '% - %' AND u.location <> 'Main Office')
                      OR u.deployment_site LIKE 'In Transit%'
                      OR u.location LIKE 'In Transit%'
                  )
                ORDER BY u.id DESC
            ");
            $rows = $stmt->fetchAll();
            $total = count($rows);
        }
    }
} catch (Exception $e) {
    $dbError = 'Could not load equipment data from the database.';
}

$pageTitle = ($selectedCompany !== '' && $role !== 'customer') ? 'Customer Equipment - ' . $selectedCompany : 'Customer Equipment';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/flash.php';

require_once __DIR__ . '/../includes/data-toolbar.php';
require_once __DIR__ . '/../includes/data-pagination.php';

$assetSortOptions = [
    ['newest', 'Newest First'],
    ['oldest', 'Oldest First'],
    ['name', 'Item Name A-Z'],
];

$assetToolbar    = buildToolbar('asset', $assetSortOptions);
$assetPagination = buildPagination('asset', ['pageSize' => 10]);
?>

<?php if (!empty($dbError)): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>

<section class="inventory-section" id="inventory">

    <div class="section-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div class="section-title">
            <?php 
                if ($role === 'customer') {
                    echo 'My Deployed Equipment' . ($company ? ' (' . e($company) . ')' : '');
                } else {
                    echo $selectedCompany !== '' ? 'Deployed Equipment — ' . e($selectedCompany) : 'Customer Deployed Equipment';
                }
            ?>
        </div>

        <?php if ($role !== 'customer' && !empty($allCompanies)): ?>
        <form method="GET" action="<?php echo BASE_URL; ?>/pages/customer-assets.php" style="display:flex; align-items:center; gap:8px; margin:0;">
            <label for="companyFilterSelect" style="font-size:13px; font-weight:600; color:var(--text, #334155); margin:0;">Company:</label>
            <select name="company" id="companyFilterSelect" onchange="this.form.submit()" style="padding:7px 12px; font-size:13px; border-radius:6px; border:1px solid var(--border-color, #cbd5e1); background:var(--card-bg, #fff); color:var(--text, #1e293b); font-weight:600; cursor:pointer;">
                <option value="">All Companies (<?php echo count($allCompanies); ?>)</option>
                <?php foreach ($allCompanies as $comp): ?>
                    <option value="<?php echo e($comp); ?>" <?php echo $selectedCompany === $comp ? 'selected' : ''; ?>>
                        <?php echo e($comp); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>

    <div class="toolbar-row"><?php echo $assetToolbar; ?></div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Action</th>
                    <th>Photo</th>
                    <th>Item</th>
                    <th>Brand</th>
                    <th>Location / Site</th>
                    <th>Status</th>
                    <th>Serial No.</th>
                    <?php if ($role !== 'customer'): ?>
                        <th>Barcode No.</th>
                    <?php endif; ?>
                    <th class="sortable" data-sort="date" data-default-dir="desc" title="Sort by Date">
                        <div class="th-sort-inner">
                            <span>Deployed Date</span>
                            <svg class="sort-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </th>
                    <th>Last Modified</th>
                </tr>
            </thead>
            <tbody id="assetBody">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="<?php echo $role === 'customer' ? '10' : '11'; ?>" style="text-align:center; color:#9ca3af; padding:40px;">No deployed equipment found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $i => $item): ?>
                        <tr class="asset-row" 
                            data-id="<?php echo (int)$item['id']; ?>" 
                            data-brand="<?php echo e($item['brand']); ?>"
                            data-status="<?php echo e($item['status']); ?>"
                            data-loc="<?php echo e($item['location']); ?>"
                            data-name="<?php echo e($item['name']); ?>"
                            data-created="<?php echo (int)strtotime((string)$item['created_at']); ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="btn icon-btn view" title="View Unit Details" onclick="openItemModal(<?php echo (int)$item['id']; ?>)">
                                        <?php echo render_icon('view'); ?>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($item['photo'])): ?>
                                    <img class="item-photo" src="<?php echo e(BASE_URL . '/' . $item['photo']); ?>" alt="<?php echo e($item['name']); ?>" loading="lazy" decoding="async" onclick="openItemModal(<?php echo (int)$item['id']; ?>)">
                                <?php else: ?>
                                    <div class="item-photo"></div>
                                <?php endif; ?>
                            </td>
                            <td><div class="item-name"><?php echo e($item['name']); ?></div></td>
                            <td><?php echo dash($item['brand']); ?></td>
                            <td><span class="location"><?php echo e($item['location']); ?></span></td>
                            <td>
                                <?php
                                $locStr = (string)$item['location'];
                                $depStr = (string)($item['deployment_site'] ?? '');
                                $isTransit = str_starts_with($locStr, 'In Transit') || str_starts_with($depStr, 'In Transit') || $item['status'] === 'in_transit';
                                $isInactive = (!empty($item['deleted_at']) || in_array(strtolower((string)$item['status']), ['inactive', 'taken'], true));
                                if ($isTransit) {
                                    $stLabel = 'IN TRANSIT';
                                    $stClass = 'in_transit';
                                } elseif ($isInactive) {
                                    $stLabel = 'INACTIVE';
                                    $stClass = 'inactive';
                                } else {
                                    $stLabel = 'ACTIVE';
                                    $stClass = 'available';
                                }
                                ?>
                                <span class="status <?php echo $stClass; ?>">
                                    <?php echo $stLabel; ?>
                                </span>
                            </td>
                            <td><span class="serial"><?php echo e($item['serial']); ?></span></td>
                            <?php if ($role !== 'customer'): ?>
                            <td>
                                <?php if (!empty($item['barcode'])): ?>
                                    <span class="serial" style="font-size:11px;"><?php echo e($item['barcode']); ?></span>
                                <?php else: ?>
                                    <span class="dash">—</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td><span class="registered-on"><?php echo e(fmt_datetime($item['created_at'])); ?></span></td>
                            <td><span class="registered-on"><?php echo !empty($item['updated_at']) ? e(fmt_datetime($item['updated_at'])) : '—'; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $assetPagination; ?>

</section>

<?php include __DIR__ . '/../includes/inventory-card.php'; ?>

<script>
    window.ASSET_DATA = <?php echo json_encode(array_map(function ($it) use ($role) {
        $locStr = (string)$it['location'];
        $depStr = (string)($it['deployment_site'] ?? '');
        $isTransit = str_starts_with($locStr, 'In Transit') || str_starts_with($depStr, 'In Transit') || $it['status'] === 'in_transit';
        return [
            'id'          => (int)$it['id'],
            'serial'      => $it['serial'],
            'name'        => $it['name'],
            'brand'       => $it['brand'],
            'quantity'    => 1,
            'location'    => $it['location'],
            'barcode'     => ($role === 'customer') ? '' : ($it['barcode'] ?? ''),
            'qr'          => $it['qr'] ?? '',
            'photo'       => !empty($it['photo']) ? BASE_URL . '/' . $it['photo'] : '',
            'status'      => $isTransit ? 'in_transit' : $it['status'],
            'is_transit'  => $isTransit,
            'created_at'  => $it['created_at'] ? date('Y-m-d H:i', strtotime($it['created_at'])) : '',
            'created_ts'  => $it['created_at'] ? (int)strtotime($it['created_at']) : 0,
            'updated_at'  => !empty($it['updated_at']) ? date('Y-m-d H:i', strtotime($it['updated_at'])) : '',
            'modified_by' => $it['modified_by'] ?? '',
        ];
    }, $rows)); ?>;

    window.INVENTORY = window.ASSET_DATA;
    window.INVENTORY_MODE = 'units';

    document.addEventListener("DOMContentLoaded", function () {
        initDataTable({
            searchInput:    '#assetSearchInput',
            sortSelect:     '#assetSortSelect',
            tbody:          '#assetBody',
            row:            '.asset-row',
            filterWrap:     '#assetFilterWrap',
            filterBtn:      '#assetFilterBtn',
            filterPanel:    '#assetFilterPanel',
            filterBadge:    '#assetFilterBadge',
            pagePrev:       '#assetPagePrev',
            pageNext:       '#assetPageNext',
            pageNumbers:    '#assetPageNumbers',
            pageRange:      '#assetPageRange',
            pagination:     '#assetPagination',
            pageSizeSelect: '#assetPageSizeSelect',
            exportFn:       'assetExport',
            exportName:     'my-equipment',
            exportCols: [
                { key: 'serial',      label: 'Serial No.' },
                { key: 'name',        label: 'Item Name' },
                { key: 'brand',       label: 'Brand' },
                { key: 'location',    label: 'Location / Site' },
                { key: 'status',      label: 'Status' },
                { key: 'created_at',  label: 'Deployed Date' },
                { key: 'updated_at',  label: 'Last Modified' },
            ],
            filterGroups: [
                {
                    title: 'Brand',
                    key: 'brand',
                    dataValue: (r) => r.brand,
                    options: Array.from(new Set(window.ASSET_DATA.map(r => r.brand).filter(Boolean))).map(b => ({ value: b, label: b })),
                },
                {
                    title: 'Status',
                    key: 'status',
                    dataValue: (r) => r.status,
                    options: [
                        { value: 'available',   label: 'Active' },
                        { value: 'in_transit',  label: 'In Transit' },
                        { value: 'taken',       label: 'Inactive' },
                    ],
                },
            ],
            defaultSortKey: 'date',
            defaultSortDir: 'desc',
            sorters: {
                'name':       function (a, b) { return (a.dataset.name || '').localeCompare(b.dataset.name || ''); },
                'newest':     function (a, b) { return (parseInt(b.dataset.created, 10) || 0) - (parseInt(a.dataset.created, 10) || 0); },
                'oldest':     function (a, b) { return (parseInt(a.dataset.created, 10) || 0) - (parseInt(b.dataset.created, 10) || 0); },
                'date_desc':  function (a, b) { return (parseInt(b.dataset.created, 10) || 0) - (parseInt(a.dataset.created, 10) || 0); },
                'date_asc':   function (a, b) { return (parseInt(a.dataset.created, 10) || 0) - (parseInt(b.dataset.created, 10) || 0); },
            },
            data:     window.ASSET_DATA,
            dataText: function (r) { return (r.name || '') + ' ' + (r.brand || '') + ' ' + (r.serial || '') + ' ' + (r.location || ''); },
        });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
