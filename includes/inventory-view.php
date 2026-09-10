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
                <?php if ($mode === 'list'): ?>
                    <button type="button" class="btn warning" id="splitToggleBtn" title="Split an item into parts" onclick="toggleSplitSelection()">Split</button>
                    <button type="button" class="btn warning" id="mergeToggleBtn" title="Merge items into one" onclick="toggleMergeSelection()">Merge</button>
                <?php endif; ?>
                <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
            <?php endif; ?>
        </div>

        <?php if (user_can('inventory.manage') && $mode === 'list'): ?>
            <?php include __DIR__ . '/split-card.php'; ?>
            <?php include __DIR__ . '/merge-card.php'; ?>
        <?php endif; ?>

    </div>

    <?php
    $emptyMessage = 'No items in the warehouse yet. Receive items via Inbound.';
    include __DIR__ . '/inventory-table.php';
    ?>

</section>

<?php include __DIR__ . '/inventory-card.php'; ?>

<?php include __DIR__ . '/inventory-edit-card.php'; ?>
