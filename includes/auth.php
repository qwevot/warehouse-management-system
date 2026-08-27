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

function require_login_api(): void
{
    if (current_user() === null) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Not authenticated. Please sign in again.']);
        exit;
    }
}
