<?php
// EXPORT LOGS TO CSV: Super admin & Admin only
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('logs.view');

$pdo = db();

$filterCat    = trim($_GET['cat'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterUser   = trim($_GET['user'] ?? '');
$search       = trim($_GET['q'] ?? '');

$sql = "SELECT id, created_at, username, role, action, entity_type, entity_id, status, ip_address, user_agent, details 
        FROM system_logs WHERE 1=1";
$params = [];

if ($filterCat !== '') {
    $sql .= " AND action LIKE ?";
    $params[] = $filterCat . '.%';
}
if ($filterStatus !== '') {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}
if ($filterUser !== '') {
    $sql .= " AND username = ?";
    $params[] = $filterUser;
}
if ($search !== '') {
    $sql .= " AND (action LIKE ? OR username LIKE ? OR ip_address LIKE ? OR details LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY id DESC LIMIT 5000";

$st = $pdo->prepare($sql);
$st->execute($params);
$logs = $st->fetchAll();

log_activity('system.export_logs', 'system_logs', null, [
    'filter_cat' => $filterCat ?: 'all',
    'filter_status' => $filterStatus ?: 'all',
    'filter_user' => $filterUser ?: 'all',
    'search_query' => $search ?: null,
    'exported_rows_count' => count($logs)
], 'success');

$filename = 'system_logs_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// UTF-8 BOM for Excel compatibility
fputs($out, "\xEF\xBB\xBF");

fputcsv($out, ['Log ID', 'Timestamp', 'Username', 'Role', 'Action', 'Entity Type', 'Entity ID', 'Status', 'IP Address', 'User Agent', 'Details']);

foreach ($logs as $row) {
    fputcsv($out, [
        $row['id'],
        $row['created_at'],
        $row['username'] ?? 'N/A',
        $row['role'] ?? 'N/A',
        $row['action'],
        $row['entity_type'] ?? 'N/A',
        $row['entity_id'] ?? 'N/A',
        $row['status'],
        $row['ip_address'] ?? 'N/A',
        $row['user_agent'] ?? '',
        $row['details'] ?? ''
    ]);
}

fclose($out);
exit;
