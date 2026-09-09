<?php
$navSections = [
    'Master Data' => [
        ['brands', 'pages/brand.php', 'Brands', 'brands'],
        ['items', 'pages/items.php', 'Items', 'items'],
        ['master-bin', 'pages/master-bin.php', 'Master Bin', 'bin'],
    ],
    'Inventory' => [
        ['inventory', 'pages/index.php', 'Lot', 'lot'],
        ['barcode', 'pages/generator.php', 'Lot Split and Merge', 'split-merge'],
        ['units', 'pages/units.php', 'Units', 'units'],
        ['bin', 'pages/bin.php', 'Inventory Bin', 'bin'],
    ],
    'Movement' => [
        ['stock-in', 'pages/stock-in.php', 'Inbound', 'inbound'],
        ['stock-out', 'pages/stock-out.php', 'Outbound', 'outbound'],
        ['transfers', 'pages/transfers.php', 'Transfers', 'transfers'],
        ['history', 'pages/history.php', 'Movement History', 'history'],
    ],
];

if (current_role() === 'customer') {
    $navSections = [];
} elseif (current_role() === 'super admin') {
    $navSections['Administration'] = [
        ['users', 'pages/users.php', 'Users', 'users'],
    ];
}
?>
<aside class="sidebar">
    <div class="sidebar-content">
        <a href="<?php echo BASE_URL; ?>/pages/dashboard.php" class="nav-item <?php echo $activeNav === 'dashboard' ? 'active' : ''; ?> dashboard-link">
            <div class="nav-icon"><?php echo render_icon('dashboard'); ?></div>
            <span>Dashboard</span>
        </a>

    <?php foreach ($navSections as $label => $navItems): ?>
        <?php $isOpen = in_array($activeNav, array_column($navItems, 0), true); ?>

        <button type="button" class="nav-group-toggle <?php echo $isOpen ? 'open' : ''; ?>" data-group="<?php echo strtolower(str_replace(' ', '-', $label)); ?>" onclick="toggleNavGroup(this)" aria-expanded="<?php echo $isOpen ? 'true' : 'false'; ?>">
            <span><?php echo $label; ?></span>
            <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </button>

        <div class="nav-group <?php echo $isOpen ? 'open' : ''; ?>">
            <?php foreach ($navItems as [$key, $href, $text, $iconKey]): ?>
                <a href="<?php echo BASE_URL . '/' . $href; ?>" class="nav-item <?php echo $activeNav === $key ? 'active' : ''; ?>">
                    <div class="nav-icon"><?php echo render_icon($iconKey); ?></div>
                    <span><?php echo $text; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

    <?php endforeach; ?>
    </div>

    <div class="sidebar-version">
        v<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.0'; ?>
    </div>

</aside>
