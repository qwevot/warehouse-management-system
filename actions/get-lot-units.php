<?php
// API endpoint: returns all active serialized units for a given lot (inventory_id)
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_once __DIR__ . '/../includes/functions.php';

$inventoryId = (int)($_GET['inventory_id'] ?? $_POST['inventory_id'] ?? 0);
if ($inventoryId <= 0) {
    json_fail('Invalid inventory ID.');
}

$pdo = json_db();

$st = $pdo->prepare("SELECT name, brand, barcode, location, quantity FROM inventory_list WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$st->execute([$inventoryId]);
$lot = $st->fetch(PDO::FETCH_ASSOC);
if (!$lot) {
    json_fail('Lot not found.');
}

$stUnits = $pdo->prepare("
    SELECT id, serial, qr, status, location, bin_location, deployment_site, created_at
    FROM inventory_units
    WHERE inventory_id = ? 
      AND deleted_at IS NULL 
      AND status = 'available'
      AND deployment_site = 'In Warehouse'
    ORDER BY id ASC
");
$stUnits->execute([$inventoryId]);
$units = $stUnits->fetchAll(PDO::FETCH_ASSOC);

json_ok([
    'lot'   => [
        'id'       => $inventoryId,
        'name'     => $lot['name'],
        'brand'    => $lot['brand'] ?? '',
        'barcode'  => $lot['barcode'] ?? '',
        'location' => $lot['location'] ?? '',
        'quantity' => (int)$lot['quantity'],
    ],
    'units' => array_map(function ($u) use ($lot) {
        return [
            'id'       => (int)$u['id'],
            'serial'   => $u['serial'],
            'status'   => $u['status'],
            'location' => $u['location'] ?: $lot['location'],
            'qr'       => $u['qr'] ?: qr_for_unit($u['serial'], $lot['barcode'] ?? '', $lot['name'], $lot['brand'] ?? '', $u['location'] ?: $lot['location']),
        ];
    }, $units),
]);
