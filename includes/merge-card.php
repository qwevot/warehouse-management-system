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
                <span class="field-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text);">New barcode number</span>
                <div class="barcode-auto-notice" style="display:flex; align-items:center; gap:8px; padding:10px 14px; background:#f8fafc; border:1px solid var(--border); border-radius:8px; font-size:13px; color:var(--text);">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--blue); flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    <span>A new barcode number will be automatically generated as a brand-new inbound lot.</span>
                </div>
            </div>

            <div class="field full" id="mergeTargetWrap" style="display:none;">
                <label for="mergeTargetSelect">Select barcode number to keep</label>
                <select id="mergeTargetSelect" class="form-select" style="width:100%; height:38px; border:1px solid var(--border); border-radius:8px; padding:0 12px; font-size:13px; background:#fff; outline:none; color:var(--text); cursor:pointer;"></select>
            </div>

        </div>

        <div class="merge-actions" style="margin-top:16px;">
            <span class="merge-hint">Select at least 2 items from the table below.</span>
            <button type="button" class="btn primary" onclick="submitMerge()">Merge Items</button>
        </div>

    </div>

</div>