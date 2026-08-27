<div class="inventory-section add-panel" id="addBrandSection" style="display:none;">

    <form id="addBrandForm" action="<?php echo BASE_URL; ?>/actions/save-brand.php" method="POST">

        <div class="modal-header">
            <div class="modal-title">Add New Brand</div>
            <button type="button" class="modal-close" onclick="closeBrandAddForm()">×</button>
        </div>

        <div class="modal-body">

            <div class="form-grid">

                <div class="field">
                    <label for="brand_name">Brand Name*</label>
                    <input type="text" id="brand_name" name="name" placeholder="e.g. HPE" required maxlength="100">
                </div>

                <div class="field">
                    <label for="brand_description">Description</label>
                    <input type="text" id="brand_description" name="description" placeholder="Optional description" maxlength="255">
                </div>

            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn secondary" onclick="closeBrandAddForm()">Cancel</button>
            <button type="submit" class="btn primary">Add Brand</button>
        </div>

    </form>

</div>
