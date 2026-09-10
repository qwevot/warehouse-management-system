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

function qr_for_unit(string $serial, string $barcode, string $name, string $brand, string $location): string
{
    $location = $location === '' ? 'N/A' : $location;
    $brand    = $brand === '' ? 'N/A' : $brand;
    $barcode  = $barcode === '' ? 'N/A' : $barcode;
    return "SN: {$serial}\nBARCODE: {$barcode}\nITEM: {$name}\nBRAND: {$brand}\nLOC: {$location}";
}

function next_unit_serial(PDO $pdo): string
{
    $lockName = 'lock_unit_serial';
    $locked = false;
    try {
        $st = $pdo->prepare('SELECT GET_LOCK(?, 5)');
        $st->execute([$lockName]);
        $locked = ($st->fetchColumn() == 1);

        $max = (int)$pdo->query(
            "SELECT MAX(CAST(SUBSTR(serial, 9) AS UNSIGNED))
               FROM inventory_units
              WHERE serial LIKE 'SN-" . date('Y') . "-%'"
        )->fetchColumn();

        return 'SN-' . date('Y') . '-' . str_pad((string)($max + 1), 5, '0', STR_PAD_LEFT);
    } finally {
        if ($locked) {
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }
    }
}

function provision_inventory_units(PDO $pdo, int $inventoryId, int $count, ?string $modifiedBy = null): array
{
    if ($count <= 0) return [];

    $st = $pdo->prepare("SELECT name, brand, location, bin_location, deployment_site, barcode FROM inventory_list WHERE id = ?");
    $st->execute([$inventoryId]);
    $item = $st->fetch(PDO::FETCH_ASSOC);
    if (!$item) return [];

    $name           = (string)$item['name'];
    $brand          = (string)($item['brand'] ?? '');
    $binLocation    = $item['bin_location'] ?? (preg_match('/^S\d+-R\d+-C\d+/', (string)($item['location'] ?? '')) ? $item['location'] : null);
    $deploymentSite = (string)($item['deployment_site'] ?? ($binLocation ? 'In Warehouse' : ($item['location'] ?: 'In Warehouse')));
    $location       = $binLocation ?: $deploymentSite;
    $barcode        = (string)($item['barcode'] ?? '');

    $created = [];
    for ($i = 0; $i < $count; $i++) {
        $unitSerial = next_unit_serial($pdo);
        $qr = qr_for_unit($unitSerial, $barcode, $name, $brand, $location);

        $pdo->prepare(
            "INSERT INTO inventory_units (inventory_id, serial, qr, status, location, bin_location, deployment_site, modified_by, created_at, updated_at)
             VALUES (?, ?, ?, 'available', ?, ?, ?, ?, NOW(), NOW())"
        )->execute([$inventoryId, $unitSerial, $qr, $location, $binLocation, $deploymentSite, $modifiedBy]);

        $created[] = [
            'id'     => (int)$pdo->lastInsertId(),
            'serial' => $unitSerial,
            'qr'     => $qr,
        ];
    }

    return $created;
}

