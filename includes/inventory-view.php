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
    $rowActions = [
        'edit'   => ['Edit',   'openEditModal(ID)',   icon_edit()],
        'view'   => ['View',   'openItemModal(ID)',   icon_view()],
        'delete' => ['Delete', 'runDeleteItem(ID)',   icon_delete()],
    ];
    if (!user_can('inventory.manage')) {
        unset($rowActions['edit'], $rowActions['delete']);
    }
    $renderActions = function ($item) use ($rowActions) {
        ob_start();
        foreach ($rowActions as $class => [$title, $fn, $icon]): ?>
            <button class="btn icon-btn <?php echo $class; ?>" title="<?php echo $title; ?>" onclick="<?php echo str_replace('ID', (int)$item['id'], $fn); ?>">
                <?php echo $icon; ?>
            </button>
        <?php endforeach;
        return ob_get_clean();
    };
    $emptyMessage = 'No items yet. Click "+ Add Item" to register the first one.';
    $tableColumns = ['no', 'action', 'photo', 'item', 'brand', 'quantity', 'location', 'status', 'serial', 'barcode', 'registered', 'last_modified', 'modified_by'];
    include __DIR__ . '/inventory-table.php';
    ?>

</section>

<?php include __DIR__ . '/inventory-card.php'; ?>

<?php include __DIR__ . '/inventory-edit-card.php'; ?>
