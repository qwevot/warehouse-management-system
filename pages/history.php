<?php
$pageTitle = 'History';
$activeNav = 'history';

require_once __DIR__ . '/../includes/auth.php';
require_login();

try {
    $pdo = db();
    // READ: every stock movement (inbound, outbound, transfer) across the
    // warehouse, newest first, joined with the item name for display.
    $movements = $pdo->query(
        "SELECT m.*, COALESCE(i.name, '') AS item_name
         FROM stock_movements m
         LEFT JOIN inventory_list i ON i.id = m.item_id
         ORDER BY m.created_at DESC, m.id DESC"
    )->fetchAll();
} catch (Exception $e) {
    $movements = [];
    $dbError   = 'Could not load the activity history from the database.';
}

$typeLabels = [
    'in'       => 'INBOUND',
    'out'      => 'OUTBOUND',
    'transfer' => 'TRANSFER',
];

include __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($dbError)): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>

<section class="inventory-section" id="inventory">

    <div class="section-header">
        <div class="section-title">Recent Activities</div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Type</th>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Issued To</th>
                    <th>Details / Note</th>
                    <th>Modified By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr><td colspan="8" style="text-align:center; color:#9ca3af; padding:40px;">No activity yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($movements as $i => $m): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <span class="status <?php echo e($m['type'] === 'in' ? 'inbound' : ($m['type'] === 'out' ? 'outbound' : 'transfer')); ?>">
                                    <?php echo e($typeLabels[$m['type']] ?? strtoupper((string)$m['type'])); ?>
                                </span>
                            </td>
                            <td><?php echo $m['item_name'] ? e($m['item_name']) : ('Item #' . (int)$m['item_id']); ?></td>
                            <td>
                                <?php if ($m['type'] === 'in'): ?>+<?php echo (int)$m['quantity'];
                                elseif ($m['type'] === 'out'): ?>-<?php echo (int)$m['quantity'];
                                else: ?><span style="color:#9ca3af;">—</span><?php endif; ?>
                            </td>
                            <td><?php echo $m['issued_to'] ? e($m['issued_to']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
                            <td><?php echo $m['note'] ? e($m['note']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
                            <td><?php echo $m['modified_by'] ? e($m['modified_by']) : '<span style="color:#9ca3af;">—</span>'; ?></td>
                            <td><?php echo e(date('Y-m-d H:i', strtotime($m['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
