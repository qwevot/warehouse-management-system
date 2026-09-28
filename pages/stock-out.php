<?php
$pageTitle = 'Stock Out';
$activeNav = 'stock-out';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/inventory-data.php';
require_staff();

$mode   = 'stock-out';
$state  = inventory_state_from_request();
$rows   = [];
$total  = 0;
$facets = ['brand' => [], 'shelf' => [], 'row' => [], 'col' => []];
$items  = [];
$availableUnits = [];
$dbError = '';

try {
    $pdo = db();
    $result = inventory_fetch($pdo, $mode, $state, false);
    $rows  = $result['rows'];
    $total = $result['total'];
    $state['page'] = $result['page'];
    $state['size'] = $result['size'];
    $facets = inventory_facets($pdo, $mode);

    // Fetch registered and deployed customer company names for autocompletion
    $customerList = $pdo->query("
        SELECT DISTINCT company 
        FROM users 
        WHERE role = 'customer' AND company IS NOT NULL AND company <> ''
        UNION
        SELECT DISTINCT SUBSTRING_INDEX(deployment_site, ' - ', 1) AS company
        FROM inventory_units
        WHERE deployment_site LIKE '% - %'
        UNION
        SELECT DISTINCT company
        FROM users
        WHERE company IS NOT NULL AND company <> ''
        ORDER BY company ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $rows = [];
    $customerList = [];
    $dbError = 'Could not load data from the database.';
}

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/flash.php'; ?>

<section class="inventory-section suggest-section">

    <div class="section-header">
        <div class="section-title">Issue Stock</div>
    </div>

    <?php if (user_can('movement.manage')): ?>
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/process-stock-out.php" class="wizard-body" id="stockForm">
        <?php echo csrf_field(); ?>

        <input type="hidden" name="outbound_mode" id="outboundModeInput" value="individual">
        <input type="hidden" name="item_id" id="stockItemId">
        <input type="hidden" name="unit_id" id="stockUnitId">

        <div class="form-grid">

            <!-- Unified Search Bar -->
            <div class="field full">
                <label for="unifiedOutboundInput">Scan or Search Item / Lot (Barcode) / Unit (Serial Number) *</label>
                <div class="suggest-wrap" style="display:flex; gap:8px; align-items:center;">
                    <input 
                        type="text" 
                        id="unifiedOutboundInput" 
                        placeholder="Scan QR/barcode, or type item name, lot barcode, or unit SN..." 
                        autocomplete="off"
                        required
                        style="flex:1;"
                    >
                    <div class="suggest-box" id="unifiedSuggestBox"></div>
                    <button type="button" class="btn sm upload photo-btn scan-inline" onclick="openScanModal()" title="Scan Item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><line x1="7" y1="12" x2="17" y2="12"></line></svg>
                        Scan
                    </button>
                </div>
            </div>

            <?php include __DIR__ . '/../includes/item-card-select.php'; ?>

            <!-- Dynamic Lot Units Checklist (appears when a Lot is selected) -->
            <div id="unitChecklistContainer" class="field full" style="display:none;">
                <div style="border:1px solid var(--border-color, #e2e8f0); border-radius:8px; padding:14px; background:var(--card-bg, #fff);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                        <div>
                            <span style="font-weight:700; font-size:13px; color:var(--text, #1e293b);">Select Units to Issue (<span id="unitChecklistCount">0</span> available):</span>
                            <div style="font-size:11.5px; color:var(--muted, #64748b);">Check off specific units to dispatch, or select all for full lot outbound.</div>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button type="button" class="btn sm primary" onclick="toggleAllUnits(true)" style="padding:4px 10px; font-size:11px;">Select All</button>
                            <button type="button" class="btn sm primary" onclick="toggleAllUnits(false)" style="padding:4px 10px; font-size:11px;">Clear All</button>
                        </div>
                    </div>
                    <div id="unitChecklist" style="max-height:200px; overflow-y:auto; display:grid; grid-template-columns:repeat(auto-fill, minmax(210px, 1fr)); gap:8px; padding:4px;"></div>
                </div>
            </div>

            <div class="field" id="quantityField">
                <label for="quantity">Quantity to issue *</label>
                <input type="number" id="quantity" name="quantity" min="1" value="1" readonly style="background:var(--bg, #f8fafc); cursor:not-allowed;" required>
                <small id="qtyHintText" style="color:var(--muted); font-size:11.5px; margin-top:4px; display:block;">Select one or more serialized units to dispatch.</small>
            </div>

            <!-- Issued To Destination Selector -->
            <div class="field" style="display:flex; flex-direction:column; justify-content:flex-start;">
                <span class="field-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text);">Issued To *</span>
                <div style="display:flex; gap:10px; margin-top:4px; flex-wrap:wrap;">
                    <label style="display:flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; font-size:13px; font-weight:600; background:#ffffff;">
                        <input type="radio" name="issued_to_type" value="office" checked onchange="toggleIssuedToType(this.value)" style="width:auto; height:auto; accent-color:var(--blue);">
                        <span>Main Office</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; font-size:13px; font-weight:600; background:#ffffff;">
                        <input type="radio" name="issued_to_type" value="customer" onchange="toggleIssuedToType(this.value)" style="width:auto; height:auto; accent-color:var(--blue);">
                        <span>Customer</span>
                    </label>
                </div>
                <input type="hidden" name="issued_to" id="finalIssuedToInput" value="Main Office">
            </div>

            <!-- Dynamic Customer & Site Inputs (Visible when Customer radio is selected) -->
            <div id="customerIssuedToFields" class="field full" style="display:none;">
                <div class="form-grid" style="grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="field" style="position:relative; margin-bottom:0;">
                        <label for="customer_name">Customer / Company *</label>
                        <div class="suggest-wrap" style="position:relative;">
                            <input type="text" id="customer_name" name="customer_name" placeholder="Type or select customer company..." autocomplete="off" oninput="onCustomerInput(this.value)" onfocus="onCustomerInput(this.value)">
                            <div class="suggest-box" id="customerSuggestBox"></div>
                        </div>
                    </div>

                    <div class="field" style="margin-bottom:0;">
                        <label for="customer_site">Site (Optional)</label>
                        <input type="text" id="customer_site" name="customer_site" placeholder="e.g. Jakarta DC 1, Floor 3" maxlength="100" oninput="updateTargetCustodyPreview()">
                    </div>
                </div>

                <div style="background:var(--blue-soft); border:1px solid var(--blue-line); border-radius:var(--radius-sm); padding:8px 12px; font-size:12px; color:var(--blue-dark); margin-top:10px;">
                    <strong>Target Custody:</strong> <span id="targetCustodyPreviewText"><em>(Select or enter customer company)</em></span>
                </div>
            </div>

            <div class="field full">
                <label for="note">Note</label>
                <input type="text" id="note" name="note" placeholder="Optional (e.g. issued to department / field engineer)">
            </div>

        </div>

        <div class="add-row" style="margin-top:20px;">
            <button type="submit" class="btn primary">Issue Stock</button>
        </div>

    </form>
    <?php else: ?>
        <p style="color:var(--muted);">You have view-only access.</p>
    <?php endif; ?>

</section>

<section class="inventory-section" id="inventory" style="margin-top:20px;">

    <?php
    $emptyMessage = 'No items registered yet.';
    include __DIR__ . '/../includes/inventory-table.php';
    ?>

</section>

<?php include __DIR__ . '/../includes/scan-modal.php'; ?>
<?php include __DIR__ . '/../includes/inventory-card.php'; ?>
<?php include __DIR__ . '/../includes/print-lot-modal.php'; ?>

<script>
    window.escapeHtml = function (str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    };

    // Load serialized units for a selected lot
    window.loadLotUnits = function(lotId) {
        const checklistContainer = document.getElementById("unitChecklistContainer");
        const container = document.getElementById("unitChecklist");
        const countSpan = document.getElementById("unitChecklistCount");
        if (!container) return;

        if (checklistContainer) checklistContainer.style.display = "block";
        container.innerHTML = '<span style="color:var(--muted); font-size:12px; grid-column:1/-1;">Loading units...</span>';

        fetch("<?php echo BASE_URL; ?>/actions/get-lot-units.php?inventory_id=" + lotId)
            .then(res => res.json())
            .then(data => {
                if (!data.ok || !data.units || data.units.length === 0) {
                    container.innerHTML = '<span style="color:var(--muted); font-size:12px; grid-column:1/-1;">No active units found for this lot.</span>';
                    if (countSpan) countSpan.textContent = "0";
                    return;
                }

                const avail = data.units.filter(u => u.status === 'available');
                if (countSpan) countSpan.textContent = avail.length;

                if (avail.length === 0) {
                    container.innerHTML = '<span style="color:var(--muted); font-size:12px; grid-column:1/-1;">No active units currently available in warehouse.</span>';
                    return;
                }

                container.innerHTML = "";
                avail.forEach(u => {
                    const label = document.createElement("label");
                    label.style.display = "flex";
                    label.style.alignItems = "center";
                    label.style.gap = "8px";
                    label.style.padding = "6px 8px";
                    label.style.border = "1px solid var(--border-color, #e2e8f0)";
                    label.style.borderRadius = "6px";
                    label.style.background = "var(--bg, #f8fafc)";
                    label.style.cursor = "pointer";
                    label.style.fontSize = "12px";

                    const cb = document.createElement("input");
                    cb.type = "checkbox";
                    cb.name = "unit_ids[]";
                    cb.value = u.id;
                    cb.dataset.serial = (u.serial || "").toLowerCase();
                    cb.style.accentColor = "var(--primary, #4f46e5)";
                    cb.style.width = "16px";
                    cb.style.height = "16px";
                    cb.onchange = window.updateSelectedUnitsCount;

                    const txt = document.createElement("span");
                    txt.style.fontWeight = "600";
                    txt.style.color = "var(--text, #1e293b)";
                    txt.textContent = u.serial;

                    label.appendChild(cb);
                    label.appendChild(txt);
                    container.appendChild(label);
                });

                window.updateSelectedUnitsCount();
            })
            .catch(() => {
                container.innerHTML = '<span style="color:var(--red, #dc2626); font-size:12px; grid-column:1/-1;">Error loading units.</span>';
            });
    };

    window.toggleAllUnits = function(check) {
        const cbs = document.querySelectorAll('#unitChecklist input[name="unit_ids[]"]');
        cbs.forEach(cb => {
            cb.checked = check;
        });
        window.updateSelectedUnitsCount();
    };

    window.updateSelectedUnitsCount = function() {
        const checked = document.querySelectorAll('#unitChecklist input[name="unit_ids[]"]:checked');
        const qtyInput = document.getElementById("quantity");
        const count = checked.length;
        if (qtyInput) {
            qtyInput.value = count;
        }
        const hint = document.getElementById("qtyHintText");
        if (hint) {
            hint.textContent = count + " unit(s) selected for outbound.";
        }
    };

    // Apply selected whole lot
    window.selectLotForStockOut = function (it) {
        if (!it) return;
        if (it.status !== "available") {
            window.showFlash("Cannot select inactive or non-warehouse lot.", "error");
            return;
        }

        document.getElementById("stockItemId").value = it.id;
        document.getElementById("stockUnitId").value = "";
        document.getElementById("outboundModeInput").value = "individual";

        const input = document.getElementById("unifiedOutboundInput");
        if (input) input.value = it.name + (it.brand ? " (" + it.brand + ")" : "") + (it.barcode ? " [" + it.barcode + "]" : "");

        const singleUnitWrap = document.getElementById("singleUnitHiddenWrap");
        if (singleUnitWrap) singleUnitWrap.innerHTML = "";

        window.showItemCard(it);
        window.loadLotUnits(it.id);
    };

    // Apply selected individual serial unit
    window.selectUnitForStockOut = function (u) {
        if (!u) return;
        if (u.status !== "available") {
            window.showFlash("Cannot select inactive unit (" + u.serial + ").", "error");
            return;
        }

        const lotId = u.inventory_id || u.id;
        document.getElementById("stockItemId").value = lotId;
        document.getElementById("stockUnitId").value = u.unit_id;
        document.getElementById("outboundModeInput").value = "individual";

        const input = document.getElementById("unifiedOutboundInput");
        if (input) input.value = u.serial + " - " + u.name;

        // Hide lot checklist container
        const checklistContainer = document.getElementById("unitChecklistContainer");
        if (checklistContainer) checklistContainer.style.display = "none";
        const container = document.getElementById("unitChecklist");
        if (container) container.innerHTML = "";

        // Provide unit_ids[] hidden input for the single unit
        let singleUnitWrap = document.getElementById("singleUnitHiddenWrap");
        if (!singleUnitWrap) {
            singleUnitWrap = document.createElement("div");
            singleUnitWrap.id = "singleUnitHiddenWrap";
            document.getElementById("stockForm").appendChild(singleUnitWrap);
        }
        singleUnitWrap.innerHTML = '<input type="hidden" name="unit_ids[]" value="' + u.unit_id + '">';

        const qtyInput = document.getElementById("quantity");
        if (qtyInput) qtyInput.value = 1;

        const hint = document.getElementById("qtyHintText");
        if (hint) hint.textContent = "1 serialized unit selected (" + u.serial + ").";

        window.showItemCard({
            id: lotId,
            name: u.name,
            brand: u.brand,
            serial: u.serial,
            location: u.location,
            photo: u.photo,
            barcode: u.barcode,
            status: u.status,
            is_unit: true,
            quantity: 1
        });
    };

    // Unified Autocomplete Suggestion & Search Handler
    (function () {
        const input = document.getElementById("unifiedOutboundInput");
        const box = document.getElementById("unifiedSuggestBox");
        if (!input || !box) return;

        let debounceTimer = null;
        let activeAbort = null;

        function renderSuggestions(val) {
            val = (val || "").trim();
            if (!val) {
                box.style.display = "none";
                box.innerHTML = "";
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                if (activeAbort) activeAbort.abort();
                activeAbort = new AbortController();

                fetch("<?php echo BASE_URL; ?>/actions/suggest.php?type=unified_outbound&q=" + encodeURIComponent(val), {
                    signal: activeAbort.signal
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.ok) return;
                    const matchedUnits = data.units || [];
                    const matchedLots = data.lots || [];

                    if (matchedUnits.length === 0 && matchedLots.length === 0) {
                        box.innerHTML = '<div style="padding:10px 14px; font-size:12px; color:var(--muted);">No active available units or lots found.</div>';
                        box.style.display = "block";
                        return;
                    }

                    let html = "";
                    if (matchedUnits.length > 0) {
                        html += '<div style="padding:6px 12px; font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; background:var(--bg, #f8fafc); border-bottom:1px solid var(--border-color, #e2e8f0);">Serial Numbers (Individual Unit)</div>';
                        matchedUnits.forEach(u => {
                            html += `
                                <div class="suggest-item" data-type="unit" data-id="${u.unit_id}" style="padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--border-color, #e2e8f0); display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <span style="font-weight:700; color:var(--primary, #4f46e5); font-size:13px;">${escapeHtml(u.serial)}</span>
                                        <span style="font-size:12px; color:var(--text, #334155); margin-left:6px;">${escapeHtml(u.name)}</span>
                                    </div>
                                    <span style="font-size:11px; color:var(--muted);">${escapeHtml(u.location || '')}</span>
                                </div>
                            `;
                        });
                    }

                    if (matchedLots.length > 0) {
                        html += '<div style="padding:6px 12px; font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; background:var(--bg, #f8fafc); border-bottom:1px solid var(--border-color, #e2e8f0);">Lots / Batches (Select Units)</div>';
                        matchedLots.forEach(l => {
                            html += `
                                <div class="suggest-item" data-type="lot" data-id="${l.id}" style="padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--border-color, #e2e8f0); display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <span style="font-weight:600; color:var(--text, #1e293b); font-size:13px;">${escapeHtml(l.name)}</span>
                                        ${l.brand ? `<span style="font-size:11.5px; color:var(--muted);"> (${escapeHtml(l.brand)})</span>` : ''}
                                        ${l.barcode ? `<div style="font-size:11px; color:var(--muted);">Barcode: ${escapeHtml(l.barcode)}</div>` : ''}
                                    </div>
                                    <span style="font-size:12px; font-weight:600; color:var(--text);">${l.quantity} in stock</span>
                                </div>
                            `;
                        });
                    }

                    box.innerHTML = html;
                    box.style.display = "block";

                    box.querySelectorAll(".suggest-item").forEach(itemEl => {
                        itemEl.addEventListener("click", () => {
                            const type = itemEl.getAttribute("data-type");
                            const id = parseInt(itemEl.getAttribute("data-id"), 10);
                            if (type === "unit") {
                                const target = matchedUnits.find(u => u.unit_id === id);
                                if (target) window.selectUnitForStockOut(target);
                            } else {
                                const target = matchedLots.find(l => l.id === id);
                                if (target) window.selectLotForStockOut(target);
                            }
                            box.style.display = "none";
                        });
                    });
                })
                .catch(err => {
                    if (err.name !== 'AbortError') {
                        box.style.display = "none";
                    }
                });
            }, 180);
        }

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        input.addEventListener("input", (e) => renderSuggestions(e.target.value));
        input.addEventListener("focus", (e) => renderSuggestions(e.target.value));

        // Exact match check on Enter or change (e.g. physical scanner emulation)
        function checkDirectScan(val) {
            val = (val || "").trim();
            if (!val) return;

            fetch("<?php echo BASE_URL; ?>/actions/suggest.php?type=unified_outbound&q=" + encodeURIComponent(val))
                .then(res => res.json())
                .then(data => {
                    if (!data.ok) return;
                    const exactUnit = (data.units || []).find(u => u.serial && u.serial.toLowerCase() === val.toLowerCase());
                    if (exactUnit) {
                        window.selectUnitForStockOut(exactUnit);
                        box.style.display = "none";
                        return;
                    }
                    const exactLot = (data.lots || []).find(l => l.barcode && l.barcode.toLowerCase() === val.toLowerCase());
                    if (exactLot) {
                        window.selectLotForStockOut(exactLot);
                        box.style.display = "none";
                    }
                })
                .catch(() => {});
        }

        input.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                checkDirectScan(input.value);
            }
        });

        document.addEventListener("click", (e) => {
            if (!input.contains(e.target) && !box.contains(e.target)) {
                box.style.display = "none";
            }
        });
    })();

    // Scanning Modal Handler
    window.setScanHandler(function (item) {
        if (item.status !== "available") {
            const msg = document.getElementById("scanMsg");
            if (msg) {
                msg.className = "scan-result error";
                msg.innerHTML = "<strong>Rejected.</strong> Inactive or non-warehouse item cannot be issued.";
            }
            return false;
        }

        if (item.unit_id || item.is_unit) {
            window.selectUnitForStockOut({
                unit_id: item.unit_id,
                inventory_id: item.id,
                serial: item.serial,
                name: item.name,
                brand: item.brand,
                location: item.location,
                barcode: item.barcode,
                photo: item.photo,
                status: item.status,
            });
        } else {
            window.selectLotForStockOut(item);
        }
        return true;
    });

    // The "Outbound" row button in the items table fills the form
    window.pickStockItem = function (id) {
        fetch("<?php echo BASE_URL; ?>/actions/suggest.php?type=get_item&id=" + encodeURIComponent(id))
            .then(res => res.json())
            .then(data => {
                if (!data.ok || !data.item) {
                    window.showFlash("Could not retrieve item details.", "error");
                    return;
                }
                const it = data.item;
                if (it.status !== "available") {
                    window.showFlash("Cannot issue this item: inactive / out of the warehouse.", "error");
                    return;
                }
                window.selectLotForStockOut(it);
                const form = document.getElementById("stockForm");
                if (form) form.scrollIntoView({ behavior: "smooth" });
            })
            .catch(() => {
                window.showFlash("Error retrieving item details.", "error");
            });
    };

    // Customer Issued To Radio & Suggestion Logic
    (function () {
        const customerList = <?php echo json_encode(array_values(array_filter(array_unique($customerList)))); ?>;

        window.toggleIssuedToType = function (type) {
            const custFields = document.getElementById("customerIssuedToFields");
            const custInput = document.getElementById("customer_name");
            const finalInput = document.getElementById("finalIssuedToInput");

            if (type === "customer") {
                if (custFields) custFields.style.display = "block";
                if (custInput) {
                    custInput.required = true;
                    custInput.focus();
                }
                updateTargetCustodyPreview();
            } else {
                if (custFields) custFields.style.display = "none";
                if (custInput) custInput.required = false;
                if (finalInput) finalInput.value = "Main Office";
            }
        };

        window.onCustomerInput = function (val) {
            const box = document.getElementById("customerSuggestBox");
            if (!box) return;

            const query = (val || "").trim().toLowerCase();
            const matches = customerList.filter(c => c && c.toLowerCase().includes(query));

            if (matches.length === 0) {
                box.style.display = "none";
                box.innerHTML = "";
                updateTargetCustodyPreview();
                return;
            }

            box.innerHTML = "";
            matches.slice(0, 8).forEach(c => {
                const item = document.createElement("div");
                item.className = "suggest-item";
                item.style.cursor = "pointer";
                item.innerHTML = '<div class="suggest-label">' + escapeHtml(c) + '</div>';
                item.onmousedown = function (e) {
                    e.preventDefault();
                    selectCustomer(c);
                };
                box.appendChild(item);
            });
            box.style.display = "block";
            updateTargetCustodyPreview();
        };

        window.selectCustomer = function (name) {
            const input = document.getElementById("customer_name");
            const box = document.getElementById("customerSuggestBox");
            if (input) input.value = name;
            if (box) {
                box.style.display = "none";
                box.innerHTML = "";
            }
            updateTargetCustodyPreview();
            document.getElementById("customer_site")?.focus();
        };

        window.updateTargetCustodyPreview = function () {
            const type = document.querySelector('input[name="issued_to_type"]:checked')?.value || "office";
            const finalInput = document.getElementById("finalIssuedToInput");
            const previewSpan = document.getElementById("targetCustodyPreviewText");

            if (type === "office") {
                if (finalInput) finalInput.value = "Main Office";
                return;
            }

            const comp = document.getElementById("customer_name")?.value.trim() || "";
            const site = document.getElementById("customer_site")?.value.trim() || "";

            if (!comp) {
                if (previewSpan) previewSpan.innerHTML = '<em>(Select or enter customer company)</em>';
                if (finalInput) finalInput.value = "";
                return;
            }

            const fullVal = site ? (comp + " - " + site) : comp;
            if (previewSpan) previewSpan.textContent = fullVal;
            if (finalInput) finalInput.value = fullVal;
        };

        document.addEventListener("click", function (e) {
            const box = document.getElementById("customerSuggestBox");
            const input = document.getElementById("customer_name");
            if (box && input && !box.contains(e.target) && e.target !== input) {
                box.style.display = "none";
            }
        });
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
