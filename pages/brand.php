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
                <button type="button" class="btn upload add-button" title="Import brands from a CSV or Excel file" onclick="document.getElementById('importBrandFile').click()">Import (.csv or .xlsx)</button>
                <button type="button" class="btn primary add-button add-toggle-btn" id="addBrandToggleBtn" onclick="toggleBrandAddForm()">+ Add Brand</button>
                <input type="file" id="importBrandFile" accept=".csv,.xlsx,.xls" style="display:none;">
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/../includes/master-brand-card.php'; ?>

    </div>

    <div class="toolbar-row"><?php echo $brandToolbar; ?></div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="action-col">Action</th>
                    <th>Brand</th>
                    <th>Description</th>
                    <th>Items</th>
                    <th class="sortable" data-sort="date" data-default-dir="desc" title="Sort by Last Modified">
                        <div class="th-sort-inner">
                            <span>Last Modified</span>
                            <svg class="sort-icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </th>
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
                        <tr class="brand-row" data-name="<?php echo e(mb_strtolower($b['name'])); ?>" data-used-by="<?php echo (int)$b['used_by']; ?>" data-in-use="<?php echo $b['used_by'] > 0 ? 'yes' : 'no'; ?>" data-updated="<?php echo $b['updated_at'] ? (int)strtotime($b['updated_at']) : 0; ?>">
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
    defaultSortKey: 'date',
    defaultSortDir: 'desc',
    sorters: {
        'date_desc': function(a, b) { return (parseInt(b.dataset.updated, 10) || 0) - (parseInt(a.dataset.updated, 10) || 0); },
        'date_asc':  function(a, b) { return (parseInt(a.dataset.updated, 10) || 0) - (parseInt(b.dataset.updated, 10) || 0); },
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

    const importBrandInput = document.getElementById("importBrandFile");
    if (importBrandInput) {
        importBrandInput.addEventListener("change", function () {
            const file = this.files && this.files[0];
            if (!file) return;
            this.value = "";

            const reader = new FileReader();
            reader.onload = (e) => {
                window.ensureXlsx()
                    .then(() => {
                        const wb = XLSX.read(e.target.result, { type: "array" });
                        const sheet = wb.Sheets[wb.SheetNames[0]];
                        const rows = XLSX.utils.sheet_to_json(sheet, { defval: "", raw: false });
                        if (!rows.length) return alert("No rows found in the file.");

                        const pick = (r, ...keys) => {
                            for (const k of keys) {
                                const v = r[k];
                                if (v !== undefined && v !== null && String(v).trim() !== "") return String(v).trim();
                            }
                            return "";
                        };

                        const mapped = rows.map((r) => ({
                            name:        pick(r, "name", "Name", "brand", "Brand", "brand_name", "Brand Name"),
                            description: pick(r, "description", "Description", "desc", "Desc"),
                        })).filter((r) => r.name !== "");

                        if (!mapped.length) return alert("No valid rows found. Each brand requires at least a Name.");

                        if (!confirm("Import " + mapped.length + " brand(s)?")) return;

                        window.post(BASE_URL + "/actions/import-brands.php", { rows: JSON.stringify(mapped) })
                            .then((res) => res.json())
                            .then((d) => {
                                if (!d.ok) throw new Error(d.message || "Import failed.");
                                window.queueFlash(d.message || "Brands imported.");
                                location.reload();
                            })
                            .catch((err) => alert(err.message || "Import failed."));
                    })
                    .catch(() => alert("Could not load the Excel/CSV reader."));
            };
            reader.readAsArrayBuffer(file);
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>