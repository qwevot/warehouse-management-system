<?php
$masterItems = [];
try {
    $masterItems = db()->query("SELECT product_name, brand FROM master_items WHERE deleted_at IS NULL ORDER BY product_name ASC, brand ASC")->fetchAll();
} catch (Exception $e) {
    $masterItems = [];
}
?>
<script>
window.MASTER_ITEMS = <?php echo json_encode(array_map(function($mi) {
    return ['product_name' => $mi['product_name'], 'brand' => $mi['brand'] ?? ''];
}, $masterItems)); ?>;
</script>
<div class="inventory-section add-panel" id="addItemSection" style="display:none;">

    <form id="addItemForm" action="<?php echo BASE_URL; ?>/actions/process-item.php" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
            <div class="modal-title">Add Item</div>
            <button type="button" class="modal-close" onclick="closeAddForm()">×</button>
        </div>

        <div class="modal-body">

            <input type="hidden" name="photo" id="photoData">

            <div class="form-grid">

                <div class="field">
                    <label for="product_name">Product Name*</label>
                    <div class="suggest-wrap">
                        <input type="text" id="product_name" name="product_name" placeholder="e.g. Network Switch" autocomplete="off" required>
                        <div class="suggest-box" id="productSuggest"></div>
                    </div>
                    <div class="error-msg">Product name is required.</div>
                </div>

                <div class="field">
                    <label for="brand">Brand</label>
                    <select id="brand" name="brand">
                        <option value="">— Select brand —</option>
                        <?php foreach (all_brands() as $b): ?>
                            <option value="<?php echo e($b); ?>"><?php echo e($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="quantity">Quantity*</label>
                    <input type="number" id="quantity" name="quantity" min="1" value="1">
                </div>

                <div class="field">
                    <div class="location-picker">
                        <div class="loc-input">
                            <label for="locShelf">Shelf</label>
                            <input type="text" id="locShelf" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); updateLocation()">
                        </div>
                        <div class="loc-input">
                            <label for="locRow">Row</label>
                            <input type="text" id="locRow" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); updateLocation()">
                        </div>
                        <div class="loc-input">
                            <label for="locCol">Column</label>
                            <input type="text" id="locCol" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); updateLocation()">
                        </div>
                    </div>
                    <input type="hidden" name="location" id="location">
                </div>

            </div>

            <div class="photo-area">

                <div class="photo-preview-box">
                    <span class="placeholder" id="photoPlaceholder">
                        No photo yet.
                        <br><br>
                        Use the buttons to take or upload one.
                    </span>
                    <img id="photoPreview" src="" alt="Item photo" style="display:none;">
                </div>

                <div class="photo-actions">

                    <button type="button" class="btn secondary photo-btn" onclick="triggerFilePick()">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none" width="16" height="16" aria-hidden="true"><g id="SVGRepo_iconCarrier"><path d="M3,12.3v7a2,2,0,0,0,2,2H19a2,2,0,0,0,2-2v-7" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path><polyline points="7.9 6.7 12 2.7 16.1 6.7" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></polyline><line x1="12" y1="16.3" x2="12" y2="4.8" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></line></g></svg>
                        Upload Photo
                    </button>

                    <button type="button" class="btn upload photo-btn" onclick="triggerCapturePick()">
                        <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" width="16" height="16" aria-hidden="true"><path fill-rule="evenodd" clip-rule="evenodd" fill="currentColor" d="M3 3H0V14H16V3H13L11 1H5L3 3ZM8 11C9.65685 11 11 9.65685 11 8C11 6.34315 9.65685 5 8 5C6.34315 5 5 6.34315 5 8C5 9.65685 6.34315 11 8 11Z"></path></svg>
                        Take Photo
                    </button>

                    <input
                        type="file"
                        id="photoFile"
                        accept="image/*"
                        style="display:none;"
                    >

                    <input
                        type="file"
                        id="photoCaptureFile"
                        accept="image/*"
                        capture="environment"
                        style="display:none;"
                    >

                </div>

            </div>

        </div>

        <div class="modal-footer">

            <button type="submit" class="btn primary">Submit Item</button>
        </div>

    </form>

</div>
