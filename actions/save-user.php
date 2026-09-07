<?php
// CREATE / UPDATE: inserts a new user or updates an existing one. Super admin only.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('super admin');

$pdo = html_db('users');

$id       = (int)($_POST['id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$fullName = trim($_POST['full_name'] ?? '');
$role     = trim($_POST['role'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '') {
    redirect_to('users', 'error', 'Username is required.');
}

if (!in_array($role, available_roles(), true)) {
    redirect_to('users', 'error', 'Please choose a valid role.');
}

$currentUserId = (int)(current_user()['id'] ?? 0);

// A super admin cannot demote their own account (avoids locking everyone out).
if ($id === $currentUserId && $role !== 'super admin') {
    redirect_to('users', 'error', 'You cannot change your own role.');
}

// Guard against duplicate usernames (case-insensitive), excluding self.
$st = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id <> ?');
$st->execute([$username, $id]);
if ($st->fetch()) {
    redirect_to('users', 'error', 'That username already exists.');
}

$company  = trim($_POST['company'] ?? '');
$company  = $company !== '' ? $company : null;
$fullName = $fullName !== '' ? $fullName : null;

if ($id > 0) {
    if ($password !== '') {
        $pdo->prepare('UPDATE users SET username = ?, full_name = ?, role = ?, company = ?, password_hash = ? WHERE id = ?')
            ->execute([$username, $fullName, $role, $company, password_hash($password, PASSWORD_DEFAULT), $id]);
    } else {
        $pdo->prepare('UPDATE users SET username = ?, full_name = ?, role = ?, company = ? WHERE id = ?')
            ->execute([$username, $fullName, $role, $company, $id]);
    }
    redirect_to('users', 'added', 'User "' . $username . '" updated.');
}

if ($password === '') {
    redirect_to('users', 'error', 'A password is required for a new user.');
}

$pdo->prepare('INSERT INTO users (username, full_name, role, company, password_hash) VALUES (?, ?, ?, ?, ?)')
    ->execute([$username, $fullName, $role, $company, password_hash($password, PASSWORD_DEFAULT)]);

redirect_to('users', 'added', 'User "' . $username . '" added.');
