<?php
// Shared server-side data + row rendering for the inventory list pages
// (pages/index.php, pages/bin.php, pages/generator.php) and the AJAX
// endpoint (actions/inventory-page.php). Keeps the query building, the
// pagination math, and the <tr> markup in one place so the initial page
// render and every subsequent AJAX page render stay identical.
require_once __DIR__ . '/functions.php';

// The column set rendered for each list mode. Order matters.
function inventory_columns(string $mode): array
{
    switch ($mode) {
        case 'bin':
            return ['no', 'photo', 'item', 'brand', 'quantity', 'serial', 'bin_note', 'status', 'last_modified', 'modified_by', 'action'];
        case 'generator':
            return ['no', 'photo', 'item', 'quantity', 'serial', 'location', 'barcode', 'action'];
        default:
            return ['no', 'action', 'photo', 'item', 'brand', 'quantity', 'location', 'status', 'serial', 'barcode', 'registered', 'last_modified', 'modified_by'];
    }
}

// Which rows a mode shows: the bin lists soft-deleted rows; everything else
// lists active rows.
function inventory_scope(string $mode): string
{
    return $mode === 'bin' ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
}

// Normalize request input (from $_GET or $_POST) into a validated state array.
function inventory_state_from_input(array $in): array
{
    $size = (int)($in['size'] ?? 10);
    if (!in_array($size, [5, 10, 15, 20, 25, 50], true)) $size = 10;

    $sort = (string)($in['sort'] ?? 'newest');
    if (!in_array($sort, ['newest', 'oldest', 'name', 'nameDesc'], true)) $sort = 'newest';

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
    $where = [inventory_scope($mode)];
    $params = [];

    $q = (string)($state['q'] ?? '');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(name LIKE ? OR brand LIKE ? OR serial LIKE ? OR barcode LIKE ? OR location LIKE ? OR COALESCE(bin_note, "") LIKE ? OR COALESCE(modified_by, "") LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like, $like, $like);
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
                $parts[] = 'location LIKE ?';
                $params[] = $v . '-%';
            } elseif ($key === 'row') {
                $parts[] = 'location LIKE ?';
                $params[] = '%-' . $v . '-%';
            } else {
                $parts[] = 'location LIKE ?';
                $params[] = '%-' . $v;
            }
        }
        if (count($parts) > 0) $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $whereSql = implode(' AND ', $where);

    $orderMap = [
        'newest'   => 'id DESC',
        'oldest'   => 'id ASC',
        'name'     => 'name ASC',
        'nameDesc' => 'name DESC',
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

// The JSON shape handed to the browser for modals and export.
function inventory_item_payload(array $item): array
{
    return [
        'id'         => (int)$item['id'],
        'serial'     => $item['serial'],
        'name'       => $item['name'],
        'brand'      => $item['brand'],
        'quantity'   => (int)$item['quantity'],
        'location'   => $item['location'],
        'barcode'    => $item['barcode'],
        'qr'         => $item['qr'],
        'photo'      => $item['photo'] ? BASE_URL . '/' . $item['photo'] : '',
        'status'     => $item['status'],
        'created_at' => $item['created_at'] ? date('Y-m-d H:i', strtotime($item['created_at'])) : '',
    ];
}

// Action buttons for a row, honoring the current user's permissions.
function render_inventory_action_buttons(array $item, string $mode): string
{
    $id = (int)$item['id'];
    $html = '';

    if ($mode === 'bin') {
        if (user_can('inventory.manage')) {
            $html .= '<button class="btn icon-btn restore" title="Restore" onclick="runRestoreItem(' . $id . ')">' . icon_restore() . '</button>';
        }
        if (user_can('permanent_delete')) {
            $html .= '<button class="btn icon-btn delete" title="Permanently Delete" onclick="runPermanentDelete(' . $id . ')">' . icon_delete() . '</button>';
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

    // list (default)
    if (user_can('inventory.manage')) {
        $html .= '<button class="btn icon-btn edit" title="Edit" onclick="openEditModal(' . $id . ')">' . icon_edit() . '</button>';
    }
    $html .= '<button class="btn icon-btn view" title="View" onclick="openItemModal(' . $id . ')">' . icon_view() . '</button>';
    if (user_can('inventory.manage')) {
        $html .= '<button class="btn icon-btn delete" title="Delete" onclick="runDeleteItem(' . $id . ')">' . icon_delete() . '</button>';
    }

    return $html;
}

// Render the <tr> rows for a single page of items. $startIndex is the global
// position of the first row (for the "No." column).
function render_inventory_rows(array $rows, string $mode, int $startIndex = 0): string
{
    $cols = inventory_columns($mode);
    $selectable = ($mode === 'generator');
    $html = '';

    foreach ($rows as $i => $item) {
        $no = $startIndex + $i + 1;

        $html .= '<tr class="inventory-row" data-id="' . (int)$item['id'] . '" data-status="' . e($item['status']) . '" data-brand="' . e($item['brand']) . '" data-loc="' . e($item['location']) . '">';

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
            if ($item['location']) {
                $html .= '<td data-label="Location"><span class="location">' . e($item['location']) . '</span></td>';
            } else {
                $html .= '<td data-label="Location"><span class="dash">—</span></td>';
            }
        }

        if (in_array('status', $cols, true)) {
            $html .= '<td data-label="Status"><span class="status ' . e($item['status']) . '">' . ($item['status'] === 'available' ? 'ACTIVE' : 'INACTIVE') . '</span></td>';
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
