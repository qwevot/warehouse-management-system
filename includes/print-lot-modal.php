<!-- Modal: Print Lot Barcode & Unit QR Codes -->
<div class="modal-backdrop" id="printLotModal">
    <div class="modal" style="max-width: 820px; width: 95%;">
        <div class="modal-header">
            <div class="modal-title">Print Barcode &amp; Unit QR Codes</div>
            <button type="button" class="modal-close" onclick="closeModal('printLotModal')">×</button>
        </div>
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
            <!-- Lot Info Summary Card -->
            <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 14px 18px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;" class="no-print">
                <div>
                    <h3 id="printLotTitle" style="margin: 0 0 4px; font-size: 16px; color: var(--ink);">—</h3>
                    <div style="font-size: 13px; color: var(--muted);" id="printLotSubtitle">—</div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn secondary" onclick="printLotSection('lot-only')">Print Lot Barcode</button>
                    <button type="button" class="btn primary" onclick="printLotSection('all')">Print All (Barcode + QRs)</button>
                </div>
            </div>

            <div id="printLotLoading" style="text-align: center; padding: 30px; color: var(--muted); font-size: 14px;">
                Loading QR codes...
            </div>

            <div id="printLotContent" style="display: none;">
                <!-- Lot Level Barcode Tile -->
                <div class="print-lot-box" id="printLotBarcodeSection" style="border: 1.5px dashed var(--border); border-radius: 8px; padding: 14px; text-align: center; margin-bottom: 20px; background: #fff;">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 2px;" id="printLotBarcodeItem">—</div>
                    <div style="font-size: 12px; color: var(--muted); margin-bottom: 8px;" id="printLotBarcodeLoc">—</div>
                    <svg id="printLotBarcodeSvg" style="margin: 0 auto; max-width: 260px; height: 60px;"></svg>
                </div>

                <!-- Unit QR Code Tiles Grid -->
                <div style="margin-bottom: 10px; font-weight: 700; font-size: 13px; color: var(--text);" class="no-print">
                    Individual Unit QR Codes (<span id="printLotUnitCount">0</span> units):
                </div>
                <div id="printLotUnitsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px;">
                    <!-- Dynamically populated -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn secondary" onclick="closeModal('printLotModal')">Close</button>
            <button type="button" class="btn primary" onclick="printLotSection('all')">Print</button>
        </div>
    </div>
</div>

<script>
window.openPrintLotModal = function (inventoryId) {
    const modal = document.getElementById("printLotModal");
    const loading = document.getElementById("printLotLoading");
    const content = document.getElementById("printLotContent");
    if (!modal) return;

    window.openModal("printLotModal");
    if (loading) loading.style.display = "block";
    if (content) content.style.display = "none";

    fetch("<?php echo BASE_URL; ?>/actions/get-lot-units.php?inventory_id=" + encodeURIComponent(inventoryId))
        .then(res => res.json())
        .then(data => {
            if (!data.ok) {
                if (loading) loading.innerHTML = '<span style="color:var(--red);">' + (data.message || 'Failed to load lot units.') + '</span>';
                return;
            }

            const lot = data.lot;
            const units = data.units || [];

            document.getElementById("printLotTitle").textContent = lot.name + (lot.brand ? ' (' + lot.brand + ')' : '');
            document.getElementById("printLotSubtitle").textContent = 'Location: ' + (lot.location || 'In Warehouse') + '  ·  Units: ' + units.length;
            document.getElementById("printLotBarcodeItem").textContent = lot.name + (lot.brand ? ' - ' + lot.brand : '');
            document.getElementById("printLotBarcodeLoc").textContent = 'Location: ' + (lot.location || 'In Warehouse') + ' | Barcode: ' + lot.barcode;
            document.getElementById("printLotUnitCount").textContent = units.length;

            // Render Lot Barcode
            const bcSvg = document.getElementById("printLotBarcodeSvg");
            if (bcSvg && lot.barcode && window.JsBarcode) {
                bcSvg.innerHTML = "";
                window.JsBarcode("#printLotBarcodeSvg", lot.barcode, {
                    format: "EAN13", displayValue: true, width: 2, height: 50, fontSize: 13
                });
            }

            // Render Units QR Grid
            const unitsGrid = document.getElementById("printLotUnitsGrid");
            unitsGrid.innerHTML = "";

            if (units.length === 0) {
                unitsGrid.innerHTML = '<div style="grid-column:1/-1; color:var(--muted); font-size:13px; text-align:center; padding:16px;">No individual unit QR codes provisioned for this lot.</div>';
            } else {
                units.forEach((u, idx) => {
                    const card = document.createElement("div");
                    card.className = "unit-qr-print-card";
                    card.style.border = "1px solid var(--border)";
                    card.style.borderRadius = "8px";
                    card.style.padding = "10px";
                    card.style.textAlign = "center";
                    card.style.background = "#ffffff";

                    const qrWrap = document.createElement("div");
                    qrWrap.id = "unitQr_" + u.id;
                    qrWrap.style.display = "flex";
                    qrWrap.style.justifyContent = "center";
                    qrWrap.style.margin = "6px 0";

                    card.innerHTML = `
                        <div style="font-weight:700; font-size:12px; color:var(--ink);">${u.serial}</div>
                        <div style="font-size:10.5px; color:var(--muted);">${lot.name}</div>
                    `;
                    card.appendChild(qrWrap);

                    const locDiv = document.createElement("div");
                    locDiv.style.fontSize = "10px";
                    locDiv.style.color = "var(--muted)";
                    locDiv.textContent = u.location || lot.location || 'In Warehouse';
                    card.appendChild(locDiv);

                    unitsGrid.appendChild(card);

                    // Generate QR
                    if (window.QRCode && u.qr) {
                        new window.QRCode(qrWrap, { text: u.qr, width: 100, height: 100 });
                    }
                });
            }

            if (loading) loading.style.display = "none";
            if (content) content.style.display = "block";
        })
        .catch(err => {
            if (loading) loading.innerHTML = '<span style="color:var(--red);">Error loading unit details.</span>';
        });
};

window.printLotSection = function (scope) {
    document.body.classList.remove("print-lot-barcode-only", "print-lot-all");
    if (scope === "lot-only") {
        document.body.classList.add("print-lot-barcode-only");
    } else {
        document.body.classList.add("print-lot-all");
    }
    setTimeout(() => window.print(), 50);
};
</script>
