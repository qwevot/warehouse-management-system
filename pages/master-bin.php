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

<?php if (!empty($dbError)): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>

<?php if (isset($_GET['added'])): ?>
    <div class="flash success"><?php echo e($_GET['added']); ?></div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="flash error"><?php echo e($_GET['error']); ?></div>
<?php endif; ?>

<?php
$restoreIcon = '<svg fill="currentColor" viewBox="0 0 14 14" role="img" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M 21,11 14,4 V 8 C 7,9 4,14 3,19 5.5,15.5 9,13.9 14,13.9 V 18 l 7,-7 z" transform="matrix(-0.66666667,0,0,0.66666667,15,-0.66666671)"></path></g></svg>';
$deleteIcon  = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M3 6.52381C3 6.12932 3.32671 5.80952 3.72973 5.80952H8.51787C8.52437 4.9683 8.61554 3.81504 9.45037 3.01668C10.1074 2.38839 11.0081 2 12 2C12.9919 2 13.8926 2.38839 14.5496 3.01668C15.3844 3.81504 15.4756 4.9683 15.4821 5.80952H20.2703C20.6733 5.80952 21 6.12932 21 6.52381C21 6.9183 20.6733 7.2381 20.2703 7.2381H3.72973C3.32671 7.2381 3 6.9183 3 6.52381Z" fill="currentColor"></path> <path d="M11.6066 22H12.3935C15.101 22 16.4547 22 17.3349 21.1368C18.2151 20.2736 18.3052 18.8576 18.4853 16.0257L18.7448 11.9452C18.8425 10.4086 18.8913 9.64037 18.4498 9.15352C18.0082 8.66667 17.2625 8.66667 15.7712 8.66667H8.22884C6.7375 8.66667 5.99183 8.66667 5.55026 9.15352C5.1087 9.64037 5.15756 10.4086 5.25528 11.9452L5.51479 16.0257C5.69489 18.8576 5.78494 20.2736 6.66513 21.1368C7.54532 22 8.89906 22 11.6066 22Z" fill="currentColor"></path> </g></svg>';
?>

<section class="inventory-section" id="binnedBrands">

    <div class="section-header">
        <div class="section-title">Binned Brands</div>
    </div>

    <div class="table-container">
        <div class="toolbar-row"><?php echo $binBrandToolbar; ?></div>
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
                        <tr class="bin-brand-row" data-name="<?php echo e(mb_strtolower($b['name'])); ?>" data-binned-ts="<?php echo strtotime($b['deleted_at']); ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="row-actions">
                                    <form method="POST" action="<?php echo BASE_URL; ?>/actions/restore-brand.php" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                                        <button type="submit" class="btn icon-btn restore" title="Restore"><?php echo $restoreIcon; ?></button>
                                    </form>
                                    <form method="POST" action="<?php echo BASE_URL; ?>/actions/delete-brand-permanent.php" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                                        <button type="submit" class="btn icon-btn delete" title="Permanently Delete" onclick="return confirm('Permanently delete this brand? This cannot be undone.')"><?php echo $deleteIcon; ?></button>
                                    </form>
                                </div>
                            </td>
                            <td><?php echo e($b['name']); ?></td>
                            <td class="registered-on"><?php echo e(date('Y-m-d H:i', strtotime($b['deleted_at']))); ?></td>
                            <td><?php echo $b['modified_by'] ? e($b['modified_by']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
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

    <div class="table-container">
        <div class="toolbar-row"><?php echo $binItemToolbar; ?></div>
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
                                    <form method="POST" action="<?php echo BASE_URL; ?>/actions/restore-master-item.php" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                                        <button type="submit" class="btn icon-btn restore" title="Restore"><?php echo $restoreIcon; ?></button>
                                    </form>
                                    <form method="POST" action="<?php echo BASE_URL; ?>/actions/delete-master-item-permanent.php" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                                        <button type="submit" class="btn icon-btn delete" title="Permanently Delete" onclick="return confirm('Permanently delete this master item? This cannot be undone.')"><?php echo $deleteIcon; ?></button>
                                    </form>
                                </div>
                            </td>
                            <td><?php echo e($it['product_code']); ?></td>
                            <td><?php echo e($it['product_name']); ?></td>
                            <td><?php echo $it['brand'] ? e($it['brand']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
                            <td><?php echo $it['product_class'] ? e($it['product_class']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
                            <td class="registered-on"><?php echo e(date('Y-m-d H:i', strtotime($it['deleted_at']))); ?></td>
                            <td><?php echo $it['modified_by'] ? e($it['modified_by']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
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
    ];
}, $binnedBrands)); ?>;

window.BIN_ITEM_DATA = <?php echo json_encode(array_map(function($it) {
    return [
        'product_code'   => $it['product_code'],
        'product_name'   => $it['product_name'],
        'brand'          => $it['brand'] ?? '',
        'product_class'  => $it['product_class'] ?? '',
        'modified_by'    => $it['modified_by'] ?? '',
        'deleted_at'     => $it['deleted_at'] ? date('Y-m-d H:i', strtotime($it['deleted_at'])) : '',
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
    filterGroups: [],
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
    filterGroups: [],
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
