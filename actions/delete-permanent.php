<?php
// PERMANENT DELETE: removes a soft-deleted item and its photo for good.
// Only intended for items already in the bin (deleted_at is set).
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_can_api('permanent_delete');

$pdo  = json_db();
$item = find_item($pdo, (int)($_POST['id'] ?? 0));

if ($item === null) {
    json_fail('Item not found.');
}

if ($item['deleted_at'] === null) {
    json_fail('Only bin items can be permanently deleted.');
}

// Remove the photo file, then the database row for good.
delete_photo($item['photo']);

$pdo->prepare('DELETE FROM inventory_list WHERE id = ?')->execute([$item['id']]);

json_ok();
