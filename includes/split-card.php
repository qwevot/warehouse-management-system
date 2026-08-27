<div class="inventory-section merge-panel" id="splitPanel" style="display:none;">

    <div class="section-header">
        <div class="section-title">Split Item</div>
    </div>

    <div class="wizard-body">

        <div class="form-grid">

            <div class="field full">
                <label for="splitParts">Number of parts</label>
                <input type="number" id="splitParts" min="2" value="2" oninput="renderSplitQtyInputs()">
            </div>

        </div>

        <div style="margin-top:14px;">
            <label style="font-size:11px;font-weight:600;color:var(--text);display:block;margin-bottom:6px;">Part quantities</label>
            <div class="split-qty-grid" id="splitQtyInputs"></div>
            <div class="merge-hint" id="splitQtySum" style="margin-top:6px;"></div>
        </div>

        <div class="merge-actions" style="margin-top:16px;">
            <span class="merge-hint">Select one item from the table below. Quantities must add up to its current stock.</span>
            <button type="button" class="btn primary" onclick="submitSplit()">Split Item</button>
        </div>

    </div>

</div>