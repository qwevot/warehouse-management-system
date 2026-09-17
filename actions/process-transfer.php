<?php
// TRANSFER (intrawarehouse): moves an existing item to a new location within
// the same warehouse and logs the move.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('movement.manage');
require_csrf('transfers');

$pdo      = html_db('transfers');
$itemId   = (int)($_POST['item_id'] ?? 0);
$destination = trim($_POST['destination'] ?? '');
$note     = trim($_POST['note'] ?? '');

if ($itemId <= 0) redirect_to('transfers', 'error', 'Please select an item.');
if ($destination === '') redirect_to('transfers', 'error', 'Please choose a destination location.');
if (!preg_match('/^S\d{2}-R\d{2}-C\d{2}$/', $destination)) {
    redirect_to('transfers', 'error', 'Invalid location format.');
}

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare('SELECT u.id, u.master_item_id, mi.product_name AS name, u.barcode,
                                 COALESCE(NULLIF(u.bin_location, ""), NULLIF(u.deployment_site, ""), u.location, "") AS location,
                                 u.deleted_at 
                         FROM inventory_units u 
                         JOIN master_items mi ON mi.id = u.master_item_id 
                         WHERE u.id = ? FOR UPDATE');
    $st->execute([$itemId]);
    $item = $st->fetch();

    if ($item === false || $item['deleted_at'] !== null) {
        $pdo->rollBack();
        redirect_to('transfers', 'error', 'Item not found.');
    }

    $old = trim($item['location'] ?? '');
    if ($old === $destination) {
        $pdo->rollBack();
        redirect_to('transfers', 'error', 'The item is already at that location.');
    }

    $modifiedBy = current_user_name();
    $unitId = (int)($_POST['unit_id'] ?? 0);

    if ($unitId > 0) {
        $stUnit = $pdo->prepare("SELECT id, serial, location, bin_location, deployment_site, status, deleted_at FROM inventory_units WHERE id = ? FOR UPDATE");
        $stUnit->execute([$unitId]);
        $unit = $stUnit->fetch();

        if (!$unit || $unit['deleted_at'] !== null) {
            $pdo->rollBack();
            redirect_to('transfers', 'error', 'Selected unit not found or binned.');
        }

        if ($unit['deployment_site'] !== 'In Warehouse' || $unit['status'] !== 'available') {
            $pdo->rollBack();
            redirect_to('transfers', 'error', 'Unit is not currently available in the warehouse.');
        }

        $oldUnitLoc = trim((string)($unit['location'] ?? $unit['bin_location'] ?? ''));
        if ($oldUnitLoc === $destination) {
            $pdo->rollBack();
            redirect_to('transfers', 'error', 'The unit is already at that location.');
        }

        // Update specifically this unit's location
        set_unit_custody($pdo, $unitId, 'warehouse', ['bin_location' => $destination], $modifiedBy);

        $log = 'Unit [' . $unit['serial'] . '] moved from ' . ($oldUnitLoc !== '' ? $oldUnitLoc : 'N/A') . ' to ' . $destination;
        if ($note !== '') $log .= ' | ' . $note;

        $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$itemId, 'transfer', 1, $log, $modifiedBy]);

        $pdo->commit();

        log_activity('movement.transfer_unit', 'inventory_unit', $unitId, [
            'item_id' => $itemId,
            'serial' => $unit['serial'],
            'from_location' => $oldUnitLoc,
            'to_location' => $destination,
            'note' => $note
        ]);

        redirect_to('transfers', 'added', 'Unit ' . $unit['serial'] . ' (' . $item['name'] . ') moved to ' . $destination . '.');
    } else {
        // Full lot transfer: update all warehouse units belonging to this lot
        $barcode = trim((string)($item['barcode'] ?? ''));
        if ($barcode !== '') {
            $stUnits = $pdo->prepare("SELECT id FROM inventory_units WHERE master_item_id = ? AND barcode = ? AND deleted_at IS NULL AND deployment_site = 'In Warehouse' AND status = 'available'");
            $stUnits->execute([(int)$item['master_item_id'], $barcode]);
        } else {
            $stUnits = $pdo->prepare("SELECT id FROM inventory_units WHERE id = ? AND deleted_at IS NULL AND deployment_site = 'In Warehouse' AND status = 'available'");
            $stUnits->execute([$itemId]);
        }
        $lotUnitIds = $stUnits->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($lotUnitIds)) {
            set_unit_custody($pdo, $lotUnitIds, 'warehouse', ['bin_location' => $destination], $modifiedBy);
        }

        $log = 'Lot moved from ' . ($old !== '' ? $old : 'N/A') . ' to ' . $destination;
        if ($note !== '') $log .= ' | ' . $note;

        $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$itemId, 'transfer', count($lotUnitIds), $log, $modifiedBy]);

        sync_lot_quantity($pdo, $itemId, $modifiedBy);
        $pdo->commit();

        log_activity('movement.transfer_lot', 'inventory_lot', $itemId, [
            'barcode' => $barcode,
            'from_location' => $old,
            'to_location' => $destination,
            'note' => $note
        ]);

        redirect_to('transfers', 'added', $item['name'] . ' moved to ' . $destination . '.');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_to('transfers', 'error', 'Transfer operation failed.');
}

