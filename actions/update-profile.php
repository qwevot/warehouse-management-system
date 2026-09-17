<?php
/**
 * Action: Update User Profile & Security Settings
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_csrf('profile');

$pdo = html_db('profile');
$user = current_user();
$userId = (int)($user['id'] ?? 0);

if ($userId <= 0) {
    redirect_to('profile', 'error', 'Invalid user session.');
}

$action = trim($_POST['action'] ?? 'update_profile');

if ($action === 'update_profile') {
    $fullName = trim($_POST['full_name'] ?? '');
    $fullNameVal = $fullName !== '' ? $fullName : null;
    $removeAvatar = !empty($_POST['remove_avatar']);

    $avatarPath = $user['avatar'] ?? null;
    $avatarUpdated = false;

    // Handle avatar removal
    if ($removeAvatar) {
        if (!empty($user['avatar'])) {
            delete_avatar($user['avatar']);
        }
        $avatarPath = null;
        $avatarUpdated = true;
    }

    // Handle new avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploaded = save_avatar_upload($_FILES['avatar'], $userId);
        if ($uploaded !== null) {
            if (!empty($user['avatar']) && $user['avatar'] !== $uploaded) {
                delete_avatar($user['avatar']);
            }
            $avatarPath = $uploaded;
            $avatarUpdated = true;
        } else {
            redirect_to('profile', 'error', 'Failed to upload image. Please ensure it is a valid JPEG, PNG, or WebP image under 5MB.');
        }
    }

    $st = $pdo->prepare('UPDATE users SET full_name = ?, avatar = ? WHERE id = ?');
    $st->execute([$fullNameVal, $avatarPath, $userId]);

    log_activity('user.profile_update', 'users', $userId, [
        'full_name' => $fullNameVal,
        'avatar_updated' => $avatarUpdated,
        'avatar_removed' => $removeAvatar
    ]);

    redirect_to('profile', 'success', 'Profile information updated successfully.');
}

if ($action === 'update_password') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword     = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        redirect_to('profile', 'error', 'All password fields are required.');
    }

    if ($newPassword !== $confirmPassword) {
        redirect_to('profile', 'error', 'New password and confirmation do not match.');
    }

    if (strlen($newPassword) < 6) {
        redirect_to('profile', 'error', 'New password must be at least 6 characters long.');
    }

    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $currentHash = $st->fetchColumn();

    if (!$currentHash || !password_verify($currentPassword, $currentHash)) {
        log_activity('user.password_change_failed', 'users', $userId, ['reason' => 'invalid_current_password'], 'failure');
        redirect_to('profile', 'error', 'Incorrect current password. Please try again.');
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stUp = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stUp->execute([$newHash, $userId]);

    log_activity('user.password_change', 'users', $userId, [], 'success');

    redirect_to('profile', 'success', 'Password successfully changed.');
}

redirect_to('profile', 'error', 'Invalid action requested.');
