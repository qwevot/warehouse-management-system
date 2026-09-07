<?php
// PROCESS CHECK-IN: Updates custody location of an item or serialized unit.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_once __DIR__ . '/../includes/functions.php';

$pdo = json_db();
$currentUser = current_user();
$role = $currentUser['role'] ?? 'guest';
$modifier = $currentUser['full_name'] ?: ($currentUser['username'] ?? 'User');

$inventoryId = (int)($_POST['inventory_id'] ?? 0);
$unitId = (int)($_POST['unit_id'] ?? 0);
$destType = trim($_POST['destination_type'] ?? '');
$siteInput = trim($_POST['site'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($inventoryId <= 0 && $unitId <= 0) {
    json_fail('No item or unit specified for check-in.');
}

// Fetch item/unit details to verify existence
$item = null;
$unit = null;

if ($unitId > 0) {
    $st = $pdo->prepare("SELECT u.*, i.name AS item_name, i.brand FROM inventory_units u JOIN inventory_list i ON i.id = u.inventory_id WHERE u.id = ? AND u.deleted_at IS NULL AND i.deleted_at IS NULL LIMIT 1");
    $st->execute([$unitId]);
    $unit = $st->fetch();
    if (!$unit) {
        json_fail('The specified unit was not found.');
    }
    $inventoryId = (int)$unit['inventory_id'];
    $itemName = $unit['item_name'];
    $unitSerial = $unit['serial'];
} else {
    $st = $pdo->prepare("SELECT * FROM inventory_list WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $st->execute([$inventoryId]);
    $item = $st->fetch();
    if (!$item) {
        json_fail('The specified item was not found.');
    }
    $itemName = $item['name'];
    $unitSerial = null;
}

// Determine final location based on role & destination
$finalLocation = '';

if ($role === 'customer') {
    $company = trim((string)($currentUser['company'] ?? ''));
    if ($company === '') {
        json_fail('Your account does not have a company assigned. Please contact an administrator.');
    }
    if ($siteInput === '') {
        json_fail('Please specify a site name.');
    }
    $finalLocation = $company . ' - ' . $siteInput;
} else {
    // Super admin, admin, engineer, technician, etc.
    if ($destType === 'office') {
        $finalLocation = 'Main Office';
    } elseif ($destType === 'warehouse') {
        $bin = trim($_POST['bin_location'] ?? '');
        $finalLocation = $bin !== '' ? $bin : 'In Warehouse';
    } elseif ($destType === 'customer') {
        $company = trim($_POST['company'] ?? '');
        if ($company === '') {
            json_fail('Please provide a company name.');
        }
        if ($siteInput === '') {
            json_fail('Please provide a site name.');
        }
        $finalLocation = $company . ' - ' . $siteInput;
    } else {
        json_fail('Invalid destination selected.');
    }
}

try {
    $pdo->beginTransaction();

    if ($unit !== null) {
        // Update unit-level location
        $up = $pdo->prepare("UPDATE inventory_units SET location = ?, updated_at = NOW(), modified_by = ? WHERE id = ?");
        $up->execute([$finalLocation, $modifier, $unitId]);

        // Also update lot location if it's currently empty
        $upLot = $pdo->prepare("UPDATE inventory_list SET location = COALESCE(NULLIF(location, ''), ?), updated_at = NOW(), modified_by = ? WHERE id = ?");
        $upLot->execute([$finalLocation, $modifier, $inventoryId]);

        // Record movement history
        $movementNote = "Unit [{$unitSerial}] checked in to {$finalLocation}" . ($notes !== '' ? " ({$notes})" : "");
        $insMove = $pdo->prepare("INSERT INTO stock_movements (item_id, type, quantity, issued_to, note, modified_by, created_at) VALUES (?, 'transfer', 1, ?, ?, ?, NOW())");
        $insMove->execute([$inventoryId, $finalLocation, $movementNote, $modifier]);
    } else {
        // Update batch-level location
        $up = $pdo->prepare("UPDATE inventory_list SET location = ?, updated_at = NOW(), modified_by = ? WHERE id = ?");
        $up->execute([$finalLocation, $modifier, $inventoryId]);

        // Record movement history
        $movementNote = "Lot checked in to {$finalLocation}" . ($notes !== '' ? " ({$notes})" : "");
        $insMove = $pdo->prepare("INSERT INTO stock_movements (item_id, type, quantity, issued_to, note, modified_by, created_at) VALUES (?, 'transfer', ?, ?, ?, ?, NOW())");
        $insMove->execute([$inventoryId, (int)($item['quantity'] ?? 1), $finalLocation, $movementNote, $modifier]);
    }

    $pdo->commit();

    json_ok([
        'message'  => "Successfully checked in to {$finalLocation}",
        'location' => $finalLocation,
        'item_name'=> $itemName,
        'serial'   => $unitSerial,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail('Failed to process check-in: ' . $e->getMessage());
}
