<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/icons.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function make_serial(int $id): string
{
    return 'PG-' . date('Y') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
}

function ean13(string $digits12): string
{
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$digits12[$i] * ($i % 2 === 0 ? 1 : 3);
    }
    $check = (10 - ($sum % 10)) % 10;
    return $digits12 . $check;
}

function ean13_valid(string $code): bool
{
    return preg_match('/^\d{13}$/', $code) === 1 && $code === ean13(substr($code, 0, 12));
}

function next_master_code(PDO $pdo): string
{
    // Auto-generates the next 4-digit product code (0001, 0002, ...) for
    // the master items table, protected by a concurrency lock to prevent collisions.
    $lockName = 'lock_master_product_code';
    $locked = false;

    try {
        $st = $pdo->prepare('SELECT GET_LOCK(?, 5)');
        $st->execute([$lockName]);
        $locked = ($st->fetchColumn() == 1);

        $max = (int)$pdo->query(
            "SELECT MAX(CAST(product_code AS UNSIGNED))
               FROM master_items
              WHERE product_code REGEXP '^[0-9]{1,4}$'"
        )->fetchColumn();
        return str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
    } finally {
        if ($locked) {
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }
    }
}

// Barcode format: YYMM + PPPP + NNN + check digit, rendered as EAN-13
// (leading number-system '0'). PPPP is the last 4 digits of the matching
// master item's product code; NNN is the per-product monthly sequence
// (resets each month).
function barcode_for_item(PDO $pdo, array $item): string
{
    $name  = (string)($item['name'] ?? '');
    $brand = (string)($item['brand'] ?? '');
    $ymm   = empty($item['created_at']) ? date('ym') : date('ym', strtotime($item['created_at']));

    // PPP: last 3 digits of the master item's product code. Active master
    // items are preferred; binned ones still match so existing labels stay
    // stable. Falls back to '000' when nothing matches or the code is not
    // 3 numeric characters.
    $st = $pdo->prepare(
        "SELECT product_code FROM master_items
          WHERE product_name = ? AND COALESCE(brand, '') = ?
          ORDER BY (deleted_at IS NULL) DESC, id ASC LIMIT 1"
    );
    $st->execute([$name, $brand]);
    $masterCode = $st->fetchColumn();
    $ppp = is_string($masterCode) ? substr($masterCode, -4) : '';
    if (preg_match('/^\d{4}$/', $ppp) !== 1) {
        $ppp = '0000';
    }

    // Idempotent: if this row already carries a new-format barcode for this
    // product+month, keep it (regenerating a label must not change the code).
    $existing = trim((string)($item['barcode'] ?? ''));
    if ($existing !== '' && preg_match('/^0' . $ymm . $ppp . '\d{4}$/', $existing) === 1) {
        return $existing;
    }

    $lockName = 'lock_barcode_' . md5($name . '|' . $brand . '|' . $ymm);
    $locked = false;

    try {
        $st = $pdo->prepare('SELECT GET_LOCK(?, 5)');
        $st->execute([$lockName]);
        $locked = ($st->fetchColumn() == 1);

        $st = $pdo->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTR(barcode, 10, 3) AS UNSIGNED)), 0)
               FROM inventory_list
              WHERE name = ? AND COALESCE(brand,'') = ?
                AND DATE_FORMAT(created_at, '%y%m') = ?
                AND barcode IS NOT NULL AND barcode <> ''"
        );
        $st->execute([$name, $brand, $ymm]);
        $nnnn = str_pad((string)((int)$st->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);

        return ean13('0' . $ymm . $ppp . $nnnn);
    } finally {
        if ($locked) {
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }
    }
}

function qr_for_item(string $serial, string $name, string $location): string
{
    $location = $location === '' ? 'N/A' : $location;
    return "SERIAL: $serial\nITEM: $name\nLOCATION: $location";
}

