<?php
$pageTitle = 'Items';
$activeNav = 'items';

require_once __DIR__ . '/../includes/auth.php';
require_staff();

$items = [];
$dbError = '';

try {
    $pdo = db();
    // READ: all active master item types, with how many active warehouse units and alert configurations.
    $items = $pdo->query(
        "SELECT mi.id, mi.product_code, mi.product_name, mi.brand, mi.product_class, mi.created_at, mi.updated_at, mi.modified_by,
                ias.alert_enabled, ias.threshold AS custom_threshold,
                (SELECT COUNT(*) FROM inventory_units u 
                 WHERE u.master_item_id = mi.id 
                   AND u.deleted_at IS NULL) AS used_by,
                (SELECT COUNT(*) FROM inventory_units u 
                 WHERE u.master_item_id = mi.id 
                   AND u.deleted_at IS NULL 
                   AND u.status = 'available' 
                   AND (u.deployment_site = 'In Warehouse' OR u.deployment_site = '' OR u.deployment_site IS NULL)) AS total_qty
         FROM master_items mi
         LEFT JOIN item_alert_settings ias ON ias.master_item_id = mi.id
         WHERE mi.deleted_at IS NULL
         ORDER BY COALESCE(mi.updated_at, mi.created_at) DESC, mi.id DESC"
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
    ['modified-desc','Modified (Newest)'],
    ['modified-asc', 'Modified (Oldest)'],
    ['code-asc',    'Code (A–Z)'],
    ['code-desc',   'Code (Z–A)'],
    ['name-asc',    'Name (A–Z)'],
    ['name-desc',   'Name (Z–A)'],
    ['brand-asc',   'Brand (A–Z)'],
    ['brand-desc',  'Brand (Z–A)'],
    ['class-asc',   'Class (A–Z)'],
    ['class-desc',  'Class (Z–A)'],
];
$itemsToolbar    = buildToolbar('items', $itemsSortOptions);
$itemsPagination = buildPagination('items');
?>

<?php include __DIR__ . '/../includes/flash.php'; ?>

<section class="inventory-section" id="items">

    <div class="sticky-zone">
        <div class="section-header">
            <?php if (user_can('master.manage')): ?>
                <button type="button" class="btn upload add-button" title="Import master items from a CSV or Excel file" onclick="document.getElementById('importMasterFile').click()">Import (.csv or .xlsx)</button>
                <button type="button" class="btn primary add-button add-toggle-btn" id="addMasterItemToggleBtn" onclick="toggleMasterAddForm()">+ Add Item</button>
                <input type="file" id="importMasterFile" accept=".csv,.xlsx,.xls" style="display:none;">
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/../includes/master-items-card.php'; ?>

    </div>

    <div class="toolbar-row"><?php echo $itemsToolbar; ?></div>

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
                    <th class="sortable" data-sort="modified" data-default-dir="desc" title="Sort by Last Modified">
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
        <?php echo csrf_field(); ?>
        <div class="modal-header">
            <div class="modal-title">Edit Master Item</div>
            <button type="button" class="modal-close" onclick="closeEditItemModal()">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id" id="editItemId">
            <div class="form-grid">
                <div class="field">
                    <label for="editProductCode">Product code (Numbers only)</label>
                    <input type="text" id="editProductCode" name="product_code" required maxlength="4" inputmode="numeric" pattern="\d{1,4}" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 4)" title="4-digit number (e.g. 0001)">
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
                    <label for="editProductClass">Product Class</label>
                    <select id="editProductClass" name="product_class" required>
                        <option value="">— Select class —</option>
                        <option value="Inventory Item">Inventory Item</option>
                    </select>
                </div>

                <!-- Low Stock Alert Toggle & Threshold -->
                <div class="field full" style="margin-top:8px; padding-top:12px; border-top:1px solid var(--border-color, #e2e8f0);">
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:var(--bg, #f8fafc); border:1px solid var(--border-color, #e2e8f0); border-radius:8px;">
                        <div>
                            <div style="font-weight:600; font-size:13px; color:var(--text, #1e293b);">Low Stock Notification</div>
                            <div style="font-size:11.5px; color:var(--muted, #64748b);">Enable or disable low stock warning for this item</div>
                        </div>
                        <label style="position:relative; display:inline-block; width:44px; height:22px; cursor:pointer; margin:0;">
                            <input type="checkbox" name="alert_enabled" value="1" id="editAlertEnabled" style="opacity:0; width:0; height:0;">
                            <span style="position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background-color:#cbd5e1; transition:.3s; border-radius:22px;" id="editAlertSlider"></span>
                            <span style="position:absolute; content:''; height:16px; width:16px; left:3px; bottom:3px; background-color:white; transition:.3s; border-radius:50%; box-shadow:0 1px 3px rgba(0,0,0,0.2); transition:.3s;" id="editAlertKnob"></span>
                        </label>
                    </div>
                </div>

                <div class="field full" id="editThresholdField">
                    <label for="editThreshold" style="font-weight:600; font-size:13px;">Low Stock Threshold (Units)</label>
                    <input type="number" id="editThreshold" name="threshold" min="0" max="99999" placeholder="Default: 5 units">
                    <small style="color:var(--muted); font-size:11.5px; display:block; margin-top:3px;">
                        When warehouse stock reaches or falls below this amount, an alert is triggered.
                    </small>
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
        { key: 'product_name',  label: 'Product Name' },
        { key: 'brand',          label: 'Brand' },
        { key: 'total_qty',      label: 'Qty' },
        { key: 'product_code',   label: 'Product Code' },
        { key: 'product_class',  label: 'Product Class' },
        { key: 'updated_at',     label: 'Last Modified' },
        { key: 'modified_by',    label: 'Modified By' },
    ],
    filterGroups: [
        { title: 'Brand',        key: 'brand',        value: 'brand' },
        { title: 'Product Class',key: 'product_class',value: 'class' },
    ],
    defaultSortKey: 'modified',
    defaultSortDir: 'desc',
    sorters: {
        'code-asc':      function(a,b) { return String(a.dataset.code||"").toLowerCase().localeCompare(String(b.dataset.code||"").toLowerCase()); },
        'code-desc':     function(a,b) { return String(b.dataset.code||"").toLowerCase().localeCompare(String(a.dataset.code||"").toLowerCase()); },
        'name-asc':      function(a,b) { return String(a.dataset.name||"").toLowerCase().localeCompare(String(b.dataset.name||"").toLowerCase()); },
        'name-desc':     function(a,b) { return String(b.dataset.name||"").toLowerCase().localeCompare(String(a.dataset.name||"").toLowerCase()); },
        'brand-asc':     function(a,b) { return String(a.dataset.brand||"").toLowerCase().localeCompare(String(b.dataset.brand||"").toLowerCase()); },
        'brand-desc':    function(a,b) { return String(b.dataset.brand||"").toLowerCase().localeCompare(String(a.dataset.brand||"").toLowerCase()); },
        'class-asc':     function(a,b) { return String(a.dataset.class||"").toLowerCase().localeCompare(String(b.dataset.class||"").toLowerCase()); },
        'class-desc':    function(a,b) { return String(b.dataset.class||"").toLowerCase().localeCompare(String(a.dataset.class||"").toLowerCase()); },
        'modified_asc':  function(a,b) { return (parseInt(a.dataset.modifiedTs, 10)||0) - (parseInt(b.dataset.modifiedTs, 10)||0); },
        'modified_desc': function(a,b) { return (parseInt(b.dataset.modifiedTs, 10)||0) - (parseInt(a.dataset.modifiedTs, 10)||0); },
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

        // Low stock alert settings
        const alertCheckbox = document.getElementById("editAlertEnabled");
        const slider = document.getElementById("editAlertSlider");
        const knob = document.getElementById("editAlertKnob");
        const threshInput = document.getElementById("editThreshold");

        const isAlertOn = (it.alert_enabled !== undefined && it.alert_enabled !== null) ? (parseInt(it.alert_enabled, 10) === 1) : false;
        if (alertCheckbox) {
            alertCheckbox.checked = isAlertOn;
            if (slider && knob) {
                slider.style.backgroundColor = isAlertOn ? "var(--primary, #4f46e5)" : "#cbd5e1";
                knob.style.left = isAlertOn ? "25px" : "3px";
            }
        }
        if (threshInput) {
            threshInput.value = (it.custom_threshold !== undefined && it.custom_threshold !== null && it.custom_threshold !== "") ? it.custom_threshold : "";
        }

        document.getElementById("editItemModal").classList.add("open");
    }

    // Toggle slider interaction
    (function () {
        const alertCheckbox = document.getElementById("editAlertEnabled");
        const slider = document.getElementById("editAlertSlider");
        const knob = document.getElementById("editAlertKnob");
        if (alertCheckbox && slider && knob) {
            alertCheckbox.addEventListener("change", function () {
                if (this.checked) {
                    slider.style.backgroundColor = "var(--primary, #4f46e5)";
                    knob.style.left = "25px";
                } else {
                    slider.style.backgroundColor = "#cbd5e1";
                    knob.style.left = "3px";
                }
            });
        }
    })();

    function closeEditItemModal() {
        document.getElementById("editItemModal").classList.remove("open");
    }

    async function confirmDeleteItem(id, code, usedBy) {
        const msg = usedBy > 0
            ? "Move master item \"" + code + "\" to the master bin? This will ALSO move all " + usedBy + " inventory unit(s) to the bin."
            : "Move master item \"" + code + "\" to the master bin?";
        const ok = await window.promptModal({
            title: "Move master item to the bin?",
            message: msg,
            confirmLabel: "Move to Bin",
            withNote: false,
        });
        if (ok === null) return;
        window.submitPostForm("<?php echo BASE_URL; ?>/actions/delete-master-item.php", { id: id });
    }

    const importMasterInput = document.getElementById("importMasterFile");
    if (importMasterInput) {
        importMasterInput.addEventListener("change", function () {
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
                            product_name:  pick(r, "product_name", "Product Name", "name", "Name", "item", "Item"),
                            brand:         pick(r, "brand", "Brand"),
                            product_code:  pick(r, "product_code", "Product Code", "code", "Code"),
                            product_class: pick(r, "product_class", "Product Class", "class", "Class"),
                        })).filter((r) => r.product_name !== "" && r.brand !== "");

                        if (!mapped.length) return alert("No valid rows found. Each item requires at least a Product Name and Brand.");

                        if (!confirm("Import " + mapped.length + " master item(s)?")) return;

                        window.post(BASE_URL + "/actions/import-master-items.php", { rows: JSON.stringify(mapped) })
                            .then((res) => res.json())
                            .then((d) => {
                                if (!d.ok) throw new Error(d.message || "Import failed.");
                                window.queueFlash(d.message || "Master items imported.");
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