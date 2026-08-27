<?php
// UPDATE: toggles an item's status (called via AJAX from the item modal).
// "taken" = out of the warehouse (location cleared); "available" = back in,
// keeping any existing location.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_can_api('inventory.manage');

$pdo  = json_db();
$item = find_item($pdo, (int)($_POST['id'] ?? 0));

if ($item === null) {
    json_fail('Item not found.');
}

$action = $_POST['action'] ?? '';

$modifiedBy = current_user_name();

// UPDATE: mark as out of the warehouse — free up its location.
if ($action === 'taken') {
    $pdo->prepare("UPDATE inventory_list SET status = 'taken', location = NULL, updated_at = NOW(), modified_by = ? WHERE id = ?")->execute([$modifiedBy, $item['id']]);
    json_ok();
}

// UPDATE: mark as available again — keep any existing location, no auto-assign.
if ($action === 'available') {
    $pdo->prepare("UPDATE inventory_list SET status = 'available', updated_at = NOW(), modified_by = ? WHERE id = ?")->execute([$modifiedBy, $item['id']]);
    json_ok();
}

json_fail('Invalid action.');