// CREATE: insert an inventory row and derive its barcode and unit records
function create_inventory_item(PDO $pdo, array $fields, ?string $barcode = null): array
{
    $name       = (string)($fields['name'] ?? '');
    $brand      = $fields['brand'] ?? null;
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
            'name'       => $name,
            'brand'      => $brand,
            'created_at' => null,
            'barcode'    => null,
        ]);
    }

    $qr = "BARCODE: {$barcode}\nITEM: {$name}\nBRAND: " . ($brand ?: 'N/A') . "\nLOC: " . ($location ?: 'N/A');

    $pdo->prepare(
        "INSERT INTO inventory_list (name, brand, quantity, location, bin_location, deployment_site, barcode, qr, photo, status, modified_by, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    )->execute([$name, $brand, $quantity, $location, $binLocation, $deploymentSite, $barcode, $qr, $photo, $status, $modifiedBy]);

    $id = (int)$pdo->lastInsertId();

    // Provision corresponding individual unit records
    $units = provision_inventory_units($pdo, $id, $quantity, $modifiedBy);

    return [
        'id'      => $id,
        'barcode' => $barcode,
        'qr'      => $qr,
        'units'   => $units,
    ];
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
        "SELECT id, name, brand, serial, quantity, location, bin_location, deployment_site, barcode, qr, status, photo, created_at
           FROM inventory_list
          WHERE deleted_at IS NULL
            AND status = 'available'
            AND deployment_site = 'In Warehouse'
          ORDER BY name ASC"
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
    if ($type === 'in' || $type === 'return') {
        return '+' . (int)$quantity;
    }
    if ($type === 'out') {
        return '-' . (int)$quantity;
    }
    if ($type === 'check_in') {
        return (string)(int)$quantity;
    }
    return '<span class="dash">—</span>';
}

