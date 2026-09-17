<?php
$pageTitle = 'Master Bin';
$activeNav = 'master-bin';

require_once __DIR__ . '/../includes/auth.php';
require_staff();

$binnedBrands    = [];
$binnedItems     = [];
$binnedCustomers = [];
$dbError = '';

try {
    $pdo = db();
    $binnedBrands = $pdo->query("
        SELECT b.id, b.name, b.updated_at, b.modified_by, b.deleted_at,
               (SELECT COUNT(*) FROM inventory_units u 
                JOIN master_items mi ON mi.id = u.master_item_id 
                WHERE mi.brand = b.name AND u.deleted_at IS NOT NULL) AS binned_units
        FROM master_brands b 
        WHERE b.deleted_at IS NOT NULL 
        ORDER BY b.deleted_at DESC
    ")->fetchAll();
    $binnedItems = $pdo->query("
        SELECT mi.id, mi.product_code, mi.product_name, mi.brand, mi.product_class, mi.updated_at, mi.modified_by, mi.deleted_at,
               (SELECT COUNT(*) FROM inventory_units u 
                WHERE u.master_item_id = mi.id AND u.deleted_at IS NOT NULL) AS binned_units
        FROM master_items mi 
        WHERE mi.deleted_at IS NOT NULL 
        ORDER BY mi.deleted_at DESC
    ")->fetchAll();
    $binnedCustomers = $pdo->query("
        SELECT c.id, c.name, c.company_code, c.contact_person, c.updated_at, c.modified_by, c.deleted_at
        FROM master_customers c 
        WHERE c.deleted_at IS NOT NULL 
        ORDER BY c.deleted_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    $dbError = 'Could not load the master bin from the database.';
}

include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/data-toolbar.php';
require_once __DIR__ . '/../includes/data-pagination.php';

$binBrandSortOptions = [
    ['date-desc', 'Binned Date (Newest)'],
    ['date-asc',  'Binned Date (Oldest)'],
    ['name-asc',  'Name (A–Z)'],
    ['name-desc', 'Name (Z–A)'],
];
$binBrandToolbar    = buildToolbar('binBrand', $binBrandSortOptions);
$binBrandPagination = buildPagination('binBrand', [
    'deletePermanentlyAction' => 'deleteAllBinnedMaster()',
]);

$binItemSortOptions = [
    ['date-desc',   'Binned Date (Newest)'],
    ['date-asc',    'Binned Date (Oldest)'],
    ['code-asc',    'Code (A–Z)'],
    ['code-desc',   'Code (Z–A)'],
    ['name-asc',    'Name (A–Z)'],
    ['name-desc',   'Name (Z–A)'],
    ['brand-asc',   'Brand (A–Z)'],
    ['brand-desc',  'Brand (Z–A)'],
    ['class-asc',   'Class (A–Z)'],
    ['class-desc',  'Class (Z–A)'],
];
$binItemToolbar    = buildToolbar('binItem', $binItemSortOptions);
$binItemPagination = buildPagination('binItem', [
    'deletePermanentlyAction' => 'deleteAllBinnedMaster()',
]);

$binCustomerSortOptions = [
    ['date-desc', 'Binned Date (Newest)'],
    ['date-asc',  'Binned Date (Oldest)'],
    ['name-asc',  'Name (A–Z)'],
    ['name-desc', 'Name (Z–A)'],
];
$binCustomerToolbar    = buildToolbar('binCustomer', $binCustomerSortOptions);
$binCustomerPagination = buildPagination('binCustomer', [
    'deletePermanentlyAction' => 'deleteAllBinnedMaster()',
]);
?>

<?php include __DIR__ . '/../includes/flash.php'; ?>

<?php
$restoreIcon = icon_restore();
$deleteIcon  = icon_delete();
?>

<section class="inventory-section" id="binnedBrands">

    <div class="section-header">
        <div class="section-title">Binned Brands</div>
    </div>

    <div class="toolbar-row"><?php echo $binBrandToolbar; ?></div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="action-col">Action</th>
                    <th>Brand</th>
                    <th class="sortable" data-sort="date" data-default-dir="desc" title="Sort by Binned Date">
                        <div class="th-sort-inner">
                            <span>Binned At</span>
                            <svg class="sort-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </th>
                    <th>Modified By</th>
                </tr>
            </thead>
            <tbody id="binBrandBody">
                <?php if (empty($binnedBrands)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:#9ca3af; padding:40px;">
                            No binned brands.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($binnedBrands as $i => $b): ?>
                        <tr class="bin-brand-row" data-name="<?php echo e(mb_strtolower($b['name'])); ?>" data-deleted="<?php echo strtotime($b['deleted_at']); ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="row-actions">
                                      <?php if (user_can('master.manage')): ?>
                                      <button type="button" class="btn icon-btn restore" title="Restore" onclick="handleRestoreBrand(<?php echo (int)$b['id']; ?>, <?php echo e(json_encode($b['name'])); ?>, <?php echo (int)($b['binned_units'] ?? 0); ?>)"><?php echo $restoreIcon; ?></button>
                                      <?php endif; ?>
                                     <?php if (user_can('permanent_delete')): ?>
                                     <form method="POST" action="<?php echo BASE_URL; ?>/actions/delete-brand-permanent.php" style="display:inline;">
                                         <?php echo csrf_field(); ?>
                                         <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                                         <button type="submit" class="btn icon-btn delete" title="Permanently Delete" onclick="return confirm('Permanently delete this brand? This cannot be undone.')"><?php echo $deleteIcon; ?></button>
                                     </form>
                                     <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo e($b['name']); ?></td>
                            <td class="registered-on"><?php echo e(fmt_datetime($b['deleted_at'])); ?></td>
                            <td><?php echo dash($b['modified_by']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $binBrandPagination; ?>

</section>

<section class="inventory-section" id="binnedItems" style="margin-top:20px;">

    <div class="section-header">
        <div class="section-title">Binned Master Items</div>
    </div>

    <div class="toolbar-row"><?php echo $binItemToolbar; ?></div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="action-col">Action</th>
                    <th>Product Code</th>
                    <th>Product Name</th>
                    <th>Brand</th>
                    <th>Product Class</th>
                    <th class="sortable" data-sort="date" data-default-dir="desc" title="Sort by Binned Date">
                        <div class="th-sort-inner">
                            <span>Binned At</span>
                            <svg class="sort-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </th>
                    <th>Modified By</th>
                </tr>
            </thead>
            <tbody id="binItemBody">
                <?php if (empty($binnedItems)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; color:#9ca3af; padding:40px;">
                            No binned master items.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($binnedItems as $i => $it): ?>
                        <tr class="bin-item-row" data-code="<?php echo e(mb_strtolower($it['product_code'])); ?>" data-name="<?php echo e(mb_strtolower($it['product_name'])); ?>" data-brand="<?php echo e(mb_strtolower($it['brand'] ?? '')); ?>" data-class="<?php echo e(mb_strtolower($it['product_class'] ?? '')); ?>" data-binned-ts="<?php echo strtotime($it['deleted_at']); ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="row-actions">
                                      <?php if (user_can('master.manage')): ?>
                                      <button type="button" class="btn icon-btn restore" title="Restore" onclick="handleRestoreItem(<?php echo (int)$it['id']; ?>, <?php echo e(json_encode($it['product_name'])); ?>, <?php echo (int)($it['binned_units'] ?? 0); ?>)"><?php echo $restoreIcon; ?></button>
                                      <?php endif; ?>
                                     <?php if (user_can('permanent_delete')): ?>
                                     <form method="POST" action="<?php echo BASE_URL; ?>/actions/delete-master-item-permanent.php" style="display:inline;">
                                         <?php echo csrf_field(); ?>
                                         <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                                         <button type="submit" class="btn icon-btn delete" title="Permanently Delete" onclick="return confirm('Permanently delete this master item? This cannot be undone.')"><?php echo $deleteIcon; ?></button>
                                     </form>
                                     <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo e($it['product_code']); ?></td>
                            <td><?php echo e($it['product_name']); ?></td>
                            <td><?php echo dash($it['brand']); ?></td>
                            <td><?php echo dash($it['product_class']); ?></td>
                            <td class="registered-on"><?php echo e(fmt_datetime($it['deleted_at'])); ?></td>
                            <td><?php echo dash($it['modified_by']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $binItemPagination; ?>

</section>

<section class="inventory-section" id="binnedCustomers" style="margin-top:20px;">

    <div class="section-header">
        <div class="section-title">Binned Customers</div>
    </div>

    <div class="toolbar-row"><?php echo $binCustomerToolbar; ?></div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="action-col">Action</th>
                    <th>Customer Code</th>
                    <th>Company / Customer Name</th>
                    <th>Contact Person</th>
                    <th class="sortable" data-sort="date" data-default-dir="desc" title="Sort by Binned Date">
                        <div class="th-sort-inner">
                            <span>Binned At</span>
                            <svg class="sort-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </th>
                    <th>Modified By</th>
                </tr>
            </thead>
            <tbody id="binCustomerBody">
                <?php if (empty($binnedCustomers)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:#9ca3af; padding:40px;">
                            The master customer bin is empty.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($binnedCustomers as $i => $c): ?>
                        <tr class="bin-customer-row" data-name="<?php echo e(mb_strtolower($c['name'])); ?>" data-deleted="<?php echo $c['deleted_at'] ? (int)strtotime($c['deleted_at']) : 0; ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="row-actions">
                                    <?php if (user_can('master.manage')): ?>
                                    <button type="button" class="btn icon-btn restore" title="Restore Customer" onclick="handleRestoreCustomer(<?php echo (int)$c['id']; ?>, <?php echo json_encode($c['name']); ?>)">
                                        <?php echo $restoreIcon; ?>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (user_can('permanent_delete')): ?>
                                    <button type="button" class="btn icon-btn delete" title="Permanently Delete" onclick="handlePermanentDeleteCustomer(<?php echo (int)$c['id']; ?>, <?php echo json_encode($c['name']); ?>)">
                                        <?php echo $deleteIcon; ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo !empty($c['company_code']) ? '<span class="badge gray">' . e($c['company_code']) . '</span>' : '—'; ?></td>
                            <td><strong><?php echo e($c['name']); ?></strong></td>
                            <td><?php echo dash($c['contact_person']); ?></td>
                            <td class="registered-on"><?php echo e(fmt_datetime($c['deleted_at'])); ?></td>
                            <td><?php echo dash($c['modified_by']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $binCustomerPagination; ?>

</section>

<script>
window.BIN_CUSTOMER_DATA = <?php echo json_encode(array_map(function($c) {
    return [
        'name'           => $c['name'],
        'company_code'   => $c['company_code'] ?? '',
        'contact_person' => $c['contact_person'] ?? '',
        'modified_by'    => $c['modified_by'] ?? '',
        'deleted_at'     => $c['deleted_at'] ? date('Y-m-d H:i', strtotime($c['deleted_at'])) : '',
        'deleted_ts'     => strtotime($c['deleted_at'] ?? ''),
    ];
}, $binnedCustomers)); ?>;
window.BIN_BRAND_DATA = <?php echo json_encode(array_map(function($b) {
    return [
        'name'       => $b['name'],
        'modified_by'=> $b['modified_by'] ?? '',
        'deleted_at' => $b['deleted_at'] ? date('Y-m-d H:i', strtotime($b['deleted_at'])) : '',
        'deleted_ts' => strtotime($b['deleted_at'] ?? ''),
    ];
}, $binnedBrands)); ?>;

<?php
$uniqueItemBrands = array_values(array_filter(array_unique(array_column($binnedItems, 'brand'))));
sort($uniqueItemBrands);
$uniqueItemClasses = array_values(array_filter(array_unique(array_column($binnedItems, 'product_class'))));
sort($uniqueItemClasses);
?>

window.BIN_ITEM_DATA = <?php echo json_encode(array_map(function($it) {
    return [
        'product_code'   => $it['product_code'],
        'product_name'   => $it['product_name'],
        'brand'          => $it['brand'] ?? '',
        'product_class'  => $it['product_class'] ?? '',
        'modified_by'    => $it['modified_by'] ?? '',
        'deleted_at'     => $it['deleted_at'] ? date('Y-m-d H:i', strtotime($it['deleted_at'])) : '',
        'deleted_ts'     => strtotime($it['deleted_at'] ?? ''),
    ];
}, $binnedItems)); ?>;

    function handleRestoreBrand(id, name, binnedUnits) {
        if (binnedUnits > 0) {
            const restoreItems = confirm(
                "Brand \"" + name + "\" has " + binnedUnits + " associated item/unit(s) in the bin.\n\n" +
                "Click OK to restore the brand AND all its " + binnedUnits + " item(s).\n" +
                "Click Cancel if you want to restore ONLY the brand definition."
            );
            window.submitPostForm("<?php echo BASE_URL; ?>/actions/restore-brand.php", {
                id: id,
                restore_units: restoreItems ? 1 : 0
            });
        } else {
            if (!confirm("Restore brand \"" + name + "\"?")) return;
            window.submitPostForm("<?php echo BASE_URL; ?>/actions/restore-brand.php", { id: id, restore_units: 0 });
        }
    }

    function handleRestoreItem(id, name, binnedUnits) {
        if (binnedUnits > 0) {
            const restoreUnits = confirm(
                "Master item \"" + name + "\" has " + binnedUnits + " inventory unit(s) in the bin.\n\n" +
                "Click OK to restore the master item AND all " + binnedUnits + " unit(s).\n" +
                "Click Cancel if you want to restore ONLY the master item definition."
            );
            window.submitPostForm("<?php echo BASE_URL; ?>/actions/restore-master-item.php", {
                id: id,
                restore_units: restoreUnits ? 1 : 0
            });
        } else {
            if (!confirm("Restore master item \"" + name + "\"?")) return;
            window.submitPostForm("<?php echo BASE_URL; ?>/actions/restore-master-item.php", { id: id, restore_units: 0 });
        }
    }

    function handleRestoreCustomer(id, name) {
        if (!confirm("Restore customer \"" + name + "\"?")) return;
        window.submitPostForm("<?php echo BASE_URL; ?>/actions/restore-customer.php", { id: id, from: "master-bin" });
    }

    async function handlePermanentDeleteCustomer(id, name) {
        const ok = await window.promptModal({
            title: "Permanently Delete Customer?",
            message: "Permanently delete customer \"" + name + "\"? This operation cannot be undone.",
            confirmLabel: "Permanently Delete",
            withNote: false,
        });
        if (ok === null) return;
        window.submitPostForm("<?php echo BASE_URL; ?>/actions/delete-customer.php", { id: id, permanent: 1 });
    }

    window.deleteAllBinnedMaster = function () {
        if (!confirm("Are you sure you want to permanently delete ALL binned master items, brands, and customers? This operation cannot be undone.")) return;
        window.submitPostForm("<?php echo BASE_URL; ?>/actions/clear-master-bin.php", {});
    };

    document.addEventListener("DOMContentLoaded", function () {
initDataTable({
    searchInput:    '#binBrandSearchInput',
    sortSelect:     '#binBrandSortSelect',
    tbody:          '#binBrandBody',
    row:            '.bin-brand-row',
    filterWrap:     '#binBrandFilterWrap',
    filterBtn:      '#binBrandFilterBtn',
    filterPanel:    '#binBrandFilterPanel',
    filterBadge:    '#binBrandFilterBadge',
    pagePrev:       '#binBrandPagePrev',
    pageNext:       '#binBrandPageNext',
    pageNumbers:    '#binBrandPageNumbers',
    pageRange:      '#binBrandPageRange',
    pagination:     '#binBrandPagination',
    pageSizeSelect: '#binBrandPageSizeSelect',
    exportFn:       'binBrandExport',
    exportName:     'binned-brands',
    exportCols: [
        { key: 'name',        label: 'Brand Name' },
        { key: 'modified_by', label: 'Modified By' },
        { key: 'deleted_at',  label: 'Binned At' },
    ],
    filterGroups: [
        {
            title: 'Binned Date',
            key: 'deleted',
            value: (tr) => createdBuckets(tr.dataset.deleted),
            dataValue: (r) => createdBuckets(r.deleted_ts),
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
        'date-desc': function(a,b) { return (parseInt(b.dataset.deleted, 10) || 0) - (parseInt(a.dataset.deleted, 10) || 0); },
        'date-asc':  function(a,b) { return (parseInt(a.dataset.deleted, 10) || 0) - (parseInt(b.dataset.deleted, 10) || 0); },
        'name-asc':  function(a,b) { return a.dataset.name.localeCompare(b.dataset.name); },
        'name-desc': function(a,b) { return b.dataset.name.localeCompare(a.dataset.name); },
    },
    data:     window.BIN_BRAND_DATA,
    dataText: function(r) { return r.name; },
});

initDataTable({
    searchInput:    '#binItemSearchInput',
    sortSelect:     '#binItemSortSelect',
    tbody:          '#binItemBody',
    row:            '.bin-item-row',
    filterWrap:     '#binItemFilterWrap',
    filterBtn:      '#binItemFilterBtn',
    filterPanel:    '#binItemFilterPanel',
    filterBadge:    '#binItemFilterBadge',
    pagePrev:       '#binItemPagePrev',
    pageNext:       '#binItemPageNext',
    pageNumbers:    '#binItemPageNumbers',
    pageRange:      '#binItemPageRange',
    pagination:     '#binItemPagination',
    pageSizeSelect: '#binItemPageSizeSelect',
    exportFn:       'binItemExport',
    exportName:     'binned-items',
    exportCols: [
        { key: 'product_code',  label: 'Product Code' },
        { key: 'product_name',  label: 'Product Name' },
        { key: 'brand',          label: 'Brand' },
        { key: 'product_class',  label: 'Product Class' },
        { key: 'modified_by',    label: 'Modified By' },
        { key: 'deleted_at',     label: 'Binned At' },
    ],
    filterGroups: [
        <?php if (!empty($uniqueItemBrands)): ?>
        {
            title: 'Brand',
            key: 'brand',
            options: <?php echo json_encode(array_map(function($b) {
                return ['value' => mb_strtolower($b), 'label' => $b];
            }, $uniqueItemBrands)); ?>,
        },
        <?php endif; ?>
        <?php if (!empty($uniqueItemClasses)): ?>
        {
            title: 'Product Class',
            key: 'class',
            options: <?php echo json_encode(array_map(function($c) {
                return ['value' => mb_strtolower($c), 'label' => $c];
            }, $uniqueItemClasses)); ?>,
        },
        <?php endif; ?>
        {
            title: 'Binned Date',
            key: 'binned',
            value: (tr) => createdBuckets(tr.dataset.binnedTs),
            dataValue: (r) => createdBuckets(r.deleted_ts),
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
        'date-desc':   function(a,b) { return (parseInt(b.dataset.binnedTs, 10) || 0) - (parseInt(a.dataset.binnedTs, 10) || 0); },
        'date-asc':    function(a,b) { return (parseInt(a.dataset.binnedTs, 10) || 0) - (parseInt(b.dataset.binnedTs, 10) || 0); },
        'code-asc':    function(a,b) { return a.dataset.code.localeCompare(b.dataset.code); },
        'code-desc':   function(a,b) { return b.dataset.code.localeCompare(a.dataset.code); },
        'name-asc':    function(a,b) { return a.dataset.name.localeCompare(b.dataset.name); },
        'name-desc':   function(a,b) { return b.dataset.name.localeCompare(a.dataset.name); },
        'brand-asc':   function(a,b) { return a.dataset.brand.localeCompare(b.dataset.brand); },
        'brand-desc':  function(a,b) { return b.dataset.brand.localeCompare(a.dataset.brand); },
        'class-asc':   function(a,b) { return a.dataset.class.localeCompare(b.dataset.class); },
        'class-desc':  function(a,b) { return b.dataset.class.localeCompare(a.dataset.class); },
    },
    data:     window.BIN_ITEM_DATA,
    dataText: function(r) { return r.product_code + ' ' + r.product_name + ' ' + r.brand; },
});

initDataTable({
    searchInput:    '#binCustomerSearchInput',
    sortSelect:     '#binCustomerSortSelect',
    tbody:          '#binCustomerBody',
    row:            '.bin-customer-row',
    filterWrap:     '#binCustomerFilterWrap',
    filterBtn:      '#binCustomerFilterBtn',
    filterPanel:    '#binCustomerFilterPanel',
    filterBadge:    '#binCustomerFilterBadge',
    pagePrev:       '#binCustomerPagePrev',
    pageNext:       '#binCustomerPageNext',
    pageNumbers:    '#binCustomerPageNumbers',
    pageRange:      '#binCustomerPageRange',
    pagination:     '#binCustomerPagination',
    pageSizeSelect: '#binCustomerPageSizeSelect',
    exportFn:       'binCustomerExport',
    exportName:     'binned-customers',
    exportCols: [
        { key: 'name',           label: 'Company Name' },
        { key: 'company_code',   label: 'Customer Code' },
        { key: 'contact_person', label: 'Contact Person' },
        { key: 'modified_by',    label: 'Modified By' },
        { key: 'deleted_at',     label: 'Binned At' },
    ],
    filterGroups: [
        {
            title: 'Binned Date',
            key: 'deleted',
            value: (tr) => createdBuckets(tr.dataset.deleted),
            dataValue: (r) => createdBuckets(r.deleted_ts),
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
        'date-desc': function(a,b) { return (parseInt(b.dataset.deleted, 10) || 0) - (parseInt(a.dataset.deleted, 10) || 0); },
        'date-asc':  function(a,b) { return (parseInt(a.dataset.deleted, 10) || 0) - (parseInt(b.dataset.deleted, 10) || 0); },
        'name-asc':  function(a,b) { return a.dataset.name.localeCompare(b.dataset.name); },
        'name-desc': function(a,b) { return b.dataset.name.localeCompare(a.dataset.name); },
    },
    data:     window.BIN_CUSTOMER_DATA,
    dataText: function(r) { return r.name + ' ' + (r.company_code || '') + ' ' + (r.contact_person || ''); },
});
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
