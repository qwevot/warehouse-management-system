<?php
// STOCK OUT: decreases the quantity of an existing item and records the movement.
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo    = html_db('stock-out');
$itemId = (int)($_POST['item_id'] ?? 0);
$qty    = (int)($_POST['quantity'] ?? 0);
$issuedTo = trim($_POST['issued_to'] ?? '');
$note   = trim($_POST['note'] ?? '');

if ($itemId <= 0)     redirect_to('stock-out', 'error', 'Please select an item.');
if ($qty <= 0)        redirect_to('stock-out', 'error', 'Quantity must be at least 1.');
if ($issuedTo === '') redirect_to('stock-out', 'error', 'Issued To is required.');

$item = find_item($pdo, $itemId);
if ($item === null || $item['deleted_at'] !== null) {
    redirect_to('stock-out', 'error', 'Item not found.');
}

$modifiedBy = current_user_name();

$onHand = (int)$item['quantity'];
if ($qty > $onHand) {
    redirect_to('stock-out', 'error', 'Cannot issue more than the on-hand quantity (' . $onHand . ').');
}

// Decrease the item's on-hand quantity.
$pdo->prepare('UPDATE inventory_list SET quantity = quantity - ?, updated_at = NOW(), modified_by = ? WHERE id = ?')->execute([$qty, $modifiedBy, $itemId]);

// Record the movement.
$pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, issued_to, note, modified_by) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$itemId, 'out', $qty, $issuedTo, $note, $modifiedBy]);

redirect_to('stock-out', 'added', $qty . ' unit(s) issued from "' . $item['name'] . '".');
