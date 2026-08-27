<?php
// STOCK IN: increases the quantity of an existing item and records the movement.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('movement.manage');

$pdo    = html_db('stock-in');
$itemId = (int)($_POST['item_id'] ?? 0);
$qty    = (int)($_POST['quantity'] ?? 0);
$note   = trim($_POST['note'] ?? '');

if ($itemId <= 0) redirect_to('stock-in', 'error', 'Please select an item.');
if ($qty <= 0)    redirect_to('stock-in', 'error', 'Quantity must be at least 1.');

$item = find_item($pdo, $itemId);
if ($item === null || $item['deleted_at'] !== null) {
    redirect_to('stock-in', 'error', 'Item not found.');
}

$modifiedBy = current_user_name();

// Increase the item's on-hand quantity.
$pdo->prepare('UPDATE inventory_list SET quantity = quantity + ?, updated_at = NOW(), modified_by = ? WHERE id = ?')->execute([$qty, $modifiedBy, $itemId]);

// Record the movement.
$pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
    ->execute([$itemId, 'in', $qty, $note, $modifiedBy]);

redirect_to('stock-in', 'added', $qty . ' unit(s) added to "' . $item['name'] . '".');
