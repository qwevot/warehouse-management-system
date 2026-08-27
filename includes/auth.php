<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;

    if ($user === null) {
        try {
            $st = db()->prepare('SELECT id, username, full_name, role FROM users WHERE id = ?');
            $st->execute([$_SESSION['user_id']]);
            $row = $st->fetch();
            $user = $row === false ? null : $row;
        } catch (Exception $e) {
            $user = null;
        }
    }

    return $user;
}

function require_login(): void
{
    if (current_user() === null) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// Display name for the logged-in user (full name, else username, else null).
function current_user_name(): ?string
{
    $user = current_user();
    if ($user === null) {
        return null;
    }
    return trim((string)($user['full_name'] ?? '')) ?: trim((string)($user['username'] ?? ''));
}

// The canonical role strings, lowest privilege first.
function available_roles(): array
{
    return ['guest', 'technician', 'engineer', 'admin', 'super admin'];
}

function current_role(): ?string
{
    $user = current_user();
    return $user === null ? null : (string)($user['role'] ?? '');
}

// Block access unless the current user has the exact role (redirects home).
function require_role(string $role): void
{
    if (current_role() !== $role) {
        header('Location: ' . BASE_URL . '/pages/dashboard.php');
        exit;
    }
}

// Permissions granted to each role (capability-based access control).
function role_permissions(string $role): array
{
    switch ($role) {
        case 'super admin':
            return ['master.manage', 'inventory.manage', 'movement.manage', 'permanent_delete', 'users.manage'];
        case 'admin':
            return ['master.manage', 'inventory.manage', 'movement.manage'];
        case 'engineer':
            return ['inventory.manage', 'movement.manage'];
        case 'technician':
            return ['movement.manage'];
        default:
            return [];
    }
}

function user_can(string $perm): bool
{
    $role = current_role();
    return $role !== null && in_array($perm, role_permissions($role), true);
}

// Redirect home when the current user lacks the permission.
function require_can(string $perm): void
{
    if (!user_can($perm)) {
        header('Location: ' . BASE_URL . '/pages/dashboard.php');
        exit;
    }
}

// JSON 403 variant for AJAX endpoints.
function require_can_api(string $perm): void
{
    if (!user_can($perm)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'You do not have permission to do this.']);
        exit;
    }
}

function require_login_api(): void
{
    if (current_user() === null) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Not authenticated. Please sign in again.']);
        exit;
    }
}
