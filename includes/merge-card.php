<div class="inventory-section merge-panel" id="mergePanel" style="display:none;">

    <div class="section-header">
        <div class="section-title">Merge Items</div>
        <div class="merge-options">
            <label class="filter-option"><input type="radio" name="mergeMode" value="new" checked onchange="updateMergeDestination()"> Merge to new barcode number</label>
            <label class="filter-option"><input type="radio" name="mergeMode" value="existing" onchange="updateMergeDestination()"> Merge to existing barcode number</label>
        </div>
    </div>

    <div class="wizard-body">

        <div class="form-grid">

            <div class="field full" id="mergeBarcodeWrap">
                <label for="mergeBarcode">New barcode number (optional)</label>
                <input type="text" id="mergeBarcode" placeholder="Leave empty to auto-generate">
            </div>

            <div class="field full" id="mergeTargetWrap" style="display:none;">
                <label for="mergeTargetSelect">Target item (existing barcode)</label>
                <select id="mergeTargetSelect" class="filter"></select>
            </div>

        </div>

        <div class="merge-actions" style="margin-top:16px;">
            <span class="merge-hint">Select at least 2 items from the table below.</span>
            <button type="button" class="btn primary" onclick="submitMerge()">Merge Items</button>
        </div>

    </div>

</div>