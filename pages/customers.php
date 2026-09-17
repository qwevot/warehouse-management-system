<?php
$pageTitle = 'Customers';
$activeNav = 'customers';

require_once __DIR__ . '/../includes/auth.php';
require_staff();

$customers = [];
$dbError = '';

try {
    $pdo = db();
    // READ: active customers with count of deployed equipment
    $customers = $pdo->query("
        SELECT c.id, c.company_code, c.name, c.contact_person, c.phone, c.email, c.address, c.updated_at, c.modified_by,
               (SELECT COUNT(*) FROM inventory_units u 
                WHERE u.deleted_at IS NULL 
                  AND (u.deployment_site LIKE CONCAT(c.name, ' - %') OR u.deployment_site = c.name 
                       OR u.location LIKE CONCAT(c.name, ' - %') OR u.location = c.name 
                       OR u.deployment_site LIKE CONCAT('In Transit (', c.name, '%'))) AS deployed_units
        FROM master_customers c
        WHERE c.deleted_at IS NULL
        ORDER BY COALESCE(c.updated_at, c.created_at) DESC, c.id DESC
    ")->fetchAll();
} catch (Exception $e) {
    $dbError = 'Could not load customers from the database.';
}

include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/data-toolbar.php';
require_once __DIR__ . '/../includes/data-pagination.php';

$customerSortOptions = [
    ['date-desc',  'Last Modified (Newest)'],
    ['date-asc',   'Last Modified (Oldest)'],
    ['name-asc',   'Company Name (A–Z)'],
    ['name-desc',  'Company Name (Z–A)'],
    ['code-asc',   'Customer Code (A–Z)'],
    ['code-desc',  'Customer Code (Z–A)'],
];
$customerToolbar    = buildToolbar('customer', $customerSortOptions);
$customerPagination = buildPagination('customer');
?>

<?php
$flashAfterError = '';
if (isset($_GET['bin_restore']) && (int)$_GET['bin_restore'] > 0) {
    $flashAfterError = ' <form method="POST" action="' . BASE_URL . '/actions/restore-customer.php" style="display:inline;">' . csrf_field() . '<input type="hidden" name="from" value="customers"><input type="hidden" name="id" value="' . (int)$_GET['bin_restore'] . '"><button type="submit" style="background:none;border:none;color:inherit;text-decoration:underline;cursor:pointer;padding:0;font:inherit;font-weight:bold;">restore it?</button></form>';
}
include __DIR__ . '/../includes/flash.php';
?>

<section class="inventory-section" id="customersSection">

    <div class="sticky-zone">

        <div class="section-header">
            <div class="section-title">Customers </div>
            <?php if (user_can('master.manage')): ?>
                <button type="button" class="btn upload add-button" title="Import customers from a CSV or Excel file" onclick="document.getElementById('importCustomerFile').click()">Import (.csv or .xlsx)</button>
                <button type="button" class="btn primary add-button add-toggle-btn" id="addCustomerToggleBtn" onclick="toggleCustomerAddForm()">+ Add Customer</button>
                <input type="file" id="importCustomerFile" accept=".csv,.xlsx,.xls" style="display:none;">
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/../includes/master-customer-card.php'; ?>

    </div>

    <div class="toolbar-row"><?php echo $customerToolbar; ?></div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="action-col">Action</th>
                    <th>Customer Code</th>
                    <th>Company / Customer Name</th>
                    <th>Contact Person</th>
                    <th>Phone / Email</th>
                    <th>Deployed Units</th>
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
            <tbody id="customerBody">
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center; color:#9ca3af; padding:40px;">
                            No customers registered yet. Add one above to organize customer deployments.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $i => $c): ?>
                        <tr class="customer-row" 
                            data-name="<?php echo e(mb_strtolower($c['name'])); ?>" 
                            data-code="<?php echo e(mb_strtolower((string)$c['company_code'])); ?>"
                            data-contact="<?php echo e(mb_strtolower((string)$c['contact_person'])); ?>"
                            data-deployed="<?php echo (int)$c['deployed_units']; ?>"
                            data-has-deployed="<?php echo $c['deployed_units'] > 0 ? 'yes' : 'no'; ?>"
                            data-updated="<?php echo $c['updated_at'] ? (int)strtotime($c['updated_at']) : 0; ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="row-actions">
                                    <?php if (user_can('master.manage')): ?>
                                    <button type="button" class="btn icon-btn edit" title="Edit Customer" 
                                            onclick='openEditCustomerModal(<?php echo (int)$c['id']; ?>, <?php echo json_encode($c['name']); ?>, <?php echo json_encode((string)($c['company_code'] ?? '')); ?>, <?php echo json_encode((string)($c['contact_person'] ?? '')); ?>, <?php echo json_encode((string)($c['phone'] ?? '')); ?>, <?php echo json_encode((string)($c['email'] ?? '')); ?>, <?php echo json_encode((string)($c['address'] ?? '')); ?>)'>
                                        <?php echo icon_edit(); ?>
                                    </button>
                                    <button type="button" class="btn icon-btn delete" title="Move to Bin" 
                                            onclick="confirmDeleteCustomer(<?php echo (int)$c['id']; ?>, <?php echo e(json_encode($c['name'])); ?>, <?php echo (int)$c['deployed_units']; ?>)">
                                        <?php echo icon_delete(); ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($c['company_code'])): ?>
                                    <span class="badge gray"><?php echo e($c['company_code']); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--muted); font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo e($c['name']); ?>
                                <?php if (!empty($c['address'])): ?>
                                    <div style="font-size:11.5px; color:var(--muted); margin-top:2px;" title="<?php echo e($c['address']); ?>">
                                        <?php echo e(mb_strimwidth($c['address'], 0, 45, '...')); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo dash($c['contact_person']); ?></td>
                            <td>
                                <?php if (!empty($c['phone']) || !empty($c['email'])): ?>
                                    <?php if (!empty($c['phone'])): ?>
                                        <div style="font-size:12px;"><?php echo e($c['phone']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($c['email'])): ?>
                                        <div style="font-size:11.5px; color:var(--muted);"><?php echo e($c['email']); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$c['deployed_units'] > 0): ?>
                                    <a href="<?php echo BASE_URL; ?>/pages/customer-assets.php?company=<?php echo urlencode($c['name']); ?>" class="badge blue" style="text-decoration:none;">
                                        <?php echo (int)$c['deployed_units']; ?> unit(s)
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--muted); font-size:12.5px;">0 units</span>
                                <?php endif; ?>
                            </td>
                            <td class="registered-on"><?php echo dash(fmt_datetime($c['updated_at'])); ?></td>
                            <td><?php echo dash($c['modified_by']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $customerPagination; ?>

</section>

<script>
window.CUSTOMER_DATA = <?php echo json_encode(array_map(function($c) {
    return [
        'company_code'   => $c['company_code'] ?? '',
        'name'           => $c['name'],
        'contact_person' => $c['contact_person'] ?? '',
        'phone'          => $c['phone'] ?? '',
        'email'          => $c['email'] ?? '',
        'address'        => $c['address'] ?? '',
        'deployed_units' => (int)$c['deployed_units'],
        'modified_by'    => $c['modified_by'] ?? '',
        'updated_at'     => $c['updated_at'] ? date('Y-m-d H:i', strtotime($c['updated_at'])) : '',
    ];
}, $customers)); ?>;

document.addEventListener("DOMContentLoaded", function () {
    initDataTable({
        searchInput:    '#customerSearchInput',
        sortSelect:     '#customerSortSelect',
        tbody:          '#customerBody',
        row:            '.customer-row',
        filterWrap:     '#customerFilterWrap',
        filterBtn:      '#customerFilterBtn',
        filterPanel:    '#customerFilterPanel',
        filterBadge:    '#customerFilterBadge',
        pagePrev:       '#customerPagePrev',
        pageNext:       '#customerPageNext',
        pageNumbers:    '#customerPageNumbers',
        pageRange:      '#customerPageRange',
        pagination:     '#customerPagination',
        pageSizeSelect: '#customerPageSizeSelect',
        exportFn:       'customerExport',
        exportName:     'master-customers',
        exportCols: [
            { key: 'company_code',   label: 'Customer Code' },
            { key: 'name',           label: 'Company Name' },
            { key: 'contact_person', label: 'Contact Person' },
            { key: 'phone',          label: 'Phone' },
            { key: 'email',          label: 'Email' },
            { key: 'address',        label: 'Address' },
            { key: 'deployed_units', label: 'Deployed Units' },
            { key: 'modified_by',    label: 'Modified By' },
            { key: 'updated_at',     label: 'Last Modified' },
        ],
        filterGroups: [
            {
                title:   'Deployed Units',
                key:     'has_deployed',
                value:   'hasDeployed',
                options: [
                    { value: 'yes', label: 'Has Deployed Units' },
                    { value: 'no',  label: 'No Deployed Units' },
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
            'code-asc':  function(a, b) { return (a.dataset.code || '').localeCompare(b.dataset.code || ''); },
            'code-desc': function(a, b) { return (b.dataset.code || '').localeCompare(a.dataset.code || ''); },
        },
        data:     window.CUSTOMER_DATA,
        dataText: function(r) { return r.name + ' ' + (r.company_code || '') + ' ' + (r.contact_person || '') + ' ' + (r.phone || '') + ' ' + (r.email || ''); },
    });
});
</script>

<!-- Edit Customer Modal -->
<div class="modal-backdrop" id="editCustomerModal">
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/save-customer.php" class="modal">
        <?php echo csrf_field(); ?>
        <div class="modal-header">
            <div class="modal-title">Edit Customer / Company</div>
            <button type="button" class="modal-close" onclick="closeEditCustomerModal()">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id" id="editCustomerId">

            <div class="form-grid">
                <div class="field">
                    <label for="editCustomerName">Company / Customer Name *</label>
                    <input type="text" id="editCustomerName" name="name" required maxlength="150">
                </div>

                <div class="field">
                    <label for="editCustomerCode">Customer Code</label>
                    <input type="text" id="editCustomerCode" name="company_code" maxlength="50">
                </div>

                <div class="field">
                    <label for="editCustomerContact">Contact Person</label>
                    <input type="text" id="editCustomerContact" name="contact_person" maxlength="100">
                </div>

                <div class="field">
                    <label for="editCustomerPhone">Phone Number</label>
                    <input type="text" id="editCustomerPhone" name="phone" maxlength="50">
                </div>

                <div class="field">
                    <label for="editCustomerEmail">Email Address</label>
                    <input type="email" id="editCustomerEmail" name="email" maxlength="150">
                </div>

                <div class="field full">
                    <label for="editCustomerAddress">Address / Notes</label>
                    <textarea id="editCustomerAddress" name="address" rows="2" maxlength="500"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn cancel" onclick="closeEditCustomerModal()">Cancel</button>
            <button type="submit" class="btn primary">Save Changes</button>
        </div>
    </form>
</div>

<script>
    function toggleCustomerAddForm() {
        const sec = document.getElementById("addCustomerSection");
        const btn = document.getElementById("addCustomerToggleBtn");
        if (!sec) return;
        if (sec.style.display === "none" || sec.style.display === "") {
            sec.style.display = "block";
            if (btn) btn.textContent = "✕ Close";
            document.getElementById("customer_name_input")?.focus();
        } else {
            sec.style.display = "none";
            if (btn) btn.textContent = "+ Add Customer";
        }
    }

    function closeCustomerAddForm() {
        const sec = document.getElementById("addCustomerSection");
        const btn = document.getElementById("addCustomerToggleBtn");
        if (sec) sec.style.display = "none";
        if (btn) btn.textContent = "+ Add Customer";
    }

    function openEditCustomerModal(id, name, code, contact, phone, email, address) {
        document.getElementById("editCustomerId").value = id;
        document.getElementById("editCustomerName").value = name;
        document.getElementById("editCustomerCode").value = code || "";
        document.getElementById("editCustomerContact").value = contact || "";
        document.getElementById("editCustomerPhone").value = phone || "";
        document.getElementById("editCustomerEmail").value = email || "";
        document.getElementById("editCustomerAddress").value = address || "";
        document.getElementById("editCustomerModal").classList.add("open");
        document.getElementById("editCustomerName").focus();
    }

    function closeEditCustomerModal() {
        document.getElementById("editCustomerModal").classList.remove("open");
    }

    async function confirmDeleteCustomer(id, name, deployedCount) {
        const msg = deployedCount > 0
            ? "Move customer \"" + name + "\" to the bin? (" + deployedCount + " equipment unit(s) are currently deployed under this customer)"
            : "Move customer \"" + name + "\" to the bin?";
        const ok = await window.promptModal({
            title: "Move customer to the bin?",
            message: msg,
            confirmLabel: "Move to Bin",
            withNote: false,
        });
        if (ok === null) return;
        window.submitPostForm("<?php echo BASE_URL; ?>/actions/delete-customer.php", { id: id });
    }

    const importCustomerInput = document.getElementById("importCustomerFile");
    if (importCustomerInput) {
        importCustomerInput.addEventListener("change", function () {
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
                            name:           pick(r, "name", "Name", "company", "Company", "customer", "Customer", "customer_name", "Company Name"),
                            company_code:   pick(r, "code", "Code", "company_code", "Customer Code", "cust_code"),
                            contact_person: pick(r, "contact", "Contact", "contact_person", "Contact Person", "pic", "PIC"),
                            phone:          pick(r, "phone", "Phone", "telp", "Telephone", "mobile"),
                            email:          pick(r, "email", "Email", "mail"),
                            address:        pick(r, "address", "Address", "alamat", "Alamat"),
                        })).filter((r) => r.name !== "");

                        if (!mapped.length) return alert("No valid rows found. Each customer requires at least a Name/Company.");

                        if (!confirm("Import " + mapped.length + " customer(s)?")) return;

                        window.post(BASE_URL + "/actions/import-customers.php", { rows: JSON.stringify(mapped) })
                            .then((res) => res.json())
                            .then((d) => {
                                if (!d.ok) throw new Error(d.message || "Import failed.");
                                window.queueFlash(d.message || "Customers imported.");
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
