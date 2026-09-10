<?php
// MERGE: combines several items into one. Two modes:
//   new      -> creates a brand-new item (new serial/barcode/QR) with the
//               summed quantity, then soft-deletes the merged items.
//   existing -> adds the summed quantity to an existing item (its barcode
//               number is kept), then soft-deletes the merged items.
// Called via AJAX from the Barcode & QR Code Generator page.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_can_api('inventory.manage');

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

$pdo->beginTransaction();

try {
    $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT * FROM inventory_list WHERE id IN ($inPlaceholders) FOR UPDATE");
    $st->execute($ids);
    $rows = $st->fetchAll();

    if (count($rows) !== count($ids)) {
        $pdo->rollBack();
        json_fail('One or more selected items could not be found.');
    }

    foreach ($rows as $row) {
        if ($row['deleted_at'] !== null) {
            $pdo->rollBack();
            json_fail('One or more selected items have been deleted.');
        }
    }

    $first = $rows[0];
    foreach ($rows as $row) {
        if (
            strcasecmp(trim($row['name'] ?? ''), trim($first['name'] ?? '')) !== 0 ||
            strcasecmp(trim($row['brand'] ?? ''), trim($first['brand'] ?? '')) !== 0
        ) {
            $pdo->rollBack();
            json_fail('Only items with the same product name and brand can be merged.');
        }
    }

    $totalQty = 0;
    foreach ($rows as $row) {
        $totalQty += max(0, (int)$row['quantity']);
    }

    $modifiedBy = current_user_name();

    if ($mode === 'existing') {
        $target = null;
        foreach ($rows as $row) {
            if ((int)$row['id'] === $targetId) {
                $target = $row;
                break;
            }
        }
        if ($target === null) {
            $pdo->rollBack();
            json_fail('Target item not found among locked items.');
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
        $name = trim($_POST['name'] ?? '');
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
        ], $customBarcode === '' ? null : $customBarcode);

        foreach ($ids as $id) {
            $pdo->prepare("UPDATE inventory_list SET deleted_at = NOW(), status = 'taken' WHERE id = ?")->execute([$id]);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_fail('Could not merge the items.');
}

json_ok(['message' => 'Items merged.']);