<div class="inventory-section add-panel" id="addMasterItemSection" style="display:none;">

    <form id="addMasterItemForm" action="<?php echo BASE_URL; ?>/actions/save-master-item.php" method="POST">

        <div class="modal-header">
            <div class="modal-title">Add Master Item</div>
            <button type="button" class="modal-close" onclick="closeMasterAddForm()">×</button>
        </div>

        <div class="modal-body">

            <div class="form-grid">

                <div class="field">
                    <label for="product_code">Product Code</label>
                    <input type="text" id="product_code" name="product_code" value="<?php echo e($nextProductCode ?? ''); ?>" placeholder="e.g. 0001" maxlength="4" disabled>
                    <label class="manual-code-toggle" for="manualProductCode">
                        <input type="checkbox" id="manualProductCode" onclick="toggleProductCodeManual()">
                        Enter manually
                    </label>
                </div>

                <div class="field">
                    <label for="product_name">Product Name*</label>
                    <input type="text" id="product_name" name="product_name" placeholder="e.g. Network Switch" required maxlength="255">
                </div>

                <div class="field">
                    <label for="brand">Brand*</label>
                    <?php $brandSelectRequired = true; include __DIR__ . '/brand-select.php'; ?>
                </div>

                <div class="field">
                    <label for="product_class">Product Class</label>
                    <select id="product_class" name="product_class" required>
                        <option value="">— Select class —</option>
                        <option value="Inventory Item">Inventory Item</option>
                    </select>
                </div>

            </div>

        </div>

        <div class="modal-footer">
            <button type="submit" class="btn primary">Submit Item</button>
        </div>

    </form>

</div>
