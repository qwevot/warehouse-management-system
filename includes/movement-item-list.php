<?php
// Shared "available items" table shown below the Stock In / Stock Out /
// Transfers forms. Expects $items (inventory rows) and $autofillTitle to be
// set before including.
$inventory = $items;
$tableColumns = ['no', 'action', 'photo', 'item', 'brand', 'quantity', 'location', 'status'];
$renderActions = function ($item) use ($autofillTitle) { ?>
    <button class="btn icon-btn view" title="View Details" onclick="openItemModal(<?php echo (int)$item['id']; ?>)">
        <?php echo icon_view(); ?>
    </button>
    <button class="btn icon-btn view" title="Print Barcode &amp; Unit QR Codes" onclick="openPrintLotModal(<?php echo (int)$item['id']; ?>)">
        <?php echo icon_print(); ?>
    </button>
    <?php if (user_can('movement.manage')): ?>
    <button class="btn icon-btn view" title="<?php echo e($autofillTitle); ?>" onclick="pickStockItem(<?php echo (int)$item['id']; ?>)">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M4 17V15.8C4 14.1198 4 13.2798 4.32698 12.638C4.6146 12.0735 5.07354 11.6146 5.63803 11.327C6.27976 11 7.11984 11 8.8 11H20M20 11L16 7M20 11L16 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
    </button>
    <?php endif; ?>
<?php };
$emptyMessage = 'No items registered yet.';
include __DIR__ . '/inventory-table.php';
