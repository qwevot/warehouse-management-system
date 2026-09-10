<?php
// STOCK OUT: decreases the quantity of an existing item and records the movement.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('movement.manage');

$pdo    = html_db('stock-out');
$itemId = (int)($_POST['item_id'] ?? 0);
$qty    = (int)($_POST['quantity'] ?? 0);
$issuedTo = trim($_POST['issued_to'] ?? '');
$note   = trim($_POST['note'] ?? '');

if ($itemId <= 0)     redirect_to('stock-out', 'error', 'Please select an item.');
if ($qty <= 0)        redirect_to('stock-out', 'error', 'Quantity must be at least 1.');
if ($issuedTo === '') redirect_to('stock-out', 'error', 'Issued To is required.');

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare('SELECT name, quantity, deleted_at FROM inventory_list WHERE id = ? FOR UPDATE');
    $st->execute([$itemId]);
    $item = $st->fetch();

    if ($item === false || $item['deleted_at'] !== null) {
        $pdo->rollBack();
        redirect_to('stock-out', 'error', 'Item not found.');
    }

    $onHand = (int)$item['quantity'];
    $outboundMode = trim($_POST['outbound_mode'] ?? 'whole_lot');
    $unitId = (int)($_POST['unit_id'] ?? 0);

    // If whole lot is chosen, quantity must match the full on-hand quantity
    if ($unitId === 0 && $outboundMode === 'whole_lot') {
        $qty = $onHand;
    }

    if ($qty > $onHand) {
        $pdo->rollBack();
        redirect_to('stock-out', 'error', 'Cannot issue more than the on-hand quantity (' . $onHand . ').');
    }

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

    if ($outboundMode === 'individual' && count($unitIds) === 0) {
        $pdo->rollBack();
        redirect_to('stock-out', 'error', 'Please select at least one unit to issue.');
    }

    if (count($unitIds) > 0) {
        $qty = count($unitIds);
    }

    // Decrease the item's on-hand quantity.
    $pdo->prepare('UPDATE inventory_list SET quantity = quantity - ?, updated_at = NOW(), modified_by = ? WHERE id = ?')
        ->execute([$qty, $modifiedBy, $itemId]);

    $transitLocation = 'In Transit (' . $issuedTo . ')';

    if (count($unitIds) > 0) {
        // Target specifically checked units in warehouse
        $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
        $stUnits = $pdo->prepare("
            SELECT u.id, u.status, u.deployment_site, COALESCE(NULLIF(u.location, ''), i.location, '') AS location
            FROM inventory_units u
            JOIN inventory_list i ON i.id = u.inventory_id
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

        $stUp = $pdo->prepare("UPDATE inventory_units SET status = 'available', location = ?, bin_location = NULL, deployment_site = ?, modified_by = ?, updated_at = NOW() WHERE id = ?");
        foreach ($unitIds as $uid) {
            $stUp->execute([$transitLocation, $transitLocation, $modifiedBy, $uid]);
        }
    } else {
        // Mark corresponding units with In Transit location in strict FIFO order (oldest first)
        $pdo->prepare("UPDATE inventory_units SET status = 'available', location = ?, bin_location = NULL, deployment_site = ?, modified_by = ?, updated_at = NOW() WHERE inventory_id = ? AND status = 'available' AND deployment_site = 'In Warehouse' AND deleted_at IS NULL ORDER BY id ASC LIMIT {$qty}")
            ->execute([$transitLocation, $transitLocation, $modifiedBy, $itemId]);
    }

    // Record the movement.
    $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, issued_to, note, modified_by) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$itemId, 'out', $qty, $issuedTo, $note, $modifiedBy]);

    $pdo->commit();
    redirect_to('stock-out', 'added', $qty . ' unit(s) issued from "' . $item['name'] . '".');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_to('stock-out', 'error', 'Stock-out operation failed.');
}

