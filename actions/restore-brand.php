<?php
// RESTORE: brings a soft-deleted brand back into the master data. Called via
// form POST from the master bin, or via a GET link from the brand page when a
// binned brand with the same name is being re-created.
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = html_db('master-bin');

$id = (int)($_REQUEST['id'] ?? 0);
if ($id <= 0) {
    redirect_to('master-bin', 'error', 'Invalid brand.');
}

$st = $pdo->prepare('SELECT name FROM master_brands WHERE id = ?');
$st->execute([$id]);
$name = $st->fetchColumn();

if ($name === false) {
    redirect_to('master-bin', 'error', 'Brand not found.');
}

$pdo->prepare('UPDATE master_brands SET deleted_at = NULL WHERE id = ?')->execute([$id]);

$fromBin = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
redirect_to($fromBin ? 'master-bin' : 'brand', 'added', 'Brand "' . $name . '" restored.');
