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
                    <label>Current Location</label>
                    <div style="padding: 10px 12px; background: var(--bg, #f8fafc); border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius-sm, 6px); font-size: 13px;">
                        <span id="editLocationDisplay" style="font-weight: 600; color: var(--text, #1e293b);">—</span>
                        <div style="font-size: 11px; color: var(--muted, #64748b); margin-top: 3px;">To change location, use <a href="<?php echo BASE_URL; ?>/pages/transfers.php" style="color:var(--blue, #4f46e5); text-decoration:underline;">Transfers</a>.</div>
                    </div>
                </div>

                <div class="field">
                    <label>Current On-Hand Quantity</label>
                    <div style="padding: 10px 12px; background: var(--bg, #f8fafc); border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius-sm, 6px); font-size: 13px;">
                        <span id="editQuantityDisplay" style="font-weight: 700; color: var(--text, #1e293b);">1</span>
                        <div style="font-size: 11px; color: var(--muted, #64748b); margin-top: 3px;">Adjust stock via <a href="<?php echo BASE_URL; ?>/pages/stock-in.php" style="color:var(--blue, #4f46e5); text-decoration:underline;">Inbound</a> or <a href="<?php echo BASE_URL; ?>/pages/stock-out.php" style="color:var(--blue, #4f46e5); text-decoration:underline;">Outbound</a>.</div>
                    </div>
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
