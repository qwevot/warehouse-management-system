<?php
// Shared server-side data + row rendering for the inventory list pages
// (pages/index.php, pages/bin.php, pages/generator.php) and the AJAX
// endpoint (actions/inventory-page.php). Keeps the query building, the
// pagination math, and the <tr> markup in one place so the initial page
// render and every subsequent AJAX page render stay identical.
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// The column set rendered for each list mode. Order matters.
function inventory_columns(string $mode): array
{
    switch ($mode) {
        case 'bin':
            return ['no', 'action', 'photo', 'item', 'brand', 'location', 'serial', 'bin_note', 'status', 'last_modified', 'modified_by'];
        case 'generator':
            return ['no', 'photo', 'item', 'brand','quantity', 'serial', 'location', 'barcode', 'action'];
        case 'units':
            return ['no', 'action', 'photo', 'item', 'brand', 'location', 'status', 'serial', 'barcode', 'registered', 'last_modified', 'modified_by'];
        default:
            return ['no', 'action', 'photo', 'item', 'brand', 'quantity', 'location', 'status', 'barcode', 'registered', 'last_modified', 'modified_by'];
    }
}

// Which rows a mode shows: the bin lists soft-deleted rows; everything else
// lists active rows.
function inventory_scope(string $mode): string
{
    if ($mode === 'bin') {
        return 'deleted_at IS NOT NULL';
    }
    if ($mode === 'list' || $mode === 'generator') {
        return "deleted_at IS NULL AND quantity > 0 AND status = 'available' AND deployment_site = 'In Warehouse'";
    }
    return "deleted_at IS NULL AND status = 'available' AND deployment_site = 'In Warehouse'";
}

// Normalize request input (from $_GET or $_POST) into a validated state array.
function inventory_state_from_input(array $in): array
{
    $size = (int)($in['size'] ?? 10);
    if (!in_array($size, [5, 10, 15, 20, 25, 50], true)) $size = 10;

    $sort = (string)($in['sort'] ?? 'newest');
    if (!in_array($sort, ['newest', 'oldest', 'name', 'nameDesc', 'created-desc', 'created-asc', 'modified-desc', 'modified-asc', 'created_desc', 'created_asc', 'modified_desc', 'modified_asc'], true)) $sort = 'newest';

    $list = function ($key) use ($in) {
        $raw = $in[$key] ?? '';
        if (is_array($raw)) {
            $vals = array_map('trim', $raw);
        } else {
            $vals = explode(',', (string)$raw);
        }
        return array_values(array_filter(array_map('trim', $vals), 'strlen'));
    };

    return [
        'page'  => max(1, (int)($in['page'] ?? 1)),
        'size'  => $size,
        'q'     => trim((string)($in['q'] ?? '')),
        'sort'  => $sort,
        'brand' => $list('brand'),
        'shelf' => $list('shelf'),
        'row'   => $list('row'),
        'col'   => $list('col'),
    ];
}

function inventory_state_from_request(): array
{
    return inventory_state_from_input($_GET);
}

