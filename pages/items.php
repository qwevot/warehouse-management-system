<?php
$pageTitle = 'Items';
$activeNav = 'items';

require_once __DIR__ . '/../includes/auth.php';
require_login();

$items = [];
$dbError = '';

try {
    $pdo = db();
    // READ: all active master item types, with how many active inventory items use each.
    $items = $pdo->query(
        "SELECT mi.id, mi.product_code, mi.product_name, mi.brand, mi.product_class, mi.created_at, mi.updated_at, mi.modified_by,
                (SELECT COUNT(*) FROM inventory_list m WHERE m.name = mi.product_name COLLATE utf8mb4_general_ci AND COALESCE(m.brand, '') = mi.brand COLLATE utf8mb4_general_ci AND m.deleted_at IS NULL) AS used_by,
                (SELECT COALESCE(SUM(m.quantity), 0) FROM inventory_list m WHERE m.name = mi.product_name COLLATE utf8mb4_general_ci AND COALESCE(m.brand, '') = mi.brand COLLATE utf8mb4_general_ci AND m.deleted_at IS NULL AND m.status = 'available') AS total_qty
         FROM master_items mi
         WHERE mi.deleted_at IS NULL
         ORDER BY mi.product_code ASC"
    )->fetchAll();
} catch (Exception $e) {
    $dbError = 'Could not load master items from the database.';
}

$brands = all_brands();

$nextProductCode = '';
try {
    $nextProductCode = next_master_code($pdo);
} catch (Exception $e) {
    $nextProductCode = '';
}

include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/data-toolbar.php';
require_once __DIR__ . '/../includes/data-pagination.php';

$itemsSortOptions = [
    ['code-asc',    'Code (A–Z)'],
    ['code-desc',   'Code (Z–A)'],
    ['name-asc',    'Name (A–Z)'],
    ['name-desc',   'Name (Z–A)'],
    ['brand-asc',   'Brand (A–Z)'],
    ['brand-desc',  'Brand (Z–A)'],
    ['class-asc',   'Class (A–Z)'],
    ['class-desc',  'Class (Z–A)'],
    ['modified-desc','Modified (Newest)'],
    ['modified-asc', 'Modified (Oldest)'],
];
$itemsToolbar    = buildToolbar('items', $itemsSortOptions);
$itemsPagination = buildPagination('items');
?>

<?php include __DIR__ . '/../includes/flash.php'; ?>

