<?php
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_csrf_api();
require_can_api('master.manage');

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
        $productName  = trim((string)($r['product_name'] ?? ''));
        $brand        = trim((string)($r['brand'] ?? ''));
        $productCode  = trim((string)($r['product_code'] ?? ''));
        $productClass = trim((string)($r['product_class'] ?? '')) ?: null;

        if ($productName === '' || $brand === '') {
            continue;
        }

        if ($productCode === '' || !preg_match('/^\d{1,4}$/', $productCode)) {
            $productCode = next_master_code($pdo);
        } else {
            $productCode = str_pad($productCode, 4, '0', STR_PAD_LEFT);
        }

        $chkCode = $pdo->prepare('SELECT id FROM master_items WHERE LOWER(product_code) = LOWER(?)');
        $chkCode->execute([$productCode]);
        if ($chkCode->fetch()) {
            $productCode = next_master_code($pdo);
        }

        $chkName = $pdo->prepare('SELECT id, deleted_at FROM master_items WHERE LOWER(product_name) = LOWER(?) AND LOWER(COALESCE(brand, "")) = LOWER(?)');
        $chkName->execute([$productName, $brand]);
        $existing = $chkName->fetch();

        if ($existing) {
            if ($existing['deleted_at'] !== null) {
                $pdo->prepare('UPDATE master_items SET product_code = ?, product_class = ?, deleted_at = NULL, updated_at = NOW(), modified_by = ? WHERE id = ?')
                    ->execute([$productCode, $productClass, $modifiedBy, $existing['id']]);
                $count++;
            }
            continue;
        }

        $pdo->prepare('INSERT INTO master_items (product_code, product_name, brand, product_class, updated_at, modified_by) VALUES (?, ?, ?, ?, NOW(), ?)')
            ->execute([$productCode, $productName, $brand, $productClass, $modifiedBy]);

        $count++;
    }

    $pdo->commit();
    log_activity('master_item.import', 'master_items', null, [
        'count' => $count,
        'source' => 'spreadsheet_import'
    ], 'success');
    json_ok(['count' => $count, 'message' => "Successfully imported {$count} master item(s)."]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_activity('master_item.import', 'master_items', null, [
        'error' => $e->getMessage()
    ], 'failure');
    json_fail('Import failed. No rows were added.');
}
