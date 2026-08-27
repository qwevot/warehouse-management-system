<?php
$pageTitle = 'Items';
$activeNav = 'inventory';

require_once __DIR__ . '/../includes/auth.php';

require_login();

try {
    $pdo = db();
    // READ: load every non-deleted inventory record, newest first, for the list view.
    $inventory = $pdo->query("SELECT * FROM inventory_list WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {
    $inventory = [];
    $dbError = 'Could not load inventory from the database.';
}

include __DIR__ . '/../includes/header.php';

include __DIR__ . '/../includes/inventory-view.php';

include __DIR__ . '/../includes/footer.php';