// Run the filtered, sorted, paginated query. Returns the page's rows, the
// matching total, and the (possibly clamped) page/size. When $all is true the
// LIMIT/OFFSET is skipped and every matching row is returned (used for export).
function inventory_fetch(PDO $pdo, string $mode, array $state, bool $all = false): array
{
    if ($mode === 'units' || $mode === 'bin') {
        $where = [$mode === 'bin' 
            ? 'u.deleted_at IS NOT NULL' 
            : "u.deleted_at IS NULL AND i.deleted_at IS NULL"
        ];
        $params = [];

        $q = (string)($state['q'] ?? '');
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(i.name LIKE ? OR i.brand LIKE ? OR u.serial LIKE ? OR i.barcode LIKE ? OR COALESCE(u.location, i.location, "") LIKE ? OR COALESCE(u.bin_note, "") LIKE ? OR COALESCE(u.modified_by, "") LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }

        $brand = (array)($state['brand'] ?? []);
        if (count($brand) > 0) {
            $ph = implode(',', array_fill(0, count($brand), '?'));
            $where[] = 'i.brand IN (' . $ph . ')';
            foreach ($brand as $b) $params[] = $b;
        }

        foreach (['shelf' => 'S', 'row' => 'R', 'col' => 'C'] as $key => $prefix) {
            $vals = (array)($state[$key] ?? []);
            $parts = [];
            foreach ($vals as $v) {
                $v = (string)$v;
                if (preg_match('/^' . $prefix . '\d{2}$/', $v) !== 1) continue;
                if ($key === 'shelf') {
                    $parts[] = 'COALESCE(u.location, i.location, "") LIKE ?';
                    $params[] = $v . '-%';
                } elseif ($key === 'row') {
                    $parts[] = 'COALESCE(u.location, i.location, "") LIKE ?';
                    $params[] = '%-' . $v . '-%';
                } else {
                    $parts[] = 'COALESCE(u.location, i.location, "") LIKE ?';
                    $params[] = '%-' . $v;
                }
            }
            if (count($parts) > 0) $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        $whereSql = implode(' AND ', $where);

        $orderMap = [
            'newest'        => 'u.id DESC',
            'oldest'        => 'u.id ASC',
            'name'          => 'i.name ASC, u.id DESC',
            'nameDesc'      => 'i.name DESC, u.id DESC',
            'created-desc'  => 'u.created_at DESC, u.id DESC',
            'created-asc'   => 'u.created_at ASC, u.id ASC',
            'modified-desc' => 'COALESCE(u.updated_at, u.created_at) DESC, u.id DESC',
            'modified-asc'  => 'COALESCE(u.updated_at, u.created_at) ASC, u.id ASC',
            'created_desc'  => 'u.created_at DESC, u.id DESC',
            'created_asc'   => 'u.created_at ASC, u.id ASC',
            'modified_desc' => 'COALESCE(u.updated_at, u.created_at) DESC, u.id DESC',
            'modified_asc'  => 'COALESCE(u.updated_at, u.created_at) ASC, u.id ASC',
        ];
        $order = $orderMap[$state['sort'] ?? 'newest'] ?? 'u.id DESC';

        $st = $pdo->prepare('SELECT COUNT(*) FROM inventory_units u JOIN inventory_list i ON i.id = u.inventory_id WHERE ' . $whereSql);
        $st->execute($params);
        $total = (int)$st->fetchColumn();

        $size = max(1, min(200, (int)($state['size'] ?? 10)));
        $page = max(1, (int)($state['page'] ?? 1));

        $selectSql = "SELECT u.id, u.inventory_id, u.serial, u.qr, u.status, u.created_at, u.updated_at, u.modified_by, u.bin_note, u.deleted_at,
                             u.bin_location, u.deployment_site,
                             COALESCE(NULLIF(u.bin_location, ''), NULLIF(u.deployment_site, ''), u.location, i.location, '') AS location,
                             i.name, i.brand, i.photo, i.barcode, 1 AS quantity
                      FROM inventory_units u
                      JOIN inventory_list i ON i.id = u.inventory_id
                      WHERE {$whereSql}
                      ORDER BY {$order}";

        if ($all) {
            $st = $pdo->prepare($selectSql);
            $st->execute($params);
            $rows = $st->fetchAll();
            return ['rows' => $rows, 'total' => $total, 'page' => 1, 'size' => $total];
        }

        $pageCount = max(1, (int)ceil($total / $size));
        if ($page > $pageCount) $page = $pageCount;
        $offset = ($page - 1) * $size;

        $st = $pdo->prepare($selectSql . ' LIMIT ' . $size . ' OFFSET ' . $offset);
        $st->execute($params);
        $rows = $st->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'size' => $size];
    }

    $where = [inventory_scope($mode)];
    $params = [];

    $q = (string)($state['q'] ?? '');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(name LIKE ? OR brand LIKE ? OR barcode LIKE ? OR location LIKE ? OR COALESCE(bin_location, "") LIKE ? OR COALESCE(deployment_site, "") LIKE ? OR COALESCE(bin_note, "") LIKE ? OR COALESCE(modified_by, "") LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
    }

    $brand = (array)($state['brand'] ?? []);
    if (count($brand) > 0) {
        $ph = implode(',', array_fill(0, count($brand), '?'));
        $where[] = 'brand IN (' . $ph . ')';
        foreach ($brand as $b) $params[] = $b;
    }

    foreach (['shelf' => 'S', 'row' => 'R', 'col' => 'C'] as $key => $prefix) {
        $vals = (array)($state[$key] ?? []);
        $parts = [];
        foreach ($vals as $v) {
            $v = (string)$v;
            if (preg_match('/^' . $prefix . '\d{2}$/', $v) !== 1) continue;
            if ($key === 'shelf') {
                $parts[] = '(COALESCE(bin_location, location) LIKE ?)';
                $params[] = $v . '-%';
            } elseif ($key === 'row') {
                $parts[] = '(COALESCE(bin_location, location) LIKE ?)';
                $params[] = '%-' . $v . '-%';
            } else {
                $parts[] = '(COALESCE(bin_location, location) LIKE ?)';
                $params[] = '%-' . $v;
            }
        }
        if (count($parts) > 0) $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $whereSql = implode(' AND ', $where);

    $orderMap = [
        'newest'        => 'id DESC',
        'oldest'        => 'id ASC',
        'name'          => 'name ASC',
        'nameDesc'      => 'name DESC',
        'created-desc'  => 'created_at DESC, id DESC',
        'created-asc'   => 'created_at ASC, id ASC',
        'modified-desc' => 'COALESCE(updated_at, created_at) DESC, id DESC',
        'modified-asc'  => 'COALESCE(updated_at, created_at) ASC, id ASC',
        'created_desc'  => 'created_at DESC, id DESC',
        'created_asc'   => 'created_at ASC, id ASC',
        'modified_desc' => 'COALESCE(updated_at, created_at) DESC, id DESC',
        'modified_asc'  => 'COALESCE(updated_at, created_at) ASC, id ASC',
    ];
    $order = $orderMap[$state['sort'] ?? 'newest'] ?? 'id DESC';

    $st = $pdo->prepare('SELECT COUNT(*) FROM inventory_list WHERE ' . $whereSql);
    $st->execute($params);
    $total = (int)$st->fetchColumn();

    $size = max(1, min(200, (int)($state['size'] ?? 10)));
    $page = max(1, (int)($state['page'] ?? 1));

    if ($all) {
        $st = $pdo->prepare('SELECT * FROM inventory_list WHERE ' . $whereSql . ' ORDER BY ' . $order);
        $st->execute($params);
        $rows = $st->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => 1, 'size' => $total];
    }

    $pageCount = max(1, (int)ceil($total / $size));
    if ($page > $pageCount) $page = $pageCount;
    $offset = ($page - 1) * $size;

    $st = $pdo->prepare('SELECT * FROM inventory_list WHERE ' . $whereSql . ' ORDER BY ' . $order . ' LIMIT ' . $size . ' OFFSET ' . $offset);
    $st->execute($params);
    $rows = $st->fetchAll();

    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'size' => $size];
}

