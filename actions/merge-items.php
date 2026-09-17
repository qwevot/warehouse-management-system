<?php
// MERGE: combines several items into one. Two modes:
//   new      -> generates a brand-new barcode for all units of selected items,
//               effectively grouping them into a new lot.
//   existing -> assigns the selected target item's barcode to all units of
//               selected items, merging them into that target lot.
// Called via AJAX from the Barcode & QR Code Generator page.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_csrf_api();
require_can_api('inventory.manage');
require_once __DIR__ . '/../includes/functions.php';

$pdo  = json_db();
$mode = $_POST['mode'] ?? 'new';

$rawIds = $_POST['ids'] ?? '';
if (is_array($rawIds)) {
    $rawIds = implode(',', $rawIds);
}
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$rawIds)))));
sort($ids);

if (count($ids) < 2) {
    json_fail('Select at least 2 items to merge.');
}

$targetId = (int)($_POST['target_id'] ?? 0);

if ($mode === 'existing') {
    if ($targetId <= 0) {
        json_fail('Choose a target item to merge into.');
    }
    if (!in_array($targetId, $ids, true)) {
        json_fail('The target item must be one of the selected items.');
    }
}

$pdo->beginTransaction();

try {
    $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    // Lock and inspect target units
    $st = $pdo->prepare("
        SELECT u.*, mi.product_name AS name, mi.brand AS brand 
        FROM inventory_units u 
        JOIN master_items mi ON mi.id = u.master_item_id 
        WHERE u.id IN ($inPlaceholders) FOR UPDATE
    ");
    $st->execute($ids);
    $rows = $st->fetchAll();

    if (count($rows) !== count($ids)) {
        $pdo->rollBack();
        json_fail('One or more selected items could not be found.');
    }

    foreach ($rows as $row) {
        if ($row['deleted_at'] !== null) {
            $pdo->rollBack();
            json_fail('One or more selected items have been deleted.');
        }
    }

    $first = $rows[0];
    foreach ($rows as $row) {
        if ((int)$row['master_item_id'] !== (int)$first['master_item_id']) {
            $pdo->rollBack();
            json_fail('Only items with the same product name and brand can be merged.');
        }
    }

    $modifiedBy = current_user_name();

    // Collect all barcodes associated with the selected items
    $selectedBarcodes = array_values(array_unique(array_filter(array_column($rows, 'barcode'))));

    if ($mode === 'existing') {
        $target = null;
        foreach ($rows as $row) {
            if ((int)$row['id'] === $targetId) {
                $target = $row;
                break;
            }
        }
        if ($target === null) {
            $pdo->rollBack();
            json_fail('Target item not found among locked items.');
        }

        $targetBarcode = $target['barcode'];
        $targetLocation = $target['location'] ?? null;
        $targetBin = $target['bin_location'] ?? null;
        $targetDeployment = $target['deployment_site'] ?? 'In Warehouse';

        // For all units matching either the selected IDs or the selected barcodes with same master_item_id:
        // update their barcode, qr, location, and bin to match the target
        if (!empty($selectedBarcodes)) {
            $bcPlaceholders = implode(',', array_fill(0, count($selectedBarcodes), '?'));
            $stAffected = $pdo->prepare("
                SELECT id, serial FROM inventory_units 
                WHERE master_item_id = ? AND barcode IN ($bcPlaceholders) AND deleted_at IS NULL
            ");
            $stAffected->execute(array_merge([(int)$first['master_item_id']], $selectedBarcodes));
        } else {
            $stAffected = $pdo->prepare("
                SELECT id, serial FROM inventory_units 
                WHERE id IN ($inPlaceholders) AND deleted_at IS NULL
            ");
            $stAffected->execute($ids);
        }
        $affectedUnits = $stAffected->fetchAll();
        $affectedUnitIds = array_column($affectedUnits, 'id');

        $isBin = preg_match('/^S\d+-R\d+-C\d+/', (string)$targetLocation);
        set_unit_custody($pdo, $affectedUnitIds, 'warehouse', [
            'bin_location' => $isBin ? $targetLocation : null,
            'custom_location' => !$isBin ? $targetLocation : null,
        ], $modifiedBy);

        $stUnitUp = $pdo->prepare("UPDATE inventory_units SET barcode = ?, qr = ? WHERE id = ?");
        foreach ($affectedUnits as $u) {
            $uQr = qr_for_unit((string)$u['serial'], (string)$targetBarcode, (string)$target['name'], (string)($target['brand'] ?? ''), (string)($targetLocation ?? ''));
            $stUnitUp->execute([$targetBarcode, $uQr, (int)$u['id']]);
        }

        $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$targetId, 'merge', count($ids), 'Merged ' . count($ids) . ' lots/units into barcode ' . $targetBarcode, $modifiedBy]);
    } else {
        $name = trim($_POST['name'] ?? '') ?: $first['name'];
        $rawLoc  = trim((string)($first['location'] ?? ''));
        $binLoc  = $first['bin_location'] ?? (preg_match('/^S\d+-R\d+-C\d+/', $rawLoc) ? $rawLoc : null);
        $depSite = $first['deployment_site'] ?? ($binLoc ? 'In Warehouse' : ($rawLoc ?: 'In Warehouse'));
        $loc     = $rawLoc !== '' ? $rawLoc : ($binLoc ?: $depSite);

        $barcode = barcode_for_item($pdo, [
            'master_item_id' => $first['master_item_id'],
            'name'           => $name,
            'brand'          => $first['brand'],
            'created_at'     => null,
            'barcode'        => null,
        ]);

        // Update all selected units to new barcode and unit-level QR
        if (!empty($selectedBarcodes)) {
            $bcPlaceholders = implode(',', array_fill(0, count($selectedBarcodes), '?'));
            $stAffected = $pdo->prepare("
                SELECT id, serial FROM inventory_units 
                WHERE master_item_id = ? AND barcode IN ($bcPlaceholders) AND deleted_at IS NULL
            ");
            $stAffected->execute(array_merge([(int)$first['master_item_id']], $selectedBarcodes));
        } else {
            $stAffected = $pdo->prepare("
                SELECT id, serial FROM inventory_units 
                WHERE id IN ($inPlaceholders) AND deleted_at IS NULL
            ");
            $stAffected->execute($ids);
        }
        $affectedUnits = $stAffected->fetchAll();
        $affectedUnitIds = array_column($affectedUnits, 'id');

        $isBin = preg_match('/^S\d+-R\d+-C\d+/', (string)$loc);
        set_unit_custody($pdo, $affectedUnitIds, 'warehouse', [
            'bin_location' => $isBin ? $loc : null,
            'custom_location' => !$isBin ? $loc : null,
        ], $modifiedBy);

        $stUnitUp = $pdo->prepare("UPDATE inventory_units SET barcode = ?, qr = ? WHERE id = ?");
        foreach ($affectedUnits as $u) {
            $uQr = qr_for_unit((string)$u['serial'], (string)$barcode, (string)$name, (string)($first['brand'] ?? ''), (string)$loc);
            $stUnitUp->execute([$barcode, $uQr, (int)$u['id']]);
        }

        $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$ids[0], 'merge', count($ids), 'Merged ' . count($ids) . ' lots/units into new barcode ' . $barcode, $modifiedBy]);
    }

    $pdo->commit();

    log_activity('inventory.merge', 'inventory_units', ($mode === 'existing' ? $targetId : $ids[0]), [
        'mode' => $mode,
        'merged_ids' => $ids,
        'final_barcode' => ($mode === 'existing' ? $targetBarcode : $barcode),
        'unit_count' => count($affectedUnits ?? [])
    ], 'success');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_activity('inventory.merge', 'inventory_units', null, [
        'mode' => $mode,
        'error' => $e->getMessage()
    ], 'failure');
    json_fail('Could not merge the items: ' . $e->getMessage());
}

json_ok(['message' => 'Items merged.']);