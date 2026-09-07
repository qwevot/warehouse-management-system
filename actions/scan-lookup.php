<?php
// SCAN LOOKUP: given a scanned barcode (EAN-13) or QR text, return the matching
// item. Barcodes are stored verbatim on the item; QR text embeds the serial.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
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
                COALESCE(u.location, i.location, '') AS location,
                i.id AS inventory_id, i.name, i.brand, i.quantity AS batch_quantity, i.barcode, i.photo, i.status AS batch_status
           FROM inventory_units u
           JOIN inventory_list i ON i.id = u.inventory_id
          WHERE u.serial = ? AND u.deleted_at IS NULL AND i.deleted_at IS NULL
          LIMIT 1"
    );
    $st->execute([$extractedSerial]);
    $unit = $st->fetch() ?: null;
}

// 2. If not found by serial, check if it's an EAN-13 Barcode or embedded BARCODE: in text
if ($unit === null) {
    $extractedBarcode = null;
    if (preg_match('/^\d{13}$/', $code) && ean13_valid($code)) {
        $extractedBarcode = $code;
    } elseif (preg_match('/BARCODE:\s*([^\r\n]+)/i', $code, $m)) {
        $extractedBarcode = trim($m[1]);
    }

    if ($extractedBarcode !== null) {
        $st = $pdo->prepare("SELECT * FROM inventory_list WHERE barcode = ? AND deleted_at IS NULL LIMIT 1");
        $st->execute([$extractedBarcode]);
        $item = $st->fetch() ?: null;
    }
}

// 3. Fallback: try raw exact match on unit serial first, then inventory barcode
if ($unit === null && $item === null) {
    $st = $pdo->prepare(
        "SELECT u.id AS unit_id, u.serial, u.qr AS unit_qr, u.status AS unit_status, u.created_at AS unit_created_at,
                COALESCE(u.location, i.location, '') AS location,
                i.id AS inventory_id, i.name, i.brand, i.quantity AS batch_quantity, i.barcode, i.photo, i.status AS batch_status
           FROM inventory_units u
           JOIN inventory_list i ON i.id = u.inventory_id
          WHERE u.serial = ? AND u.deleted_at IS NULL AND i.deleted_at IS NULL
          LIMIT 1"
    );
    $st->execute([$code]);
    $unit = $st->fetch() ?: null;

    if ($unit === null) {
        $st = $pdo->prepare("SELECT * FROM inventory_list WHERE barcode = ? AND deleted_at IS NULL LIMIT 1");
        $st->execute([$code]);
        $item = $st->fetch() ?: null;
    }
}

if ($unit === null && $item === null) {
    json_fail('No matching unit or item was found for that code.');
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
            'barcode'        => $unit['barcode'] ?? '',
            'qr'             => $unit['unit_qr'] ?: qr_for_unit($unit['serial'], $unit['barcode'] ?? '', $unit['name'], $unit['brand'] ?? '', $unit['location'] ?? ''),
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
