<div class="inventory-section merge-panel" id="splitPanel" style="display:none;">

    <div class="section-header">
        <div class="section-title">Split Item</div>
    </div>

    <div class="wizard-body">

        <div class="form-grid" style="align-items: center;">

            <div class="field" style="max-width:140px;">
                <label for="splitParts">Number of parts</label>
                <input type="number" id="splitParts" min="2" max="10" value="2" onkeydown="return false" oninput="renderSplitQtyInputs()">
            </div>

            <div class="merge-hint" id="splitQtySum" style="align-self: flex-end; margin-bottom: 8px; font-weight: 600;"></div>

        </div>

        <div class="split-parts-container" id="splitPartsContainer"></div>

        <div class="merge-actions" style="margin-top:16px;">
            <span class="merge-hint">Select one item from the table below. Quantities must add up to its current stock.</span>
            <button type="button" class="btn primary" onclick="submitSplit()">Split Item</button>
        </div>

    </div>

</div>