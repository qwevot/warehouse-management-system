<?php
// RESTORE: brings a soft-deleted item back into the active inventory.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_can_api('inventory.manage');

$pdo  = json_db();
$item = find_item($pdo, (int)($_POST['id'] ?? 0));

if ($item === null) {
    json_fail('Item not found.');
}

$pdo->prepare("UPDATE inventory_list SET deleted_at = NULL, status = 'available' WHERE id = ?")->execute([$item['id']]);

json_ok();
