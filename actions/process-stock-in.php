<?php
// STOCK IN: increases the quantity of an existing item and records the movement.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('movement.manage');
require_csrf('stock-in');

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

    if ($location === '' || !preg_match('/^S\d{1,2}-R\d{1,2}-C\d{1,2}$/i', $location)) {
        redirect_to('stock-in', 'error', 'Please specify a valid Shelf Location with Shelf, Row, and Column (e.g. S01-R01-C01).');
    }
    $location = strtoupper(preg_replace_callback('/^S(\d+)-R(\d+)-C(\d+)$/i', function ($m) {
        return sprintf('S%02d-R%02d-C%02d', $m[1], $m[2], $m[3]);
    }, $location));

    // Verify product exists in master items
    $st = $pdo->prepare('SELECT id, product_name, brand FROM master_items WHERE deleted_at IS NULL AND product_name = ? AND COALESCE(brand, "") = ? LIMIT 1');
    $st->execute([$name, $brand]);
    $mRow = $st->fetch();
    if (!$mRow) {
        redirect_to('stock-in', 'error', 'Please choose a product name and brand that exist in Master Items.');
    }
    $masterItemId = (int)$mRow['id'];
    $name         = $mRow['product_name'];
    $brand        = $mRow['brand'];

    try {
        $pdo->beginTransaction();

        $modifiedBy = current_user_name();
        $created = create_inventory_item($pdo, [
            'master_item_id' => $masterItemId,
            'name'           => $name,
            'brand'          => $brand,
            'quantity'       => $qty,
            'location'       => $location,
            'status'         => 'available',
            'modified_by'    => $modifiedBy,
        ]);

        $id = $created['id'];

        if ($photoData !== '') {
            $photo = save_photo($photoData, 'item_' . $id . '_' . time());
            if ($photo !== null) {
                $pdo->prepare('UPDATE inventory_units SET photo = ? WHERE barcode = ?')->execute([$photo, $created['barcode']]);
            }
        }

        // Record inbound movement
        $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, 'in', $qty, $note ?: 'Received as new lot', $modifiedBy]);

        $pdo->commit();

        log_activity('movement.inbound_new_lot', 'inventory_lot', $id, [
            'barcode' => $created['barcode'],
            'product_name' => $name,
            'quantity' => $qty,
            'location' => $location,
            'note' => $note
        ]);

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

    $st = $pdo->prepare('SELECT u.id, u.master_item_id, u.barcode, mi.product_name AS name, u.deleted_at 
                         FROM inventory_units u 
                         JOIN master_items mi ON mi.id = u.master_item_id 
                         WHERE u.id = ? FOR UPDATE');
    $st->execute([$itemId]);
    $item = $st->fetch();

    if ($item === false || $item['deleted_at'] !== null) {
        $pdo->rollBack();
        redirect_to('stock-in', 'error', 'Item not found.');
    }

    $modifiedBy = current_user_name();

    // Provision corresponding individual unit records
    provision_inventory_units($pdo, $itemId, $qty, $modifiedBy);

    // Record the movement.
    $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
        ->execute([$itemId, 'in', $qty, $note, $modifiedBy]);

    $pdo->commit();

    log_activity('movement.inbound_existing_lot', 'inventory_lot', $itemId, [
        'product_name' => $item['name'] ?? '',
        'barcode' => $item['barcode'] ?? '',
        'quantity' => $qty,
        'note' => $note
    ]);

    redirect_to('stock-in', 'added', $qty . ' unit(s) added to "' . $item['name'] . '".');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_to('stock-in', 'error', 'Stock-in operation failed.');
}

