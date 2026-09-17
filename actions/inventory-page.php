<?php
// INVENTORY PAGE (AJAX): returns one page of filtered/sorted inventory rows as
// rendered HTML, plus the JSON payload for modals and pagination state.
// With format=all it returns every matching row as JSON (no HTML) for export.
require_once __DIR__ . '/../includes/auth.php';
require_staff_api();
require_csrf_api();

require_once __DIR__ . '/../includes/inventory-data.php';

$pdo = json_db();

$mode = $_POST['mode'] ?? 'list';
if (!in_array($mode, ['list', 'bin', 'generator', 'units', 'stock-in', 'stock-out', 'transfers'], true)) {
    $mode = 'list';
}

$state  = inventory_state_from_input($_POST);
$format = (string)($_POST['format'] ?? '');

$result = inventory_fetch($pdo, $mode, $state, $format === 'all');

$payload = array_map('inventory_item_payload', $result['rows']);

if ($format === 'all') {
    json_ok(['rows' => $payload, 'total' => $result['total']]);
}

json_ok([
    'rows_html' => render_inventory_rows($result['rows'], $mode, ($result['page'] - 1) * $result['size']),
    'rows'      => $payload,
    'total'     => $result['total'],
    'page'      => $result['page'],
    'size'      => $result['size'],
]);
