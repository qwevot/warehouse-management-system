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
                    <input type="text" id="edit_item_name" name="item_name" required>
                    <div class="error-msg">Item name is required.</div>
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
                    <label for="edit_brand">Brand</label>
                    <?php $brandSelectId = 'edit_brand'; include __DIR__ . '/brand-select.php'; ?>
                </div>

                <div class="field">
                    <label for="edit_quantity">Quantity *</label>
                    <input type="number" id="edit_quantity" name="quantity" min="1" value="1" required>
                </div>

                <div class="field">
                    <label for="edit_serial">Serial No.</label>
                    <input type="text" id="edit_serial" name="serial" maxlength="32">
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
