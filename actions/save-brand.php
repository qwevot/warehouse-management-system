<?php
// CREATE / UPDATE: inserts a new brand or renames an existing one. Called from
// pages/brand.php (add form + rename modal). Redirects back with a flash message.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('master.manage');
require_csrf('brand');

$pdo = html_db('brand');

$id          = (int)($_POST['id'] ?? 0);
$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$description = $description === '' ? null : $description;

if ($name === '') {
    redirect_to('brand', 'error', 'Brand name is required.');
}

// Guard against duplicates (case-insensitive). Prefer an active match when one
// exists; otherwise a binned (soft-deleted) brand with the same name can be
// restored instead of re-created.
$st = $pdo->prepare('SELECT id, deleted_at FROM master_brands WHERE LOWER(name) = LOWER(?) AND id <> ? ORDER BY deleted_at IS NULL DESC LIMIT 1');
$st->execute([$name, $id]);
$dup = $st->fetch();
if ($dup) {
    if ($dup['deleted_at'] !== null) {
        header('Location: ' . BASE_URL . '/pages/brand.php?error=' . urlencode('Brand "' . $name . '" already exists in the bin. Would you like to ') . '&bin_restore=' . (int)$dup['id']);
        exit;
    }
    redirect_to('brand', 'error', 'That brand already exists.');
}

$modifiedBy = current_user_name();

if ($id > 0) {
    $stOld = $pdo->prepare('SELECT name FROM master_brands WHERE id = ?');
    $stOld->execute([$id]);
    $oldName = $stOld->fetchColumn();

    $pdo->prepare('UPDATE master_brands SET name = ?, description = ?, updated_at = NOW(), modified_by = ? WHERE id = ?')->execute([$name, $description, $modifiedBy, $id]);
    if ($oldName && strcasecmp($oldName, $name) !== 0) {
        $pdo->prepare('UPDATE master_items SET brand = ?, updated_at = NOW(), modified_by = ? WHERE brand = ?')->execute([$name, $modifiedBy, $oldName]);
    }
    log_activity('master_brand.edit', 'master_brand', $id, [
        'old_name' => $oldName,
        'new_name' => $name,
        'description' => $description
    ]);
    $message = 'Brand renamed to "' . $name . '".';
} else {
    $pdo->prepare('INSERT INTO master_brands (name, description, updated_at, modified_by) VALUES (?, ?, NOW(), ?)')->execute([$name, $description, $modifiedBy]);
    $newBrandId = (int)$pdo->lastInsertId();
    log_activity('master_brand.create', 'master_brand', $newBrandId, [
        'name' => $name,
        'description' => $description
    ]);
    $message = 'Brand "' . $name . '" added.';
}

redirect_to('brand', 'added', $message);