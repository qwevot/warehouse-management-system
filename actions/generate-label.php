<?php
// UPDATE: generates (and saves) the barcode + QR code for an already-registered
// item at its recorded location (called via AJAX from generator.php). The
// location itself is set on the add-item form, not here.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_can_api('inventory.manage');

$pdo    = json_db();
$itemId = (int)($_POST['item_id'] ?? 0);

$item = find_item($pdo, $itemId);

if ($item === null) {
    json_fail('Item not found.');
}

$location = trim($item['location'] ?? '');

$barcode = barcode_for_item($pdo, $item);
$qr      = qr_for_item($item['serial'], $item['name'], $location);

// UPDATE: persist the generated labels on the item row.
$pdo->prepare('UPDATE inventory_list SET barcode = ?, qr = ? WHERE id = ?')
    ->execute([$barcode, $qr, $item['id']]);

json_ok([
    'serial'   => $item['serial'],
    'name'     => $item['name'],
    'location' => $location,
    'barcode'  => $barcode,
    'qr'       => $qr,
]);
