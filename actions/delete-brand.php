<?php
// DELETE: removes a brand. Blocked while any item still uses it, so inventory
// rows never end up pointing at a missing brand.
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = html_db('brand');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect_to('brand', 'error', 'Invalid brand.');
}

$st = $pdo->prepare('SELECT name FROM master_brands WHERE id = ?');
$st->execute([$id]);
$name = $st->fetchColumn();

if ($name === false) {
    redirect_to('brand', 'error', 'Brand not found.');
}

// Only allow deleting a brand that no item references anymore.
$used = $pdo->prepare('SELECT COUNT(*) FROM inventory_list WHERE brand = ? AND deleted_at IS NULL');
$used->execute([$name]);
if ((int)$used->fetchColumn() > 0) {
    redirect_to('brand', 'error', 'Cannot delete "' . $name . '" — it is still used by inventory items.');
}

$pdo->prepare('UPDATE master_brands SET deleted_at = NOW() WHERE id = ?')->execute([$id]);

redirect_to('brand', 'added', 'Brand "' . $name . '" moved to the master bin.');