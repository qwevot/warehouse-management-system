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
                <div id="selCardBadge" style="display:none; margin-bottom:6px;"></div>
                <h3 class="scan-name" id="selCardName"></h3>
                <div class="scan-row" id="selCardSerialRow"><span>Serial:</span> <b id="selCardSerial"></b></div>
                <div class="scan-row" id="selCardBrandRow"><span>Brand:</span> <span id="selCardBrand"></span></div>
                <div class="scan-row" id="selCardQtyRow"><span>Quantity:</span> <b id="selCardQty"></b></div>
                <div class="scan-row" id="selCardLocationRow"><span>Location:</span> <span id="selCardLocation"></span></div>
                <div class="scan-row" id="selCardStatusRow"><span>Status:</span> <span class="status" id="selCardStatus"></span></div>
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