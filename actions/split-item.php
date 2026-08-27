<?php
// SPLIT: divides an inventory item into N parts with user-defined quantities.
// The original keeps the first part; every other part becomes a new item with
// its own serial, barcode, and QR code (called via AJAX from the Barcode &
// QR Code Generator page).
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_can_api('inventory.manage');

$pdo    = json_db();
$itemId = (int)($_POST['item_id'] ?? 0);

$rawQty = $_POST['quantities'] ?? '';
if (is_array($rawQty)) {
    $rawQty = implode(',', $rawQty);
}
$quantities = array_values(array_filter(array_map('intval', explode(',', (string)$rawQty))));

if (count($quantities) < 2) {
    json_fail('You need at least 2 parts.');
}

if (min($quantities) < 1) {
    json_fail('Each part quantity must be at least 1.');
}

$rawLoc = $_POST['locations'] ?? [];
if (!is_array($rawLoc)) {
    $rawLoc = explode('||', (string)$rawLoc);
}
$locations = array_values(array_filter(array_map('trim', $rawLoc)));

if (count($locations) !== count($quantities)) {
    json_fail('You must provide a location for every part.');
}

$item = find_item($pdo, $itemId);

if ($item === null || $item['deleted_at'] !== null) {
    json_fail('Item not found.');
}

$qty = max(0, (int)$item['quantity']);

if (array_sum($quantities) !== $qty) {
    json_fail('Part quantities must add up to the item\'s quantity (' . $qty . ').');
}

$pdo->beginTransaction();

try {
    $modifiedBy = current_user_name();

    // Original item keeps the first part.
    $pdo->prepare('UPDATE inventory_list SET quantity = ?, location = ?, updated_at = NOW(), modified_by = ? WHERE id = ?')->execute([$quantities[0], $locations[0], $modifiedBy, $item['id']]);

    // Every remaining part becomes a new item.
    for ($i = 1; $i < count($quantities); $i++) {
        create_inventory_item($pdo, [
            'name'        => $item['name'],
            'brand'       => $item['brand'],
            'quantity'    => $quantities[$i],
            'location'    => $locations[$i],
            'photo'       => $item['photo'],
            'status'      => 'available',
            'modified_by' => $modifiedBy,
        ]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_fail('Could not split the item.');
}

json_ok(['message' => 'Item split into ' . count($quantities) . ' parts.']);