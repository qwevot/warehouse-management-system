<?php if (isset($_GET['added'])): ?>
    <div class="flash success">Item <strong><?php echo e($_GET['added']); ?></strong> was added to the inventory.</div>
<?php endif; ?>

<?php if (isset($_GET['edited'])): ?>
    <div class="flash success">Changes to <strong><?php echo e($_GET['edited']); ?></strong> were saved.</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="flash error"><?php echo e($_GET['error']); ?></div>
<?php endif; ?>

<?php if (!empty($dbError)): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>

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
        'edit'   => ['Edit',   'openEditModal(ID)',   '<svg viewBox="0 -0.5 21 21" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="currentColor"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <title>edit_fill [#1480]</title> <desc>Created with Sketch.</desc> <defs> </defs> <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="Dribbble-Light-Preview" transform="translate(-59.000000, -400.000000)" fill="currentColor"> <g id="icons" transform="translate(56.000000, 160.000000)"> <path d="M3,260 L24,260 L24,258.010742 L3,258.010742 L3,260 Z M13.3341,254.032226 L9.3,254.032226 L9.3,249.950269 L19.63095,240 L24,244.115775 L13.3341,254.032226 Z" id="edit_fill-[#1480]"> </path>'],
        'view'   => ['View',   'openItemModal(ID)',   '<circle fill="none" stroke="currentColor" stroke-width="2" cx="12" cy="12" r="9"/><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M12 11v5"/><circle fill="currentColor" stroke="none" cx="12" cy="8" r="1"/>'],
        'delete' => ['Delete', 'runDeleteItem(ID)',   '<path d="M3 6.52381C3 6.12932 3.32671 5.80952 3.72973 5.80952H8.51787C8.52437 4.9683 8.61554 3.81504 9.45037 3.01668C10.1074 2.38839 11.0081 2 12 2C12.9919 2 13.8926 2.38839 14.5496 3.01668C15.3844 3.81504 15.4756 4.9683 15.4821 5.80952H20.2703C20.6733 5.80952 21 6.12932 21 6.52381C21 6.9183 20.6733 7.2381 20.2703 7.2381H3.72973C3.32671 7.2381 3 6.9183 3 6.52381Z" fill="currentColor"></path> <path d="M11.6066 22H12.3935C15.101 22 16.4547 22 17.3349 21.1368C18.2151 20.2736 18.3052 18.8576 18.4853 16.0257L18.7448 11.9452C18.8425 10.4086 18.8913 9.64037 18.4498 9.15352C18.0082 8.66667 17.2625 8.66667 15.7712 8.66667H8.22884C6.7375 8.66667 5.99183 8.66667 5.55026 9.15352C5.1087 9.64037 5.15756 10.4086 5.25528 11.9452L5.51479 16.0257C5.69489 18.8576 5.78494 20.2736 6.66513 21.1368C7.54532 22 8.89906 22 11.6066 22Z" fill="currentColor"></path>'],
    ];
    if (!user_can('inventory.manage')) {
        unset($rowActions['edit'], $rowActions['delete']);
    }
    $renderActions = function ($item) use ($rowActions) {
        ob_start();
        foreach ($rowActions as $class => [$title, $fn, $icon]): ?>
            <button class="btn icon-btn <?php echo $class; ?>" title="<?php echo $title; ?>" onclick="<?php echo str_replace('ID', (int)$item['id'], $fn); ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><?php echo $icon; ?></svg>
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
