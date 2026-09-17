<?php
// IMPORT: receives rows parsed from an uploaded .csv/.xlsx file for master_customers
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_csrf_api();
require_can_api('master.manage');

$pdo = json_db();
$rows = json_decode($_POST['rows'] ?? '[]', true);

if (!is_array($rows) || count($rows) === 0) {
    json_fail('No data to import.');
}

$modifiedBy = current_user_name();
$pdo->beginTransaction();

try {
    $count = 0;
    $stCheck = $pdo->prepare('SELECT id FROM master_customers WHERE LOWER(name) = LOWER(?)');
    $stIns   = $pdo->prepare('INSERT INTO master_customers (name, company_code, contact_person, phone, email, address, updated_at, modified_by) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)');
    $stUp    = $pdo->prepare('UPDATE master_customers 
                              SET company_code = COALESCE(NULLIF(?, ""), company_code), 
                                  contact_person = COALESCE(NULLIF(?, ""), contact_person),
                                  phone = COALESCE(NULLIF(?, ""), phone),
                                  email = COALESCE(NULLIF(?, ""), email),
                                  address = COALESCE(NULLIF(?, ""), address),
                                  deleted_at = NULL,
                                  updated_at = NOW(),
                                  modified_by = ?
                              WHERE id = ?');

    foreach ($rows as $r) {
        $name = trim((string)($r['name'] ?? ''));
        if ($name === '') continue;

        $code    = trim((string)($r['company_code'] ?? '')) ?: null;
        $contact = trim((string)($r['contact_person'] ?? '')) ?: null;
        $phone   = trim((string)($r['phone'] ?? '')) ?: null;
        $email   = trim((string)($r['email'] ?? '')) ?: null;
        $addr    = trim((string)($r['address'] ?? '')) ?: null;

        $stCheck->execute([$name]);
        $existingId = $stCheck->fetchColumn();

        if ($existingId) {
            $stUp->execute([$code, $contact, $phone, $email, $addr, $modifiedBy, (int)$existingId]);
        } else {
            $stIns->execute([$name, $code, $contact, $phone, $email, $addr, $modifiedBy]);
        }
        $count++;
    }

    $pdo->commit();
    log_activity('master_customer.import', 'master_customer', null, ['count' => $count], 'success');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_activity('master_customer.import', 'master_customer', null, ['error' => $e->getMessage()], 'failure');
    json_fail('Import failed: ' . $e->getMessage());
}

json_ok(['message' => 'Imported ' . $count . ' customer(s).']);
