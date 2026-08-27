<?php
$pageTitle = 'Barcode & QR Code Generator';
$activeNav = 'barcode';

require_once __DIR__ . '/../includes/auth.php';
require_login();

try {
    $pdo = db();
    // READ: every non-deleted inventory record, newest first, same as the inventory list.
    $inventory = $pdo->query("SELECT * FROM inventory_list WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {
    $inventory = [];
    $dbError   = 'Could not load inventory from the database.';
}

include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($dbError)): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>

<section class="inventory-section" id="inventory">

    <div class="sticky-zone">

        <div class="section-header">
            <button type="button" class="btn upload add-button" title="Import items from a CSV or Excel file" onclick="document.getElementById('importFile').click()">Import (.csv or .xlsx)</button>
            <button type="button" class="btn warning" id="splitToggleBtn" title="Split an item into parts" onclick="toggleSplitSelection()">Split</button>
            <button type="button" class="btn warning" id="mergeToggleBtn" title="Merge items into one" onclick="toggleMergeSelection()">Merge</button>
            <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
        </div>

        <?php include __DIR__ . '/../includes/inventory-add-card.php'; ?>

        <?php include __DIR__ . '/../includes/split-card.php'; ?>

        <?php include __DIR__ . '/../includes/merge-card.php'; ?>

    </div>

    <?php
    $rowSelectable = true;
    $tableColumns = ['no', 'photo', 'item', 'quantity','serial', 'location', 'barcode', 'action'];
    $renderActions = function ($item) {
        if ($item['barcode']) { ?>
            <button class="btn icon-btn view" title="View" onclick="openItemModal(<?php echo (int)$item['id']; ?>)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="8" r="1" fill="currentColor" stroke="none"/></svg>
            </button>
        <?php } else { ?>
            <button class="btn sm primary" title="Generate Barcode & QR Code" onclick="openGenModal(<?php echo (int)$item['id']; ?>)">Generate</button>
        <?php }
    };
    $emptyMessage = 'No items registered yet.';
    include __DIR__ . '/../includes/inventory-table.php';
    ?>

</section>

<?php include __DIR__ . '/../includes/inventory-card.php'; ?>

<?php include __DIR__ . '/../includes/generate-card.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>