function find_item(PDO $pdo, $id): ?array
{
    // READ: fetch a single inventory record by its id.
    $st = $pdo->prepare('SELECT * FROM inventory_list WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function all_brands(): array
{
    // READ: every brand name for dropdowns. Falls back to the distinct brands
    // already in use on items so the master table doesn't have to be complete.
    try {
        $pdo = db();
        return $pdo->query('SELECT name FROM master_brands WHERE deleted_at IS NULL ORDER BY name ASC')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

function save_photo(string $dataUrl, string $filename): ?string
{
    // CREATE (filesystem): decode the JPEG data URL and write it to uploads/.
    // Returns the web-accessible path, or null on failure.
    if (!preg_match('#^data:image/jpeg;base64,(.*)$#is', $dataUrl, $m)) {
        return null;
    }

    $bytes = base64_decode($m[1], true);
    if ($bytes === false || strlen($bytes) === 0) {
        return null;
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0777, true)) {
        return null;
    }

    $name = preg_replace('/[^A-Za-z0-9_\-]/', '', $filename) . '.jpg';
    if (file_put_contents(UPLOAD_DIR . $name, $bytes) === false) {
        return null;
    }

    return UPLOAD_URL . $name;
}

// Redirect back to the inventory page with an error shown in place.
function fail(string $message): void
{
    header('Location: ' . BASE_URL . '/pages/index.php?error=' . urlencode($message));
    exit;
}

function delete_photo(?string $photo): void
{
    if ($photo && is_file(UPLOAD_DIR . basename($photo))) {
        @unlink(UPLOAD_DIR . basename($photo));
    }
}

// JSON responses for AJAX endpoints.
function json_ok(array $data = []): void
{
    header('Content-Type: application/json');
    echo json_encode(['ok' => true] + $data);
    exit;
}

function json_fail(string $message): void
{
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function json_db(): PDO
{
    try {
        return db();
    } catch (Exception $e) {
        json_fail('Database connection failed.');
    }
}

// HTML-redirect counterpart to json_db(): connect or bounce back to the given
// page with an error flash. Used by form-action scripts.
function html_db(string $page): PDO
{
    try {
        return db();
    } catch (Exception $e) {
        redirect_to($page, 'error', 'Database connection failed.');
    }
}

// Redirect to a page with a single query-string flash parameter.
function redirect_to(string $page, string $key, string $message): void
{
    header('Location: ' . BASE_URL . '/pages/' . $page . '.php?' . $key . '=' . urlencode($message));
    exit;
}

// Whether a table column is enabled in the $tableColumns list.
function col_on(array $cols, string $key): bool
{
    return in_array($key, $cols, true);
}

// Format a DB timestamp for display; empty when there is no value.
function fmt_datetime(?string $value): string
{
    return $value ? date('Y-m-d H:i', strtotime($value)) : '';
}

// Render a cell value, or a muted em-dash when it is empty.
function dash(?string $value): string
{
    return ($value !== null && $value !== '') ? e($value) : '<span class="dash">—</span>';
}

// CREATE: insert an inventory row and derive its serial/barcode/QR, returning
// the created row's id, serial, barcode, and qr. $fields may contain name,
// brand, quantity, location, photo, status, and modified_by. When $serial is
// empty it is auto-generated from the new id; likewise for $barcode.
function create_inventory_item(PDO $pdo, array $fields, ?string $serial = null, ?string $barcode = null): array
{
    $name     = (string)($fields['name'] ?? '');
    $brand    = $fields['brand'] ?? null;
    $quantity = max(0, (int)($fields['quantity'] ?? 1));
    $location = $fields['location'] ?? null;
    $photo    = (string)($fields['photo'] ?? '');
    $status   = (string)($fields['status'] ?? 'available');
    $modifiedBy = $fields['modified_by'] ?? null;

    $pdo->prepare(
        "INSERT INTO inventory_list (name, brand, quantity, location, photo, status, modified_by, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    )->execute([$name, $brand, $quantity, $location, $photo, $status, $modifiedBy]);

    $id = (int)$pdo->lastInsertId();

    if ($serial === null || $serial === '') {
        $serial = make_serial($id);
    }

    if ($barcode === null || $barcode === '') {
        $barcode = barcode_for_item($pdo, [
            'name'       => $name,
            'brand'      => $brand,
            'created_at' => null,
            'barcode'    => null,
        ]);
    }

    $qr = qr_for_item($serial, $name, (string)$location);

    $pdo->prepare('UPDATE inventory_list SET serial = ?, barcode = ?, qr = ? WHERE id = ?')
        ->execute([$serial, $barcode, $qr, $id]);

    return ['id' => $id, 'serial' => $serial, 'barcode' => $barcode, 'qr' => $qr];
}

// Shared stock-movement helpers used by the dashboard and history views.

function fetch_movements(PDO $pdo, ?int $limit = null): array
{
    $sql = "SELECT m.*, COALESCE(i.name, '') AS item_name
            FROM stock_movements m
            LEFT JOIN inventory_list i ON i.id = m.item_id
            ORDER BY m.created_at DESC, m.id DESC";

    if ($limit !== null && $limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }

    return $pdo->query($sql)->fetchAll();
}

// Active inventory rows, shared by the Stock In / Stock Out / Transfers pages.
function fetch_active_items(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id, name, brand, serial, quantity, location, barcode, qr, status, photo, created_at
           FROM inventory_list
          WHERE deleted_at IS NULL
          ORDER BY name ASC"
    )->fetchAll();
}

function movement_type_labels(): array
{
    return ['in' => 'INBOUND', 'out' => 'OUTBOUND', 'transfer' => 'TRANSFER'];
}

function movement_status_class(?string $type): string
{
    return $type === 'in' ? 'inbound' : ($type === 'out' ? 'outbound' : 'transfer');
}

function movement_qty(string $type, $quantity): string
{
    if ($type === 'in') {
        return '+' . (int)$quantity;
    }
    if ($type === 'out') {
        return '-' . (int)$quantity;
    }
    return '<span class="dash">—</span>';
}