// Distinct filter values (brands + shelf/row/col tokens) for the filter panel's
// autocomplete datalists. These don't change per page, so they're computed once
// on the initial page load.
function inventory_facets(PDO $pdo, string $mode): array
{
    $scope = inventory_scope($mode);

    if ($mode === 'units' || $mode === 'bin') {
        $whereSql = $mode === 'bin' ? 'u.deleted_at IS NOT NULL' : 'u.deleted_at IS NULL AND i.deleted_at IS NULL';
        $brands = $pdo->query("SELECT DISTINCT i.brand FROM inventory_units u JOIN inventory_list i ON i.id = u.inventory_id WHERE {$whereSql} AND i.brand IS NOT NULL AND i.brand <> '' ORDER BY i.brand ASC")
            ->fetchAll(PDO::FETCH_COLUMN);

        $locs = $pdo->query("SELECT DISTINCT i.location FROM inventory_units u JOIN inventory_list i ON i.id = u.inventory_id WHERE {$whereSql} AND i.location IS NOT NULL AND i.location <> ''")
            ->fetchAll(PDO::FETCH_COLUMN);

        $shelves = [];
        $rows    = [];
        $cols    = [];
        foreach ($locs as $loc) {
            $parts = explode('-', (string)$loc);
            if (isset($parts[0]) && preg_match('/^S\d{2}$/', $parts[0])) $shelves[] = $parts[0];
            if (isset($parts[1]) && preg_match('/^R\d{2}$/', $parts[1])) $rows[]    = $parts[1];
            if (isset($parts[2]) && preg_match('/^C\d{2}$/', $parts[2])) $cols[]    = $parts[2];
        }

        return [
            'brand' => array_values(array_unique($brands)),
            'shelf' => array_values(array_unique($shelves)),
            'row'   => array_values(array_unique($rows)),
            'col'   => array_values(array_unique($cols)),
        ];
    } else {
        $brands = $pdo->query("SELECT DISTINCT brand FROM inventory_list WHERE $scope AND brand IS NOT NULL AND brand <> '' ORDER BY brand ASC")
            ->fetchAll(PDO::FETCH_COLUMN);

        $locations = $pdo->query("SELECT DISTINCT location FROM inventory_list WHERE $scope AND location IS NOT NULL AND location <> ''")
            ->fetchAll(PDO::FETCH_COLUMN);

        $shelves = [];
        $rows = [];
        $cols = [];
        foreach ($locations as $loc) {
            $parts = explode('-', (string)$loc);
            if (count($parts) === 3) {
                $shelves[] = $parts[0];
                $rows[] = $parts[1];
                $cols[] = $parts[2];
            }
        }
        sort($shelves);
        sort($rows);
        sort($cols);

        return [
            'brand' => array_values(array_unique($brands)),
            'shelf' => array_values(array_unique($shelves)),
            'row'   => array_values(array_unique($rows)),
            'col'   => array_values(array_unique($cols)),
        ];
    }
}

