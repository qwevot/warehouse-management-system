<?php
// CLEAR HISTORY: Truncates stock_movements table. Restricted to super admin only.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('super admin');
require_csrf('history');

$pdo = html_db('history');

try {
    $pdo->exec('TRUNCATE TABLE stock_movements');
    log_activity('system.truncate_history', 'stock_movements', null, ['action' => 'truncate_all'], 'success');
    redirect_to('history', 'added', 'Movement history has been deleted.');
} catch (Exception $e) {
    log_activity('system.truncate_history', 'stock_movements', null, ['error' => $e->getMessage()], 'failure');
    redirect_to('history', 'error', 'Failed to delete movement history.');
}
