<?php
// TRANSFER (intrawarehouse): moves an existing item to a new location within
// the same warehouse and logs the move.
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo      = html_db('transfers');
$itemId   = (int)($_POST['item_id'] ?? 0);
$destination = trim($_POST['destination'] ?? '');
$note     = trim($_POST['note'] ?? '');

if ($itemId <= 0) redirect_to('transfers', 'error', 'Please select an item.');
if ($destination === '') redirect_to('transfers', 'error', 'Please choose a destination location.');
if (!preg_match('/^S\d{2}-R\d{2}-C\d{2}$/', $destination)) {
    redirect_to('transfers', 'error', 'Invalid location format.');
}

$item = find_item($pdo, $itemId);
if ($item === null || $item['deleted_at'] !== null) {
    redirect_to('transfers', 'error', 'Item not found.');
}

$modifiedBy = current_user_name();

$old = trim($item['location'] ?? '');
if ($old === $destination) {
    redirect_to('transfers', 'error', 'The item is already at that location.');
}

// Update the item's location.
$pdo->prepare('UPDATE inventory_list SET location = ?, updated_at = NOW(), modified_by = ? WHERE id = ?')->execute([$destination, $modifiedBy, $itemId]);

// Log the move so a history is kept.
$log = 'Moved from ' . ($old !== '' ? $old : 'N/A') . ' to ' . $destination;
if ($note !== '') $log .= ' | ' . $note;
$pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
    ->execute([$itemId, 'transfer', 0, $log, $modifiedBy]);

redirect_to('transfers', 'added', $item['name'] . ' moved to ' . $destination . '.');
