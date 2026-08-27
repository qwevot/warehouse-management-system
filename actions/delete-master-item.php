<?php
// DELETE: removes a master item from the items database. Called from
// pages/items.php (delete button).
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = html_db('items');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect_to('items', 'error', 'Invalid item.');
}

$st = $pdo->prepare('SELECT product_name, brand FROM master_items WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();

if ($row === false) {
    redirect_to('items', 'error', 'Master item not found.');
}
$name  = $row['product_name'];
$brand = (string)$row['brand'];

// Block while any active inventory record still references this product (name + brand).
$used = $pdo->prepare('SELECT COUNT(*) FROM inventory_list WHERE name = ? AND COALESCE(brand, "") = ? AND deleted_at IS NULL');
$used->execute([$name, $brand]);
if ((int)$used->fetchColumn() > 0) {
    redirect_to('items', 'error', 'Cannot delete "' . $name . '" — it is still used by inventory items.');
}

$pdo->prepare('UPDATE master_items SET deleted_at = NOW() WHERE id = ?')->execute([$id]);

redirect_to('items', 'added', 'Master item "' . $name . '" moved to the master bin.');