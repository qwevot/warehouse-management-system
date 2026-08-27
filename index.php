<?php
// Root entry point: visiting /Inventory lands on the sign-in page. If the
// session is already authenticated, route straight into the app.
require_once __DIR__ . '/includes/auth.php';

if (current_user() === null) {
    header('Location: ' . BASE_URL . '/login.php');
} else {
    header('Location: ' . BASE_URL . '/pages/index.php');
}
exit;