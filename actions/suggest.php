<?php
/**
 * AJAX Autocomplete & Dynamic Suggestion Controller
 * Replaces monolithic in-memory DOM injections with debounced, on-demand queries.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login_api();

header('Content-Type: application/json; charset=utf-8');

$pdo = json_db();
$type = trim((string)($_GET['type'] ?? $_POST['type'] ?? ''));
$q = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
$limit = min(50, max(1, (int)($_GET['limit'] ?? $_POST['limit'] ?? 20)));

try {
    switch ($type) {
        case 'master':
        case 'master_items':
            $params = [];
            $sql = "SELECT id, product_code, product_name, brand, product_class 
                    FROM master_items 
                    WHERE deleted_at IS NULL";
            if ($q !== '') {
                $sql .= " AND (product_name LIKE ? OR brand LIKE ? OR product_code LIKE ?)";
                $term = '%' . $q . '%';
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }
            $sql .= " ORDER BY product_name ASC LIMIT " . $limit;
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $items = $st->fetchAll(PDO::FETCH_ASSOC);
            json_ok(['items' => $items]);
            break;

        case 'brands':
            $params = [];
            $sql = "SELECT name, description FROM master_brands WHERE deleted_at IS NULL";
            if ($q !== '') {
                $sql .= " AND name LIKE ?";
                $params[] = '%' . $q . '%';
            }
            $sql .= " ORDER BY name ASC LIMIT " . $limit;
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $items = $st->fetchAll(PDO::FETCH_ASSOC);
            json_ok(['items' => $items]);
            break;

        case 'stock_items':
            $params = [];
            $sql = "SELECT MIN(u.id) AS id, u.master_item_id, mi.product_name AS name, mi.brand AS brand, 
                           '' AS serial, COUNT(u.id) AS quantity,
                           COALESCE(NULLIF(MAX(u.bin_location), ''), NULLIF(MAX(u.location), ''), MAX(u.deployment_site), '') AS location,
                           MAX(u.bin_location) AS bin_location, MAX(u.deployment_site) AS deployment_site, MAX(u.location) AS raw_location,
                           u.barcode, '' AS qr, MAX(u.status) AS status, COALESCE(MAX(NULLIF(u.photo, '')), '') AS photo
                    FROM inventory_units u
                    JOIN master_items mi ON mi.id = u.master_item_id
                    WHERE u.deleted_at IS NULL
                      AND u.status = 'available'
                      AND (u.deployment_site = 'In Warehouse' OR u.deployment_site = '' OR u.deployment_site IS NULL)";
            if ($q !== '') {
                $sql .= " AND (mi.product_name LIKE ? OR mi.brand LIKE ? OR u.barcode LIKE ?)";
                $term = '%' . $q . '%';
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }
            $sql .= " GROUP BY COALESCE(NULLIF(u.barcode, ''), CONCAT('unit-', u.id)), u.master_item_id
                      ORDER BY mi.product_name ASC LIMIT " . $limit;
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $items = $st->fetchAll(PDO::FETCH_ASSOC);
            json_ok(['items' => $items]);
            break;

        case 'available_units':
            $params = [];
            $sql = "SELECT u.id AS unit_id, u.id AS inventory_id, u.serial, u.status, u.bin_location, u.deployment_site, 
                           u.location AS raw_location,
                           COALESCE(NULLIF(u.bin_location, ''), NULLIF(u.location, ''), u.deployment_site, '') AS location,
                           mi.product_name AS name, mi.brand AS brand, u.barcode, COALESCE(u.photo, '') AS photo
                    FROM inventory_units u
                    JOIN master_items mi ON mi.id = u.master_item_id
                    WHERE u.deleted_at IS NULL 
                      AND u.status = 'available'
                      AND (u.deployment_site = 'In Warehouse' OR u.deployment_site = '' OR u.deployment_site IS NULL)";
            if ($q !== '') {
                $sql .= " AND (u.serial LIKE ? OR mi.product_name LIKE ? OR mi.brand LIKE ? OR u.barcode LIKE ?)";
                $term = '%' . $q . '%';
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }
            $sql .= " ORDER BY u.serial ASC LIMIT " . $limit;
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $items = $st->fetchAll(PDO::FETCH_ASSOC);
            json_ok(['items' => $items]);
            break;

        case 'unified_outbound':
            $term = '%' . $q . '%';
            // 1. Units matching serial/name/barcode
            $stUnits = $pdo->prepare("
                SELECT u.id AS unit_id, u.id AS inventory_id, u.serial, u.status, u.bin_location, u.deployment_site,
                       COALESCE(NULLIF(u.bin_location, ''), NULLIF(u.location, ''), u.deployment_site, '') AS location,
                       mi.product_name AS name, mi.brand AS brand, u.barcode, COALESCE(u.photo, '') AS photo
                FROM inventory_units u
                JOIN master_items mi ON mi.id = u.master_item_id
                WHERE u.deleted_at IS NULL 
                  AND u.status = 'available'
                  AND (u.deployment_site = 'In Warehouse' OR u.deployment_site = '' OR u.deployment_site IS NULL)
                  AND (u.serial LIKE ? OR mi.product_name LIKE ? OR u.barcode LIKE ?)
                ORDER BY u.serial ASC
                LIMIT 10
            ");
            $stUnits->execute([$term, $term, $term]);
            $units = $stUnits->fetchAll(PDO::FETCH_ASSOC);

            // 2. Lots matching name/brand/barcode
            $stLots = $pdo->prepare("
                SELECT MIN(u.id) AS id, u.master_item_id, mi.product_name AS name, mi.brand AS brand,
                       COUNT(u.id) AS quantity, u.barcode,
                       COALESCE(NULLIF(MAX(u.bin_location), ''), NULLIF(MAX(u.location), ''), MAX(u.deployment_site), '') AS location,
                       MAX(u.status) AS status, COALESCE(MAX(NULLIF(u.photo, '')), '') AS photo
                FROM inventory_units u
                JOIN master_items mi ON mi.id = u.master_item_id
                WHERE u.deleted_at IS NULL
                  AND u.status = 'available'
                  AND (u.deployment_site = 'In Warehouse' OR u.deployment_site = '' OR u.deployment_site IS NULL)
                  AND (mi.product_name LIKE ? OR mi.brand LIKE ? OR u.barcode LIKE ?)
                GROUP BY COALESCE(NULLIF(u.barcode, ''), CONCAT('unit-', u.id)), u.master_item_id
                ORDER BY mi.product_name ASC
                LIMIT 8
            ");
            $stLots->execute([$term, $term, $term]);
            $lots = $stLots->fetchAll(PDO::FETCH_ASSOC);

            json_ok(['units' => $units, 'lots' => $lots]);
            break;

        case 'get_item':
            $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) {
                json_fail('Invalid item ID.');
            }
            $item = find_item($pdo, $id);
            if (!$item) {
                json_fail('Item not found.');
            }
            json_ok(['item' => [
                'id'       => (int)$item['id'],
                'name'     => $item['name'],
                'brand'    => $item['brand'] ?? '',
                'serial'   => $item['serial'] ?? '',
                'quantity' => (int)($item['quantity'] ?? 1),
                'location' => $item['resolved_location'] ?? $item['location'] ?? '',
                'barcode'  => $item['barcode'] ?? '',
                'qr'       => $item['qr'] ?? '',
                'status'   => $item['status'] ?? 'available',
                'photo'    => $item['photo'] ? BASE_URL . '/' . $item['photo'] : '',
            ]]);
            break;

        default:
            json_fail('Unknown suggestion type requested.');
    }
} catch (Exception $e) {
    json_fail('Failed to retrieve suggestions: ' . $e->getMessage());
}
