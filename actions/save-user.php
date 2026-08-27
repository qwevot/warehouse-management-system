<?php
// CREATE / UPDATE: inserts a new user or updates an existing one. Super admin only.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('super admin');

$pdo = html_db('adjustments');

$id       = (int)($_POST['id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$fullName = trim($_POST['full_name'] ?? '');
$role     = trim($_POST['role'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '') {
    redirect_to('adjustments', 'error', 'Username is required.');
}

if (!in_array($role, available_roles(), true)) {
    redirect_to('adjustments', 'error', 'Please choose a valid role.');
}

$currentUserId = (int)(current_user()['id'] ?? 0);

// A super admin cannot demote their own account (avoids locking everyone out).
if ($id === $currentUserId && $role !== 'super admin') {
    redirect_to('adjustments', 'error', 'You cannot change your own role.');
}

// Guard against duplicate usernames (case-insensitive), excluding self.
$st = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id <> ?');
$st->execute([$username, $id]);
if ($st->fetch()) {
    redirect_to('adjustments', 'error', 'That username already exists.');
}

$fullName = $fullName !== '' ? $fullName : null;

if ($id > 0) {
    if ($password !== '') {
        $pdo->prepare('UPDATE users SET username = ?, full_name = ?, role = ?, password_hash = ? WHERE id = ?')
            ->execute([$username, $fullName, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
    } else {
        $pdo->prepare('UPDATE users SET username = ?, full_name = ?, role = ? WHERE id = ?')
            ->execute([$username, $fullName, $role, $id]);
    }
    redirect_to('adjustments', 'added', 'User "' . $username . '" updated.');
}

if ($password === '') {
    redirect_to('adjustments', 'error', 'A password is required for a new user.');
}

$pdo->prepare('INSERT INTO users (username, full_name, role, password_hash) VALUES (?, ?, ?, ?)')
    ->execute([$username, $fullName, $role, password_hash($password, PASSWORD_DEFAULT)]);

redirect_to('adjustments', 'added', 'User "' . $username . '" added.');
