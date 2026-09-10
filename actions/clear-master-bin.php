<?php
// CLEAR MASTER BIN: Permanently deletes all soft-deleted master brands and items. Restricted to super admin / users with permanent_delete.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('super admin');

$pdo = html_db('master-bin');

try {
    $pdo->beginTransaction();

    $pdo->exec("DELETE FROM master_items WHERE deleted_at IS NOT NULL");
    $pdo->exec("DELETE FROM master_brands WHERE deleted_at IS NOT NULL");

    $pdo->commit();
    redirect_to('master-bin', 'added', 'All binned master items and brands have been permanently deleted.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_to('master-bin', 'error', 'Failed to permanently delete binned master records.');
}
