<?php
// PROCESS CHECK-IN: Updates custody location of an item or serialized unit.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_csrf_api();
require_once __DIR__ . '/../includes/functions.php';

$pdo = json_db();
$currentUser = current_user();
$role = $currentUser['role'] ?? 'guest';
$modifier = $currentUser['full_name'] ?: ($currentUser['username'] ?? 'User');

if ($role === 'guest') {
    json_fail('Guest accounts have read-only access and cannot perform check-ins.');
}

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
    $st = $pdo->prepare("SELECT u.*, mi.product_name AS item_name, mi.brand 
                         FROM inventory_units u 
                         JOIN master_items mi ON mi.id = u.master_item_id 
                         WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1");
    $st->execute([$unitId]);
    $unit = $st->fetch();
    if (!$unit) {
        json_fail('The specified unit was not found.');
    }
    $inventoryId = (int)$unit['id'];
    $itemName = $unit['item_name'];
    $unitSerial = $unit['serial'];
} else {
    $st = $pdo->prepare("SELECT u.*, mi.product_name AS name, mi.brand 
                         FROM inventory_units u 
                         JOIN master_items mi ON mi.id = u.master_item_id 
                         WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1");
    $st->execute([$inventoryId]);
    $item = $st->fetch();
    if (!$item) {
        json_fail('The specified item was not found.');
    }
    $itemName = $item['name'];
    $unitSerial = $item['serial'] ?? null;
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
        if ($bin === '' || !preg_match('/^S\d{2}-R\d{2}-C\d{2}$/i', $bin)) {
            json_fail('Please specify a valid Shelf Location with Shelf, Row, and Column (e.g. S01-R01-C01).');
        }
        $finalLocation = strtoupper($bin);
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

    $isMovingOutside = ($destType === 'office' || $destType === 'customer' || $role === 'customer');
    $isReturnToWarehouse = ($destType === 'warehouse');

    $targetBin = null;
    $targetDeployment = 'In Warehouse';
    if ($destType === 'warehouse') {
        $rawBin = trim($_POST['bin_location'] ?? '');
        $targetBin = $rawBin !== '' ? $rawBin : null;
        $targetDeployment = 'In Warehouse';
    } else {
        $targetBin = null;
        $targetDeployment = $finalLocation;
    }

    if ($unit !== null) {
        $previousLocation = (string)($unit['location'] ?? '');
        $previousCat = classify_record_location($unit);
        $isReturningUnit = $isReturnToWarehouse && (
            $previousCat === 'transit' ||
            $previousCat === 'customer' ||
            $previousLocation === 'Main Office'
        );
        $custodyType = ($role === 'customer' || $destType === 'customer') ? 'customer' : ($destType === 'office' ? 'office' : 'warehouse');
        $custodyParams = [
            'bin_location' => ($destType === 'warehouse') ? $targetBin : null,
            'company'      => ($role === 'customer') ? $company : trim($_POST['company'] ?? ''),
            'site'         => $siteInput,
            'destination'  => $finalLocation,
        ];

        // 1. Update ONLY this specific unit's location, bin_location, deployment_site, and status
        set_unit_custody($pdo, $unitId, $custodyType, $custodyParams, $modifier);

        // 2. Recalculate parent lot's warehouse on-hand quantity (units physically available in the warehouse)
        sync_lot_quantity($pdo, $inventoryId, $modifier);

        $movementType = $isReturningUnit ? 'return' : 'check_in';

        // Record movement history (RETURN or CHECK IN) for this specific unit
        $movementActionText = $isReturningUnit ? "Unit [{$unitSerial}] returned to warehouse at {$finalLocation}" : "Unit [{$unitSerial}] checked in to {$finalLocation}";
        $movementNote = $movementActionText . ($notes !== '' ? " ({$notes})" : "");
        $insMove = $pdo->prepare("INSERT INTO stock_movements (item_id, type, quantity, issued_to, note, modified_by, created_at) VALUES (?, ?, 1, ?, ?, ?, NOW())");
        $insMove->execute([$inventoryId, $movementType, $finalLocation, $movementNote, $modifier]);
    } else {
        $previousLocation = (string)($item['location'] ?? '');
        $previousCat = classify_record_location($item);
        $isReturningLot = $isReturnToWarehouse && (
            $previousCat === 'transit' ||
            $previousCat === 'customer' ||
            $previousLocation === 'Main Office'
        );
        $movementType = $isReturningLot ? 'return' : 'check_in';

        $custodyType = ($role === 'customer' || $destType === 'customer') ? 'customer' : ($destType === 'office' ? 'office' : 'warehouse');
        $custodyParams = [
            'bin_location' => ($destType === 'warehouse') ? $targetBin : null,
            'company'      => ($role === 'customer') ? $company : trim($_POST['company'] ?? ''),
            'site'         => $siteInput,
            'destination'  => $finalLocation,
        ];

        // Batch / Unit level check-in: update the target unit/lot in inventory_units
        $barcode = $item['barcode'] ?? null;
        $masterItemId = $item['master_item_id'] ?? null;
        if (!empty($barcode) && !empty($masterItemId)) {
            $stUnits = $pdo->prepare("SELECT id FROM inventory_units WHERE barcode = ? AND master_item_id = ? AND deleted_at IS NULL");
            $stUnits->execute([$barcode, $masterItemId]);
            $uIds = $stUnits->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($uIds)) {
                set_unit_custody($pdo, $uIds, $custodyType, $custodyParams, $modifier);
            }
        } else {
            set_unit_custody($pdo, $inventoryId, $custodyType, $custodyParams, $modifier);
        }

        // Record movement history (RETURN or CHECK IN)
        $movementActionText = $isReturningLot ? "Lot returned to warehouse at {$finalLocation}" : "Lot checked in to {$finalLocation}";
        $movementNote = $movementActionText . ($notes !== '' ? " ({$notes})" : "");
        $insMove = $pdo->prepare("INSERT INTO stock_movements (item_id, type, quantity, issued_to, note, modified_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $insMove->execute([$inventoryId, $movementType, (int)($item['quantity'] ?? 1), $finalLocation, $movementNote, $modifier]);
    }

    $pdo->commit();

    log_activity('movement.checkin', $unitId > 0 ? 'inventory_unit' : 'inventory_lot', $inventoryId, [
        'item_name' => $itemName,
        'serial' => $unitSerial,
        'movement_type' => $movementType,
        'final_location' => $finalLocation,
        'notes' => $notes
    ]);

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
