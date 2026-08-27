<div class="modal-backdrop" id="genModal">

    <div class="modal">

        <div class="modal-header">
            <div class="modal-title">Generate Barcode & QR Code</div>
            <button type="button" class="modal-close">×</button>
        </div>

        <div class="modal-body">

            <div class="form-grid no-print">

                <div class="field full">
                    <label>Item</label>
                    <div class="dd" id="genItemName">—</div>
                </div>

                <div class="field full">
                    <label>Location</label>
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
                <button type="button" class="btn upload" onclick="printScoped('barcode')">Print Barcode</button>
                <button type="button" class="btn upload" onclick="printScoped('qr')">Print QR Code</button>
                <button type="button" class="btn primary" onclick="printScoped('both')">Print Both</button>
            </span>
        </div>

    </div>

</div>