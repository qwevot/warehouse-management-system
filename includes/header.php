<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$activeNav = $activeNav ?? 'dashboard';

$currentUser = current_user();
$fullName    = $currentUser['full_name'] ?? $currentUser['username'] ?? 'User';
$roleLabel   = ucfirst($currentUser['role'] ?? 'User');

$words = preg_split('/\s+/', trim($fullName));
$initials = strtoupper(($words[0][0] ?? 'U') . ($words[1][0] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL; ?>/favicon.svg">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
</head>
<body>

<div class="page-loader" id="pageLoader" aria-hidden="true">
    <div class="loader-box">
        <div class="spinner"></div>
    </div>
</div>

<div class="app">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="main">

        <header class="topbar">

            <button type="button" class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="20" height="20">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>

            <div class="user-area">

                <div class="user-avatar">
                    <?php echo e($initials); ?>
                </div>

                <div>
                    <div class="user-name"><?php echo e($fullName); ?></div>
                    <div class="user-role"><?php echo e($roleLabel); ?></div>
                </div>

                <a href="<?php echo BASE_URL; ?>/logout.php" class="logout-link">Log out</a>

            </div>

        </header>

        <div class="content">
