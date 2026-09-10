<?php
$pageTitle = 'Stock Out';
$activeNav = 'stock-out';

require_once __DIR__ . '/../includes/auth.php';
require_login();

try {
    $pdo = db();

    // Fetch active warehouse lots for whole lot outbound (in warehouse & quantity > 0)
    $items = $pdo->query("
        SELECT id, name, brand, serial, quantity, location, bin_location, deployment_site, barcode, qr, status, photo, created_at
        FROM inventory_list
        WHERE deleted_at IS NULL
          AND quantity > 0
          AND status = 'available'
          AND deployment_site = 'In Warehouse'
        ORDER BY name ASC
    ")->fetchAll();

    // Fetch active available units physically in warehouse for manual SN dropdown & search
    $availableUnits = $pdo->query("
        SELECT u.id AS unit_id, u.inventory_id, u.serial, u.status, u.bin_location, u.deployment_site,
               COALESCE(NULLIF(u.bin_location, ''), NULLIF(u.location, ''), i.location, '') AS location,
               i.name, i.brand, i.barcode, i.photo
        FROM inventory_units u
        JOIN inventory_list i ON i.id = u.inventory_id
        WHERE u.deleted_at IS NULL 
          AND i.deleted_at IS NULL 
          AND u.status = 'available'
          AND u.deployment_site = 'In Warehouse'
        ORDER BY u.serial ASC
    ")->fetchAll();
} catch (Exception $e) {
    $items = [];
    $availableUnits = [];
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

        <input type="hidden" name="outbound_mode" id="outboundModeInput" value="whole_lot">
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
                    <button type="button" class="btn sm upload photo-btn scan-inline" onclick="openScanModal()" title="Quick Scan QR or Barcode" style="margin:0; height:40px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><line x1="7" y1="12" x2="17" y2="12"></line></svg>
                        Quick Scan
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
                            <button type="button" class="btn sm secondary" onclick="toggleAllUnits(true)" style="padding:4px 10px; font-size:11px;">Select All</button>
                            <button type="button" class="btn sm secondary" onclick="toggleAllUnits(false)" style="padding:4px 10px; font-size:11px;">Clear All</button>
                        </div>
                    </div>
                    <div id="unitChecklist" style="max-height:200px; overflow-y:auto; display:grid; grid-template-columns:repeat(auto-fill, minmax(210px, 1fr)); gap:8px; padding:4px;"></div>
                </div>
            </div>

            <div class="field" id="quantityField">
                <label for="quantity">Quantity to issue *</label>
                <input type="number" id="quantity" name="quantity" min="1" value="1" readonly style="background:var(--bg, #f8fafc); cursor:not-allowed;" required>
                <small id="qtyHintText" style="color:var(--muted); font-size:11.5px; margin-top:4px; display:block;">Whole lot outbound automatically dispatches all on-hand units in the batch.</small>
            </div>

            <div class="field">
                <label for="issued_to">Issued To *</label>
                <input type="text" id="issued_to" name="issued_to" placeholder="Who is this stock being issued to?" required>
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
        <p style="color:var(--muted);">You have view-only access. Stock movements require a technician account or higher.</p>
    <?php endif; ?>

</section>

<section class="inventory-section" id="inventory" style="margin-top:20px;">

    <?php
    $autofillTitle = 'Autofill the stock-out form with this item';
    include __DIR__ . '/../includes/movement-item-list.php';
    ?>

</section>

<?php include __DIR__ . '/../includes/scan-modal.php'; ?>
<?php include __DIR__ . '/../includes/inventory-card.php'; ?>
<?php include __DIR__ . '/../includes/print-lot-modal.php'; ?>

<script>
    <?php include __DIR__ . '/../includes/stock-items-data.php'; ?>

    window.AVAILABLE_UNITS = <?php echo json_encode(array_map(function ($u) {
        return [
            'unit_id'      => (int)$u['unit_id'],
            'inventory_id' => (int)$u['inventory_id'],
            'serial'       => $u['serial'],
            'name'         => $u['name'],
            'brand'        => $u['brand'] ?? '',
            'location'     => $u['location'] ?? '',
            'barcode'      => $u['barcode'] ?? '',
            'photo'        => $u['photo'] ? BASE_URL . '/' . $u['photo'] : '',
            'status'       => $u['status'],
        ];
    }, $availableUnits)); ?>;


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

        function renderSuggestions(val) {
            val = (val || "").trim().toLowerCase();
            if (!val) {
                box.style.display = "none";
                box.innerHTML = "";
                return;
            }

            // Filter active units only
            const matchedUnits = (window.AVAILABLE_UNITS || []).filter(u =>
                u.status === 'available' &&
                (u.serial.toLowerCase().includes(val) || u.name.toLowerCase().includes(val) || (u.barcode && u.barcode.toLowerCase().includes(val)))
            ).slice(0, 8);

            // Filter active lots only
            const matchedLots = (window.STOCK_ITEMS || []).filter(i =>
                i.status === 'available' &&
                (i.name.toLowerCase().includes(val) || (i.barcode && i.barcode.toLowerCase().includes(val)) || (i.brand && i.brand.toLowerCase().includes(val)))
            ).slice(0, 6);

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
                        const target = (window.AVAILABLE_UNITS || []).find(u => u.unit_id === id);
                        if (target) window.selectUnitForStockOut(target);
                    } else {
                        const target = (window.STOCK_ITEMS || []).find(l => l.id === id);
                        if (target) window.selectLotForStockOut(target);
                    }
                    box.style.display = "none";
                });
            });
        }

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        input.addEventListener("input", (e) => renderSuggestions(e.target.value));
        input.addEventListener("focus", (e) => renderSuggestions(e.target.value));

        // Exact match check on Enter or change (e.g. physical scanner emulation)
        function checkDirectScan(val) {
            val = (val || "").trim().toLowerCase();
            if (!val) return;

            // Check if exact SN match
            const exactUnit = (window.AVAILABLE_UNITS || []).find(u => u.serial && u.serial.toLowerCase() === val);
            if (exactUnit) {
                window.selectUnitForStockOut(exactUnit);
                box.style.display = "none";
                return;
            }

            // Check if exact Lot Barcode match
            const exactLot = (window.STOCK_ITEMS || []).find(l => l.barcode && l.barcode.toLowerCase() === val);
            if (exactLot) {
                window.selectLotForStockOut(exactLot);
                box.style.display = "none";
                return;
            }
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

    // The "Outbound" row button in movement-item-list fills the form
    window.pickStockItem = function (id) {
        const it = (window.STOCK_ITEMS || []).find((i) => i.id === id);
        if (!it) return;
        if (it.status !== "available") {
            window.showFlash("Cannot issue this item: inactive / out of the warehouse.", "error");
            return;
        }
        window.selectLotForStockOut(it);
        const form = document.getElementById("stockForm");
        if (form) form.scrollIntoView({ behavior: "smooth" });
    };
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
