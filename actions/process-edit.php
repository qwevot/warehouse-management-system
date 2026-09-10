<?php
// UPDATE: receives the edit modal form (index.php) and updates an existing
// inventory record's name/brand/location, replacing the photo only
// if a new one was captured/uploaded.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('inventory.manage');

$pdo = html_db('index');

$id        = (int)($_POST['id'] ?? 0);
$name      = trim($_POST['item_name'] ?? '');
$brand     = trim($_POST['brand'] ?? '');
$photoData = $_POST['photo'] ?? '';

$item = find_item($pdo, $id);

if ($item === null) {
    fail('Item not found.');
}

if ($name === '') fail('Item name is required.');

// Only allow products that exist in Master Items (matched by name + brand).
$st = $pdo->prepare('SELECT COUNT(*) FROM master_items WHERE deleted_at IS NULL AND product_name = ? AND COALESCE(brand, "") = ?');
$st->execute([$name, $brand]);
if ((int)$st->fetchColumn() === 0) {
    fail('Please choose a product name and brand that exist in Master Items.');
}

// UPDATE: apply the edited fields (name, brand, photo) to the item row.
$modifiedBy = current_user_name();

// Replace the photo only when a new one was captured/uploaded.
$photo = $item['photo'];

if ($photoData !== '') {
    $newPhoto = save_photo($photoData, 'item_' . $id . '_' . time());
    if ($newPhoto === null) {
        fail('The new photo could not be saved. Please try again.');
    }

    delete_photo($item['photo']);
    $photo = $newPhoto;
}

$pdo->prepare(
    'UPDATE inventory_list SET name = ?, brand = ?, photo = ?, updated_at = NOW(), modified_by = ? WHERE id = ?'
)->execute([$name, $brand, $photo, $modifiedBy, $id]);

header('Location: ' . BASE_URL . '/pages/index.php?edited=' . urlencode($name));
exit;
