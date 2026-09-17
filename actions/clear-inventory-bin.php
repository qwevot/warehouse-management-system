<?php
// CLEAR INVENTORY BIN: Permanently deletes all soft-deleted inventory lots and units. Restricted to super admin / users with permanent_delete.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('permanent_delete');
require_csrf('bin');

$pdo = html_db('bin');

try {
    $pdo->beginTransaction();

    // Remove photos of permanently deleted units
    $stPhotos = $pdo->query("SELECT photo FROM inventory_units WHERE deleted_at IS NOT NULL AND photo IS NOT NULL AND photo <> ''");
    while ($row = $stPhotos->fetch()) {
        delete_photo($row['photo']);
    }

    // Delete all binned units
    $pdo->exec("DELETE FROM inventory_units WHERE deleted_at IS NOT NULL");

    // If all units are now cleared, reset sequence to 1
    $remaining = (int)$pdo->query("SELECT COUNT(*) FROM inventory_units")->fetchColumn();
    if ($remaining === 0) {
        $pdo->exec("ALTER TABLE inventory_units AUTO_INCREMENT = 1");
    }

    $pdo->commit();
    log_activity('inventory.purge_bin', 'inventory_units', null, [
        'purged' => 'all'
    ], 'success');
    redirect_to('bin', 'added', 'All binned inventory items have been permanently deleted.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_to('bin', 'error', 'Failed to permanently delete binned items.');
}
