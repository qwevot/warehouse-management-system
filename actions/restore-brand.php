<?php
require_once __DIR__ . '/../includes/functions.php';
$target = ($_POST['from'] ?? '') === 'brand' ? 'brand' : 'master-bin';
handle_master_lifecycle('brand', 'restore', (int)($_POST['id'] ?? 0), $target);
