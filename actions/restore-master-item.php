<?php
require_once __DIR__ . '/../includes/functions.php';
handle_master_lifecycle('master-item', 'restore', (int)($_POST['id'] ?? 0));
