<?php
require_once __DIR__ . '/../includes/functions.php';
handle_inventory_lifecycle('permanent_delete', (int)($_POST['id'] ?? 0), '', trim($_POST['type'] ?? ''));