// The JSON shape handed to the browser for modals and export.
function inventory_item_payload(array $item): array
{
    $depSite = trim((string)($item['deployment_site'] ?? ''));
    $locCategory = classify_record_location($item);

    return [
        'id'              => (int)$item['id'],
        'serial'          => $item['serial'],
        'name'            => $item['name'],
        'brand'           => $item['brand'],
        'quantity'        => (int)$item['quantity'],
        'location'        => $item['location'],
        'loc_category'    => $locCategory,
        'deployment_site' => $depSite,
        'barcode'         => $item['barcode'],
        'qr'              => $item['qr'],
        'photo'           => $item['photo'] ? BASE_URL . '/' . $item['photo'] : '',
        'status'          => $item['status'],
        'created_at'      => $item['created_at'] ? date('Y-m-d H:i', strtotime($item['created_at'])) : '',
        'created_ts'      => $item['created_at'] ? (int)strtotime($item['created_at']) : 0,
    ];
}

// Action buttons for a row, honoring the current user's permissions.
function render_inventory_action_buttons(array $item, string $mode): string
{
    $id = (int)$item['id'];
    $html = '';

    if ($mode === 'bin') {
        if (user_can('inventory.manage')) {
            $html .= '<button class="btn icon-btn restore" title="Restore" onclick="runRestoreItem(' . $id . ', \'unit\')">' . icon_restore() . '</button>';
        }
        if (user_can('permanent_delete')) {
            $html .= '<button class="btn icon-btn delete" title="Permanently Delete" onclick="runPermanentDelete(' . $id . ', \'unit\')">' . icon_delete() . '</button>';
        }
        return $html;
    }

    if ($mode === 'generator') {
        if ($item['barcode']) {
            $html .= '<button class="btn icon-btn view" title="View" onclick="openItemModal(' . $id . ')">' . icon_view() . '</button>';
        } elseif (user_can('inventory.manage')) {
            $html .= '<button class="btn sm primary" title="Generate Barcode &amp; QR Code" onclick="openGenModal(' . $id . ')">Generate</button>';
        }
        return $html;
    }

    if ($mode === 'units') {
        $html .= '<button class="btn icon-btn view" title="View Unit Details" onclick="openItemModal(' . $id . ')">' . icon_view() . '</button>';
        if (user_can('inventory.manage')) {
            $html .= '<button class="btn icon-btn delete" title="Move Unit to Bin" onclick="runDeleteItem(' . $id . ', \'unit\')">' . icon_delete() . '</button>';
        }
        return $html;
    }

    // list (default)
    if (user_can('inventory.manage')) {
        $html .= '<button class="btn icon-btn edit" title="Edit" onclick="openEditModal(' . $id . ')">' . icon_edit() . '</button>';
    }
    $html .= '<button class="btn icon-btn view" title="View" onclick="openItemModal(' . $id . ')">' . icon_view() . '</button>';
    if (user_can('inventory.manage')) {
        $html .= '<button class="btn icon-btn delete" title="Delete" onclick="runDeleteItem(' . $id . ', \'lot\')">' . icon_delete() . '</button>';
    }

    return $html;
}

