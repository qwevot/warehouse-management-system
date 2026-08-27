<?php
// DELETE: removes a user account. Super admin only; cannot delete yourself.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('super admin');

$pdo = html_db('adjustments');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect_to('adjustments', 'error', 'Invalid user.');
}

$currentUserId = (int)(current_user()['id'] ?? 0);
if ($id === $currentUserId) {
    redirect_to('adjustments', 'error', 'You cannot delete your own account.');
}

$st = $pdo->prepare('SELECT username FROM users WHERE id = ?');
$st->execute([$id]);
$username = $st->fetchColumn();
if ($username === false) {
    redirect_to('adjustments', 'error', 'User not found.');
}

$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);

redirect_to('adjustments', 'added', 'User "' . $username . '" deleted.');
