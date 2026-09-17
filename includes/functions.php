<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/auth.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function get_system_setting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $st = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([$key]);
        $val = $st->fetchColumn();
        return $val !== false ? (string)$val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function set_system_setting(PDO $pdo, string $key, string $value, ?string $updatedBy = null): void
{
    $st = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at) 
                        VALUES (?, ?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
    $st->execute([$key, $value, $updatedBy]);
}

/**
 * Record an action or security event in the system_logs table.
 * Designed to be fail-safe so logging failures never disrupt user operations.
 */
function log_activity(
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    array $details = [],
    string $status = 'success',
    ?string $overrideUsername = null,
    ?int $overrideUserId = null,
    ?string $overrideRole = null
): void {
    try {
        $pdo = db();
        $user = function_exists('current_user') ? current_user() : null;

        $userId   = $overrideUserId ?? ($user['id'] ?? null);
        $username = $overrideUsername ?? ($user['username'] ?? null);
        $role     = $overrideRole ?? ($user['role'] ?? null);

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if ($ip && str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ip = substr((string)$ip, 0, 45);

        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $st = $pdo->prepare("
            INSERT INTO system_logs 
                (user_id, username, role, action, entity_type, entity_id, details, ip_address, user_agent, status, created_at)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $st->execute([
            $userId,
            $username,
            $role,
            $action,
            $entityType,
            $entityId,
            !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
            $ip,
            $ua,
            $status
        ]);
    } catch (Throwable $e) {
        // Fail-safe: write to standard PHP error log if DB write fails
        error_log('[SystemLog Failure] ' . $e->getMessage());
    }
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
    if (!empty($item['master_item_id'])) {
        $st = $pdo->prepare("SELECT product_code FROM master_items WHERE id = ? LIMIT 1");
        $st->execute([(int)$item['master_item_id']]);
        $masterCode = $st->fetchColumn();
    } else {
        $st = $pdo->prepare(
            "SELECT product_code FROM master_items
            WHERE product_name = ? AND COALESCE(brand, '') = ?
            ORDER BY (deleted_at IS NULL) DESC, id ASC LIMIT 1"
        );
        $st->execute([$name, $brand]);
        $masterCode = $st->fetchColumn();
    }
    $ppp = is_string($masterCode) ? substr($masterCode, -4) : '';
    if (preg_match('/^\d{4}$/', $ppp) !== 1) {
        $ppp = '0000';
    }

    // Idempotent: if this row already carries a new-format barcode for this
    // product+month, keep it (regenerating a label must not change the code).
    $existing = trim((string)($item['barcode'] ?? ''));
    if ($existing !== '' && preg_match('/^0' . preg_quote($ymm . $ppp, '/') . '\d{4}$/', $existing) === 1) {
        return $existing;
    }

    // Allocate the next sequence number for this product and month under a
    // mutex lock so concurrent inserts cannot claim the same barcode.
    $lockName = 'inv_barcode_' . md5($name . '|' . $brand . '|' . $ymm);
    $st = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $st->execute([$lockName]);
    $locked = ($st->fetchColumn() == 1);

    try {
        if (!empty($item['master_item_id'])) {
            $st = $pdo->prepare(
                "SELECT COALESCE(MAX(CAST(SUBSTR(barcode, 10, 3) AS UNSIGNED)), 0)
                   FROM inventory_units
                  WHERE master_item_id = ?
                    AND DATE_FORMAT(created_at, '%y%m') = ?
                    AND barcode IS NOT NULL AND barcode <> ''"
            );
            $st->execute([(int)$item['master_item_id'], $ymm]);
        } else {
            $st = $pdo->prepare(
                "SELECT COALESCE(MAX(CAST(SUBSTR(i.barcode, 10, 3) AS UNSIGNED)), 0)
                FROM inventory_units i
                JOIN master_items mi ON mi.id = i.master_item_id
                WHERE mi.product_name = ? AND COALESCE(mi.brand,'') = ?
                    AND DATE_FORMAT(i.created_at, '%y%m') = ?
                    AND i.barcode IS NOT NULL AND i.barcode <> ''"
            );
            $st->execute([$name, $brand, $ymm]);
        }
        $nnnn = str_pad((string)((int)$st->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);

        return ean13('0' . $ymm . $ppp . $nnnn);
    } finally {
        if ($locked) {
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }
    }
}

function find_item(PDO $pdo, $id): ?array
{
    // READ: fetch an inventory record by unit or lot id, joining master_items.
    $st = $pdo->prepare('
        SELECT u.*, u.id, mi.product_name AS name, mi.brand AS brand, mi.product_code, mi.product_class,
               COALESCE(NULLIF(u.bin_location, ""), NULLIF(u.deployment_site, ""), u.location, "") AS resolved_location
        FROM inventory_units u 
        JOIN master_items mi ON mi.id = u.master_item_id 
        WHERE u.id = ?
    ');
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

function delete_photo(?string $photo): void
{
    if ($photo && is_file(UPLOAD_DIR . basename($photo))) {
        @unlink(UPLOAD_DIR . basename($photo));
    }
}

function save_avatar_upload(array $file, int $userId): ?string
{
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $avatarDir = UPLOAD_DIR . 'avatars/';
    if (!is_dir($avatarDir) && !mkdir($avatarDir, 0755, true)) {
        return null;
    }

    $tmpPath = $file['tmp_name'];
    $imgInfo = @getimagesize($tmpPath);
    if (!$imgInfo) {
        return null;
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($imgInfo['mime'], $allowedMimes, true)) {
        return null;
    }

    $data = @file_get_contents($tmpPath);
    if ($data === false) {
        return null;
    }

    $srcImg = @imagecreatefromstring($data);
    if (!$srcImg) {
        return null;
    }

    // Square crop / resize avatar to max 512x512
    $origW = imagesx($srcImg);
    $origH = imagesy($srcImg);
    $minDim = min($origW, $origH);
    $srcX = (int)(($origW - $minDim) / 2);
    $srcY = (int)(($origH - $minDim) / 2);

    $targetDim = min(512, $minDim);
    $dstImg = imagecreatetruecolor($targetDim, $targetDim);
    imagecopyresampled($dstImg, $srcImg, 0, 0, $srcX, $srcY, $targetDim, $targetDim, $minDim, $minDim);

    $filename = 'avatar_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $destPath = $avatarDir . $filename;

    imagejpeg($dstImg, $destPath, 85);
    imagedestroy($srcImg);
    imagedestroy($dstImg);

    return UPLOAD_URL . 'avatars/' . $filename;
}

function delete_avatar(?string $avatar): void
{
    if ($avatar) {
        $base = basename($avatar);
        $path = UPLOAD_DIR . 'avatars/' . $base;
        if (is_file($path)) {
            @unlink($path);
        }
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
    if (function_exists('log_activity')) {
        log_activity('api.failure', 'system_error', null, [
            'message' => $message,
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'post_keys' => array_keys($_POST)
        ], 'failure');
    }
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

function qr_for_unit(string $serial, string $barcode, string $name, string $brand, string $location = ''): string
{
    $brand    = $brand === '' ? 'N/A' : $brand;
    $barcode  = $barcode === '' ? 'N/A' : $barcode;
    return "SN: {$serial}\nBARCODE: {$barcode}\nITEM: {$name}\nBRAND: {$brand}";
}

function next_unit_serials(PDO $pdo, int $count = 1): array
{
    if ($count <= 0) return [];

    $lockName = 'lock_unit_serial';
    $locked = false;
    try {
        $st = $pdo->prepare('SELECT GET_LOCK(?, 10)');
        $st->execute([$lockName]);
        $locked = ($st->fetchColumn() == 1);

        $prefix = 'SN-' . date('Y') . '-';
        $prefixLen = strlen($prefix);

        $stMax = $pdo->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING(serial, ?) AS UNSIGNED)), 0)
              FROM inventory_units
             WHERE serial LIKE ?
               AND SUBSTRING(serial, ?) REGEXP '^[0-9]+$'
        ");
        $stMax->execute([$prefixLen + 1, $prefix . '%', $prefixLen + 1]);
        $max = (int)$stMax->fetchColumn();

        $serials = [];
        for ($i = 1; $i <= $count; $i++) {
            $serials[] = $prefix . str_pad((string)($max + $i), 5, '0', STR_PAD_LEFT);
        }
        return $serials;
    } finally {
        if ($locked) {
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }
    }
}

function next_unit_serial(PDO $pdo): string
{
    $list = next_unit_serials($pdo, 1);
    return $list[0] ?? ('SN-' . date('Y') . '-00001');
}

function provision_inventory_units(PDO $pdo, $inventoryId, int $count, ?string $modifiedBy = null, string $status = 'available'): array
{
    if ($count <= 0) return [];

    $st = $pdo->prepare("SELECT u.master_item_id, mi.product_name AS name, mi.brand AS brand, u.location, u.bin_location, u.deployment_site, u.barcode, u.photo 
                          FROM inventory_units u 
                          JOIN master_items mi ON mi.id = u.master_item_id 
                          WHERE u.id = ? OR u.barcode = ? LIMIT 1");
    $st->execute([$inventoryId, (string)$inventoryId]);
    $item = $st->fetch(PDO::FETCH_ASSOC);
    if (!$item) return [];

    $masterItemId   = (int)$item['master_item_id'];
    $name           = (string)$item['name'];
    $brand          = (string)($item['brand'] ?? '');
    $binLocation    = $item['bin_location'] ?? (preg_match('/^S\d+-R\d+-C\d+/', (string)($item['location'] ?? '')) ? $item['location'] : null);
    $deploymentSite = (string)($item['deployment_site'] ?? ($binLocation ? 'In Warehouse' : ($item['location'] ?: 'In Warehouse')));
    $location       = $binLocation ?: $deploymentSite;
    $barcode        = (string)($item['barcode'] ?? '');
    $photo          = (string)($item['photo'] ?? '');

    $serials = next_unit_serials($pdo, $count);
    $created = [];
    $insStmt = $pdo->prepare(
        "INSERT INTO inventory_units (master_item_id, serial, barcode, qr, photo, status, location, bin_location, deployment_site, modified_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );

    foreach ($serials as $unitSerial) {
        $qr = qr_for_unit($unitSerial, $barcode, $name, $brand);
        $insStmt->execute([$masterItemId, $unitSerial, $barcode, $qr, $photo, $status, $location, $binLocation, $deploymentSite, $modifiedBy]);

        $created[] = [
            'id'     => (int)$pdo->lastInsertId(),
            'serial' => $unitSerial,
            'qr'     => $qr,
        ];
    }

    return $created;
}

// CREATE: insert units directly into inventory_units as the sole source of truth
function create_inventory_item(PDO $pdo, array $fields, ?string $barcode = null): array
{
    $masterItemId = !empty($fields['master_item_id']) ? (int)$fields['master_item_id'] : null;
    $name         = trim((string)($fields['name'] ?? ''));
    $brand        = isset($fields['brand']) ? trim((string)$fields['brand']) : null;
    if ($brand === '') $brand = null;

    if (!$masterItemId) {
        if ($name === '') {
            throw new InvalidArgumentException('Product name or master_item_id is required.');
        }
        $st = $pdo->prepare('SELECT id, product_name, brand FROM master_items WHERE deleted_at IS NULL AND product_name = ? AND COALESCE(brand, "") = ? ORDER BY id ASC LIMIT 1');
        $st->execute([$name, (string)($brand ?? '')]);
        $mRow = $st->fetch();
        if (!$mRow) {
            $st = $pdo->prepare('SELECT id, product_name, brand FROM master_items WHERE deleted_at IS NULL AND product_name = ? ORDER BY id ASC LIMIT 1');
            $st->execute([$name]);
            $mRow = $st->fetch();
        }
        if (!$mRow) {
            $pCode = next_master_code($pdo);
            $modBy = $fields['modified_by'] ?? null;
            $insMaster = $pdo->prepare('INSERT INTO master_items (product_code, product_name, brand, product_class, updated_at, modified_by) VALUES (?, ?, ?, ?, NOW(), ?)');
            $insMaster->execute([$pCode, $name, $brand ?: 'Generic', 'Inventory Item', $modBy]);
            $masterItemId = (int)$pdo->lastInsertId();
            $brand = $brand ?: 'Generic';
        } else {
            $masterItemId = (int)$mRow['id'];
            $name = $mRow['product_name'];
            $brand = $mRow['brand'];
        }
    } else {
        $st = $pdo->prepare('SELECT product_name, brand FROM master_items WHERE id = ?');
        $st->execute([$masterItemId]);
        $mRow = $st->fetch();
        if ($mRow) {
            $name = $mRow['product_name'];
            $brand = $mRow['brand'];
        }
    }

    $quantity   = max(0, (int)($fields['quantity'] ?? 1));
    $rawLoc     = trim((string)($fields['location'] ?? ''));
    $photo      = (string)($fields['photo'] ?? '');
    $status     = (string)($fields['status'] ?? 'available');
    $modifiedBy = $fields['modified_by'] ?? null;

    $binLocation    = $fields['bin_location'] ?? (preg_match('/^S\d+-R\d+-C\d+/', $rawLoc) ? $rawLoc : null);
    $deploymentSite = $fields['deployment_site'] ?? ($binLocation !== null ? 'In Warehouse' : ($rawLoc !== '' ? $rawLoc : 'In Warehouse'));
    $location       = $rawLoc !== '' ? $rawLoc : ($binLocation ?: $deploymentSite);

    if ($barcode === null || $barcode === '') {
        $barcode = barcode_for_item($pdo, [
            'master_item_id' => $masterItemId,
            'name'           => $name,
            'brand'          => $brand,
            'created_at'     => null,
            'barcode'        => null,
        ]);
    }

    $customSerial = !empty($fields['serial']) ? trim((string)$fields['serial']) : null;
    $customSerials = !empty($fields['serials']) && is_array($fields['serials']) ? $fields['serials'] : ($customSerial !== null ? [$customSerial] : []);

    $createdUnits = [];
    $firstId = 0;
    $firstQr = '';

    $serials = [];
    for ($i = 0; $i < $quantity; $i++) {
        if (!empty($customSerials[$i])) {
            $serials[] = $customSerials[$i];
        }
    }
    $needed = $quantity - count($serials);
    if ($needed > 0) {
        $generated = next_unit_serials($pdo, $needed);
        $serials = array_merge($serials, $generated);
    }

    $insStmt = $pdo->prepare(
        "INSERT INTO inventory_units (master_item_id, serial, barcode, qr, photo, status, location, bin_location, deployment_site, modified_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );

    foreach ($serials as $unitSerial) {
        $qr = qr_for_unit($unitSerial, $barcode, $name, $brand);
        if ($firstQr === '') $firstQr = $qr;

        $insStmt->execute([$masterItemId, $unitSerial, $barcode, $qr, $photo, $status, $location, $binLocation, $deploymentSite, $modifiedBy]);

        $uid = (int)$pdo->lastInsertId();
        if ($firstId === 0) $firstId = $uid;

        $createdUnits[] = [
            'id'     => $uid,
            'serial' => $unitSerial,
            'qr'     => $qr,
        ];
    }

    return [
        'id'             => $firstId,
        'master_item_id' => $masterItemId,
        'barcode'        => $barcode,
        'qr'             => $firstQr,
        'units'          => $createdUnits,
    ];
}

// Shared stock-movement helpers used by the dashboard and history views.
function fetch_movements(PDO $pdo, ?int $limit = null): array
{
    $sql = "SELECT m.*, COALESCE(mi.product_name, '') AS item_name
            FROM stock_movements m
            LEFT JOIN inventory_units u ON u.id = m.item_id
            LEFT JOIN master_items mi ON mi.id = u.master_item_id
            ORDER BY m.created_at DESC, m.id DESC";

    if ($limit !== null && $limit > 0) {
        $st = $pdo->prepare($sql . " LIMIT ?");
        $st->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    return $pdo->query($sql)->fetchAll();
}

// Active inventory rows, shared by the Stock In / Stock Out / Transfers pages.
function fetch_active_items(PDO $pdo): array
{
    return $pdo->query(
        "SELECT MIN(u.id) AS id, u.master_item_id, mi.product_name AS name, mi.brand AS brand, '' AS serial, COUNT(u.id) AS quantity,
                COALESCE(NULLIF(MAX(u.bin_location), ''), NULLIF(MAX(u.location), ''), MAX(u.deployment_site), '') AS location,
                MAX(u.bin_location) AS bin_location, MAX(u.deployment_site) AS deployment_site, MAX(u.location) AS raw_location, u.barcode, '' AS qr, MAX(u.status) AS status, COALESCE(MAX(NULLIF(u.photo, '')), '') AS photo, MIN(u.created_at) AS created_at
         FROM inventory_units u
         JOIN master_items mi ON mi.id = u.master_item_id
         WHERE u.deleted_at IS NULL
           AND u.status = 'available'
           AND (u.deployment_site = 'In Warehouse' OR u.deployment_site = '' OR u.deployment_site IS NULL)
         GROUP BY COALESCE(NULLIF(u.barcode, ''), CONCAT('unit-', u.id)), u.master_item_id
         ORDER BY mi.product_name ASC"
    )->fetchAll();
}

function movement_type_labels(): array
{
    return [
        'in'       => 'INBOUND',
        'out'      => 'OUTBOUND',
        'transfer' => 'TRANSFER',
        'check_in' => 'CHECK IN',
        'return'   => 'RETURN',
    ];
}

function movement_status_class(?string $type): string
{
    return match ($type) {
        'in'       => 'inbound',
        'out'      => 'outbound',
        'check_in' => 'check-in',
        'return'   => 'return',
        default    => 'transfer',
    };
}

function movement_qty(string $type, $quantity): string
{
    $q = (int)$quantity;
    if ($type === 'in' || $type === 'check_in' || $type === 'return') {
        return '+' . $q;
    }
    if ($type === 'out') {
        return '-' . $q;
    }
    return (string)$q;
}

function handle_master_lifecycle(string $entity, string $action, int $id, ?string $redirectTarget = null): void
{
    require_login();
    require_can('master.manage');
    require_csrf($redirectTarget ?: ($entity === 'brand' ? 'brand' : 'items'));

    $pdo = html_db($redirectTarget ?: ($entity === 'brand' ? 'brand' : 'items'));

    if ($id <= 0) {
        redirect_to($redirectTarget ?: ($entity === 'brand' ? 'brand' : 'items'), 'error', 'Invalid item.');
    }

    if ($entity === 'brand') {
        $st = $pdo->prepare('SELECT name, deleted_at FROM master_brands WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            redirect_to($redirectTarget ?: 'master-bin', 'error', 'Brand not found.');
        }
        $name = $row['name'];

        if ($action === 'delete') {
            $pdo->beginTransaction();
            // 1. Bin active inventory units under items with this brand
            $pdo->prepare('UPDATE inventory_units u 
                           JOIN master_items mi ON mi.id = u.master_item_id 
                           SET u.deleted_at = NOW(), u.bin_note = CONCAT("Cascaded from brand bin: ", ?) 
                           WHERE mi.brand = ? AND u.deleted_at IS NULL')
                ->execute([$name, $name]);

            // 2. Bin active master items with this brand
            $pdo->prepare('UPDATE master_items SET deleted_at = NOW() WHERE brand = ? AND deleted_at IS NULL')
                ->execute([$name]);

            // 3. Bin brand itself
            $pdo->prepare('UPDATE master_brands SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
            $pdo->commit();

            log_activity('master_brand.bin', 'master_brand', $id, [
                'name' => $name,
                'cascaded' => true
            ], 'success');

            redirect_to($redirectTarget ?: 'brand', 'added', 'Brand "' . $name . '" and all associated items were moved to the bin.');
        } elseif ($action === 'permanent_delete') {
            if ($row['deleted_at'] === null) {
                redirect_to('master-bin', 'error', 'Only binned brands can be permanently deleted.');
            }
            $pdo->prepare('DELETE FROM master_brands WHERE id = ?')->execute([$id]);

            log_activity('master_brand.permanent_delete', 'master_brand', $id, [
                'name' => $name
            ], 'success');

            redirect_to($redirectTarget ?: 'master-bin', 'added', 'Brand "' . $name . '" permanently deleted.');
        } elseif ($action === 'restore') {
            $restoreUnits = (int)($_POST['restore_units'] ?? 0) === 1;
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE master_brands SET deleted_at = NULL WHERE id = ?')->execute([$id]);
            if ($restoreUnits) {
                // Restore master items for this brand
                $pdo->prepare('UPDATE master_items SET deleted_at = NULL WHERE brand = ?')->execute([$name]);
                // Restore inventory units for this brand
                $pdo->prepare('UPDATE inventory_units u 
                               JOIN master_items mi ON mi.id = u.master_item_id 
                               SET u.deleted_at = NULL, u.bin_note = NULL 
                               WHERE mi.brand = ?')->execute([$name]);
                $pdo->commit();

                log_activity('master_brand.restore', 'master_brand', $id, [
                    'name' => $name,
                    'restored_children' => true
                ]);

                redirect_to($redirectTarget ?: 'master-bin', 'added', 'Brand "' . $name . '" and its associated items were restored.');
            } else {
                $pdo->commit();

                log_activity('master_brand.restore', 'master_brand', $id, [
                    'name' => $name,
                    'restored_children' => false
                ]);

                redirect_to($redirectTarget ?: 'master-bin', 'added', 'Brand "' . $name . '" restored (items remained in bin).');
            }
        }
    } elseif ($entity === 'master-item') {
        $st = $pdo->prepare('SELECT product_name, brand, deleted_at FROM master_items WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) {
            redirect_to($redirectTarget ?: 'master-bin', 'error', 'Master item not found.');
        }
        $name  = $row['product_name'];
        $brand = (string)$row['brand'];

        if ($action === 'delete') {
            $pdo->beginTransaction();
            // 1. Bin active inventory units under this master item
            $pdo->prepare('UPDATE inventory_units 
                           SET deleted_at = NOW(), bin_note = CONCAT("Cascaded from item bin: ", ?) 
                           WHERE master_item_id = ? AND deleted_at IS NULL')
                ->execute([$name, $id]);

            // 2. Bin master item
            $pdo->prepare('UPDATE master_items SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
            $pdo->commit();

            log_activity('master_item.bin', 'master_item', $id, [
                'name' => $name,
                'brand' => $brand,
                'cascaded' => true
            ], 'success');

            redirect_to($redirectTarget ?: 'items', 'added', 'Master item "' . $name . '" and its inventory units were moved to the bin.');
        } elseif ($action === 'permanent_delete') {
            if ($row['deleted_at'] === null) {
                redirect_to('master-bin', 'error', 'Only binned items can be permanently deleted.');
            }
            $pdo->prepare('DELETE FROM master_items WHERE id = ?')->execute([$id]);

            log_activity('master_item.permanent_delete', 'master_item', $id, [
                'name' => $name,
                'brand' => $brand
            ], 'success');

            redirect_to($redirectTarget ?: 'master-bin', 'added', 'Master item "' . $name . '" permanently deleted.');
        } elseif ($action === 'restore') {
            $restoreUnits = (int)($_POST['restore_units'] ?? 0) === 1;
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE master_items SET deleted_at = NULL WHERE id = ?')->execute([$id]);

            // Backward cascade: restore parent brand if currently binned
            $brandName = trim((string)($row['brand'] ?? ''));
            if ($brandName !== '') {
                $pdo->prepare('UPDATE master_brands SET deleted_at = NULL WHERE name = ? AND deleted_at IS NOT NULL')
                    ->execute([$brandName]);
            }

            if ($restoreUnits) {
                $pdo->prepare('UPDATE inventory_units SET deleted_at = NULL, bin_note = NULL WHERE master_item_id = ?')
                    ->execute([$id]);
                $pdo->commit();

                log_activity('master_item.restore', 'master_item', $id, [
                    'name' => $name,
                    'brand' => $brand,
                    'restored_children' => true
                ]);

                redirect_to($redirectTarget ?: 'master-bin', 'added', 'Master item "' . $name . '" and its units were restored.');
            } else {
                $pdo->commit();

                log_activity('master_item.restore', 'master_item', $id, [
                    'name' => $name,
                    'brand' => $brand,
                    'restored_children' => false
                ]);

                redirect_to($redirectTarget ?: 'master-bin', 'added', 'Master item "' . $name . '" restored (units remained in bin).');
            }
        }
    }
}

/**
 * Return the count of active units belonging to a given lot / unit.
 */
function sync_lot_quantity(PDO $pdo, int $lotId, ?string $modifiedBy = null): int
{
    if ($lotId <= 0) return 0;

    $st = $pdo->prepare("
        SELECT COUNT(*) FROM inventory_units u1
        JOIN inventory_units u2 ON (u1.barcode = u2.barcode AND u1.barcode IS NOT NULL AND u1.barcode <> '') OR u1.id = u2.id
        WHERE u2.id = ?
          AND u1.deleted_at IS NULL
          AND u1.status = 'available'
          AND (u1.deployment_site = 'In Warehouse' OR u1.deployment_site = '' OR u1.deployment_site IS NULL)
    ");
    $st->execute([$lotId]);
    return (int)$st->fetchColumn();
}

function handle_inventory_lifecycle(string $action, int $id, string $note = '', string $type = ''): void
{
    require_login_api();
    require_csrf_api();
    $requiredPerm = ($action === 'permanent_delete') ? 'permanent_delete' : 'inventory.manage';
    require_can_api($requiredPerm);

    $pdo = json_db();
    $modifiedBy = current_user_name();

    $unitSt = $pdo->prepare("SELECT * FROM inventory_units WHERE id = ?");
    $unitSt->execute([$id]);
    $unit = $unitSt->fetch();

    if (!$unit) {
        json_fail('Item or unit not found.');
    }

    $isLot = ($type === 'lot');
    $barcode = trim((string)($unit['barcode'] ?? ''));

    if ($isLot && $barcode !== '') {
        // Perform lifecycle on the whole lot sharing this barcode
        if ($action === 'delete') {
            $pdo->prepare("UPDATE inventory_units SET deleted_at = NOW(), status = 'inactive', bin_note = ?, updated_at = NOW(), modified_by = ? WHERE barcode = ? AND deleted_at IS NULL")
                ->execute([$note ?: null, $modifiedBy, $barcode]);
            log_activity('inventory.lot_bin', 'inventory_lot', $id, [
                'barcode' => $barcode,
                'note' => $note
            ], 'success');
            json_ok();
        } elseif ($action === 'permanent_delete') {
            $pdo->prepare("DELETE FROM inventory_units WHERE barcode = ? AND deleted_at IS NOT NULL")
                ->execute([$barcode]);
            if ((int)$pdo->query("SELECT COUNT(*) FROM inventory_units")->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE inventory_units AUTO_INCREMENT = 1");
            }
            log_activity('inventory.lot_permanent_delete', 'inventory_lot', $id, [
                'barcode' => $barcode
            ], 'success');
            json_ok();
        } elseif ($action === 'restore') {
            $pdo->prepare("UPDATE inventory_units SET deleted_at = NULL, bin_note = NULL, status = 'available', updated_at = NOW() WHERE barcode = ?")
                ->execute([$barcode]);

            // Backward cascade: restore parent master item & brand if binned
            if (!empty($unit['master_item_id'])) {
                $stMi = $pdo->prepare("SELECT id, brand, deleted_at FROM master_items WHERE id = ?");
                $stMi->execute([$unit['master_item_id']]);
                $mi = $stMi->fetch();
                if ($mi) {
                    if ($mi['deleted_at'] !== null) {
                        $pdo->prepare("UPDATE master_items SET deleted_at = NULL WHERE id = ?")->execute([$mi['id']]);
                    }
                    $bName = trim((string)($mi['brand'] ?? ''));
                    if ($bName !== '') {
                        $pdo->prepare("UPDATE master_brands SET deleted_at = NULL WHERE name = ? AND deleted_at IS NOT NULL")->execute([$bName]);
                    }
                }
            }

            log_activity('inventory.lot_restore', 'inventory_lot', $id, [
                'barcode' => $barcode
            ]);
            json_ok();
        }
    } else {
        // Perform lifecycle on individual unit
        if ($action === 'delete') {
            $pdo->prepare("UPDATE inventory_units SET deleted_at = NOW(), status = 'inactive', bin_note = ?, updated_at = NOW(), modified_by = ? WHERE id = ?")
                ->execute([$note ?: null, $modifiedBy, $id]);
            log_activity('inventory.unit_bin', 'inventory_unit', $id, [
                'barcode' => $unit['barcode'] ?? null,
                'serial' => $unit['serial'] ?? null,
                'note' => $note
            ], 'success');
            json_ok();
        } elseif ($action === 'permanent_delete') {
            if ($unit['deleted_at'] === null) {
                json_fail('Only bin items can be permanently deleted.');
            }
            if (!empty($unit['photo'])) {
                delete_photo($unit['photo']);
            }
            $pdo->prepare("DELETE FROM inventory_units WHERE id = ?")->execute([$id]);
            if ((int)$pdo->query("SELECT COUNT(*) FROM inventory_units")->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE inventory_units AUTO_INCREMENT = 1");
            }
            log_activity('inventory.unit_permanent_delete', 'inventory_unit', $id, [
                'barcode' => $unit['barcode'] ?? null,
                'serial' => $unit['serial'] ?? null
            ], 'success');
            json_ok();
        } elseif ($action === 'restore') {
            $pdo->prepare("UPDATE inventory_units SET deleted_at = NULL, bin_note = NULL, status = 'available', updated_at = NOW() WHERE id = ?")
                ->execute([$id]);

            // Backward cascade: restore parent master item & brand if binned
            if (!empty($unit['master_item_id'])) {
                $stMi = $pdo->prepare("SELECT id, brand, deleted_at FROM master_items WHERE id = ?");
                $stMi->execute([$unit['master_item_id']]);
                $mi = $stMi->fetch();
                if ($mi) {
                    if ($mi['deleted_at'] !== null) {
                        $pdo->prepare("UPDATE master_items SET deleted_at = NULL WHERE id = ?")->execute([$mi['id']]);
                    }
                    $bName = trim((string)($mi['brand'] ?? ''));
                    if ($bName !== '') {
                        $pdo->prepare("UPDATE master_brands SET deleted_at = NULL WHERE name = ? AND deleted_at IS NOT NULL")->execute([$bName]);
                    }
                }
            }

            log_activity('inventory.unit_restore', 'inventory_unit', $id, [
                'barcode' => $unit['barcode'] ?? null,
                'serial_number' => $unit['serial_number'] ?? null
            ]);
            json_ok();
        }
    }

    json_fail('Invalid action.');
}

/**
 * Classify a unit or item location into one of three standardized domain categories:
 * 'warehouse' | 'transit' | 'customer'
 *
 * Single source of truth for location taxonomy across the system.
 */
function classify_unit_location(string $deploymentSite = '', string $location = '', string $status = '', string $binLocation = ''): string
{
    $dep = trim($deploymentSite);
    $loc = trim($location);
    $bin = trim($binLocation);
    $st  = strtolower(trim($status));

    if (str_starts_with($dep, 'In Transit') || str_starts_with($loc, 'In Transit') || $st === 'in_transit') {
        return 'transit';
    }

    if ($dep === 'Main Office' || $loc === 'Main Office') {
        return 'office';
    }

    if ($dep === 'In Warehouse' || $bin !== '' || preg_match('/^S\d+-R\d+-C\d+/', $loc) || $loc === 'In Warehouse') {
        return 'warehouse';
    }

    return 'customer';
}

/**
 * Classify directly from a unit/item database record array.
 */
function classify_record_location(array $record): string
{
    return classify_unit_location(
        (string)($record['deployment_site'] ?? ''),
        (string)($record['location'] ?? ''),
        (string)($record['status'] ?? ''),
        (string)($record['bin_location'] ?? '')
    );
}

/**
 * Atomically updates the physical and logical custody of unit(s).
 * Ensures bin_location, deployment_site, location, and status are consistently synchronized.
 *
 * @param PDO $pdo Database connection
 * @param int|array $unitIds Single unit ID or array of unit IDs
 * @param string $custodyType 'warehouse' | 'transit' | 'customer' | 'office'
 * @param array $params Additional parameters ('bin_location', 'custom_location', 'destination', 'company', 'site')
 * @param string|null $modifier Name/username of modifier
 * @return bool
 */
function set_unit_custody(PDO $pdo, int|array $unitIds, string $custodyType, array $params = [], ?string $modifier = null): bool
{
    $ids = is_array($unitIds) ? array_filter(array_map('intval', $unitIds)) : [(int)$unitIds];
    if (empty($ids)) return false;

    $binLocation    = null;
    $deploymentSite = 'In Warehouse';
    $location       = 'In Warehouse';
    $status         = 'available';

    switch ($custodyType) {
        case 'warehouse':
            $rawBin = trim((string)($params['bin_location'] ?? ''));
            $customLoc = trim((string)($params['custom_location'] ?? ''));
            if ($rawBin !== '' && preg_match('/^S\d+-R\d+-C\d+/i', $rawBin)) {
                $binLocation    = strtoupper($rawBin);
                $deploymentSite = 'In Warehouse';
                $location       = $binLocation;
            } elseif ($customLoc !== '') {
                $binLocation    = null;
                $deploymentSite = 'In Warehouse';
                $location       = $customLoc;
            } elseif ($rawBin !== '') {
                $binLocation    = null;
                $deploymentSite = 'In Warehouse';
                $location       = $rawBin;
            } else {
                $binLocation    = null;
                $deploymentSite = 'In Warehouse';
                $location       = 'In Warehouse';
            }
            break;

        case 'transit':
            $destination    = trim((string)($params['destination'] ?? $params['issued_to'] ?? ''));
            $transitLabel   = $destination !== '' ? "In Transit ($destination)" : 'In Transit';
            $binLocation    = null;
            $deploymentSite = $transitLabel;
            $location       = $transitLabel;
            break;

        case 'customer':
            $company        = trim((string)($params['company'] ?? ''));
            $site           = trim((string)($params['site'] ?? ''));
            $siteLabel      = ($company !== '' && $site !== '') ? "$company - $site" : ($company ?: $site ?: 'Customer Site');
            $binLocation    = null;
            $deploymentSite = $siteLabel;
            $location       = $siteLabel;
            break;

        case 'office':
            $binLocation    = null;
            $deploymentSite = 'Main Office';
            $location       = 'Main Office';
            break;

        default:
            return false;
    }

    $modBy = $modifier ?? (function_exists('current_user_name') ? current_user_name() : 'System');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "UPDATE inventory_units 
            SET status = ?, location = ?, bin_location = ?, deployment_site = ?, modified_by = ?, updated_at = NOW() 
            WHERE id IN ($placeholders)";

    $st = $pdo->prepare($sql);
    return $st->execute(array_merge([$status, $location, $binLocation, $deploymentSite, $modBy], $ids));
}

/**
 * Garbage collector: Purges unfinalized staged units that have been abandoned
 * beyond the threshold duration (default 6 hours).
 */
function purge_stale_staged_units(PDO $pdo, int $maxAgeHours = 6): int
{
    try {
        $st = $pdo->prepare("
            DELETE FROM inventory_units 
            WHERE status = 'staged' 
              AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $st->execute([$maxAgeHours]);
        $count = $st->rowCount();
        if ($count > 0) {
            log_activity('system.garbage_collect', 'inventory_units', null, [
                'purged_staged_units' => $count,
                'max_age_hours' => $maxAgeHours
            ]);
        }
        return $count;
    } catch (Throwable $e) {
        error_log('[Staged Purge Error] ' . $e->getMessage());
        return 0;
    }
}

