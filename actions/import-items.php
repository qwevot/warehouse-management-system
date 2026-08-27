<?php
// IMPORT: receives rows parsed from an uploaded .csv/.xlsx file (parsed in the
// browser with SheetJS) and inserts them into the inventory. Each row gets its
// own serial, barcode, and QR. Called via AJAX from the Items and Generator pages.
require_once __DIR__ . '/../includes/auth.php';
require_login_api();

$pdo  = json_db();
$rows = json_decode($_POST['rows'] ?? '[]', true);

if (!is_array($rows) || count($rows) === 0) {
    json_fail('No data to import.');
}

$modifiedBy = current_user_name();

$pdo->beginTransaction();

try {
    $count = 0;
    foreach ($rows as $r) {
        $name = trim((string)($r['name'] ?? ''));
        if ($name === '') {
            continue; // rows without a product name are skipped
        }

        $qty      = max(1, (int)($r['quantity'] ?? 1));
        $brand    = trim((string)($r['brand'] ?? '')) ?: null;
        $location = trim((string)($r['location'] ?? '')) ?: null;
        $serial   = trim((string)($r['serial'] ?? '')) ?: null;
        $status   = strtolower(trim((string)($r['status'] ?? ''))) === 'inactive' ? 'taken' : 'available';

        // Guard against duplicate imported serials colliding with active rows;
        // on collision fall back to an auto-generated serial.
        if ($serial !== null) {
            $dup = $pdo->prepare('SELECT id FROM inventory_list WHERE serial = ? AND deleted_at IS NULL');
            $dup->execute([$serial]);
            if ($dup->fetch()) {
                $serial = null;
            }
        }

        create_inventory_item($pdo, [
            'name'        => $name,
            'brand'       => $brand,
            'quantity'    => $qty,
            'location'    => $location,
            'status'      => $status,
            'modified_by' => $modifiedBy,
        ], $serial);

        $count++;
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_fail('Import failed. No rows were added.');
}

json_ok(['message' => 'Imported ' . $count . ' item(s).']);