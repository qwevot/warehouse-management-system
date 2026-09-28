<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    // Detect HTTPS even behind Cloudflare Tunnel or other reverse proxies
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

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
            $st = db()->prepare('SELECT id, username, full_name, role, company, avatar, created_at FROM users WHERE id = ?');
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
    return ['guest', 'customer', 'technician', 'engineer', 'admin', 'super admin'];
}

function current_role(): ?string
{
    $user = current_user();
    return $user === null ? null : (string)($user['role'] ?? '');
}

// Check if the current user is an internal staff member (not customer/guest).
function is_staff(): bool
{
    $role = current_role();
    return in_array($role, ['technician', 'engineer', 'admin', 'super admin'], true);
}


// Check if the current user is a guest.
function is_guest(): bool
{
    return current_role() === 'guest';
}

// Block access unless the current user is staff or a guest with view privileges (redirects to dashboard).
function require_staff(): void
{
    require_login();
    if (!is_staff() && !is_guest()) {
        header('Location: ' . BASE_URL . '/pages/dashboard.php');
        exit;
    }
}

// Block API access unless the current user is staff or guest (returns 403 JSON).
function require_staff_api(): void
{
    require_login_api();
    if (!is_staff() && !is_guest()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Access restricted.']);
        exit;
    }
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
            return ['master.manage', 'inventory.manage', 'movement.manage', 'permanent_delete', 'users.manage', 'logs.view'];
        case 'admin':
            return ['master.manage', 'inventory.manage', 'movement.manage', 'logs.view'];
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

function require_can_api(string $perm): void
{
    if (!user_can($perm)) {
        if (function_exists('log_activity')) {
            log_activity('auth.permission_denied', 'security', null, [
                'required_permission' => $perm,
                'endpoint' => $_SERVER['REQUEST_URI'] ?? ''
            ], 'failure');
        }
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'You do not have permission to do this.']);
        exit;
    }
}

function require_login_api(): void
{
    if (current_user() === null) {
        if (function_exists('log_activity')) {
            log_activity('auth.unauthenticated_api_request', 'security', null, [
                'endpoint' => $_SERVER['REQUEST_URI'] ?? ''
            ], 'failure');
        }
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Not authenticated. Please sign in again.']);
        exit;
    }
}

// ------------------------------------------------------------
// CSRF Protection Helpers
// ------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $token = $_POST['csrf_token']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? '';

    if ($token === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (strcasecmp((string)$k, 'X-CSRF-Token') === 0) {
                    $token = $v;
                    break;
                }
            }
        }
    }

    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(string $fallbackPage = 'dashboard'): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
        if (function_exists('log_activity')) {
            log_activity('security.csrf_invalid', 'security', null, [
                'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? ''
            ], 'failure');
        }
        redirect_to($fallbackPage, 'error', 'Security validation failed (CSRF token invalid or expired). Please try again.');
    }
}

function require_csrf_api(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
        if (function_exists('log_activity')) {
            log_activity('security.csrf_api_invalid', 'security', null, [
                'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? ''
            ], 'failure');
        }
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Security validation failed (CSRF token invalid or expired). Please refresh and try again.']);
        exit;
    }
}

