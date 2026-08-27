<?php
$pageTitle = 'Bin';
$activeNav = 'bin';

require_once __DIR__ . '/../includes/auth.php';
require_login();

try {
    $pdo = db();
    // READ: soft-deleted items only, newest first.
    $inventory = $pdo->query("SELECT * FROM inventory_list WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC")->fetchAll();
} catch (Exception $e) {
    $inventory = [];
    $dbError   = 'Could not load the bin from the database.';
}

include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($dbError)): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>

<section class="inventory-section" id="inventory">

    <?php
    $tableColumns = ['no', 'photo', 'item', 'brand', 'quantity', 'serial', 'bin_note', 'status', 'last_modified', 'modified_by', 'action'];
    $renderActions = function ($item) { ?>
        <?php if (user_can('inventory.manage')): ?>
        <button class="btn icon-btn restore" title="Restore" onclick="runRestoreItem(<?php echo (int)$item['id']; ?>)">
            <svg fill="currentColor" viewBox="0 0 14 14" role="img" focusable="false" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M 21,11 14,4 V 8 C 7,9 4,14 3,19 5.5,15.5 9,13.9 14,13.9 V 18 l 7,-7 z" transform="matrix(-0.66666667,0,0,0.66666667,15,-0.66666671)"></path></g></svg>        </button>
        <?php endif; ?>
        <?php if (user_can('permanent_delete')): ?>
        <button class="btn icon-btn delete" title="Permanently Delete" onclick="runPermanentDelete(<?php echo (int)$item['id']; ?>)">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M3 6.52381C3 6.12932 3.32671 5.80952 3.72973 5.80952H8.51787C8.52437 4.9683 8.61554 3.81504 9.45037 3.01668C10.1074 2.38839 11.0081 2 12 2C12.9919 2 13.8926 2.38839 14.5496 3.01668C15.3844 3.81504 15.4756 4.9683 15.4821 5.80952H20.2703C20.6733 5.80952 21 6.12932 21 6.52381C21 6.9183 20.6733 7.2381 20.2703 7.2381H3.72973C3.32671 7.2381 3 6.9183 3 6.52381Z" fill="currentColor"></path> <path d="M11.6066 22H12.3935C15.101 22 16.4547 22 17.3349 21.1368C18.2151 20.2736 18.3052 18.8576 18.4853 16.0257L18.7448 11.9452C18.8425 10.4086 18.8913 9.64037 18.4498 9.15352C18.0082 8.66667 17.2625 8.66667 15.7712 8.66667H8.22884C6.7375 8.66667 5.99183 8.66667 5.55026 9.15352C5.1087 9.64037 5.15756 10.4086 5.25528 11.9452L5.51479 16.0257C5.69489 18.8576 5.78494 20.2736 6.66513 21.1368C7.54532 22 8.89906 22 11.6066 22Z" fill="currentColor"></path> </g></svg>
        </button>
        <?php endif; ?>
    <?php };
    $emptyMessage = 'The bin is empty. Deleted items will appear here.';
    include __DIR__ . '/../includes/inventory-table.php';
    ?>

</section>

<?php include __DIR__ . '/../includes/inventory-card.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>