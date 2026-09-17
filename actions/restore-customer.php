<?php
require_once __DIR__ . '/../includes/functions.php';
$target = ($_POST['from'] ?? '') === 'customers' ? 'customers' : 'master-bin';
handle_master_lifecycle('customer', 'restore', (int)($_POST['id'] ?? 0), $target);
