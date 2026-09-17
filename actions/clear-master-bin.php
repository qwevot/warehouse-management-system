<?php
// CLEAR MASTER BIN: Permanently deletes all soft-deleted master brands and items. Restricted to super admin / users with permanent_delete.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('permanent_delete');
require_csrf('master-bin');

$pdo = html_db('master-bin');

try {
    $pdo->beginTransaction();

    // 1. Delete associated binned units under binned master items (satisfies foreign key constraints)
    $stPhotos1 = $pdo->query("
        SELECT u.photo FROM inventory_units u
        JOIN master_items mi ON mi.id = u.master_item_id
        WHERE mi.deleted_at IS NOT NULL AND u.deleted_at IS NOT NULL AND u.photo IS NOT NULL AND u.photo <> ''
    ");
    while ($r = $stPhotos1->fetch()) {
        delete_photo($r['photo']);
    }
    $pdo->exec("
        DELETE u FROM inventory_units u
        JOIN master_items mi ON mi.id = u.master_item_id
        WHERE mi.deleted_at IS NOT NULL AND u.deleted_at IS NOT NULL
    ");

    // 2. Delete associated binned units under binned brands (satisfies foreign key constraints)
    $stPhotos2 = $pdo->query("
        SELECT u.photo FROM inventory_units u
        JOIN master_items mi ON mi.id = u.master_item_id
        JOIN master_brands mb ON mb.name = mi.brand
        WHERE mb.deleted_at IS NOT NULL AND u.deleted_at IS NOT NULL AND u.photo IS NOT NULL AND u.photo <> ''
    ");
    while ($r = $stPhotos2->fetch()) {
        delete_photo($r['photo']);
    }
    $pdo->exec("
        DELETE u FROM inventory_units u
        JOIN master_items mi ON mi.id = u.master_item_id
        JOIN master_brands mb ON mb.name = mi.brand
        WHERE mb.deleted_at IS NOT NULL AND u.deleted_at IS NOT NULL
    ");

    // 3. Delete alert settings referencing binned master items
    $pdo->exec("
        DELETE ias FROM item_alert_settings ias
        JOIN master_items mi ON mi.id = ias.master_item_id
        WHERE mi.deleted_at IS NOT NULL
    ");

    // 4. Delete binned master items
    $pdo->exec("DELETE FROM master_items WHERE deleted_at IS NOT NULL");

    // 5. Delete binned master brands
    $pdo->exec("DELETE FROM master_brands WHERE deleted_at IS NOT NULL");

    // If tables are empty, reset AUTO_INCREMENT to 1
    if ((int)$pdo->query("SELECT COUNT(*) FROM master_items")->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE master_items AUTO_INCREMENT = 1");
    }
    if ((int)$pdo->query("SELECT COUNT(*) FROM master_brands")->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE master_brands AUTO_INCREMENT = 1");
    }
    if ((int)$pdo->query("SELECT COUNT(*) FROM inventory_units")->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE inventory_units AUTO_INCREMENT = 1");
    }

    $pdo->commit();
    log_activity('master.purge_bin', 'master_data', null, [
        'purged' => 'all_binned'
    ], 'success');
    redirect_to('master-bin', 'added', 'All binned master items, brands, and their binned units have been permanently deleted.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_activity('master.purge_bin', 'master_data', null, [
        'error' => $e->getMessage()
    ], 'failure');
    redirect_to('master-bin', 'error', 'Failed to permanently delete binned master records: ' . $e->getMessage());
}