function handle_master_lifecycle(string $entity, string $action, int $id, ?string $redirectTarget = null): void
{
    require_login();
    $requiredPerm = ($action === 'permanent_delete') ? 'permanent_delete' : 'master.manage';
    require_can($requiredPerm);

    $defaultRedirect = $entity === 'brand' ? 'brand' : 'items';
    $pdo = html_db($entity === 'brand' ? 'brand' : 'items');

    if ($id <= 0) {
        redirect_to($redirectTarget ?: ($action === 'permanent_delete' ? 'master-bin' : $defaultRedirect), 'error', 'Invalid ' . ($entity === 'brand' ? 'brand.' : 'item.'));
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
            $used = $pdo->prepare('SELECT COUNT(*) FROM inventory_list WHERE brand = ? AND deleted_at IS NULL');
            $used->execute([$name]);
            if ((int)$used->fetchColumn() > 0) {
                redirect_to('brand', 'error', 'Cannot delete "' . $name . '" — it is still used by inventory items.');
            }
            $pdo->prepare('UPDATE master_brands SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
            redirect_to($redirectTarget ?: 'brand', 'added', 'Brand "' . $name . '" moved to the master bin.');
        } elseif ($action === 'permanent_delete') {
            if ($row['deleted_at'] === null) {
                redirect_to('master-bin', 'error', 'Only binned brands can be permanently deleted.');
            }
            $pdo->prepare('DELETE FROM master_brands WHERE id = ?')->execute([$id]);
            redirect_to($redirectTarget ?: 'master-bin', 'added', 'Brand "' . $name . '" permanently deleted.');
        } elseif ($action === 'restore') {
            $pdo->prepare('UPDATE master_brands SET deleted_at = NULL WHERE id = ?')->execute([$id]);
            redirect_to($redirectTarget ?: 'master-bin', 'added', 'Brand "' . $name . '" restored.');
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
            $used = $pdo->prepare('SELECT COUNT(*) FROM inventory_list WHERE name = ? AND COALESCE(brand, "") = ? AND deleted_at IS NULL');
            $used->execute([$name, $brand]);
            if ((int)$used->fetchColumn() > 0) {
                redirect_to('items', 'error', 'Cannot delete "' . $name . '" — it is still used by inventory items.');
            }
            $pdo->prepare('UPDATE master_items SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
            redirect_to($redirectTarget ?: 'items', 'added', 'Master item "' . $name . '" moved to the master bin.');
        } elseif ($action === 'permanent_delete') {
            if ($row['deleted_at'] === null) {
                redirect_to('master-bin', 'error', 'Only binned items can be permanently deleted.');
            }
            $pdo->prepare('DELETE FROM master_items WHERE id = ?')->execute([$id]);
            redirect_to($redirectTarget ?: 'master-bin', 'added', 'Master item "' . $name . '" permanently deleted.');
        } elseif ($action === 'restore') {
            $pdo->prepare('UPDATE master_items SET deleted_at = NULL WHERE id = ?')->execute([$id]);
            redirect_to($redirectTarget ?: 'master-bin', 'added', 'Master item "' . $name . '" restored.');
        }
    }
}

function handle_inventory_lifecycle(string $action, int $id, string $note = '', string $type = ''): void
{
    require_login_api();
    $requiredPerm = ($action === 'permanent_delete') ? 'permanent_delete' : 'inventory.manage';
    require_can_api($requiredPerm);

    $pdo = json_db();
    $modifiedBy = current_user_name();

    // 1. If explicitly requested as 'unit' or if not a lot:
    $isUnit = ($type === 'unit');
    $isLot  = ($type === 'lot');

    // If type is not explicitly forced to 'lot', check if it's a unit (only if $isUnit is true or $isLot is false)
    if ($isUnit) {
        $unitSt = ($action === 'restore')
            ? $pdo->prepare("SELECT u.*, i.deleted_at AS parent_deleted_at FROM inventory_units u JOIN inventory_list i ON i.id = u.inventory_id WHERE u.id = ?")
            : $pdo->prepare("SELECT * FROM inventory_units WHERE id = ?");
        $unitSt->execute([$id]);
        $unit = $unitSt->fetch();

        if ($unit) {
            if ($action === 'delete') {
                $pdo->prepare("UPDATE inventory_units SET deleted_at = NOW(), status = 'inactive', bin_note = ?, updated_at = NOW(), modified_by = ? WHERE id = ?")
                    ->execute([$note ?: null, $modifiedBy, $id]);
                $pdo->prepare("UPDATE inventory_list SET quantity = (SELECT COUNT(*) FROM inventory_units WHERE inventory_id = ? AND deleted_at IS NULL), updated_at = NOW(), modified_by = ? WHERE id = ?")
                    ->execute([$unit['inventory_id'], $modifiedBy, $unit['inventory_id']]);
                json_ok();
            } elseif ($action === 'permanent_delete') {
                if ($unit['deleted_at'] === null) {
                    json_fail('Only bin units can be permanently deleted.');
                }
                $inventoryId = (int)$unit['inventory_id'];
                $pdo->prepare("DELETE FROM inventory_units WHERE id = ?")->execute([$id]);
                $remSt = $pdo->prepare("SELECT COUNT(*) FROM inventory_units WHERE inventory_id = ?");
                $remSt->execute([$inventoryId]);
                $remaining = (int)$remSt->fetchColumn();

                if ($remaining === 0) {
                    $parent = find_item($pdo, $inventoryId);
                    if ($parent) {
                        delete_photo($parent['photo']);
                        $pdo->prepare("DELETE FROM inventory_list WHERE id = ?")->execute([$inventoryId]);
                    }
                } else {
                    $pdo->prepare("UPDATE inventory_list SET quantity = (SELECT COUNT(*) FROM inventory_units WHERE inventory_id = ? AND deleted_at IS NULL) WHERE id = ?")
                        ->execute([$inventoryId, $inventoryId]);
                }
                json_ok();
            } elseif ($action === 'restore') {
                if ($unit['parent_deleted_at'] !== null) {
                    $pdo->prepare("UPDATE inventory_list SET deleted_at = NULL, status = 'available', bin_note = NULL, updated_at = NOW() WHERE id = ?")
                        ->execute([$unit['inventory_id']]);
                }
                $pdo->prepare("UPDATE inventory_units SET deleted_at = NULL, bin_note = NULL, status = 'available', updated_at = NOW() WHERE id = ?")
                    ->execute([$id]);
                $pdo->prepare("UPDATE inventory_list SET quantity = (SELECT COUNT(*) FROM inventory_units WHERE inventory_id = ? AND deleted_at IS NULL) WHERE id = ?")
                    ->execute([$unit['inventory_id'], $unit['inventory_id']]);
                json_ok();
            }
        }
    }

    // 2. Lot in inventory_list (Warehouse Lots / Split & Merge)
    $item = find_item($pdo, $id);
    if ($item === null && !$isLot) {
        // If not found in inventory_list and not explicitly lot, fallback to check inventory_units
        $unitSt = $pdo->prepare("SELECT * FROM inventory_units WHERE id = ?");
        $unitSt->execute([$id]);
        $unit = $unitSt->fetch();
        if ($unit) {
            if ($action === 'delete') {
                $pdo->prepare("UPDATE inventory_units SET deleted_at = NOW(), status = 'inactive', bin_note = ?, updated_at = NOW(), modified_by = ? WHERE id = ?")
                    ->execute([$note ?: null, $modifiedBy, $id]);
                $pdo->prepare("UPDATE inventory_list SET quantity = (SELECT COUNT(*) FROM inventory_units WHERE inventory_id = ? AND deleted_at IS NULL), updated_at = NOW(), modified_by = ? WHERE id = ?")
                    ->execute([$unit['inventory_id'], $modifiedBy, $unit['inventory_id']]);
                json_ok();
            } elseif ($action === 'permanent_delete') {
                $inventoryId = (int)$unit['inventory_id'];
                $pdo->prepare("DELETE FROM inventory_units WHERE id = ?")->execute([$id]);
                $pdo->prepare("UPDATE inventory_list SET quantity = (SELECT COUNT(*) FROM inventory_units WHERE inventory_id = ? AND deleted_at IS NULL) WHERE id = ?")
                    ->execute([$inventoryId, $inventoryId]);
                json_ok();
            } elseif ($action === 'restore') {
                $pdo->prepare("UPDATE inventory_units SET deleted_at = NULL, bin_note = NULL, status = 'available', updated_at = NOW() WHERE id = ?")
                    ->execute([$id]);
                $pdo->prepare("UPDATE inventory_list SET quantity = (SELECT COUNT(*) FROM inventory_units WHERE inventory_id = ? AND deleted_at IS NULL) WHERE id = ?")
                    ->execute([$unit['inventory_id'], $unit['inventory_id']]);
                json_ok();
            }
        }
        json_fail('Item or unit not found.');
    }

    if ($item !== null) {
        if ($action === 'delete') {
            // Bin the whole lot
            $pdo->prepare("UPDATE inventory_list SET deleted_at = NOW(), status = 'inactive', bin_note = ?, updated_at = NOW(), modified_by = ? WHERE id = ?")
                ->execute([$note ?: null, $modifiedBy, $item['id']]);
            // Bin EVERY single unit belonging to this lot
            $pdo->prepare("UPDATE inventory_units SET deleted_at = NOW(), status = 'inactive', bin_note = ?, updated_at = NOW(), modified_by = ? WHERE inventory_id = ? AND deleted_at IS NULL")
                ->execute([$note ?: null, $modifiedBy, $item['id']]);
            json_ok();
        } elseif ($action === 'permanent_delete') {
            if ($item['deleted_at'] === null) {
                json_fail('Only bin items can be permanently deleted.');
            }
            delete_photo($item['photo']);
            $pdo->prepare('DELETE FROM inventory_units WHERE inventory_id = ?')->execute([$item['id']]);
            $pdo->prepare('DELETE FROM inventory_list WHERE id = ?')->execute([$item['id']]);
            json_ok();
        } elseif ($action === 'restore') {
            $pdo->prepare("UPDATE inventory_list SET deleted_at = NULL, status = 'available', bin_note = NULL, updated_at = NOW() WHERE id = ?")->execute([$item['id']]);
            $pdo->prepare("UPDATE inventory_units SET deleted_at = NULL, bin_note = NULL, status = 'available', updated_at = NOW() WHERE inventory_id = ?")->execute([$item['id']]);
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
 * Return user-facing display label for a location category.
 */
function location_category_label(string $category): string
{
    return match ($category) {
        'transit'   => 'In Transit',
        'customer'  => 'Customer Site',
        default     => 'In Warehouse',
    };
}