<section class="inventory-section" id="items">

    <div class="sticky-zone">
        <div class="section-header">
            <div class="section-title">Master Items</div>
            <?php if (user_can('master.manage')): ?>
                <button type="button" class="btn primary add-button add-toggle-btn" id="addMasterItemToggleBtn" onclick="toggleMasterAddForm()">+ Add Item</button>
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/../includes/master-items-card.php'; ?>

    </div>

    <?php echo $itemsToolbar; ?>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="action-col">Action</th>
                    <th>Product Name</th>
                    <th>Brand</th>
                    <th>Qty</th>
                    <th>Product Code</th>
                    <th>Product Class</th>
                    <th>Last Modified</th>
                    <th>Modified By</th>
                </tr>
            </thead>
            <tbody id="itemsBody">
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center; color:#9ca3af; padding:40px;">
                            No master items yet. Add one above.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $i => $it): ?>
                        <tr class="master-item-row" data-code="<?php echo e($it['product_code']); ?>" data-name="<?php echo e($it['product_name']); ?>" data-brand="<?php echo e($it['brand'] ?? ''); ?>" data-class="<?php echo e($it['product_class'] ?? ''); ?>" data-qty="<?php echo (int)$it['total_qty']; ?>" data-modified-ts="<?php echo $it['updated_at'] ? strtotime($it['updated_at']) : 0; ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="row-actions">
                                    <?php if (user_can('master.manage')): ?>
                                    <button type="button" class="btn icon-btn edit" title="Edit" onclick="openEditItemModal(<?php echo (int)$it['id']; ?>, <?php echo e(json_encode($it)); ?>)">
                                        <?php echo icon_edit(); ?>
                                    </button>
                                    <button type="button" class="btn icon-btn delete" title="Delete" onclick="confirmDeleteItem(<?php echo (int)$it['id']; ?>, <?php echo e(json_encode($it['product_code'])); ?>, <?php echo (int)$it['used_by']; ?>)">
                                        <?php echo icon_delete(); ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo e($it['product_name']); ?></td>
                            <td><?php echo dash($it['brand']); ?></td>
                            <td><?php echo (int)$it['total_qty']; ?></td>
                            <td><?php echo e($it['product_code']); ?></td>
                            <td><?php echo dash($it['product_class']); ?></td>
                            <td class="registered-on"><?php echo dash(fmt_datetime($it['updated_at'])); ?></td>
                            <td><?php echo dash($it['modified_by']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $itemsPagination; ?>

</section>

<!-- Edit master item modal -->
<div class="modal-backdrop" id="editItemModal">
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/save-master-item.php" class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Master Item</div>
            <button type="button" class="modal-close" onclick="closeEditItemModal()">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id" id="editItemId">
            <div class="form-grid">
                <div class="field">
                    <label for="editProductCode">Product code</label>
                    <input type="text" id="editProductCode" name="product_code" required maxlength="4">
                </div>
                <div class="field">
                    <label for="editProductName">Product name</label>
                    <input type="text" id="editProductName" name="product_name" required maxlength="255">
                </div>
                <div class="field">
                    <label for="editBrand">Brand</label>
                    <?php
                    $brandSelectId = 'editBrand';
                    $brandSelectRequired = true;
                    $brandSelectPlaceholder = '— Brand —';
                    $brandOptions = $brands;
                    include __DIR__ . '/../includes/brand-select.php';
                    ?>
                </div>
                <div class="field">
                    <label for="editProductClass">Product class</label>
                    <input type="text" id="editProductClass" name="product_class" maxlength="100">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn cancel" onclick="closeEditItemModal()">Cancel</button>
            <button type="submit" class="btn primary">Save</button>
        </div>
    </form>
</div>

<script>
window.ITEMS_DATA = <?php echo json_encode(array_map(function($it) {
    return [
        'product_code'   => $it['product_code'],
        'product_name'   => $it['product_name'],
        'brand'          => $it['brand'] ?? '',
        'product_class'  => $it['product_class'] ?? '',
        'total_qty'      => (int)$it['total_qty'],
        'modified_by'    => $it['modified_by'] ?? '',
        'updated_at'     => $it['updated_at'] ? date('Y-m-d H:i', strtotime($it['updated_at'])) : '',
    ];
}, $items)); ?>;

document.addEventListener("DOMContentLoaded", function () {
initDataTable({
    searchInput:    '#itemsSearchInput',
    sortSelect:     '#itemsSortSelect',
    tbody:          '#itemsBody',
    row:            '.master-item-row',
    caseSensitive:  true,
    filterWrap:     '#itemsFilterWrap',
    filterBtn:      '#itemsFilterBtn',
    filterPanel:    '#itemsFilterPanel',
    filterBadge:    '#itemsFilterBadge',
    pagePrev:       '#itemsPagePrev',
    pageNext:       '#itemsPageNext',
    pageNumbers:    '#itemsPageNumbers',
    pageRange:      '#itemsPageRange',
    pagination:     '#itemsPagination',
    pageSizeSelect: '#itemsPageSizeSelect',
    exportFn:       'itemsExport',
    exportName:     'master-items',
    exportCols: [
        { key: 'product_code',   label: 'Product Code' },
        { key: 'product_name',  label: 'Product Name' },
        { key: 'brand',          label: 'Brand' },
        { key: 'product_class',  label: 'Product Class' },
        { key: 'total_qty',      label: 'Qty' },
        { key: 'modified_by',    label: 'Modified By' },
        { key: 'updated_at',     label: 'Last Modified' },
    ],
    filterGroups: [
        { title: 'Brand',        key: 'brand',        value: 'brand' },
        { title: 'Product Class',key: 'product_class',value: 'class' },
    ],
    sorters: {
        'code-asc':     function(a,b) { return String(a.dataset.code||"").toLowerCase().localeCompare(String(b.dataset.code||"").toLowerCase()); },
        'code-desc':    function(a,b) { return String(b.dataset.code||"").toLowerCase().localeCompare(String(a.dataset.code||"").toLowerCase()); },
        'name-asc':     function(a,b) { return String(a.dataset.name||"").toLowerCase().localeCompare(String(b.dataset.name||"").toLowerCase()); },
        'name-desc':    function(a,b) { return String(b.dataset.name||"").toLowerCase().localeCompare(String(a.dataset.name||"").toLowerCase()); },
        'brand-asc':    function(a,b) { return String(a.dataset.brand||"").toLowerCase().localeCompare(String(b.dataset.brand||"").toLowerCase()); },
        'brand-desc':   function(a,b) { return String(b.dataset.brand||"").toLowerCase().localeCompare(String(a.dataset.brand||"").toLowerCase()); },
        'class-asc':    function(a,b) { return String(a.dataset.class||"").toLowerCase().localeCompare(String(b.dataset.class||"").toLowerCase()); },
        'class-desc':   function(a,b) { return String(b.dataset.class||"").toLowerCase().localeCompare(String(a.dataset.class||"").toLowerCase()); },
        'modified-asc': function(a,b) { return parseInt(a.dataset.modifiedTs) - parseInt(b.dataset.modifiedTs); },
        'modified-desc':function(a,b) { return parseInt(b.dataset.modifiedTs) - parseInt(a.dataset.modifiedTs); },
    },
    data:     window.ITEMS_DATA,
    dataText: function(r) { return r.product_code + ' ' + r.product_name + ' ' + r.brand; },
});
});

    function openEditItemModal(id, it) {
        document.getElementById("editItemId").value = id;
        document.getElementById("editProductCode").value = it.product_code || "";
        document.getElementById("editProductName").value = it.product_name || "";
        document.getElementById("editBrand").value = it.brand || "";
        document.getElementById("editProductClass").value = it.product_class || "";
        document.getElementById("editItemModal").classList.add("open");
    }

    function closeEditItemModal() {
        document.getElementById("editItemModal").classList.remove("open");
    }

    async function confirmDeleteItem(id, code, usedBy) {
        if (usedBy > 0) {
            alert("Cannot delete \"" + code + "\" — it is still used by " + usedBy + " inventory item(s).");
            return;
        }
        const ok = await window.promptModal({
            title: "Move master item to the bin?",
            message: "Move \"" + code + "\" to the master bin?",
            confirmLabel: "Move to Bin",
            withNote: false,
        });
        if (ok === null) return;
        window.submitPostForm("<?php echo BASE_URL; ?>/actions/delete-master-item.php", { id: id });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>