<?php
// RESTORE: brings a soft-deleted item back into the active inventory.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_can_api('inventory.manage');

$pdo = json_db();
$id  = (int)($_POST['id'] ?? 0);

// 1. Check if ID matches an individual unit in inventory_units
$unitSt = $pdo->prepare("SELECT u.*, i.deleted_at AS parent_deleted_at FROM inventory_units u JOIN inventory_list i ON i.id = u.inventory_id WHERE u.id = ?");
$unitSt->execute([$id]);
$unit = $unitSt->fetch();

if ($unit) {
    // If parent lot was soft-deleted, restore parent lot as well
    if ($unit['parent_deleted_at'] !== null) {
        $pdo->prepare("UPDATE inventory_list SET deleted_at = NULL, status = 'available', bin_note = NULL, updated_at = NOW() WHERE id = ?")
            ->execute([$unit['inventory_id']]);
    }

    // Restore unit
    $pdo->prepare("UPDATE inventory_units SET deleted_at = NULL, bin_note = NULL, status = 'available', updated_at = NOW() WHERE id = ?")
        ->execute([$id]);

    // Recalculate parent batch on-hand quantity
    $pdo->prepare("UPDATE inventory_list SET quantity = (SELECT COUNT(*) FROM inventory_units WHERE inventory_id = ? AND deleted_at IS NULL) WHERE id = ?")
        ->execute([$unit['inventory_id'], $unit['inventory_id']]);

    json_ok();
}

// 2. Fallback: check if ID matches a batch item in inventory_list
$item = find_item($pdo, $id);
if ($item === null) {
    json_fail('Item or unit not found.');
}

$pdo->prepare("UPDATE inventory_list SET deleted_at = NULL, status = 'available', bin_note = NULL, updated_at = NOW() WHERE id = ?")->execute([$item['id']]);
$pdo->prepare("UPDATE inventory_units SET deleted_at = NULL, bin_note = NULL, status = 'available', updated_at = NOW() WHERE inventory_id = ?")->execute([$item['id']]);

json_ok();
