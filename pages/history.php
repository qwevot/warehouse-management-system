<?php
$pageTitle = 'History';
$activeNav = 'history';

require_once __DIR__ . '/../includes/auth.php';
require_login();

try {
    $pdo = db();
    // READ: every stock movement (inbound, outbound, transfer) across the
    // warehouse, newest first, joined with the item name for display.
    $movements = fetch_movements($pdo);
} catch (Exception $e) {
    $movements = [];
    $dbError   = 'Could not load the activity history from the database.';
}

$typeLabels = movement_type_labels();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/flash.php';

require_once __DIR__ . '/../includes/data-toolbar.php';
require_once __DIR__ . '/../includes/data-pagination.php';

$histSortOptions = [
    ['newest', 'Newest First'],
    ['oldest', 'Oldest First'],
];
$histToolbar    = buildToolbar('hist', $histSortOptions);
$histPagination = buildPagination('hist', ['pageSize' => 10]);
?>

<section class="inventory-section" id="inventory">

    <div class="section-header">
        <div class="section-title">Recent Activities</div>
        <?php if (current_role() === 'super admin'): ?>
            <button type="button" class="btn danger" onclick="deleteHistory()">Delete History</button>
        <?php endif; ?>
    </div>

    <div class="toolbar-row"><?php echo $histToolbar; ?></div>

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
            <tbody id="histBody">
                <?php if (empty($movements)): ?>
                    <tr><td colspan="8" style="text-align:center; color:#9ca3af; padding:40px;">No activity yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($movements as $i => $m): ?>
                        <tr class="hist-row" data-type="<?php echo e($m['type']); ?>" data-created="<?php echo strtotime($m['created_at']); ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <span class="status <?php echo e(movement_status_class($m['type'])); ?>">
                                    <?php echo e($typeLabels[$m['type']] ?? strtoupper((string)$m['type'])); ?>
                                </span>
                            </td>
                            <td><?php echo $m['item_name'] ? e($m['item_name']) : ('Item #' . (int)$m['item_id']); ?></td>
                            <td><?php echo movement_qty($m['type'], $m['quantity']); ?></td>
                            <td><?php echo dash($m['issued_to']); ?></td>
                            <td><?php echo dash($m['note']); ?></td>
                            <td><?php echo dash($m['modified_by']); ?></td>
                            <td><?php echo e(fmt_datetime($m['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $histPagination; ?>

</section>

<script>
    window.HIST_DATA = <?php echo json_encode(array_map(function ($m) {
        return [
            'type'        => $m['type'],
            'item_name'   => $m['item_name'],
            'quantity'    => (int)$m['quantity'],
            'issued_to'   => $m['issued_to'] ?? '',
            'note'        => $m['note'] ?? '',
            'modified_by' => $m['modified_by'] ?? '',
            'created_at'  => $m['created_at'] ? date('Y-m-d H:i', strtotime($m['created_at'])) : '',
            'created_ts'  => $m['created_at'] ? (int)strtotime($m['created_at']) : 0,
        ];
    }, $movements)); ?>;

    function deleteHistory() {
        if (!confirm("Are you sure you want to delete all movement history? This will permanently wipe all activity logs and cannot be undone.")) return;
        window.submitPostForm("<?php echo BASE_URL; ?>/actions/delete-history.php", {});
    }

    document.addEventListener("DOMContentLoaded", function () {
        if (typeof initHistoryTable === "function") {
            initHistoryTable();
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
