<?php
// SOFT DELETE: moves an inventory record to the bin (called via AJAX from the
// item modal). The row is kept and marked deleted so it can be restored later.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_can_api('inventory.manage');

$pdo  = json_db();
$item = find_item($pdo, (int)($_POST['id'] ?? 0));

if ($item === null) {
    json_fail('Item not found.');
}

$note = trim($_POST['note'] ?? '');

$modifiedBy = current_user_name();

$pdo->prepare("UPDATE inventory_list SET deleted_at = NOW(), status = 'taken', bin_note = ?, updated_at = NOW(), modified_by = ? WHERE id = ?")
    ->execute([$note ?: null, $modifiedBy, $item['id']]);

json_ok();
