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

<?php if (!empty($dbError)): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>

<?php if (isset($_GET['added'])): ?>
    <div class="flash success"><?php echo e($_GET['added']); ?></div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="flash error">
        <?php echo e($_GET['error']); ?>
        <?php if (isset($_GET['bin_restore']) && (int)$_GET['bin_restore'] > 0): ?>
            <a href="<?php echo BASE_URL; ?>/actions/restore-brand.php?id=<?php echo (int)$_GET['bin_restore']; ?>">restore it?</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

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
                                        <svg viewBox="0 -0.5 21 21" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="currentColor"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <title>edit_fill [#1480]</title> <desc>Created with Sketch.</desc> <defs> </defs> <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="Dribbble-Light-Preview" transform="translate(-59.000000, -400.000000)" fill="currentColor"> <g id="icons" transform="translate(56.000000, 160.000000)"> <path d="M3,260 L24,260 L24,258.010742 L3,258.010742 L3,260 Z M13.3341,254.032226 L9.3,254.032226 L9.3,249.950269 L19.63095,240 L24,244.115775 L13.3341,254.032226 Z" id="edit_fill-[#1480]"> </path></svg>
                                    </button>
                                    <button type="button" class="btn icon-btn delete" title="Delete" onclick="confirmDelete(<?php echo (int)$b['id']; ?>, '<?php echo e($b['name']); ?>', <?php echo (int)$b['used_by']; ?>)">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.52381C3 6.12932 3.32671 5.80952 3.72973 5.80952H8.51787C8.52437 4.9683 8.61554 3.81504 9.45037 3.01668C10.1074 2.38839 11.0081 2 12 2C12.9919 2 13.8926 2.38839 14.5496 3.01668C15.3844 3.81504 15.4756 4.9683 15.4821 5.80952H20.2703C20.6733 5.80952 21 6.12932 21 6.52381C21 6.9183 20.6733 7.2381 20.2703 7.2381H3.72973C3.32671 7.2381 3 6.9183 3 6.52381Z" fill="currentColor"></path> <path d="M11.6066 22H12.3935C15.101 22 16.4547 22 17.3349 21.1368C18.2151 20.2736 18.3052 18.8576 18.4853 16.0257L18.7448 11.9452C18.8425 10.4086 18.8913 9.64037 18.4498 9.15352C18.0082 8.66667 17.2625 8.66667 15.7712 8.66667H8.22884C6.7375 8.66667 5.99183 8.66667 5.55026 9.15352C5.1087 9.64037 5.15756 10.4086 5.25528 11.9452L5.51479 16.0257C5.69489 18.8576 5.78494 20.2736 6.66513 21.1368C7.54532 22 8.89906 22 11.6066 22Z" fill="currentColor"></path></svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo e($b['name']); ?></td>
                            <td><?php echo $b['description'] ? e($b['description']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
                            <td><?php echo (int)$b['used_by']; ?></td>
                            <td class="registered-on"><?php echo $b['updated_at'] ? e(date('Y-m-d H:i', strtotime($b['updated_at']))) : '<span style="color:#9ca3af;">—</span>'; ?></td>
                            <td><?php echo $b['modified_by'] ? e($b['modified_by']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
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
        const f = document.createElement("form");
        f.method = "POST";
        f.action = "<?php echo BASE_URL; ?>/actions/delete-brand.php";
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