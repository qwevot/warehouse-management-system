<?php
// PERMANENT DELETE: removes a binned master item for good.
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = html_db('master-bin');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect_to('master-bin', 'error', 'Invalid item.');
}

$st = $pdo->prepare('SELECT product_name, deleted_at FROM master_items WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();

if ($row === false) {
    redirect_to('master-bin', 'error', 'Master item not found.');
}

if ($row['deleted_at'] === null) {
    redirect_to('master-bin', 'error', 'Only binned items can be permanently deleted.');
}

$pdo->prepare('DELETE FROM master_items WHERE id = ?')->execute([$id]);

redirect_to('master-bin', 'added', 'Master item "' . $row['product_name'] . '" permanently deleted.');
