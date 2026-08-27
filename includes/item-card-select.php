<!-- Shared item card shown when an item is selected/scanned. Hidden by default.
     Populate it with window.showItemCard(item) (defined in app.js). Used by
     Scan, Stock In, Stock Out, and Transfers. -->
<div class="field full" id="selCard" style="display:none;">
    <div class="transfer-item-card">

        <div class="scan-item">
            <div id="selCardPhotoWrap">
                <img id="selCardPhoto" class="scan-photo" alt="Item photo" style="display:none;">
                <div id="selCardPhotoPh" class="scan-photo"></div>
            </div>
            <div style="flex:1; min-width:0;">
                <h3 class="scan-name" id="selCardName"></h3>
                <div class="scan-row"><span>Serial:</span> <b id="selCardSerial"></b></div>
                <div class="scan-row"><span>Brand:</span> <span id="selCardBrand"></span></div>
                <div class="scan-row"><span>Quantity:</span> <b id="selCardQty"></b></div>
                <div class="scan-row"><span>Location:</span> <span id="selCardLocation"></span></div>
<div class="scan-row"><span>Status:</span> <span class="status" id="selCardStatus"></span></div>
            </div>
        </div>

        <div class="detail-grid" style="margin-top:12px;">
            <div class="detail-box full">
                <div class="dt">Barcode</div>
                <svg id="selCardBarcode" style="width:100%;"></svg>
            </div>
            <div class="detail-box full">
                <div class="dt">QR Code</div>
                <div id="selCardQr"></div>
            </div>
        </div>

    </div>
</div>