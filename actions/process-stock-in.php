<?php
// STOCK IN: increases the quantity of an existing item and records the movement.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('movement.manage');

$pdo = html_db('stock-in');
$inboundType = trim($_POST['inbound_type'] ?? 'existing');

if ($inboundType === 'new_lot') {
    $name      = trim($_POST['product_name'] ?? '');
    $brand     = trim($_POST['brand'] ?? '');
    $location  = trim($_POST['location'] ?? '');
    $qty       = max(1, (int)($_POST['new_quantity'] ?? ($_POST['quantity'] ?? 1)));
    $note      = trim($_POST['new_note'] ?? ($_POST['note'] ?? ''));
    $photoData = $_POST['photo'] ?? '';

    if ($name === '') {
        redirect_to('stock-in', 'error', 'Product name is required.');
    }

    // Verify product exists in master items
    $st = $pdo->prepare('SELECT COUNT(*) FROM master_items WHERE deleted_at IS NULL AND product_name = ? AND COALESCE(brand, "") = ?');
    $st->execute([$name, $brand]);
    if ((int)$st->fetchColumn() === 0) {
        redirect_to('stock-in', 'error', 'Please choose a product name and brand that exist in Master Items.');
    }

    try {
        $pdo->beginTransaction();

        $modifiedBy = current_user_name();
        $created = create_inventory_item($pdo, [
            'name'        => $name,
            'brand'       => $brand,
            'quantity'    => $qty,
            'location'    => $location,
            'status'      => 'available',
            'modified_by' => $modifiedBy,
        ]);

        $id = $created['id'];

        if ($photoData !== '') {
            $photo = save_photo($photoData, 'item_' . $id . '_' . time());
            if ($photo !== null) {
                $pdo->prepare('UPDATE inventory_list SET photo = ? WHERE id = ?')->execute([$photo, $id]);
            }
        }

        // Record inbound movement
        $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, 'in', $qty, $note ?: 'Received as new lot', $modifiedBy]);

        $pdo->commit();
        redirect_to('stock-in', 'added', $qty . ' unit(s) received into new lot "' . $name . '" (Barcode: ' . $created['barcode'] . ').');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        redirect_to('stock-in', 'error', 'Failed to receive new lot.');
    }
}

// Default: Add to existing lot
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

    // Provision corresponding individual unit records
    provision_inventory_units($pdo, $itemId, $qty, $modifiedBy);

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

