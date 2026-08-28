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
            <?php echo icon_restore(); ?>        </button>
        <?php endif; ?>
        <?php if (user_can('permanent_delete')): ?>
        <button class="btn icon-btn delete" title="Permanently Delete" onclick="runPermanentDelete(<?php echo (int)$item['id']; ?>)">
            <?php echo icon_delete(); ?>
        </button>
        <?php endif; ?>
    <?php };
    $emptyMessage = 'The bin is empty. Deleted items will appear here.';
    include __DIR__ . '/../includes/inventory-table.php';
    ?>

</section>

<?php include __DIR__ . '/../includes/inventory-card.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>