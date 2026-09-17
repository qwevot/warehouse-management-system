<?php
// FINALIZE STOCK IN: Updates lot location and photo for the staged/inbounded lot,
// and synchronizes the location across its newly added active units.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_csrf_api();
require_can_api('movement.manage');
require_once __DIR__ . '/../includes/functions.php';

$pdo = json_db();

$lotId     = (int)($_POST['lot_id'] ?? 0);
$location  = trim($_POST['location'] ?? '');
$photoData = $_POST['photo'] ?? '';

if ($lotId <= 0) {
    json_fail('Invalid lot ID.');
}

$locationType = trim($_POST['location_type'] ?? 'shelf');

if ($location === '') {
    json_fail('Location is required to finalize stock-in.');
}

if ($locationType === 'outside') {
    $binLocation = null;
    $deploymentSite = 'In Warehouse';
} else {
    // Strictly enforce warehouse shelf location format: Sxx-Rxx-Cxx
    if (!preg_match('/^S\d{1,2}-R\d{1,2}-C\d{1,2}$/i', $location, $mParts)) {
        json_fail('Please specify a valid Shelf Location with Shelf, Row, and Column (e.g. S01-R01-C01).');
    }

    // Normalize to uppercase padded 2-digit format (S01-R01-C01)
    $binLocation = strtoupper(preg_replace_callback('/^S(\d+)-R(\d+)-C(\d+)$/i', function ($m) {
        return sprintf('S%02d-R%02d-C%02d', $m[1], $m[2], $m[3]);
    }, $location));
    $deploymentSite = 'In Warehouse';
    $location = $binLocation;
}

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare('SELECT u.id, u.master_item_id, mi.product_name AS name, mi.brand AS brand, u.barcode,
                                 COALESCE(NULLIF(MAX(u.photo), ""), "") AS photo 
                          FROM inventory_units u 
                          JOIN master_items mi ON mi.id = u.master_item_id 
                          WHERE (u.barcode = (SELECT barcode FROM inventory_units WHERE id = ? LIMIT 1) OR u.id = ?)
                            AND u.deleted_at IS NULL
                          GROUP BY u.master_item_id, u.barcode');
    $st->execute([$lotId, $lotId]);
    $lot = $st->fetch();

    if (!$lot) {
        $pdo->rollBack();
        json_fail('Lot not found.');
    }

    $modifiedBy = current_user_name();
    $barcode = (string)($lot['barcode'] ?? '');

    // Handle photo upload if supplied
    $newPhotoPath = null;
    if ($photoData !== '') {
        if (str_starts_with($photoData, 'data:')) {
            $newPhotoPath = save_photo($photoData, 'item_' . $lotId . '_' . time());
        } elseif (!empty($lot['photo'])) {
            $newPhotoPath = $lot['photo'];
        }
    } elseif (!empty($lot['photo'])) {
        $newPhotoPath = $lot['photo'];
    }

    // Synchronize location, photo, and promote status from 'staged' to 'available'
    $unitIdsRaw = $_POST['unit_ids'] ?? '';
    $unitIds = array_filter(array_map('intval', is_array($unitIdsRaw) ? $unitIdsRaw : explode(',', (string)$unitIdsRaw)));
    $qty = max(1, (int)($_POST['quantity'] ?? (!empty($unitIds) ? count($unitIds) : 1)));
    $note = trim($_POST['note'] ?? '');

    if (!empty($unitIds)) {
        $placeholders = implode(',', array_fill(0, count($unitIds), '?'));
        if ($newPhotoPath !== null) {
            $params = array_merge([$location, $binLocation, $deploymentSite, $newPhotoPath, $modifiedBy], $unitIds);
            $pdo->prepare("
                UPDATE inventory_units
                SET location = ?, bin_location = ?, deployment_site = ?, photo = ?, status = 'available', updated_at = NOW(), modified_by = ?
                WHERE id IN ($placeholders) AND deleted_at IS NULL
            ")->execute($params);
        } else {
            $params = array_merge([$location, $binLocation, $deploymentSite, $modifiedBy], $unitIds);
            $pdo->prepare("
                UPDATE inventory_units
                SET location = ?, bin_location = ?, deployment_site = ?, status = 'available', updated_at = NOW(), modified_by = ?
                WHERE id IN ($placeholders) AND deleted_at IS NULL
            ")->execute($params);
        }
    } else {
        $masterId = (int)$lot['master_item_id'];
        if ($barcode !== '') {
            if ($newPhotoPath !== null) {
                $pdo->prepare("
                    UPDATE inventory_units
                    SET location = ?, bin_location = ?, deployment_site = ?, photo = ?, status = 'available', updated_at = NOW(), modified_by = ?
                    WHERE master_item_id = ? AND barcode = ? AND deleted_at IS NULL AND (deployment_site = 'In Warehouse' OR deployment_site = '' OR deployment_site IS NULL)
                ")->execute([$location, $binLocation, $deploymentSite, $newPhotoPath, $modifiedBy, $masterId, $barcode]);
            } else {
                $pdo->prepare("
                    UPDATE inventory_units
                    SET location = ?, bin_location = ?, deployment_site = ?, status = 'available', updated_at = NOW(), modified_by = ?
                    WHERE master_item_id = ? AND barcode = ? AND deleted_at IS NULL AND (deployment_site = 'In Warehouse' OR deployment_site = '' OR deployment_site IS NULL)
                ")->execute([$location, $binLocation, $deploymentSite, $modifiedBy, $masterId, $barcode]);
            }
        } else {
            if ($newPhotoPath !== null) {
                $pdo->prepare("
                    UPDATE inventory_units
                    SET location = ?, bin_location = ?, deployment_site = ?, photo = ?, status = 'available', updated_at = NOW(), modified_by = ?
                    WHERE id = ? AND deleted_at IS NULL
                ")->execute([$location, $binLocation, $deploymentSite, $newPhotoPath, $modifiedBy, $lotId]);
            } else {
                $pdo->prepare("
                    UPDATE inventory_units
                    SET location = ?, bin_location = ?, deployment_site = ?, status = 'available', updated_at = NOW(), modified_by = ?
                    WHERE id = ? AND deleted_at IS NULL
                ")->execute([$location, $binLocation, $deploymentSite, $modifiedBy, $lotId]);
            }
        }
    }

    // Record official stock inbound movement upon full finalization
    $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
        ->execute([$lotId, 'in', $qty, $note ?: 'Received into inventory', $modifiedBy]);

    $pdo->commit();

    log_activity('movement.inbound', 'inventory_lot', $lotId, [
        'barcode'      => $barcode,
        'product_name' => $lot['name'] ?? '',
        'brand'        => $lot['brand'] ?? '',
        'location'     => $location,
        'unit_count'   => $qty
    ], 'success');

    json_ok([
        'lot_id'   => $lotId,
        'location' => $location,
        'message'  => 'Stock-in finalized successfully.',
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail('Failed to finalize stock-in: ' . $e->getMessage());
}
