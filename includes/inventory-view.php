<?php
if (isset($_GET['added'])) {
    $flashAdded = 'Item <strong>' . e($_GET['added']) . '</strong> was added to the inventory.';
}
if (isset($_GET['edited'])) {
    $flashEdited = 'Changes to <strong>' . e($_GET['edited']) . '</strong> were saved.';
}
include __DIR__ . '/flash.php';
?>

<section class="inventory-section" id="inventory">

    <div class="sticky-zone">

        <div class="section-header">
            <?php if (user_can('inventory.manage')): ?>
                <button type="button" class="btn upload add-button" title="Import items from a CSV or Excel file" onclick="document.getElementById('importFile').click()">Import (.csv or .xlsx)</button>
                <button type="button" class="btn primary add-button add-toggle-btn" id="addItemToggleBtn" onclick="toggleAddForm()">+ Add Item</button>
                <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/inventory-add-card.php'; ?>

    </div>

    <?php
    $emptyMessage = 'No items yet. Click "+ Add Item" to register the first one.';
    include __DIR__ . '/inventory-table.php';
    ?>

</section>

<?php include __DIR__ . '/inventory-card.php'; ?>

<?php include __DIR__ . '/inventory-edit-card.php'; ?>
