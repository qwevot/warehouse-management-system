<?php
// DISCARD STAGED INBOUND: Cleans up an unfinalized staged inbound lot or rollback units
require_once __DIR__ . '/../includes/auth.php';
require_login_api();
require_csrf_api();
require_can_api('movement.manage');
require_once __DIR__ . '/../includes/functions.php';

$pdo = json_db();

$lotId       = (int)($_POST['lot_id'] ?? 0);
$inboundType = trim($_POST['inbound_type'] ?? 'existing');
$unitIdsRaw  = $_POST['unit_ids'] ?? [];

if ($lotId <= 0) {
    json_fail('Invalid lot ID.');
}

try {
    $pdo->beginTransaction();

    if ($inboundType === 'new_lot') {
        $st = $pdo->prepare('SELECT id, barcode, location, status, deleted_at FROM inventory_units WHERE id = ? FOR UPDATE');
        $st->execute([$lotId]);
        $lot = $st->fetch();

        if ($lot) {
            $barcode = (string)($lot['barcode'] ?? '');
            if ($barcode !== '') {
                $pdo->prepare("DELETE FROM inventory_units WHERE barcode = ? AND status = 'staged'")
                    ->execute([$barcode]);
            } else {
                $pdo->prepare("DELETE FROM inventory_units WHERE id = ? AND status = 'staged'")
                    ->execute([$lotId]);
            }
        }
    } else {
        if (!empty($unitIdsRaw)) {
            $unitIds = is_array($unitIdsRaw) ? $unitIdsRaw : explode(',', (string)$unitIdsRaw);
            $cleanIds = array_filter(array_map('intval', $unitIds));

            if (!empty($cleanIds)) {
                $inClause = implode(',', array_fill(0, count($cleanIds), '?'));
                $pdo->prepare("DELETE FROM inventory_units WHERE id IN ($inClause) AND status = 'staged'")
                    ->execute($cleanIds);
            }
        }
    }

    $pdo->commit();

    log_activity('movement.inbound_discard', 'inventory_lot', $lotId, [
        'inbound_type' => $inboundType,
        'discarded_units_count' => !empty($cleanIds) ? count($cleanIds) : null
    ], 'success');

    json_ok(['message' => 'Previous staged session discarded.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_activity('movement.inbound_discard', 'inventory_lot', $lotId, [
        'inbound_type' => $inboundType,
        'error' => $e->getMessage()
    ], 'failure');
    json_fail('Failed to discard staged session: ' . $e->getMessage());
}
