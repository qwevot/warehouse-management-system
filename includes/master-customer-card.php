<div class="inventory-section add-panel" id="addCustomerSection" style="display:none;">

    <form id="addCustomerForm" action="<?php echo BASE_URL; ?>/actions/save-customer.php" method="POST">
        <?php echo csrf_field(); ?>

        <div class="modal-header">
            <div class="modal-title">Add New Customer / Company</div>
            <button type="button" class="modal-close" onclick="closeCustomerAddForm()">×</button>
        </div>

        <div class="modal-body">

            <div class="form-grid">

                <div class="field">
                    <label for="customer_name_input">Company / Customer Name *</label>
                    <input type="text" id="customer_name_input" name="name" placeholder="Required" required maxlength="150">
                </div>

                <div class="field">
                    <label for="customer_code_input">Customer Code</label>
                    <input type="text" id="customer_code_input" name="company_code" placeholder="(Optional)" maxlength="50">
                </div>

                <div class="field">
                    <label for="customer_contact_input">Contact Person</label>
                    <input type="text" id="customer_contact_input" name="contact_person" placeholder="(Optional)" maxlength="100">
                </div>

                <div class="field">
                    <label for="customer_phone_input">Phone Number</label>
                    <input type="text" id="customer_phone_input" name="phone" placeholder="(Optional)" maxlength="50">
                </div>

                <div class="field">
                    <label for="customer_email_input">Email Address</label>
                    <input type="email" id="customer_email_input" name="email" placeholder="(Optional)" maxlength="150">
                </div>

                <div class="field full">
                    <label for="customer_address_input">Address / Notes</label>
                    <textarea id="customer_address_input" name="address" rows="2" placeholder="(Optional)" maxlength="500"></textarea>
                </div>

            </div>

        </div>

        <div class="modal-footer">
            <button type="button" class="btn secondary" onclick="closeCustomerAddForm()">Cancel</button>
            <button type="submit" class="btn primary">Add Customer</button>
        </div>

    </form>

</div>
