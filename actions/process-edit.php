<?php
// UPDATE: receives the edit modal form (index.php) and updates an existing
// inventory record's name/brand/location, replacing the photo only
// if a new one was captured/uploaded.
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = html_db('index');

$id          = (int)($_POST['id'] ?? 0);
$name        = trim($_POST['item_name'] ?? '');
$location    = trim($_POST['location'] ?? '');
$brand       = trim($_POST['brand'] ?? '');
$quantity    = max(1, (int)($_POST['quantity'] ?? 1));
$serialInput = trim($_POST['serial'] ?? '');
$photoData   = $_POST['photo'] ?? '';

$item = find_item($pdo, $id);

if ($item === null) {
    fail('Item not found.');
}

if ($name === '')     fail('Item name is required.');

// Keep the existing serial when left blank, otherwise validate uniqueness.
$serial = $serialInput === '' ? $item['serial'] : $serialInput;
if ($serial !== $item['serial']) {
    $dup = $pdo->prepare('SELECT id FROM inventory_list WHERE serial = ? AND id <> ?');
    $dup->execute([$serial, $id]);
    if ($dup->fetch()) {
        fail('That serial number is already in use.');
    }
}

// Replace the photo only when a new one was captured/uploaded.
$photo = $item['photo'];

if ($photoData !== '') {
    $newPhoto = save_photo($photoData, $serial);
    if ($newPhoto === null) {
        fail('The new photo could not be saved. Please try again.');
    }

    delete_photo($item['photo']);

    $photo = $newPhoto;
}

// UPDATE: apply the edited fields to the item row.
$modifiedBy = current_user_name();
$pdo->prepare(
    'UPDATE inventory_list SET name = ?, brand = ?, quantity = ?, location = ?, serial = ?, photo = ?, updated_at = NOW(), modified_by = ? WHERE id = ?'
)->execute([$name, $brand, $quantity, $location, $serial, $photo, $modifiedBy, $id]);

header('Location: ' . BASE_URL . '/pages/index.php?edited=' . urlencode($serial));
exit;
