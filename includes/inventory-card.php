<div class="modal-backdrop" id="itemModal">

    <div class="modal">

        <div class="modal-header">
            <div class="modal-title">Item Details</div>
            <button type="button" class="modal-close">×</button>
        </div>

        <div class="modal-body">

            <div class="item-detail">

                <img class="detail-photo" id="detailPhoto" src="" alt="Item photo">

                <h3 id="detailName"></h3>

                <span class="status available" id="detailStatus">
                </span>

                <div class="detail-grid">

                    <div class="detail-box">
                        <div class="dt">Serial Number</div>
                        <div class="dd" id="detailSerial"></div>
                    </div>

                    <div class="detail-box">
                        <div class="dt">Quantity</div>
                        <div class="dd" id="detailQty"></div>
                    </div>

                    <div class="detail-box">
                        <div class="dt">Location</div>
                        <div class="dd" id="detailLocation"></div>
                    </div>

                    <div class="detail-box full">
                        <div class="dt">Registered On</div>
                        <div class="dd" id="detailDate"></div>
                    </div>

                    <div class="detail-box full" data-print="qr">
                        <div class="dt">QR Code</div>
                        <div id="detailQr"></div>
                    </div>

                    <div class="detail-box full" data-print="barcode">
                        <div class="dt">Barcode</div>
                        <svg id="detailBarcode" style="width:100%;"></svg>
                    </div>

                </div>

            </div>

        </div>

        <div class="modal-footer">
            <span style="margin-right:auto;" class="merge-hint">Print labels:</span>
            <button type="button" class="btn upload" onclick="printScoped('barcode')">Print Barcode</button>
            <button type="button" class="btn upload" onclick="printScoped('qr')">Print QR Code</button>
            <button type="button" class="btn primary" onclick="printScoped('both')">Print Both</button>
        </div>

    </div>

</div>
