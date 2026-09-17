<?php
// IMPORT: receives brand rows parsed from an uploaded .csv/.xlsx file
// and inserts them into master_brands table.
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
        $name        = trim((string)($r['name'] ?? ''));
        $description = trim((string)($r['description'] ?? '')) ?: null;

        if ($name === '') {
            continue;
        }

        // Check duplicate brand
        $chk = $pdo->prepare('SELECT id, deleted_at FROM master_brands WHERE LOWER(name) = LOWER(?)');
        $chk->execute([$name]);
        $existing = $chk->fetch();

        if ($existing) {
            if ($existing['deleted_at'] !== null) {
                // Restore & update description if it was in the bin
                $pdo->prepare('UPDATE master_brands SET description = ?, deleted_at = NULL, updated_at = NOW(), modified_by = ? WHERE id = ?')
                    ->execute([$description, $modifiedBy, $existing['id']]);
                $count++;
            }
            continue;
        }

        $pdo->prepare('INSERT INTO master_brands (name, description, updated_at, modified_by) VALUES (?, ?, NOW(), ?)')
            ->execute([$name, $description, $modifiedBy]);

        $count++;
    }

    $pdo->commit();
    log_activity('master_brand.import', 'master_brands', null, [
        'count' => $count,
        'source' => 'spreadsheet_import'
    ], 'success');
    json_ok(['count' => $count, 'message' => "Successfully imported {$count} brand(s)."]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_activity('master_brand.import', 'master_brands', null, [
        'error' => $e->getMessage()
    ], 'failure');
    json_fail('Import failed. No rows were added.');
}
