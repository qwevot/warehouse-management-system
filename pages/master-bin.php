<?php
$pageTitle = 'Master Bin';
$activeNav = 'master-bin';

require_once __DIR__ . '/../includes/auth.php';
require_login();

$binnedBrands = [];
$binnedItems  = [];
$dbError = '';

try {
    $pdo = db();
    $binnedBrands = $pdo->query("SELECT id, name, updated_at, modified_by, deleted_at FROM master_brands WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC")->fetchAll();
    $binnedItems  = $pdo->query("SELECT id, product_code, product_name, brand, product_class, updated_at, modified_by, deleted_at FROM master_items WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC")->fetchAll();
} catch (Exception $e) {
    $dbError = 'Could not load the master bin from the database.';
}

include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/data-toolbar.php';
require_once __DIR__ . '/../includes/data-pagination.php';

$binBrandSortOptions = [
    ['name-asc',  'Name (A–Z)'],
    ['name-desc', 'Name (Z–A)'],
];
$binBrandToolbar    = buildToolbar('binBrand', $binBrandSortOptions);
$binBrandPagination = buildPagination('binBrand');

$binItemSortOptions = [
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
$binItemPagination = buildPagination('binItem');
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
                    <th>Binned At</th>
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
                                    <button type="button" class="btn icon-btn restore" title="Restore" onclick="restoreBinnedBrand(<?php echo (int)$b['id']; ?>, <?php echo e(json_encode($b['name'])); ?>)">
                                        <?php echo $restoreIcon; ?>
                                    </button>
                                    <button type="button" class="btn icon-btn delete" title="Delete permanently" onclick="purgeBinnedBrand(<?php echo (int)$b['id']; ?>, <?php echo e(json_encode($b['name'])); ?>)">
                                        <?php echo $deleteIcon; ?>
                                    </button>
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
                    <th>Binned At</th>
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
                                    <form method="POST" action="<?php echo BASE_URL; ?>/actions/restore-master-item.php" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                                        <button type="submit" class="btn icon-btn restore" title="Restore"><?php echo $restoreIcon; ?></button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if (user_can('permanent_delete')): ?>
                                    <form method="POST" action="<?php echo BASE_URL; ?>/actions/delete-master-item-permanent.php" style="display:inline;">
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

<script>
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
    sorters: {
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
    sorters: {
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
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
