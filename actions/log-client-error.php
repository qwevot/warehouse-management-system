<?php
/**
 * AJAX Endpoint: Report Client Network/JavaScript Errors to System Logs
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$action = trim((string)($data['action'] ?? 'client.network_error'));
$errorMessage = trim((string)($data['error'] ?? 'Unknown client-side error'));
$url = trim((string)($data['url'] ?? ''));
$context = is_array($data['context'] ?? null) ? $data['context'] : [];

$details = [
    'error' => $errorMessage,
    'endpoint' => $url,
    'context' => $context,
    'client_time' => date('Y-m-d H:i:s'),
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
];

log_activity($action, 'system_error', null, $details, 'failure');

echo json_encode(['ok' => true]);
