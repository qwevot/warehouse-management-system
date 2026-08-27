<?php
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

require_once __DIR__ . '/../includes/auth.php';
require_login();

include __DIR__ . '/../includes/header.php';

include __DIR__ . '/../includes/coming-soon.php';

include __DIR__ . '/../includes/footer.php';
