<?php
// API endpoint: returns all active serialized units for a given lot (inventory_id)
require_once __DIR__ . '/../includes/auth.php';
require_staff_api();
require_once __DIR__ . '/../includes/functions.php';

$inventoryId = (int)($_GET['inventory_id'] ?? $_POST['inventory_id'] ?? 0);
if ($inventoryId <= 0) {
    json_fail('Invalid inventory ID.');
}

$pdo = json_db();

$st = $pdo->prepare("SELECT u.id, u.master_item_id, u.barcode, mi.product_name AS name, mi.brand AS brand,
                             COALESCE(NULLIF(u.bin_location, ''), NULLIF(u.location, ''), u.deployment_site, '') AS location
                      FROM inventory_units u 
                      JOIN master_items mi ON mi.id = u.master_item_id 
                      WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1");
$st->execute([$inventoryId]);
$lot = $st->fetch(PDO::FETCH_ASSOC);
if (!$lot) {
    json_fail('Lot not found.');
}

$barcode = (string)($lot['barcode'] ?? '');
if ($barcode !== '') {
    $stUnits = $pdo->prepare("
        SELECT id, serial, qr, status, location, bin_location, deployment_site, created_at
        FROM inventory_units
        WHERE master_item_id = ? AND barcode = ?
          AND deleted_at IS NULL 
          AND status = 'available'
          AND (deployment_site = 'In Warehouse' OR deployment_site = '' OR deployment_site IS NULL)
        ORDER BY id ASC
    ");
    $stUnits->execute([(int)$lot['master_item_id'], $barcode]);
} else {
    $stUnits = $pdo->prepare("
        SELECT id, serial, qr, status, location, bin_location, deployment_site, created_at
        FROM inventory_units
        WHERE id = ?
          AND deleted_at IS NULL 
          AND status = 'available'
          AND (deployment_site = 'In Warehouse' OR deployment_site = '' OR deployment_site IS NULL)
        ORDER BY id ASC
    ");
    $stUnits->execute([$inventoryId]);
}
$units = $stUnits->fetchAll(PDO::FETCH_ASSOC);

json_ok([
    'lot'   => [
        'id'       => $inventoryId,
        'name'     => $lot['name'],
        'brand'    => $lot['brand'] ?? '',
        'barcode'  => $lot['barcode'] ?? '',
        'location' => $lot['location'] ?? '',
        'quantity' => count($units),
    ],
    'units' => array_map(function ($u) use ($lot) {
        return [
            'id'       => (int)$u['id'],
            'serial'   => $u['serial'],
            'status'   => $u['status'],
            'location' => $u['location'] ?: $lot['location'],
            'qr'       => $u['qr'] ?: qr_for_unit($u['serial'], $lot['barcode'] ?? '', $lot['name'], $lot['brand'] ?? ''),
        ];
    }, $units),
]);
