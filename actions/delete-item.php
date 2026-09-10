<?php
require_once __DIR__ . '/../includes/functions.php';
handle_inventory_lifecycle('delete', (int)($_POST['id'] ?? 0), trim($_POST['note'] ?? ''), trim($_POST['type'] ?? ''));
