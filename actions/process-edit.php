<?php
// UPDATE: receives the edit modal form (index.php) and updates an existing
// inventory record's name/brand/location, replacing the photo only
// if a new one was captured/uploaded.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('inventory.manage');
require_csrf('index');

$pdo = html_db('index');

$id        = (int)($_POST['id'] ?? 0);
$name      = trim($_POST['item_name'] ?? '');
$brand     = trim($_POST['brand'] ?? '');
$photoData = $_POST['photo'] ?? '';

$item = find_item($pdo, $id);

if ($item === null) {
    redirect_to('index', 'error', 'Item not found.');
}

if ($name === '') {
    redirect_to('index', 'error', 'Item name is required.');
}

// Only allow products that exist in Master Items (matched by name + brand).
$st = $pdo->prepare('SELECT id, product_name, brand FROM master_items WHERE deleted_at IS NULL AND product_name = ? AND COALESCE(brand, "") = ? LIMIT 1');
$st->execute([$name, $brand]);
$mRow = $st->fetch();
if (!$mRow) {
    redirect_to('index', 'error', 'Please choose a product name and brand that exist in Master Items.');
}
$masterItemId = (int)$mRow['id'];
$name         = $mRow['product_name'];
$brand        = $mRow['brand'];

// UPDATE: apply the edited fields (master_item_id, photo) to the item row.
$modifiedBy = current_user_name();

// Replace the photo only when a new one was captured/uploaded.
$photo = $item['photo'];

if ($photoData !== '') {
    $newPhoto = save_photo($photoData, 'item_' . $id . '_' . time());
    if ($newPhoto === null) {
        redirect_to('index', 'error', 'The new photo could not be saved. Please try again.');
    }

    delete_photo($item['photo']);
    $photo = $newPhoto;
}

$barcode = (string)($item['barcode'] ?? '');
if ($barcode !== '') {
    $pdo->prepare(
        'UPDATE inventory_units SET master_item_id = ?, photo = ?, updated_at = NOW(), modified_by = ? WHERE barcode = ?'
    )->execute([$masterItemId, $photo, $modifiedBy, $barcode]);
} else {
    $pdo->prepare(
        'UPDATE inventory_units SET master_item_id = ?, photo = ?, updated_at = NOW(), modified_by = ? WHERE id = ?'
    )->execute([$masterItemId, $photo, $modifiedBy, $id]);
}

log_activity('inventory.edit', 'inventory_unit', $id, [
    'product_name' => $name,
    'brand' => $brand,
    'barcode' => $barcode,
    'photo_updated' => ($photoData !== '')
]);

header('Location: ' . BASE_URL . '/pages/index.php?edited=' . urlencode($name));
exit;
