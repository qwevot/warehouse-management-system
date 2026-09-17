<?php
require_once 'includes/auth.php';

if (function_exists('log_activity')) {
    log_activity('auth.logout', 'user', $_SESSION['user_id'] ?? null, [
        'username' => $_SESSION['username'] ?? null
    ]);
}

$_SESSION = [];

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: login.php');
exit;
