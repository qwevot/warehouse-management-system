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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
    <title><?php echo e($pageTitle); ?></title>
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo BASE_URL; ?>/favicon.svg">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css" onerror="this.href='<?php echo BASE_URL; ?>/css/katex.min.css'">
    <script>
        window.BASE_URL = <?php echo json_encode(BASE_URL); ?>;
        window.PAGE_TITLE = <?php echo json_encode($pageTitle ?? ''); ?>;
        window.ACTIVE_NAV = <?php echo json_encode($activeNav ?? ''); ?>;
    </script>
</head>
<body>

<div class="page-loader" id="pageLoader" aria-hidden="true">
    <div class="spinner"></div>
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

                <a href="<?php echo BASE_URL; ?>/pages/profile.php" class="user-profile-link" title="My Profile">
                    <div class="user-avatar">
                        <?php if (!empty($currentUser['avatar']) && is_file(__DIR__ . '/../' . $currentUser['avatar'])): ?>
                            <img src="<?php echo BASE_URL . '/' . e($currentUser['avatar']); ?>" alt="<?php echo e($fullName); ?>">
                        <?php else: ?>
                            <?php echo e($initials); ?>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="user-name"><?php echo e($fullName); ?></div>
                        <div class="user-role"><?php echo e($roleLabel); ?></div>
                    </div>
                </a>

                <a href="<?php echo BASE_URL; ?>/logout.php" class="logout-link">Log out</a>

            </div>

        </header>

        <div class="content">
