<?php
// DELETE: removes a user account. Super admin only; cannot delete yourself.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('super admin');
require_csrf('users');

$pdo = html_db('users');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect_to('users', 'error', 'Invalid user.');
}

$currentUserId = (int)(current_user()['id'] ?? 0);
if ($id === $currentUserId) {
    redirect_to('users', 'error', 'You cannot delete your own account.');
}

$st = $pdo->prepare('SELECT username, role FROM users WHERE id = ?');
$st->execute([$id]);
$targetUser = $st->fetch(PDO::FETCH_ASSOC);
if (!$targetUser) {
    redirect_to('users', 'error', 'User not found.');
}
$username = $targetUser['username'];

if ($targetUser['role'] === 'super admin') {
    $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super admin'")->fetchColumn();
    if ($adminCount <= 1) {
        redirect_to('users', 'error', 'Cannot delete the sole Super Administrator account.');
    }
}

$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);

log_activity('user.delete', 'user', $id, [
    'username' => $username
], 'success');

redirect_to('users', 'added', 'User "' . $username . '" deleted.');
