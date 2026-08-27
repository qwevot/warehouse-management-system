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
                    <div class="location-picker">
                        <div class="loc-input">
                            <label for="editLocShelf">Shelf</label>
                            <input type="text" id="editLocShelf" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); updateEditLocation()">
                        </div>
                        <div class="loc-input">
                            <label for="editLocRow">Row</label>
                            <input type="text" id="editLocRow" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); updateEditLocation()">
                        </div>
                        <div class="loc-input">
                            <label for="editLocCol">Column</label>
                            <input type="text" id="editLocCol" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); updateEditLocation()">
                        </div>
                    </div>
                    <input type="hidden" name="location" id="edit_location">
                    <div class="error-msg">Please enter a location.</div>
                </div>

                <div class="field">
                    <label for="edit_brand">Brand</label>
                    <select id="edit_brand" name="brand">
                        <option value="">— Select brand —</option>
                        <?php foreach (all_brands() as $b): ?>
                            <option value="<?php echo e($b); ?>"><?php echo e($b); ?></option>
                        <?php endforeach; ?>
                    </select>
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
            <button type="button" class="btn danger" id="editStatusBtn" style="margin-right:auto;" onclick="runEditStatus()">
                Mark as Inactive
            </button>
            <button type="button" class="btn cancel" onclick="closeModal('editModal')">Cancel</button>
            <button type="submit" class="btn primary">Save Changes</button>
        </div>

    </form>

</div>
