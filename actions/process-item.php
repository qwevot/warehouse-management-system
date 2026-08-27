<?php
// CREATE: receives the add-item form (index.php) and inserts a new inventory
// record with its location, serial, barcode, and QR code.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('inventory.manage');

$pdo = html_db('index');

$name        = trim($_POST['product_name'] ?? '');
$brand       = trim($_POST['brand'] ?? '');
$location    = trim($_POST['location'] ?? '');
$quantity    = max(1, (int)($_POST['quantity'] ?? 1));
$photoData   = $_POST['photo'] ?? '';
$serialInput = trim($_POST['serial'] ?? '');

if ($name === '') fail('Product name is required.');

// Only allow products that exist in Master Items (matched by name + brand).
$st = $pdo->prepare('SELECT COUNT(*) FROM master_items WHERE deleted_at IS NULL AND product_name = ? AND COALESCE(brand, "") = ?');
$st->execute([$name, $brand]);
if ((int)$st->fetchColumn() === 0) {
    fail('Please choose a product name and brand that exist in Master Items.');
}

// Reject duplicate serials before inserting anything.
if ($serialInput !== '') {
    $dup = $pdo->prepare('SELECT id FROM inventory_list WHERE serial = ? AND deleted_at IS NULL');
    $dup->execute([$serialInput]);
    if ($dup->fetch()) {
        fail('That serial number is already in use.');
    }
}

$modifiedBy = current_user_name();

$created = create_inventory_item($pdo, [
    'name'        => $name,
    'brand'       => $brand,
    'quantity'    => $quantity,
    'location'    => $location,
    'status'      => 'available',
    'modified_by' => $modifiedBy,
], $serialInput === '' ? null : $serialInput);

$id     = $created['id'];
$serial = $created['serial'];

$photo = '';
if ($photoData !== '') {
    $photo = save_photo($photoData, $serial);
    if ($photo === null) {
        $pdo->prepare('DELETE FROM inventory_list WHERE id = ?')->execute([$id]);
        fail('The photo could not be saved. Please try again.');
    }
    $pdo->prepare('UPDATE inventory_list SET photo = ? WHERE id = ?')->execute([$photo, $id]);
}

header('Location: ' . BASE_URL . '/pages/index.php?added=' . urlencode($serial));
exit;
