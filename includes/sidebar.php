<?php
$navSections = [
    'Dashboard' => [
        ['dashboard', 'pages/dashboard.php', 'Overview', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>'],
    ],
    'Master Data' => [
        ['brands', 'pages/brand.php', 'Brands', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>'],
        ['items', 'pages/items.php', 'Items', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9"/><path d="M3 9h18v12H3z"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg>'],
        ['master-bin', 'pages/master-bin.php', 'Master Bin', '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M7.69231 8.70833H5V8.16667H9.84615M7.69231 8.70833V19H16.3077V8.70833M7.69231 8.70833H16.3077M16.3077 8.70833H19V8.16667H14.1538M9.84615 8.16667V6H14.1538V8.16667M9.84615 8.16667H14.1538" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M10 11V17" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M12 11V17" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M14 11V17" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>'],

    ],
    'Inventory' => [
        ['inventory', 'pages/index.php', 'List', '<svg viewBox="0 0 512 512" version="1.1" fill="currentColor"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <title>list</title> <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="add" fill="currentColor" transform="translate(64.000000, 64.000000)"> <path d="M106.666667,1.42108547e-14 L384,1.42108547e-14 L384,42.6666667 L106.666667,42.6666667 L106.666667,1.42108547e-14 Z M1.42108547e-14,1.42108547e-14 L64,1.42108547e-14 L64,42.6666667 L1.42108547e-14,42.6666667 L1.42108547e-14,1.42108547e-14 Z M106.666667,106.666667 L384,106.666667 L384,149.333333 L106.666667,149.333333 L106.666667,106.666667 Z M1.42108547e-14,106.666667 L64,106.666667 L64,149.333333 L1.42108547e-14,149.333333 L1.42108547e-14,106.666667 Z M106.666667,213.333333 L384,213.333333 L384,256 L106.666667,256 L106.666667,213.333333 Z M1.42108547e-14,213.333333 L64,213.333333 L64,256 L1.42108547e-14,256 L1.42108547e-14,213.333333 Z M106.666667,320 L384,320 L384,362.666667 L106.666667,362.666667 L106.666667,320 Z M1.42108547e-14,320 L64,320 L64,362.666667 L1.42108547e-14,362.666667 L1.42108547e-14,320 Z" id="Combined-Shape"> </path> </g> </g> </g></svg>'],
        ['bin', 'pages/bin.php', 'Inventory Bin', '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M7.69231 8.70833H5V8.16667H9.84615M7.69231 8.70833V19H16.3077V8.70833M7.69231 8.70833H16.3077M16.3077 8.70833H19V8.16667H14.1538M9.84615 8.16667V6H14.1538V8.16667M9.84615 8.16667H14.1538" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M10 11V17" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M12 11V17" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M14 11V17" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>'],
        ['barcode', 'pages/generator.php', 'Split and Merge', '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <rect width="48" height="48" fill="white" fill-opacity="0.01"></rect> <path d="M20 14V5C20 4.44772 19.5523 4 19 4H5C4.44772 4 4 4.44772 4 5V43C4 43.5523 4.44772 44 5 44H19C19.5523 44 20 43.5523 20 43V34" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path> <path d="M28 34V43C28 43.5523 28.4477 44 29 44H43C43.5523 44 44 43.5523 44 43V5C44 4.44772 43.5523 4 43 4H29C28.4477 4 28 4.44772 28 5V14" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path> <path d="M28 24H44" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path> <path d="M5 24H20" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path> <path d="M32.7485 28.8183L31.1575 27.2274L27.9756 24.0454L31.1575 20.8634L32.7485 19.2724" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M15.375 28.8183L16.966 27.2274L20.148 24.0454L16.966 20.8634L15.375 19.2724" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>'],
        ['scan', 'pages/scan.php', 'Scan', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><line x1="7" y1="12" x2="17" y2="12"></line></svg>'],
    ],
    'Movement' => [
        ['stock-in', 'pages/stock-in.php', 'Inbound', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"></path><polyline points="8 11 12 15 16 11"></polyline><path d="M4 21h16"></path></svg>'],
        ['stock-out', 'pages/stock-out.php', 'Outbound', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21V9"></path><polyline points="8 13 12 9 16 13"></polyline><path d="M4 3h16"></path></svg>'],
        ['transfers', 'pages/transfers.php', 'Transfers', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>'],
        ['history', 'pages/history.php', 'Movement History', '<svg viewBox="0 0 24 24"><path fill-rule="evenodd" d="M5.07868 5.06891C8.87402 1.27893 15.0437 1.31923 18.8622 5.13778C22.6824 8.95797 22.7211 15.1313 18.9262 18.9262C15.1312 22.7211 8.95793 22.6824 5.13774 18.8622C2.87389 16.5984 1.93904 13.5099 2.34047 10.5812C2.39672 10.1708 2.775 9.88377 3.18537 9.94002C3.59575 9.99627 3.88282 10.3745 3.82658 10.7849C3.4866 13.2652 4.27782 15.881 6.1984 17.8016C9.44288 21.0461 14.6664 21.0646 17.8655 17.8655C21.0646 14.6664 21.046 9.44292 17.8015 6.19844C14.5587 2.95561 9.33889 2.93539 6.13935 6.12957L6.88705 6.13333C7.30126 6.13541 7.63535 6.47288 7.63327 6.88709C7.63119 7.3013 7.29372 7.63539 6.87951 7.63331L4.33396 7.62052C3.92269 7.61845 3.58981 7.28556 3.58774 6.8743L3.57495 4.32874C3.57286 3.91454 3.90696 3.57707 4.32117 3.57498C4.73538 3.5729 5.07285 3.907 5.07493 4.32121L5.07868 5.06891ZM11.9999 7.24992C12.4141 7.24992 12.7499 7.58571 12.7499 7.99992V11.6893L15.0302 13.9696C15.3231 14.2625 15.3231 14.7374 15.0302 15.0302C14.7373 15.3231 14.2624 15.3231 13.9696 15.0302L11.2499 12.3106V7.99992C11.2499 7.58571 11.5857 7.24992 11.9999 7.24992Z" fill="currentColor"/></svg>'],
    ],
];

if (current_role() === 'super admin') {
    $navSections['Administration'] = [
        ['users', 'pages/adjustments.php', 'Users', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'],
    ];
}
?>
<aside class="sidebar">

    <?php foreach ($navSections as $label => $navItems): ?>
        <?php $isOpen = in_array($activeNav, array_column($navItems, 0), true); ?>

        <button type="button" class="nav-group-toggle <?php echo $isOpen ? 'open' : ''; ?>" data-group="<?php echo strtolower(str_replace(' ', '-', $label)); ?>" onclick="toggleNavGroup(this)" aria-expanded="<?php echo $isOpen ? 'true' : 'false'; ?>">
            <span><?php echo $label; ?></span>
            <svg class="nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </button>

        <div class="nav-group <?php echo $isOpen ? 'open' : ''; ?>">
            <?php foreach ($navItems as [$key, $href, $text, $icon]): ?>
                <a href="<?php echo BASE_URL . '/' . $href; ?>" class="nav-item <?php echo $activeNav === $key ? 'active' : ''; ?>">
                    <div class="nav-icon"><?php echo $icon; ?></div>
                    <span><?php echo $text; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

    <?php endforeach; ?>

</aside>
