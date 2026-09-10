<?php
// CREATE / UPDATE: inserts a new master item or updates an existing one in the
// items database. Called from pages/items.php (add form + edit modal).
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('master.manage');

$pdo = html_db('items');

$id            = (int)($_POST['id'] ?? 0);
$productCode   = trim($_POST['product_code'] ?? '');
$productName   = trim($_POST['product_name'] ?? '');
$brand         = trim($_POST['brand'] ?? '');
$productClass  = trim($_POST['product_class'] ?? '');

// Auto-generate a 4-digit code when the user leaves the field blank.
if ($productCode === '') {
    $productCode = next_master_code($pdo);
}

if ($productName === '' || $brand === '') {
    redirect_to('items', 'error', 'Product name and brand are required.');
}

// Guard against duplicate product codes (case-insensitive).
$st = $pdo->prepare('SELECT id FROM master_items WHERE LOWER(product_code) = LOWER(?) AND id <> ?');
$st->execute([$productCode, $id]);
if ($st->fetch()) {
    redirect_to('items', 'error', 'That product code already exists.');
}

// Guard against duplicate master items (same name + brand, case-insensitive).
$st = $pdo->prepare('SELECT id FROM master_items WHERE LOWER(product_name) = LOWER(?) AND LOWER(COALESCE(brand, "")) = LOWER(?) AND deleted_at IS NULL AND id <> ?');
$st->execute([$productName, $brand, $id]);
if ($st->fetch()) {
    redirect_to('items', 'error', 'That product "' . $productName . '" (' . $brand . ') already exists.');
}

$modifiedBy = current_user_name();

if ($id > 0) {
    $pdo->prepare(
        'UPDATE master_items SET product_code = ?, product_name = ?, brand = ?, product_class = ?, updated_at = NOW(), modified_by = ? WHERE id = ?'
    )->execute([$productCode, $productName, $brand, $productClass, $modifiedBy, $id]);
    $targetItemId = $id;
    $message = 'Master item "' . $productName . '" updated.';
} else {
    $pdo->prepare(
        'INSERT INTO master_items (product_code, product_name, brand, product_class, updated_at, modified_by) VALUES (?, ?, ?, ?, NOW(), ?)'
    )->execute([$productCode, $productName, $brand, $productClass, $modifiedBy]);
    $targetItemId = (int)$pdo->lastInsertId();
    $message = 'Master item "' . $productName . '" added.';
}

// Persist low stock notification settings if submitted (from edit modal)
if (isset($_POST['alert_enabled']) || isset($_POST['threshold'])) {
    $alertEnabled = isset($_POST['alert_enabled']) && $_POST['alert_enabled'] == '1' ? 1 : 0;
    $thresholdRaw = trim($_POST['threshold'] ?? '');
    $thresholdVal = ($thresholdRaw !== '' && is_numeric($thresholdRaw)) ? (int)$thresholdRaw : 5;
    if ($thresholdVal < 0) $thresholdVal = 0;

    $stAlert = $pdo->prepare("
        INSERT INTO item_alert_settings (master_item_id, alert_enabled, threshold, updated_by, updated_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            alert_enabled = VALUES(alert_enabled),
            threshold = VALUES(threshold),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
    ");
    $stAlert->execute([$targetItemId, $alertEnabled, $thresholdVal, $modifiedBy]);
}

redirect_to('items', 'added', $message);