<?php
// CREATE / UPDATE: inserts a new customer or edits an existing one.
// Called from pages/customers.php (add form + edit modal). Redirects back with flash message.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('master.manage');
require_csrf('customers');

$pdo = html_db('customers');

$id            = (int)($_POST['id'] ?? 0);
$name          = trim((string)($_POST['name'] ?? ''));
$companyCode   = trim((string)($_POST['company_code'] ?? ''));
$contactPerson = trim((string)($_POST['contact_person'] ?? ''));
$phone         = trim((string)($_POST['phone'] ?? ''));
$email         = trim((string)($_POST['email'] ?? ''));
$address       = trim((string)($_POST['address'] ?? ''));

$companyCode   = $companyCode === '' ? null : $companyCode;
$contactPerson = $contactPerson === '' ? null : $contactPerson;
$phone         = $phone === '' ? null : $phone;
$email         = $email === '' ? null : $email;
$address       = $address === '' ? null : $address;

if ($name === '') {
    redirect_to('customers', 'error', 'Company / Customer name is required.');
}

// Guard against duplicate name (case-insensitive)
$st = $pdo->prepare('SELECT id, deleted_at FROM master_customers WHERE LOWER(name) = LOWER(?) AND id <> ? ORDER BY deleted_at IS NULL DESC LIMIT 1');
$st->execute([$name, $id]);
$dup = $st->fetch();
if ($dup) {
    if ($dup['deleted_at'] !== null) {
        header('Location: ' . BASE_URL . '/pages/customers.php?error=' . urlencode('Customer "' . $name . '" already exists in the bin. Would you like to ') . '&bin_restore=' . (int)$dup['id']);
        exit;
    }
    redirect_to('customers', 'error', 'A customer with that name already exists.');
}

$modifiedBy = current_user_name();

if ($id > 0) {
    $stOld = $pdo->prepare('SELECT name FROM master_customers WHERE id = ?');
    $stOld->execute([$id]);
    $oldName = $stOld->fetchColumn();

    $stUp = $pdo->prepare('UPDATE master_customers 
                           SET name = ?, company_code = ?, contact_person = ?, phone = ?, email = ?, address = ?, updated_at = NOW(), modified_by = ? 
                           WHERE id = ?');
    $stUp->execute([$name, $companyCode, $contactPerson, $phone, $email, $address, $modifiedBy, $id]);

    // If customer name was renamed, optionally update customer user records and deployed unit locations
    if ($oldName && strcasecmp($oldName, $name) !== 0) {
        $pdo->prepare('UPDATE users SET company = ? WHERE company = ?')->execute([$name, $oldName]);
    }

    log_activity('master_customer.edit', 'master_customer', $id, [
        'old_name' => $oldName,
        'new_name' => $name,
        'company_code' => $companyCode,
        'contact_person' => $contactPerson
    ]);
    $message = 'Customer "' . $name . '" updated.';
} else {
    $stIns = $pdo->prepare('INSERT INTO master_customers (name, company_code, contact_person, phone, email, address, updated_at, modified_by) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)');
    $stIns->execute([$name, $companyCode, $contactPerson, $phone, $email, $address, $modifiedBy]);
    $newId = (int)$pdo->lastInsertId();

    log_activity('master_customer.create', 'master_customer', $newId, [
        'name' => $name,
        'company_code' => $companyCode,
        'contact_person' => $contactPerson
    ]);
    $message = 'Customer "' . $name . '" added.';
}

redirect_to('customers', 'added', $message);
