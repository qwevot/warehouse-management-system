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

<?php if (!empty($dbError)): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>

<?php if (isset($_GET['added'])): ?>
    <div class="flash success"><?php echo e($_GET['added']); ?></div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="flash error"><?php echo e($_GET['error']); ?></div>
<?php endif; ?>

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
                        <tr class="master-item-row" data-code="<?php echo e(mb_strtolower($it['product_code'])); ?>" data-name="<?php echo e(mb_strtolower($it['product_name'])); ?>" data-brand="<?php echo e(mb_strtolower($it['brand'] ?? '')); ?>" data-class="<?php echo e(mb_strtolower($it['product_class'] ?? '')); ?>" data-qty="<?php echo (int)$it['total_qty']; ?>" data-modified-ts="<?php echo $it['updated_at'] ? strtotime($it['updated_at']) : 0; ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="row-actions">
                                    <?php if (user_can('master.manage')): ?>
                                    <button type="button" class="btn icon-btn edit" title="Edit" onclick="openEditItemModal(<?php echo (int)$it['id']; ?>, <?php echo e(json_encode($it)); ?>)">
                                        <svg viewBox="0 -0.5 21 21" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="currentColor"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <title>edit_fill [#1480]</title> <desc>Created with Sketch.</desc> <defs> </defs> <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="Dribbble-Light-Preview" transform="translate(-59.000000, -400.000000)" fill="currentColor"> <g id="icons" transform="translate(56.000000, 160.000000)"> <path d="M3,260 L24,260 L24,258.010742 L3,258.010742 L3,260 Z M13.3341,254.032226 L9.3,254.032226 L9.3,249.950269 L19.63095,240 L24,244.115775 L13.3341,254.032226 Z" id="edit_fill-[#1480]"> </path></svg>
                                    </button>
                                    <button type="button" class="btn icon-btn delete" title="Delete" onclick="confirmDeleteItem(<?php echo (int)$it['id']; ?>, <?php echo e(json_encode($it['product_code'])); ?>, <?php echo (int)$it['used_by']; ?>)">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.52381C3 6.12932 3.32671 5.80952 3.72973 5.80952H8.51787C8.52437 4.9683 8.61554 3.81504 9.45037 3.01668C10.1074 2.38839 11.0081 2 12 2C12.9919 2 13.8926 2.38839 14.5496 3.01668C15.3844 3.81504 15.4756 4.9683 15.4821 5.80952H20.2703C20.6733 5.80952 21 6.12932 21 6.52381C21 6.9183 20.6733 7.2381 20.2703 7.2381H3.72973C3.32671 7.2381 3 6.9183 3 6.52381Z" fill="currentColor"></path> <path d="M11.6066 22H12.3935C15.101 22 16.4547 22 17.3349 21.1368C18.2151 20.2736 18.3052 18.8576 18.4853 16.0257L18.7448 11.9452C18.8425 10.4086 18.8913 9.64037 18.4498 9.15352C18.0082 8.66667 17.2625 8.66667 15.7712 8.66667H8.22884C6.7375 8.66667 5.99183 8.66667 5.55026 9.15352C5.1087 9.64037 5.15756 10.4086 5.25528 11.9452L5.51479 16.0257C5.69489 18.8576 5.78494 20.2736 6.66513 21.1368C7.54532 22 8.89906 22 11.6066 22Z" fill="currentColor"></path></svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo e($it['product_name']); ?></td>
                            <td><?php echo $it['brand'] ? e($it['brand']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
                            <td><?php echo (int)$it['total_qty']; ?></td>
                            <td><?php echo e($it['product_code']); ?></td>
                            <td><?php echo $it['product_class'] ? e($it['product_class']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
                            <td class="registered-on"><?php echo $it['updated_at'] ? e(date('Y-m-d H:i', strtotime($it['updated_at']))) : '<span style="color:#9ca3af;">—</span>'; ?></td>
                            <td><?php echo $it['modified_by'] ? e($it['modified_by']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
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
                    <select id="editBrand" name="brand" required>
                        <option value="">— Brand —</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?php echo e($b); ?>"><?php echo e($b); ?></option>
                        <?php endforeach; ?>
                    </select>
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
        'code-asc':     function(a,b) { return a.dataset.code.localeCompare(b.dataset.code); },
        'code-desc':    function(a,b) { return b.dataset.code.localeCompare(a.dataset.code); },
        'name-asc':     function(a,b) { return a.dataset.name.localeCompare(b.dataset.name); },
        'name-desc':    function(a,b) { return b.dataset.name.localeCompare(a.dataset.name); },
        'brand-asc':    function(a,b) { return a.dataset.brand.localeCompare(b.dataset.brand); },
        'brand-desc':   function(a,b) { return b.dataset.brand.localeCompare(a.dataset.brand); },
        'class-asc':    function(a,b) { return a.dataset.class.localeCompare(b.dataset.class); },
        'class-desc':   function(a,b) { return b.dataset.class.localeCompare(a.dataset.class); },
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
        const f = document.createElement("form");
        f.method = "POST";
        f.action = "<?php echo BASE_URL; ?>/actions/delete-master-item.php";
        const inp = document.createElement("input");
        inp.type = "hidden";
        inp.name = "id";
        inp.value = id;
        f.appendChild(inp);
        document.body.appendChild(f);
        f.submit();
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>