<?php
if (!isset($masterItems)) {
    try {
        $masterItems = db()->query("SELECT product_name, brand FROM master_items WHERE deleted_at IS NULL ORDER BY product_name ASC, brand ASC")->fetchAll();
    } catch (Exception $e) {
        $masterItems = [];
    }
}
?>
<script>
if (!window.MASTER_ITEMS) {
    window.MASTER_ITEMS = <?php echo json_encode(array_map(function($mi) {
        return ['product_name' => $mi['product_name'], 'brand' => $mi['brand'] ?? ''];
    }, $masterItems)); ?>;
}
</script>
<div class="modal-backdrop" id="editModal">

    <form id="editItemForm" action="<?php echo BASE_URL; ?>/actions/process-edit.php" method="POST" enctype="multipart/form-data" class="modal">

        <div class="modal-header">
            <div class="modal-title">Edit Item</div>
            <button type="button" class="modal-close">×</button>
        </div>

        <div class="modal-body">

            <input type="hidden" name="id" id="editId">
            <input type="hidden" name="photo" id="editPhotoData">

            <div class="form-grid">

                <div class="field">
                    <label for="edit_item_name">Item Name *</label>
                    <div class="suggest-wrap">
                        <input
                            type="text"
                            id="edit_item_name"
                            name="item_name"
                            placeholder="Search item name..."
                            autocomplete="off"
                            required
                        >
                        <div class="suggest-box" id="editItemNameSuggest"></div>
                    </div>
                    <div class="error-msg">Please select an item from Master Data.</div>
                </div>

                <div class="field">
                    <label for="edit_brand">Brand</label>
                    <div class="suggest-wrap">
                        <input
                            type="text"
                            id="edit_brand"
                            name="brand"
                            class="js-brand-suggest"
                            placeholder="Search or select brand..."
                            autocomplete="off"
                        >
                        <div class="suggest-box" id="editBrandSuggest"></div>
                    </div>
                </div>

                <div class="field">
                    <?php
                    $locPrefix = 'editLoc';
                    $locUpdateFn = 'updateEditLocation()';
                    $locHiddenName = 'location';
                    $locHiddenId = 'edit_location';
                    include __DIR__ . '/location-picker.php';
                    ?>
                    <div class="error-msg">Please enter a location.</div>
                </div>

                <div class="field">
                    <label for="edit_quantity">Quantity *</label>
                    <input type="number" id="edit_quantity" name="quantity" min="1" value="1" required>
                </div>

            </div>

            <div class="photo-area">

                <div class="photo-preview-box">
                    <span class="placeholder" id="editPhotoPlaceholder">No photo yet.</span>
                    <img id="editPhotoPreview" src="" alt="Item photo" style="display:none;">
                </div>

                <div class="photo-actions">

                    <button type="button" class="btn secondary" onclick="triggerFilePick('edit')">Change Photo</button>

                    <input
                        type="file"
                        id="editPhotoFile"
                        accept="image/*"
                        capture="environment"
                        style="display:none;"
                    >

                </div>

            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn danger" id="editBinBtn" style="margin-right:auto;" onclick="runEditMoveToBin()">
                Move to Bin
            </button>
            <button type="button" class="btn cancel" onclick="closeModal('editModal')">Cancel</button>
            <button type="submit" class="btn primary">Save Changes</button>
        </div>

    </form>

</div>
