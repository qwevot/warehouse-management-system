<?php
// SCAN LOOKUP: given a scanned barcode (EAN-13) or QR text, return the matching
// item. Barcodes are stored verbatim on the item; QR text embeds the serial.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_csrf_api();
require_once __DIR__ . '/../includes/functions.php';

$code = trim($_POST['code'] ?? '');

// Scanners often decode an EAN-13 barcode that begins with '0' as a 12-digit
// UPC-A (the leading zero is implicit). Normalize it back to 13 digits.
if (preg_match('/^\d{12}$/', $code)) {
    $code = '0' . $code;
}

if ($code === '') {
    json_fail('Nothing was scanned.');
}

$pdo = json_db();
$unit = null;
$item = null;
$userRole = current_role();
$isCustomer = ($userRole === 'customer');

// If a customer attempts to scan a standalone EAN-13 LOT Barcode or barcode-only string, restrict
if ($isCustomer && ((preg_match('/^\d{13}$/', $code) && ean13_valid($code)) || preg_match('/^BARCODE:\s*\d{13}$/i', $code))) {
    json_fail('Barcode and LOT scanning is restricted to internal warehouse operations.');
}

// 1. Check if the scanned payload contains an explicit Unit Serial (SN: ... or SERIAL: ...) or starts with SN-
$extractedSerial = null;
if (preg_match('/(?:SN|SERIAL):\s*([^\r\n]+)/i', $code, $m)) {
    $extractedSerial = trim($m[1]);
} elseif (preg_match('/^SN-\d{4}-\d+$/i', $code)) {
    $extractedSerial = $code;
}

if ($extractedSerial !== null) {
    $st = $pdo->prepare(
        "SELECT u.id AS unit_id, u.serial, u.qr AS unit_qr, u.status AS unit_status, u.created_at AS unit_created_at,
                COALESCE(NULLIF(u.bin_location, ''), NULLIF(u.location, ''), u.deployment_site, '') AS location,
                u.id AS inventory_id, mi.product_name AS name, mi.brand, 1 AS batch_quantity, u.barcode, COALESCE(u.photo, '') AS photo, u.status AS batch_status
           FROM inventory_units u
           JOIN master_items mi ON mi.id = u.master_item_id
          WHERE u.serial = ? AND u.deleted_at IS NULL
          LIMIT 1"
    );
    $st->execute([$extractedSerial]);
    $unit = $st->fetch() ?: null;
}

