<?php
// TRANSFER (intrawarehouse): moves an existing item to a new location within
// the same warehouse and logs the move.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('movement.manage');

$pdo      = html_db('transfers');
$itemId   = (int)($_POST['item_id'] ?? 0);
$destination = trim($_POST['destination'] ?? '');
$note     = trim($_POST['note'] ?? '');

if ($itemId <= 0) redirect_to('transfers', 'error', 'Please select an item.');
if ($destination === '') redirect_to('transfers', 'error', 'Please choose a destination location.');
if (!preg_match('/^S\d{2}-R\d{2}-C\d{2}$/', $destination)) {
    redirect_to('transfers', 'error', 'Invalid location format.');
}

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare('SELECT name, location, deleted_at FROM inventory_list WHERE id = ? FOR UPDATE');
    $st->execute([$itemId]);
    $item = $st->fetch();

    if ($item === false || $item['deleted_at'] !== null) {
        $pdo->rollBack();
        redirect_to('transfers', 'error', 'Item not found.');
    }

    $old = trim($item['location'] ?? '');
    if ($old === $destination) {
        $pdo->rollBack();
        redirect_to('transfers', 'error', 'The item is already at that location.');
    }

    $modifiedBy = current_user_name();

    // Update the item's location.
    $pdo->prepare('UPDATE inventory_list SET location = ?, updated_at = NOW(), modified_by = ? WHERE id = ?')
        ->execute([$destination, $modifiedBy, $itemId]);

    // Log the move so a history is kept.
    $log = 'Moved from ' . ($old !== '' ? $old : 'N/A') . ' to ' . $destination;
    if ($note !== '') $log .= ' | ' . $note;

    $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
        ->execute([$itemId, 'transfer', 0, $log, $modifiedBy]);

    $pdo->commit();
    redirect_to('transfers', 'added', $item['name'] . ' moved to ' . $destination . '.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_to('transfers', 'error', 'Transfer operation failed.');
}

