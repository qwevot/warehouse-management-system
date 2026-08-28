<?php
$pageTitle = 'Bin';
$activeNav = 'bin';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/inventory-data.php';

require_login();

$mode   = 'bin';
$state  = inventory_state_from_request();
$rows   = [];
$total  = 0;
$facets = ['brand' => [], 'shelf' => [], 'row' => [], 'col' => []];
$dbError = '';

try {
    $pdo = db();
    // READ: one page of soft-deleted items, newest first by default.
    $result = inventory_fetch($pdo, $mode, $state);
    $rows  = $result['rows'];
    $total = $result['total'];
    $state['page'] = $result['page'];
    $state['size'] = $result['size'];
    $facets = inventory_facets($pdo, $mode);
} catch (Exception $e) {
    $dbError = 'Could not load the bin from the database.';
}

include __DIR__ . '/../includes/header.php';

if ($dbError !== '') {
    echo '<div class="flash error">' . e($dbError) . '</div>';
}
?>

<section class="inventory-section" id="inventory">

    <?php
    $emptyMessage = 'The bin is empty. Deleted items will appear here.';
    include __DIR__ . '/../includes/inventory-table.php';
    ?>

</section>

<?php include __DIR__ . '/../includes/inventory-card.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
