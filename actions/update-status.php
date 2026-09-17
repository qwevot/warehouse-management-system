<?php
// UPDATE: toggles an item's status (called via AJAX from the item modal).
// "taken" = out of the warehouse (location cleared); "available" = back in,
// keeping any existing location.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_csrf_api();
require_can_api('inventory.manage');
require_once __DIR__ . '/../includes/functions.php';

$pdo  = json_db();
$id   = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$modifiedBy = current_user_name();

if ($action !== 'taken' && $action !== 'available') {
    json_fail('Invalid action.');
}

$type = trim($_POST['type'] ?? '');

if ($type === 'unit') {
    $stUnit = $pdo->prepare("SELECT id, serial, barcode FROM inventory_units WHERE id = ? AND deleted_at IS NULL");
    $stUnit->execute([$id]);
    $unit = $stUnit->fetch();
    if (!$unit) {
        json_fail('Unit not found.');
    }

    if ($action === 'taken') {
        $pdo->prepare("UPDATE inventory_units SET status = 'taken', location = NULL, bin_location = NULL, updated_at = NOW(), modified_by = ? WHERE id = ?")->execute([$modifiedBy, $id]);
    } else {
        $pdo->prepare("UPDATE inventory_units SET status = 'available', updated_at = NOW(), modified_by = ? WHERE id = ?")->execute([$modifiedBy, $id]);
    }

    $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, 1, ?, ?)')
        ->execute([$id, 'status_change', "Unit [{$unit['serial']}] status changed to {$action}", $modifiedBy]);

    log_activity('inventory.status_change', 'inventory_unit', $id, [
        'serial' => $unit['serial'],
        'status' => $action,
        'scope' => 'unit'
    ], 'success');

    json_ok();
}

$item = find_item($pdo, $id);

if ($item !== null) {
    $lotId = (int)$item['id'];
    $barcode = trim((string)($item['barcode'] ?? ''));
    $masterItemId = $item['master_item_id'] ?? null;

    if ($action === 'taken') {
        if ($barcode !== '' && !empty($masterItemId)) {
            $pdo->prepare("UPDATE inventory_units SET status = 'taken', location = NULL, bin_location = NULL, updated_at = NOW(), modified_by = ? WHERE barcode = ? AND master_item_id = ? AND deleted_at IS NULL")->execute([$modifiedBy, $barcode, $masterItemId]);
        } else {
            $pdo->prepare("UPDATE inventory_units SET status = 'taken', location = NULL, bin_location = NULL, updated_at = NOW(), modified_by = ? WHERE id = ?")->execute([$modifiedBy, $lotId]);
        }
        $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$lotId, 'status_change', (int)($item['quantity'] ?? 1), 'Status changed to taken (out of warehouse)', $modifiedBy]);

        log_activity('inventory.status_change', 'inventory_lot', $lotId, [
            'status' => 'taken',
            'barcode' => $barcode,
            'scope' => 'lot'
        ], 'success');

        json_ok();
    } elseif ($action === 'available') {
        if ($barcode !== '' && !empty($masterItemId)) {
            $pdo->prepare("UPDATE inventory_units SET status = 'available', updated_at = NOW(), modified_by = ? WHERE barcode = ? AND master_item_id = ? AND deleted_at IS NULL")->execute([$modifiedBy, $barcode, $masterItemId]);
        } else {
            $pdo->prepare("UPDATE inventory_units SET status = 'available', updated_at = NOW(), modified_by = ? WHERE id = ?")->execute([$modifiedBy, $lotId]);
        }
        $pdo->prepare('INSERT INTO stock_movements (item_id, type, quantity, note, modified_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$lotId, 'status_change', (int)($item['quantity'] ?? 1), 'Status changed to available', $modifiedBy]);

        log_activity('inventory.status_change', 'inventory_lot', $lotId, [
            'status' => 'available',
            'barcode' => $barcode,
            'scope' => 'lot'
        ], 'success');

        json_ok();
    }
}

json_fail('Item or unit not found.');
