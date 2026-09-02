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

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare('SELECT name, deleted_at FROM inventory_list WHERE id = ? FOR UPDATE');
    $st->execute([$itemId]);
    $item = $st->fetch();

    if ($item === false || $item['deleted_at'] !== null) {
        $pdo->rollBack();
        redirect_to('stock-in', 'error', 'Item not found.');
    }

    $modifiedBy = current_user_name();

    // Increase the item's on-hand quantity.
    $pdo->prepare('UPDATE inventory_list SET quantity = quantity + ?, updated_at = NOW(), modified_by = ? WHERE id = ?')
        ->execute([$qty, $modifiedBy, $itemId]);

    // Record the movement.
    $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
        ->execute([$itemId, 'in', $qty, $note, $modifiedBy]);

    $pdo->commit();
    redirect_to('stock-in', 'added', $qty . ' unit(s) added to "' . $item['name'] . '".');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_to('stock-in', 'error', 'Stock-in operation failed.');
}

