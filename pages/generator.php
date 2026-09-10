<?php
$pageTitle = 'Lot Management';
$activeNav = 'barcode';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/inventory-data.php';

require_login();

$mode   = 'generator';
$state  = inventory_state_from_request();
$rows   = [];
$total  = 0;
$facets = ['brand' => [], 'shelf' => [], 'row' => [], 'col' => []];
$dbError = '';

try {
    $pdo = db();
    // READ: all non-deleted inventory records, for full client-side sorting and pagination.
    $result = inventory_fetch($pdo, $mode, $state, true);
    $rows  = $result['rows'];
    $total = $result['total'];
    $state['page'] = $result['page'];
    $state['size'] = $result['size'];
    $facets = inventory_facets($pdo, $mode);
} catch (Exception $e) {
    $dbError = 'Could not load inventory from the database.';
}

include __DIR__ . '/../includes/header.php';

if ($dbError !== '') {
    echo '<div class="flash error">' . e($dbError) . '</div>';
}
?>

<section class="inventory-section" id="inventory">

    <div class="sticky-zone">

        <div class="section-header">
            <?php if (user_can('inventory.manage')): ?>
                <button type="button" class="btn upload add-button" title="Import items from a CSV or Excel file" onclick="document.getElementById('importFile').click()">Import (.csv or .xlsx)</button>
                <button type="button" class="btn warning" id="splitToggleBtn" title="Split an item into parts" onclick="toggleSplitSelection()">Split</button>
                <button type="button" class="btn warning" id="mergeToggleBtn" title="Merge items into one" onclick="toggleMergeSelection()">Merge</button>
                <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/../includes/split-card.php'; ?>

        <?php include __DIR__ . '/../includes/merge-card.php'; ?>

    </div>

    <?php
    $emptyMessage = 'No items registered yet.';
    include __DIR__ . '/../includes/inventory-table.php';
    ?>

</section>

<?php include __DIR__ . '/../includes/inventory-card.php'; ?>

<?php include __DIR__ . '/../includes/generate-card.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
