<?php
// STAGE STOCK IN: Creates or increments inventory units, generates lot barcode and unit QRs.
// Used in Step 1 of the stepped Stock-In wizard.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_csrf_api();
require_can_api('movement.manage');
require_once __DIR__ . '/../includes/functions.php';

$pdo = json_db();

// Opportunistically sweep any stale unfinalized staged records older than 6 hours
purge_stale_staged_units($pdo, 6);

$inboundType = trim($_POST['inbound_type'] ?? 'existing');

if ($inboundType === 'new_lot') {
    $name   = trim($_POST['product_name'] ?? '');
    $brand  = trim($_POST['brand'] ?? '');
    $qty    = max(1, (int)($_POST['quantity'] ?? 1));
    $note   = trim($_POST['note'] ?? '');

    if ($name === '') {
        json_fail('Product name is required.');
    }

    // Verify product exists in master items
    $st = $pdo->prepare('SELECT id, product_name, brand FROM master_items WHERE deleted_at IS NULL AND product_name = ? AND COALESCE(brand, "") = ? LIMIT 1');
    $st->execute([$name, $brand]);
    $mRow = $st->fetch();
    if (!$mRow) {
        json_fail('Please choose a product name and brand that exist in Master Items.');
    }
    $masterItemId = (int)$mRow['id'];
    $name         = $mRow['product_name'];
    $brand        = $mRow['brand'];

    try {
        $pdo->beginTransaction();

        $modifiedBy = current_user_name();
        // Units remain staged (hidden from active inventory) until finalized in Step 3
        $created = create_inventory_item($pdo, [
            'master_item_id' => $masterItemId,
            'name'           => $name,
            'brand'          => $brand,
            'quantity'       => $qty,
            'location'       => '',
            'status'         => 'staged',
            'modified_by'    => $modifiedBy,
        ]);

        $lotId   = (int)$created['id'];
        $barcode = (string)$created['barcode'];

        // Fetch newly provisioned units for this lot
        $stUnits = $pdo->prepare("
            SELECT id, serial, qr, location, status
            FROM inventory_units
            WHERE barcode = ? AND deleted_at IS NULL
            ORDER BY id ASC
        ");
        $stUnits->execute([$barcode]);
        $units = $stUnits->fetchAll(PDO::FETCH_ASSOC);

        $pdo->commit();

        log_activity('movement.inbound_stage', 'inventory_lot', $lotId, [
            'inbound_type' => 'new_lot',
            'product_name' => $name,
            'brand'        => $brand,
            'quantity'     => $qty,
            'barcode'      => $barcode,
            'note'         => $note
        ], 'success');

        json_ok([
            'inbound_type' => 'new_lot',
            'lot_id'       => $lotId,
            'name'         => $name,
            'brand'        => $brand,
            'quantity'     => $qty,
            'barcode'      => $barcode,
            'note'         => $note,
            'current_location' => '',
            'units'        => array_map(function ($u) use ($barcode, $name, $brand) {
                return [
                    'id'       => (int)$u['id'],
                    'serial'   => $u['serial'],
                    'qr'       => $u['qr'] ?: qr_for_unit($u['serial'], $barcode, $name, $brand),
                    'location' => $u['location'] ?? '',
                ];
            }, $units),
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_fail('Failed to create new lot: ' . $e->getMessage());
    }
} else {
    // Existing Lot mode
    $itemId = (int)($_POST['item_id'] ?? 0);
    $qty    = max(1, (int)($_POST['quantity'] ?? 1));
    $note   = trim($_POST['note'] ?? '');

    if ($itemId <= 0) {
        json_fail('Please select an existing item.');
    }

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT u.id, u.master_item_id, mi.product_name AS name, mi.brand AS brand, u.barcode,
                                     COALESCE(NULLIF(MAX(u.bin_location), ""), NULLIF(MAX(u.location), ""), MAX(u.deployment_site), "") AS location,
                                     COALESCE(MAX(NULLIF(u.photo, "")), "") AS photo,
                                     u.deleted_at 
                              FROM inventory_units u 
                              JOIN master_items mi ON mi.id = u.master_item_id 
                              WHERE (u.barcode = (SELECT barcode FROM inventory_units WHERE id = ? LIMIT 1) OR u.id = ?)
                                AND u.deleted_at IS NULL
                              GROUP BY u.master_item_id, u.barcode');
        $st->execute([$itemId, $itemId]);
        $lot = $st->fetch();

        if (!$lot) {
            $pdo->rollBack();
            json_fail('Selected item was not found.');
        }

        $modifiedBy = current_user_name();

        // Provision the new units with staged status (hidden until finalized)
        $newUnits = provision_inventory_units($pdo, $itemId, $qty, $modifiedBy, 'staged');

        $pdo->commit();

        $barcode = (string)($lot['barcode'] ?? '');
        $name    = (string)$lot['name'];
        $brand   = (string)($lot['brand'] ?? '');
        $currLoc = (string)($lot['location'] ?? '');
        $photo   = !empty($lot['photo']) ? BASE_URL . '/' . ltrim($lot['photo'], '/') : '';

        log_activity('movement.inbound_stage', 'inventory_lot', $itemId, [
            'inbound_type' => 'existing_lot',
            'product_name' => $name,
            'brand'        => $brand,
            'quantity'     => $qty,
            'barcode'      => $barcode,
            'note'         => $note
        ], 'success');

        json_ok([
            'inbound_type' => 'existing',
            'lot_id'       => $itemId,
            'name'         => $name,
            'brand'        => $brand,
            'quantity'     => $qty,
            'barcode'      => $barcode,
            'note'         => $note,
            'current_location' => $currLoc,
            'photo'        => $photo,
            'units'        => array_map(function ($u) use ($barcode, $name, $brand, $currLoc) {
                return [
                    'id'       => (int)$u['id'],
                    'serial'   => $u['serial'],
                    'qr'       => $u['qr'] ?: qr_for_unit($u['serial'], $barcode, $name, $brand),
                    'location' => $currLoc,
                ];
            }, $newUnits),
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_fail('Failed to process existing lot inbound: ' . $e->getMessage());
    }
}
