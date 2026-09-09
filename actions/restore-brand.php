<?php
require_once __DIR__ . '/../includes/functions.php';
$fromBin = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
handle_master_lifecycle('brand', 'restore', (int)($_REQUEST['id'] ?? 0), $fromBin ? 'master-bin' : 'brand');
