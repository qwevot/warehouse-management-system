<?php
// CLEAR HISTORY: Truncates stock_movements table. Restricted to super admin only.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('super admin');

$pdo = html_db('history');

try {
    $pdo->exec('TRUNCATE TABLE stock_movements');
    redirect_to('history', 'added', 'Movement history has been deleted.');
} catch (Exception $e) {
    redirect_to('history', 'error', 'Failed to delete movement history.');
}
