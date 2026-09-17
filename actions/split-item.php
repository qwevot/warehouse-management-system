<?php
// SPLIT: divides an inventory lot (units sharing barcode/master_item_id)
// into N parts with user-defined quantities.
// Part 0 keeps the original barcode/lot; every subsequent part receives
// a new generated barcode, partitioning the inventory_units cleanly.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_csrf_api();
require_can_api('inventory.manage');
require_once __DIR__ . '/../includes/functions.php';

$pdo    = json_db();
$itemId = (int)($_POST['item_id'] ?? 0);

$rawQty = $_POST['quantities'] ?? '';
if (is_array($rawQty)) {
    $rawQty = implode(',', $rawQty);
}
$quantities = array_values(array_filter(array_map('intval', explode(',', (string)$rawQty))));

if (count($quantities) < 2) {
    json_fail('You need at least 2 parts.');
}

if (min($quantities) < 1) {
    json_fail('Each part quantity must be at least 1.');
}

$pdo->beginTransaction();

try {
    // Look up the sample unit identifying this lot
    $st = $pdo->prepare('
        SELECT u.*, mi.product_name AS name, mi.brand AS brand 
        FROM inventory_units u 
        JOIN master_items mi ON mi.id = u.master_item_id 
        WHERE u.id = ? FOR UPDATE
    ');
    $st->execute([$itemId]);
    $item = $st->fetch();

    if ($item === false || $item['deleted_at'] !== null) {
        $pdo->rollBack();
        json_fail('Item not found.');
    }

    $rawLoc = $_POST['locations'] ?? [];
    if (is_string($rawLoc)) {
        $rawLoc = (strpos($rawLoc, '||') !== false) ? explode('||', $rawLoc) : explode(',', $rawLoc);
    }
    if (!is_array($rawLoc)) {
        $rawLoc = [];
    }
    $parsedLocations = array_map('trim', $rawLoc);

    $defaultLoc = trim($item['location'] ?? '') !== '' ? trim($item['location']) : 'N/A';
    $locations = [];
    for ($i = 0; $i < count($quantities); $i++) {
        $loc = $parsedLocations[$i] ?? '';
        $locations[] = ($loc !== '') ? $loc : $defaultLoc;
    }

    $totalSplitQty = array_sum($quantities);

    // Fetch all active units physically available in this lot
    $barcode = $item['barcode'] ?? null;
    $masterItemId = (int)$item['master_item_id'];

    if (!empty($barcode)) {
        $stUnits = $pdo->prepare("
            SELECT id, serial, barcode FROM inventory_units
            WHERE master_item_id = ? AND barcode = ?
              AND deleted_at IS NULL
              AND status = 'available'
              AND (deployment_site = 'In Warehouse' OR deployment_site = '' OR deployment_site IS NULL)
            ORDER BY id ASC
            FOR UPDATE
        ");
        $stUnits->execute([$masterItemId, $barcode]);
    } else {
        $stUnits = $pdo->prepare("
            SELECT id, serial, barcode FROM inventory_units
            WHERE id = ?
              AND deleted_at IS NULL
              AND status = 'available'
              AND (deployment_site = 'In Warehouse' OR deployment_site = '' OR deployment_site IS NULL)
            ORDER BY id ASC
            FOR UPDATE
        ");
        $stUnits->execute([$itemId]);
    }
    $availableUnits = $stUnits->fetchAll();

    if (count($availableUnits) < $totalSplitQty) {
        $pdo->rollBack();
        json_fail('Cannot split: only ' . count($availableUnits) . ' unit(s) are available in the warehouse for this item.');
    }

    $modifiedBy = current_user_name();
    $unitOffset = 0;

    // Part 0: Retains original barcode; update location and unit QR if specified
    $part0Units = array_slice($availableUnits, $unitOffset, $quantities[0]);
    $part0Ids = array_column($part0Units, 'id');
    $unitOffset += $quantities[0];
    $loc0 = $locations[0];
    $isBin0 = preg_match('/^S\d+-R\d+-C\d+/', $loc0);

    set_unit_custody($pdo, $part0Ids, 'warehouse', [
        'bin_location' => $isBin0 ? $loc0 : null,
        'custom_location' => !$isBin0 ? $loc0 : null,
    ], $modifiedBy);

    $stQr0 = $pdo->prepare("UPDATE inventory_units SET qr = ? WHERE id = ?");
    foreach ($part0Units as $u0) {
        $uQr0 = qr_for_unit((string)$u0['serial'], (string)($barcode ?? ''), (string)$item['name'], (string)($item['brand'] ?? ''), (string)$loc0);
        $stQr0->execute([$uQr0, (int)$u0['id']]);
    }

    // Every remaining part gets a new barcode assigned to its slice of units
    for ($i = 1; $i < count($quantities); $i++) {
        $partIUnits = array_slice($availableUnits, $unitOffset, $quantities[$i]);
        $unitOffset += $quantities[$i];
        $partIIds = array_column($partIUnits, 'id');

        $locI = $locations[$i];
        $isBinI = preg_match('/^S\d+-R\d+-C\d+/', $locI);

        $newBarcode = barcode_for_item($pdo, [
            'master_item_id' => $item['master_item_id'],
            'name'           => $item['name'],
            'brand'          => $item['brand'],
            'created_at'     => null,
            'barcode'        => null,
        ]);

        set_unit_custody($pdo, $partIIds, 'warehouse', [
            'bin_location' => $isBinI ? $locI : null,
            'custom_location' => !$isBinI ? $locI : null,
        ], $modifiedBy);

        $stUnitUpI = $pdo->prepare("UPDATE inventory_units SET barcode = ?, qr = ? WHERE id = ?");
        foreach ($partIUnits as $uI) {
            $uQrI = qr_for_unit((string)$uI['serial'], (string)$newBarcode, (string)$item['name'], (string)($item['brand'] ?? ''), (string)$locI);
            $stUnitUpI->execute([$newBarcode, $uQrI, (int)$uI['id']]);
        }

        // Record movement audit log
        $firstIdOfPart = !empty($partIIds) ? $partIIds[0] : $itemId;
        $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$firstIdOfPart, 'split', $quantities[$i], 'Split from Lot #' . $itemId . ' ("' . $item['name'] . '") with new barcode ' . $newBarcode, $modifiedBy]);
    }

    // Record movement audit log for the original retained slice
    $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
        ->execute([$itemId, 'split', $quantities[0], 'Retained from split into ' . count($quantities) . ' parts', $modifiedBy]);

    $pdo->commit();

    log_activity('inventory.split', 'inventory_units', $itemId, [
        'product_name' => $item['name'],
        'original_barcode' => $barcode,
        'parts_count' => count($quantities),
        'quantities' => $quantities
    ], 'success');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_activity('inventory.split', 'inventory_units', $itemId, [
        'error' => $e->getMessage()
    ], 'failure');
    json_fail('Could not split the item: ' . $e->getMessage());
}

json_ok(['message' => 'Item split into ' . count($quantities) . ' parts.']);