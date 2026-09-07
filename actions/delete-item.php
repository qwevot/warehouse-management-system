<?php
// SOFT DELETE: moves an inventory record to the bin (called via AJAX from the
// item modal). The row is kept and marked deleted so it can be restored later.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_can_api('inventory.manage');

$pdo = json_db();
$id = (int)($_POST['id'] ?? 0);
$note = trim($_POST['note'] ?? '');
$modifiedBy = current_user_name();

// 1. Check if ID matches an individual unit
$unitSt = $pdo->prepare("SELECT * FROM inventory_units WHERE id = ?");
$unitSt->execute([$id]);
$unit = $unitSt->fetch();

if ($unit) {
    $pdo->prepare("UPDATE inventory_units SET deleted_at = NOW(), status = 'taken', bin_note = ?, updated_at = NOW(), modified_by = ? WHERE id = ?")
        ->execute([$note ?: null, $modifiedBy, $id]);

    // Recalculate parent batch on-hand quantity
    $pdo->prepare("UPDATE inventory_list SET quantity = (SELECT COUNT(*) FROM inventory_units WHERE inventory_id = ? AND deleted_at IS NULL), updated_at = NOW(), modified_by = ? WHERE id = ?")
        ->execute([$unit['inventory_id'], $modifiedBy, $unit['inventory_id']]);

    json_ok();
}

// 2. Fallback: check if ID matches a batch item
$item = find_item($pdo, $id);
if ($item === null) {
    json_fail('Item or unit not found.');
}

$pdo->prepare("UPDATE inventory_list SET deleted_at = NOW(), status = 'taken', bin_note = ?, updated_at = NOW(), modified_by = ? WHERE id = ?")
    ->execute([$note ?: null, $modifiedBy, $item['id']]);

$pdo->prepare("UPDATE inventory_units SET deleted_at = NOW(), status = 'taken', bin_note = ?, updated_at = NOW(), modified_by = ? WHERE inventory_id = ? AND deleted_at IS NULL")
    ->execute([$note ?: null, $modifiedBy, $item['id']]);

json_ok();
