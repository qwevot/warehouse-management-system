<div class="modal-backdrop" id="genModal">

    <div class="modal">

        <div class="modal-header">
            <div class="modal-title">Generate Barcode & QR Code</div>
            <button type="button" class="modal-close">×</button>
        </div>

        <div class="modal-body">

            <div class="form-grid no-print">

                <div class="field full">
                    <span class="field-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text);">Item</span>
                    <div class="dd" id="genItemName">—</div>
                </div>

                <div class="field full">
                    <span class="field-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text);">Location</span>
                    <div class="dd" id="genItemLocation">—</div>
                </div>

            </div>

            <div class="labels-preview" id="labelsPreview" style="display:none;">

                <div class="label-tile" data-print="info">
                    <div class="label-title">Item</div>
                    <div class="label-value" id="genName">—</div>
                </div>

                <div class="label-tile" data-print="info">
                    <div class="label-title">Serial Number</div>
                    <div class="label-value" id="genSerial">—</div>
                </div>

                <div class="label-tile" data-print="info">
                    <div class="label-title">Location</div>
                    <div class="label-value" id="genLocationValue">—</div>
                </div>

                <div class="label-tile" data-print="barcode">
                    <div class="label-title">Barcode</div>
                    <svg id="genBarcode" style="width:100%;"></svg>
                </div>

                <div class="label-tile" data-print="qr">
                    <div class="label-title">QR Code</div>
                    <div id="genQr"></div>
                </div>

            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn cancel" onclick="closeModal('genModal')">Cancel</button>
            <button type="button" class="btn primary" id="genGenerateBtn" onclick="generateItemLabel()">Generate Barcode & QR Code</button>
            <span id="genPrintButtons" style="display:none; margin-left:auto; display:inline-flex; gap:8px;">
                <button type="button" class="btn primary" onclick="printScoped('both')">Print</button>
            </span>
        </div>

    </div>

</div>