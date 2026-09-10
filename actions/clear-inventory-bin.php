<?php
// CLEAR INVENTORY BIN: Permanently deletes all soft-deleted inventory lots and units. Restricted to super admin / users with permanent_delete.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('super admin');

$pdo = html_db('bin');

try {
    $pdo->beginTransaction();

    // Remove photos of permanently deleted lots
    $stPhotos = $pdo->query("SELECT photo FROM inventory_list WHERE deleted_at IS NOT NULL AND photo IS NOT NULL AND photo <> ''");
    while ($row = $stPhotos->fetch()) {
        delete_photo($row['photo']);
    }

    // Delete all binned units and lots
    $pdo->exec("DELETE FROM inventory_units WHERE deleted_at IS NOT NULL");
    $pdo->exec("DELETE FROM inventory_list WHERE deleted_at IS NOT NULL");

    $pdo->commit();
    redirect_to('bin', 'added', 'All binned inventory items have been permanently deleted.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_to('bin', 'error', 'Failed to permanently delete binned items.');
}
