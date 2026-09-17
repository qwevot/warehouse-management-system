<?php
// PURGE LOGS: Purge older logs or all logs. Super admin only.
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('super admin');
require_csrf('system-logs');

$pdo = html_db('system-logs');

$range = trim($_POST['range'] ?? 'all');

try {
    if ($range === '30') {
        $st = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $st->execute();
        $affected = $st->rowCount();
        log_activity('system.purge_logs', 'system_logs', null, ['range' => 'older_than_30_days', 'count' => $affected], 'success');
        $msg = "Purged {$affected} logs older than 30 days.";
    } elseif ($range === '90') {
        $st = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $st->execute();
        $affected = $st->rowCount();
        log_activity('system.purge_logs', 'system_logs', null, ['range' => 'older_than_90_days', 'count' => $affected], 'success');
        $msg = "Purged {$affected} logs older than 90 days.";
    } else {
        $pdo->exec("TRUNCATE TABLE system_logs");
        log_activity('system.purge_logs', 'system_logs', null, ['range' => 'all'], 'success');
        $msg = "All system logs have been purged.";
    }

    redirect_to('system-logs', 'added', $msg);
} catch (Exception $e) {
    redirect_to('system-logs', 'error', 'Failed to purge system logs.');
}
