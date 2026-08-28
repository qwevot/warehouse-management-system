<div class="field">
    <label for="stockItemSearch">Item *</label>
    <div class="suggest-wrap">
        <input
            type="text"
            id="stockItemSearch"
            placeholder="Search item name, brand, or serial..."
            autocomplete="off"
            required
        >
        <input type="hidden" name="item_id" id="stockItemId">
        <div class="suggest-box" id="stockSuggest"></div>
        <button type="button" class="btn sm upload photo-btn scan-inline" onclick="openScanModal()" title="Scan Item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><line x1="7" y1="12" x2="17" y2="12"></line></svg>
            Scan
        </button>
    </div>
</div>
