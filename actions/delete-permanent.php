<?php
// PERMANENT DELETE: removes a soft-deleted item and its photo for good.
// Only intended for items already in the bin (deleted_at is set).
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_can_api('permanent_delete');

$pdo = json_db();
$id  = (int)($_POST['id'] ?? 0);

// 1. Check if ID matches an individual unit in inventory_units
$unitSt = $pdo->prepare("SELECT * FROM inventory_units WHERE id = ?");
$unitSt->execute([$id]);
$unit = $unitSt->fetch();

if ($unit) {
    if ($unit['deleted_at'] === null) {
        json_fail('Only bin units can be permanently deleted.');
    }

    $inventoryId = (int)$unit['inventory_id'];
    $pdo->prepare("DELETE FROM inventory_units WHERE id = ?")->execute([$id]);

    // Check if any units remain for this parent item
    $remSt = $pdo->prepare("SELECT COUNT(*) FROM inventory_units WHERE inventory_id = ?");
    $remSt->execute([$inventoryId]);
    $remaining = (int)$remSt->fetchColumn();

    if ($remaining === 0) {
        $parent = find_item($pdo, $inventoryId);
        if ($parent) {
            delete_photo($parent['photo']);
            $pdo->prepare("DELETE FROM inventory_list WHERE id = ?")->execute([$inventoryId]);
        }
    } else {
        $pdo->prepare("UPDATE inventory_list SET quantity = (SELECT COUNT(*) FROM inventory_units WHERE inventory_id = ? AND deleted_at IS NULL) WHERE id = ?")
            ->execute([$inventoryId, $inventoryId]);
    }

    json_ok();
}

// 2. Fallback: check if ID matches a batch item in inventory_list
$item = find_item($pdo, $id);
if ($item === null) {
    json_fail('Item or unit not found.');
}

if ($item['deleted_at'] === null) {
    json_fail('Only bin items can be permanently deleted.');
}

// Remove the photo file, then the database row for good.
delete_photo($item['photo']);

$pdo->prepare('DELETE FROM inventory_list WHERE id = ?')->execute([$item['id']]);

json_ok();
