<?php
// PERMANENT DELETE: removes a binned brand for good.
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = html_db('master-bin');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect_to('master-bin', 'error', 'Invalid brand.');
}

$st = $pdo->prepare('SELECT name, deleted_at FROM master_brands WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();

if ($row === false) {
    redirect_to('master-bin', 'error', 'Brand not found.');
}

if ($row['deleted_at'] === null) {
    redirect_to('master-bin', 'error', 'Only binned brands can be permanently deleted.');
}

$pdo->prepare('DELETE FROM master_brands WHERE id = ?')->execute([$id]);

redirect_to('master-bin', 'added', 'Brand "' . $row['name'] . '" permanently deleted.');
