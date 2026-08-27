<?php
// MERGE: combines several items into one. Two modes:
//   new      -> creates a brand-new item (new serial/barcode/QR) with the
//               summed quantity, then soft-deletes the merged items.
//   existing -> adds the summed quantity to an existing item (its barcode
//               number is kept), then soft-deletes the merged items.
// Called via AJAX from the Barcode & QR Code Generator page.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();

$pdo  = json_db();
$mode = $_POST['mode'] ?? 'new';

$rawIds = $_POST['ids'] ?? '';
if (is_array($rawIds)) {
    $rawIds = implode(',', $rawIds);
}
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$rawIds)))));
sort($ids);

if (count($ids) < 2) {
    json_fail('Select at least 2 items to merge.');
}

$targetId = (int)($_POST['target_id'] ?? 0);

if ($mode === 'existing') {
    if ($targetId <= 0) {
        json_fail('Choose a target item to merge into.');
    }
    if (!in_array($targetId, $ids, true)) {
        json_fail('The target item must be one of the selected items.');
    }
}

$rows = [];
foreach ($ids as $id) {
    $row = find_item($pdo, $id);
    if ($row === null || $row['deleted_at'] !== null) {
        json_fail('One or more selected items could not be found.');
    }
    $rows[] = $row;
}

$totalQty = 0;
foreach ($rows as $row) {
    $totalQty += max(0, (int)$row['quantity']);
}

$pdo->beginTransaction();

try {
    $modifiedBy = current_user_name();

    if ($mode === 'existing') {
        $target = find_item($pdo, $targetId);
        if ($target === null || $target['deleted_at'] !== null) {
            $pdo->rollBack();
            json_fail('Target item not found.');
        }

        // The target keeps its own barcode and absorbs the summed quantity of
        // every selected item (its own quantity is already part of the total).
        $pdo->prepare('UPDATE inventory_list SET quantity = ?, updated_at = NOW(), modified_by = ? WHERE id = ?')
            ->execute([$totalQty, $modifiedBy, $targetId]);

        foreach ($ids as $id) {
            if ($id === $targetId) continue;
            $pdo->prepare("UPDATE inventory_list SET deleted_at = NOW(), status = 'taken' WHERE id = ?")->execute([$id]);
        }
    } else {
        $first = $rows[0];
        $name  = trim($_POST['name'] ?? '');
        if ($name === '') {
            $name = $first['name'];
        }

        $customBarcode = trim((string)($_POST['barcode'] ?? ''));
        if ($customBarcode !== '' && mb_strlen($customBarcode) > 16) {
            $pdo->rollBack();
            json_fail('The barcode number is too long (max 16 characters).');
        }

        create_inventory_item($pdo, [
            'name'        => $name,
            'brand'       => $first['brand'],
            'quantity'    => $totalQty,
            'location'    => $first['location'],
            'photo'       => $first['photo'],
            'status'      => 'available',
            'modified_by' => $modifiedBy,
        ], null, $customBarcode === '' ? null : $customBarcode);

        foreach ($ids as $id) {
            $pdo->prepare("UPDATE inventory_list SET deleted_at = NOW(), status = 'taken' WHERE id = ?")->execute([$id]);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_fail('Could not merge the items.');
}

json_ok(['message' => 'Items merged.']);