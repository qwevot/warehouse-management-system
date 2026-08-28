<?php
$pageTitle = 'Brands';
$activeNav = 'brands';

require_once __DIR__ . '/../includes/auth.php';
require_login();

$brands = [];
$dbError = '';

try {
    $pdo = db();
    // READ: all active brands, with how many active items use each.
    $brands = $pdo->query(
        "SELECT b.id, b.name, b.description, b.updated_at, b.modified_by,
                (SELECT COUNT(*) FROM inventory_list m WHERE m.brand = b.name AND m.deleted_at IS NULL) AS used_by
         FROM master_brands b
         WHERE b.deleted_at IS NULL
         ORDER BY b.name ASC"
    )->fetchAll();
} catch (Exception $e) {
    $dbError = 'Could not load brands from the database.';
}

include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/data-toolbar.php';
require_once __DIR__ . '/../includes/data-pagination.php';

$brandSortOptions = [
    ['name-asc',   'Name (A–Z)'],
    ['name-desc',  'Name (Z–A)'],
];
$brandToolbar    = buildToolbar('brand', $brandSortOptions);
$brandPagination = buildPagination('brand');
?>

<?php
$flashAfterError = '';
if (isset($_GET['bin_restore']) && (int)$_GET['bin_restore'] > 0) {
    $flashAfterError = ' <a href="' . BASE_URL . '/actions/restore-brand.php?id=' . (int)$_GET['bin_restore'] . '">restore it?</a>';
}
include __DIR__ . '/../includes/flash.php';
?>

<section class="inventory-section" id="brands">

    <div class="sticky-zone">

        <div class="section-header">
            <div class="section-title">Brands</div>
            <?php if (user_can('master.manage')): ?>
                <button type="button" class="btn primary add-button add-toggle-btn" id="addBrandToggleBtn" onclick="toggleBrandAddForm()">+ Add Brand</button>
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/../includes/master-brand-card.php'; ?>

    </div>

    <div class="table-container">
        <div class="toolbar-row"><?php echo $brandToolbar; ?></div>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="action-col">Action</th>
                    <th>Brand</th>
                    <th>Description</th>
                    <th>Items</th>
                    <th>Last Modified</th>
                    <th>Modified By</th>
                </tr>
            </thead>
            <tbody id="brandBody">
                <?php if (empty($brands)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:#9ca3af; padding:40px;">
                            No brands yet. Add one above, or brands from your items will appear here.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($brands as $i => $b): ?>
                        <tr class="brand-row" data-name="<?php echo e(mb_strtolower($b['name'])); ?>" data-used-by="<?php echo (int)$b['used_by']; ?>" data-in-use="<?php echo $b['used_by'] > 0 ? 'yes' : 'no'; ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="row-actions">
                                    <?php if (user_can('master.manage')): ?>
                                    <button type="button" class="btn icon-btn edit" title="Edit" onclick="openRenameModal(<?php echo (int)$b['id']; ?>, <?php echo e(json_encode($b['name'])); ?>, <?php echo e(json_encode($b['description'] ?? '')); ?>)">
                                        <?php echo icon_edit(); ?>
                                    </button>
                                    <button type="button" class="btn icon-btn delete" title="Delete" onclick="confirmDelete(<?php echo (int)$b['id']; ?>, '<?php echo e($b['name']); ?>', <?php echo (int)$b['used_by']; ?>)">
                                        <?php echo icon_delete(); ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo e($b['name']); ?></td>
                            <td><?php echo dash($b['description']); ?></td>
                            <td><?php echo (int)$b['used_by']; ?></td>
                            <td class="registered-on"><?php echo dash(fmt_datetime($b['updated_at'])); ?></td>
                            <td><?php echo dash($b['modified_by']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $brandPagination; ?>

</section>

<script>
window.BRAND_DATA = <?php echo json_encode(array_map(function($b) {
    return [
        'name'       => $b['name'],
        'used_by'    => (int)$b['used_by'],
        'modified_by'=> $b['modified_by'] ?? '',
        'updated_at' => $b['updated_at'] ? date('Y-m-d H:i', strtotime($b['updated_at'])) : '',
    ];
}, $brands)); ?>;

document.addEventListener("DOMContentLoaded", function () {
initDataTable({
    searchInput:   '#brandSearchInput',
    sortSelect:    '#brandSortSelect',
    tbody:         '#brandBody',
    row:           '.brand-row',
    filterWrap:    '#brandFilterWrap',
    filterBtn:     '#brandFilterBtn',
    filterPanel:   '#brandFilterPanel',
    filterBadge:   '#brandFilterBadge',
    pagePrev:      '#brandPagePrev',
    pageNext:      '#brandPageNext',
    pageNumbers:   '#brandPageNumbers',
    pageRange:     '#brandPageRange',
    pagination:    '#brandPagination',
    pageSizeSelect:'#brandPageSizeSelect',
    exportFn:      'brandExport',
    exportName:    'brands',
    exportCols: [
        { key: 'name',       label: 'Brand Name' },
        { key: 'used_by',    label: 'Used By' },
        { key: 'modified_by',label: 'Modified By' },
        { key: 'updated_at', label: 'Last Modified' },
    ],
    filterGroups: [
        {
            title:   'In Use',
            key:     'in_use',
            value:   'inUse',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no',  label: 'No' },
            ],
        },
    ],
    sorters: {
        'name-asc':  function(a, b) { return a.dataset.name.localeCompare(b.dataset.name); },
        'name-desc': function(a, b) { return b.dataset.name.localeCompare(a.dataset.name); },
    },
    data:     window.BRAND_DATA,
    dataText: function(r) { return r.name; },
});
});
</script>

<!-- Edit brand modal -->
<div class="modal-backdrop" id="renameBrandModal">
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/save-brand.php" class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit Brand</div>
            <button type="button" class="modal-close" onclick="closeRenameModal()">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id" id="renameBrandId">
            <div class="field">
                <label for="renameBrandName">Brand name</label>
                <input type="text" id="renameBrandName" name="name" required>
            </div>
            <div class="field">
                <label for="renameBrandDescription">Description</label>
                <input type="text" id="renameBrandDescription" name="description" maxlength="255">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn cancel" onclick="closeRenameModal()">Cancel</button>
            <button type="submit" class="btn primary">Save</button>
        </div>
    </form>
</div>

<script>
    function openRenameModal(id, name, description) {
        document.getElementById("renameBrandId").value = id;
        document.getElementById("renameBrandName").value = name;
        document.getElementById("renameBrandDescription").value = description || "";
        document.getElementById("renameBrandModal").classList.add("open");
        document.getElementById("renameBrandName").focus();
    }

    function closeRenameModal() {
        document.getElementById("renameBrandModal").classList.remove("open");
    }

    async function confirmDelete(id, name, usedBy) {
        if (usedBy > 0) {
            alert("Cannot delete \"" + name + "\" — it is still used by " + usedBy + " item(s).");
            return;
        }
        const ok = await window.promptModal({
            title: "Move brand to the bin?",
            message: "Move brand \"" + name + "\" to the master bin?",
            confirmLabel: "Move to Bin",
            withNote: false,
        });
        if (ok === null) return;
        window.submitPostForm("<?php echo BASE_URL; ?>/actions/delete-brand.php", { id: id });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>