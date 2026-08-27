<?php
// SCAN LOOKUP: given a scanned barcode (EAN-13) or QR text, return the matching
// item. Barcodes are stored verbatim on the item; QR text embeds the serial.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();

$code = trim($_POST['code'] ?? '');

// Scanners often decode an EAN-13 barcode that begins with '0' as a 12-digit
// UPC-A (the leading zero is implicit). Normalize it back to 13 digits so it
// matches the stored barcode.
if (preg_match('/^\d{12}$/', $code)) {
    $code = '0' . $code;
}

if ($code === '') {
    json_fail('Nothing was scanned.');
}

$pdo = json_db();
$item = null;

// Barcode: a 13-digit EAN-13 with a correct check digit is matched directly
// against the stored barcode column (misreads are rejected by the check digit).
if (preg_match('/^\d{13}$/', $code)) {
    if (ean13_valid($code)) {
        $st = $pdo->prepare("SELECT * FROM inventory_list WHERE barcode = ? AND deleted_at IS NULL LIMIT 1");
        $st->execute([$code]);
        $item = $st->fetch() ?: null;
    }
} else {
    // QR text: look for the serial number.
    if (preg_match('/SERIAL:\s*(\S+)/i', $code, $m)) {
        $st = $pdo->prepare("SELECT * FROM inventory_list WHERE serial = ? AND deleted_at IS NULL LIMIT 1");
        $st->execute([$m[1]]);
        $item = $st->fetch() ?: null;
    }
}

if ($item === null) {
    json_fail('No matching item was found for that code.');
}

json_ok([
    'item' => [
        'id'         => (int)$item['id'],
        'serial'     => $item['serial'],
        'name'       => $item['name'],
        'brand'      => $item['brand'],
        'quantity'   => (int)$item['quantity'],
        'location'   => $item['location'],
        'barcode'    => $item['barcode'],
        'qr'         => $item['qr'],
        'status'     => $item['status'],
        'photo'      => $item['photo'] ? BASE_URL . '/' . $item['photo'] : '',
    ],
]);