// 2. If not found by serial, check if it's an EAN-13 Barcode or embedded BARCODE: in text (Staff only)
if ($unit === null && !$isCustomer) {
    $extractedBarcode = null;
    if (preg_match('/^\d{13}$/', $code) && ean13_valid($code)) {
        $extractedBarcode = $code;
    } elseif (preg_match('/BARCODE:\s*([^\r\n]+)/i', $code, $m)) {
        $extractedBarcode = trim($m[1]);
    }

    if ($extractedBarcode !== null) {
        $st = $pdo->prepare("SELECT MIN(u.id) AS id, MIN(u.id) AS inventory_id, u.master_item_id, mi.product_name AS name, mi.brand AS brand,
                                    COUNT(u.id) AS quantity, COUNT(u.id) AS batch_quantity, u.barcode,
                                    COALESCE(MAX(NULLIF(u.photo, '')), '') AS photo, MAX(u.status) AS status, MAX(u.status) AS batch_status,
                                    COALESCE(NULLIF(MAX(u.bin_location), ''), NULLIF(MAX(u.location), ''), MAX(u.deployment_site), '') AS location,
                                    MIN(u.created_at) AS created_at, '' AS qr
                             FROM inventory_units u 
                             JOIN master_items mi ON mi.id = u.master_item_id 
                             WHERE u.barcode = ? AND u.deleted_at IS NULL 
                             GROUP BY u.barcode, u.master_item_id LIMIT 1");
        $st->execute([$extractedBarcode]);
        $item = $st->fetch() ?: null;
    }
}

// 3. Fallback: try raw exact match on unit serial first, then inventory barcode (Staff only for barcode)
if ($unit === null && $item === null) {
    $st = $pdo->prepare(
        "SELECT u.id AS unit_id, u.serial, u.qr AS unit_qr, u.status AS unit_status, u.created_at AS unit_created_at,
                COALESCE(NULLIF(u.bin_location, ''), NULLIF(u.location, ''), u.deployment_site, '') AS location,
                u.id AS inventory_id, u.master_item_id, mi.product_name AS name, mi.brand, 1 AS batch_quantity, u.barcode, COALESCE(u.photo, '') AS photo, u.status AS batch_status
           FROM inventory_units u
           JOIN master_items mi ON mi.id = u.master_item_id
          WHERE u.serial = ? AND u.deleted_at IS NULL
          LIMIT 1"
    );
    $st->execute([$code]);
    $unit = $st->fetch() ?: null;

    if ($unit === null && !$isCustomer) {
        $st = $pdo->prepare("SELECT MIN(u.id) AS id, MIN(u.id) AS inventory_id, u.master_item_id, mi.product_name AS name, mi.brand AS brand,
                                    COUNT(u.id) AS quantity, COUNT(u.id) AS batch_quantity, u.barcode,
                                    COALESCE(MAX(NULLIF(u.photo, '')), '') AS photo, MAX(u.status) AS status, MAX(u.status) AS batch_status,
                                    COALESCE(NULLIF(MAX(u.bin_location), ''), NULLIF(MAX(u.location), ''), MAX(u.deployment_site), '') AS location,
                                    MIN(u.created_at) AS created_at, '' AS qr
                             FROM inventory_units u 
                             JOIN master_items mi ON mi.id = u.master_item_id 
                             WHERE u.barcode = ? AND u.deleted_at IS NULL 
                             GROUP BY u.barcode, u.master_item_id LIMIT 1");
        $st->execute([$code]);
        $item = $st->fetch() ?: null;
    }
}

if ($unit === null && $item === null) {
    json_fail($isCustomer ? 'No equipment matching that serial number or QR code was found.' : 'No matching unit or item was found for that code.');
}

if ($unit !== null) {
    json_ok([
        'type' => 'unit',
        'item' => [
            'id'             => (int)$unit['inventory_id'],
            'unit_id'        => (int)$unit['unit_id'],
            'serial'         => $unit['serial'],
            'name'           => $unit['name'],
            'brand'          => $unit['brand'] ?? '',
            'quantity'       => 1,
            'batch_quantity' => (int)$unit['batch_quantity'],
            'location'       => $unit['location'] ?? '',
            'barcode'        => $isCustomer ? '' : ($unit['barcode'] ?? ''),
            'qr'             => $unit['unit_qr'] ?: qr_for_unit($unit['serial'], $unit['barcode'] ?? '', $unit['name'], $unit['brand'] ?? ''),
            'status'         => $unit['unit_status'],
            'is_unit'        => true,
            'created_at'     => $unit['unit_created_at'],
            'photo'          => $unit['photo'] ? BASE_URL . '/' . $unit['photo'] : '',
        ],
    ]);
}

json_ok([
    'type' => 'batch',
    'item' => [
        'id'             => (int)$item['id'],
        'serial'         => '',
        'name'           => $item['name'],
        'brand'          => $item['brand'] ?? '',
        'quantity'       => (int)$item['quantity'],
        'batch_quantity' => (int)$item['quantity'],
        'location'       => $item['location'] ?? '',
        'barcode'        => $item['barcode'] ?? '',
        'qr'             => $item['qr'] ?? '',
        'status'         => $item['status'],
        'is_unit'        => false,
        'created_at'     => $item['created_at'],
        'photo'          => $item['photo'] ? BASE_URL . '/' . $item['photo'] : '',
    ],
]);