// Render the <tr> rows for a single page of items. $startIndex is the global
// position of the first row (for the "No." column).
function render_inventory_rows(array $rows, string $mode, int $startIndex = 0): string
{
    $cols = inventory_columns($mode);
    $selectable = ($mode === 'generator' || $mode === 'list');
    $html = '';

    foreach ($rows as $i => $item) {
        $no = $startIndex + $i + 1;
        $modTs = !empty($item['updated_at']) ? (int)strtotime((string)$item['updated_at']) : (int)strtotime((string)$item['created_at']);

        $depSite = trim((string)($item['deployment_site'] ?? ''));
        $locCategory = classify_record_location($item);

        $html .= '<tr class="inventory-row" data-id="' . (int)$item['id'] . '" data-status="' . e($item['status']) . '" data-brand="' . e($item['brand']) . '" data-loc="' . e($item['location']) . '" data-loc-category="' . $locCategory . '" data-deployment-site="' . e($depSite) . '" data-created="' . (int)strtotime((string)$item['created_at']) . '" data-modified="' . $modTs . '">';

        if ($selectable) {
            $html .= '<td class="merge-check-col" data-label=""><input type="checkbox" class="row-select" value="' . (int)$item['id'] . '"></td>';
        }

        if (in_array('no', $cols, true)) {
            $html .= '<td class="no-col" data-label="No.">' . $no . '</td>';
        }

        if (in_array('action', $cols, true)) {
            $html .= '<td data-label="Action"><div class="row-actions">' . render_inventory_action_buttons($item, $mode) . '</div></td>';
        }

        if (in_array('photo', $cols, true)) {
            if ($item['photo']) {
                $html .= '<td data-label="Photo"><img class="item-photo" src="' . e(BASE_URL . '/' . $item['photo']) . '" alt="' . e($item['name']) . '" loading="lazy" decoding="async" onclick="openItemModal(' . (int)$item['id'] . ')"></td>';
            } else {
                $html .= '<td data-label="Photo"><div class="item-photo"></div></td>';
            }
        }

        if (in_array('item', $cols, true)) {
            $html .= '<td data-label="Item"><div class="item-name">' . e($item['name']) . '</div></td>';
        }

        if (in_array('brand', $cols, true)) {
            $html .= '<td data-label="Brand">' . dash($item['brand']) . '</td>';
        }

        if (in_array('quantity', $cols, true)) {
            $html .= '<td data-label="Quantity"><span class="qty">' . (int)$item['quantity'] . '</span></td>';
        }

        if (in_array('location', $cols, true)) {
            $binLoc = trim((string)($item['bin_location'] ?? ''));
            $depSite = trim((string)($item['deployment_site'] ?? ''));
            if ($binLoc === '' && preg_match('/^S\d+-R\d+-C\d+/', (string)($item['location'] ?? ''))) {
                $binLoc = trim((string)$item['location']);
            }
            if ($depSite === '') {
                $depSite = ($binLoc !== '') ? 'In Warehouse' : trim((string)($item['location'] ?? ''));
            }

            if ($binLoc !== '') {
                $html .= '<td data-label="Location">';
                $html .= '<div class="location-cell">';
                $html .= '<span class="bin-badge">' . e($binLoc) . '</span>';
                if ($depSite !== '' && $depSite !== $binLoc) {
                    $html .= '<span class="site-subtext">' . e($depSite) . '</span>';
                }
                $html .= '</div></td>';
            } elseif ($depSite !== '') {
                $html .= '<td data-label="Location">';
                $html .= '<div class="location-cell"><span class="site-text">' . e($depSite) . '</span></div>';
                $html .= '</td>';
            } else {
                $html .= '<td data-label="Location"><span class="dash">—</span></td>';
            }
        }

        if (in_array('status', $cols, true)) {
            $isInactive = ($mode === 'bin' || !empty($item['deleted_at']) || in_array(strtolower((string)$item['status']), ['inactive', 'taken'], true));
            $stLabel = $isInactive ? 'INACTIVE' : 'ACTIVE';
            $stClass = $isInactive ? 'inactive' : 'available';
            $html .= '<td data-label="Status"><span class="status ' . $stClass . '">' . $stLabel . '</span></td>';
        }

        if (in_array('serial', $cols, true)) {
            $html .= '<td data-label="Serial"><span class="serial">' . e($item['serial']) . '</span></td>';
        }

        if (in_array('barcode', $cols, true)) {
            if ($item['barcode']) {
                $html .= '<td data-label="Barcode"><span class="serial" style="font-size:11px;">' . e($item['barcode']) . '</span></td>';
            } else {
                $html .= '<td data-label="Barcode"><span class="dash">—</span></td>';
            }
        }

        if (in_array('qr', $cols, true)) {
            $modalId = (int)$item['id'];
            $html .= '<td data-label="Print Label"><button type="button" class="btn icon-btn view" title="Print Label" onclick="openPrintUnitModal(' . $modalId . ')"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg></button></td>';
        }

        if (in_array('registered', $cols, true)) {
            $html .= '<td data-label="Registered"><span class="registered-on">' . e(fmt_datetime($item['created_at'])) . '</span></td>';
        }

        if (in_array('last_modified', $cols, true)) {
            if (!empty($item['updated_at'])) {
                $html .= '<td data-label="Last Modified"><span class="registered-on">' . e(fmt_datetime($item['updated_at'])) . '</span></td>';
            } else {
                $html .= '<td data-label="Last Modified"><span class="dash">—</span></td>';
            }
        }

        if (in_array('modified_by', $cols, true)) {
            $html .= '<td data-label="Modified By">' . dash($item['modified_by']) . '</td>';
        }

        if (in_array('bin_note', $cols, true)) {
            $html .= '<td data-label="Bin Note">' . dash($item['bin_note']) . '</td>';
        }

        $html .= '</tr>';
    }

    return $html;
}

