<?php
// STOCK OUT: decreases the quantity of an existing item and records the movement.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('movement.manage');
require_csrf('stock-out');

$pdo    = html_db('stock-out');
$itemId = (int)($_POST['item_id'] ?? 0);
$qty    = (int)($_POST['quantity'] ?? 0);
$issuedToType = trim($_POST['issued_to_type'] ?? '');
if ($issuedToType === 'customer') {
    $custCompany = trim($_POST['customer_name'] ?? '');
    $custSite    = trim($_POST['customer_site'] ?? '');
    if ($custCompany === '') {
        $issuedTo = trim($_POST['issued_to'] ?? '');
    } else {
        $issuedTo = $custSite !== '' ? ($custCompany . ' - ' . $custSite) : $custCompany;
    }
} elseif ($issuedToType === 'office') {
    $issuedTo = 'Main Office';
} else {
    $issuedTo = trim($_POST['issued_to'] ?? '');
}
$note   = trim($_POST['note'] ?? '');

if ($itemId <= 0)     redirect_to('stock-out', 'error', 'Please select an item.');
if ($qty <= 0)        redirect_to('stock-out', 'error', 'Quantity must be at least 1.');
if ($issuedTo === '') redirect_to('stock-out', 'error', 'Issued To is required.');

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare('SELECT u.id, u.master_item_id, mi.product_name AS name, u.barcode, u.deployment_site, u.location, u.bin_location, u.deleted_at 
                         FROM inventory_units u 
                         JOIN master_items mi ON mi.id = u.master_item_id 
                         WHERE u.id = ? FOR UPDATE');
    $st->execute([$itemId]);
    $item = $st->fetch();

    if ($item === false || $item['deleted_at'] !== null) {
        $pdo->rollBack();
        redirect_to('stock-out', 'error', 'Item not found.');
    }

    $barcode = trim((string)($item['barcode'] ?? ''));
    if ($barcode !== '') {
        $stCount = $pdo->prepare("
            SELECT COUNT(*) FROM inventory_units 
            WHERE master_item_id = ? AND barcode = ? AND deleted_at IS NULL AND status = 'available' AND (deployment_site = 'In Warehouse' OR deployment_site = '' OR deployment_site IS NULL)
        ");
        $stCount->execute([(int)$item['master_item_id'], $barcode]);
    } else {
        $stCount = $pdo->prepare("
            SELECT COUNT(*) FROM inventory_units 
            WHERE id = ? AND deleted_at IS NULL AND status = 'available' AND (deployment_site = 'In Warehouse' OR deployment_site = '' OR deployment_site IS NULL)
        ");
        $stCount->execute([$itemId]);
    }
    $onHand = (int)$stCount->fetchColumn();

    $depSite = (string)($item['deployment_site'] ?? '');
    $itemLoc = (string)($item['location'] ?? '');
    $isItemCustomerLoc = (classify_unit_location($depSite, $itemLoc) === 'customer');
    if ($isItemCustomerLoc) {
        $pdo->rollBack();
        redirect_to('stock-out', 'error', 'This item is located at a customer site and cannot be issued out from the warehouse.');
    }

    $modifiedBy = current_user_name();
    $unitIds = array_filter(array_map('intval', (array)($_POST['unit_ids'] ?? [])));
    $unitId  = (int)($_POST['unit_id'] ?? 0);
    if ($unitId > 0 && !in_array($unitId, $unitIds, true)) {
        $unitIds[] = $unitId;
    }

    if (empty($unitIds)) {
        $pdo->rollBack();
        redirect_to('stock-out', 'error', 'Please select at least one serialized unit to issue.');
    }

    $qty = count($unitIds);
    $transitLocation = 'In Transit (' . $issuedTo . ')';

    // Target specifically checked units in warehouse
    $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
    $stUnits = $pdo->prepare("
        SELECT u.id, u.status, u.deployment_site, COALESCE(NULLIF(u.bin_location, ''), NULLIF(u.deployment_site, ''), u.location, '') AS location
        FROM inventory_units u
        WHERE u.id IN ($placeholders) AND u.deleted_at IS NULL
        FOR UPDATE
    ");
    $stUnits->execute($unitIds);
    $foundUnits = $stUnits->fetchAll();

    foreach ($foundUnits as $unitRow) {
        if ($unitRow['status'] !== 'available') {
            $pdo->rollBack();
            redirect_to('stock-out', 'error', 'One or more selected units are not available in the warehouse.');
        }
        $isUnitCustomerLoc = (classify_record_location($unitRow) === 'customer');
        if ($isUnitCustomerLoc) {
            $pdo->rollBack();
            redirect_to('stock-out', 'error', 'One or more selected units are currently at a customer site and cannot be issued.');
        }
    }

    set_unit_custody($pdo, $unitIds, 'transit', ['destination' => $issuedTo], $modifiedBy);

    // Record the movement.
    $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, issued_to, note, modified_by) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$itemId, 'out', $qty, $issuedTo, $note, $modifiedBy]);

    $pdo->commit();

    log_activity('movement.outbound', 'inventory_lot', $itemId, [
        'product_name' => $item['name'] ?? '',
        'barcode' => $item['barcode'] ?? '',
        'quantity' => $qty,
        'issued_to' => $issuedTo,
        'transit_location' => $transitLocation,
        'note' => $note
    ]);

    redirect_to('stock-out', 'added', $qty . ' unit(s) issued from "' . $item['name'] . '".');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_to('stock-out', 'error', 'Stock-out operation failed.');
}

