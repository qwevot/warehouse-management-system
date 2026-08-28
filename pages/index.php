<?php
$pageTitle = 'Items';
$activeNav = 'inventory';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/inventory-data.php';

require_login();

$mode   = 'list';
$state  = inventory_state_from_request();
$rows   = [];
$total  = 0;
$facets = ['brand' => [], 'shelf' => [], 'row' => [], 'col' => []];
$dbError = '';

try {
    $pdo = db();
    // READ: one page of non-deleted inventory records matching the current
    // search/filter/sort, newest first by default.
    $result = inventory_fetch($pdo, $mode, $state);
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

include __DIR__ . '/../includes/inventory-view.php';

include __DIR__ . '/../includes/footer.php';