function render_inventory_screen(string $pageTitle, string $activeNav, string $mode): void
{
    require_login();

    $state  = inventory_state_from_request();
    $rows   = [];
    $total  = 0;
    $facets = ['brand' => [], 'shelf' => [], 'row' => [], 'col' => []];
    $dbError = '';

    try {
        $pdo = db();
        $result = inventory_fetch($pdo, $mode, $state, true);
        $rows  = $result['rows'];
        $total = $result['total'];
        $state['page'] = $result['page'];
        $state['size'] = $result['size'];
        $facets = inventory_facets($pdo, $mode);
    } catch (Exception $e) {
        $dbError = $mode === 'bin' ? 'Could not load the bin from the database.' : 'Could not load inventory from the database.';
    }

    include __DIR__ . '/header.php';

    if ($dbError !== '') {
        echo '<div class="flash error">' . e($dbError) . '</div>';
    }

    if ($mode === 'bin') {
        echo '<section class="inventory-section" id="inventory">';
        $emptyMessage = 'The bin is empty. Deleted items will appear here.';
        include __DIR__ . '/inventory-table.php';
        echo '</section>';
        include __DIR__ . '/inventory-card.php';
        ?>
        <script>
        function deleteAllBinnedItems() {
            if (!confirm("Are you sure you want to permanently delete ALL items in the inventory bin? This operation cannot be undone.")) return;
            window.submitPostForm("<?php echo BASE_URL; ?>/actions/clear-inventory-bin.php", {});
        }
        </script>
        <?php
    } else {
        include __DIR__ . '/inventory-view.php';
    }

    include __DIR__ . '/footer.php';
}

