<?php
require_once __DIR__ . '/../includes/functions.php';
handle_master_lifecycle('customer', 'delete', (int)($_POST['id'] ?? 0), 'customers');
