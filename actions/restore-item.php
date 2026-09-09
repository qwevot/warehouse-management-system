<?php
require_once __DIR__ . '/../includes/functions.php';
handle_inventory_lifecycle('restore', (int)($_POST['id'] ?? 0));
