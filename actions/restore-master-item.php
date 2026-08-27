<?php
// RESTORE: brings a soft-deleted master item back into the master data.
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = html_db('master-bin');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect_to('master-bin', 'error', 'Invalid item.');
}

$st = $pdo->prepare('SELECT product_name FROM master_items WHERE id = ?');
$st->execute([$id]);
$name = $st->fetchColumn();

if ($name === false) {
    redirect_to('master-bin', 'error', 'Master item not found.');
}

$pdo->prepare('UPDATE master_items SET deleted_at = NULL WHERE id = ?')->execute([$id]);

redirect_to('master-bin', 'added', 'Master item "' . $name . '" restored.');
