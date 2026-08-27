<?php
// Shared inventory list: search/filter/sort toolbar + the items table + the
// client-side data JSON. Used by index.php (Items), bin.php, and generator.php.
//
// Expected variables:
//   $inventory     array of inventory rows
//   $renderActions callable($item): string  -> HTML for the action cell
//   $emptyMessage  string (optional) text shown when there are no items
//   $tableColumns  array (optional) which columns to show. Defaults to all.
//   $rowSelectable bool (optional) render a hidden checkbox column per row,
//                    toggled on via body.merge-mode (used by Merge on generator).
$emptyMessage = $emptyMessage ?? 'No items registered yet.';
$rowSelectable = $rowSelectable ?? false;

require_once __DIR__ . '/data-toolbar.php';
require_once __DIR__ . '/data-pagination.php';

$inventorySortOptions = [
    ['newest', 'Newest First'],
    ['oldest', 'Oldest First'],
    ['name', 'Name A-Z'],
    ['nameDesc', 'Name Z-A'],
];

$axisPanelHtml = '
<div class="filter-group">
    <div class="filter-group-title">Brand</div>
    <input type="text" class="axis-input" data-axis="brand" placeholder="Search brands...">
    <div class="axis-chips" data-chips="brand"></div>
</div>
<div class="filter-group">
    <div class="filter-group-title">Shelf</div>
    <input type="text" class="axis-input" data-axis="shelf" placeholder="Search used shelves...">
    <div class="axis-chips" data-chips="shelf"></div>
</div>
<div class="filter-group">
    <div class="filter-group-title">Row</div>
    <input type="text" class="axis-input" data-axis="row" placeholder="Search used rows...">
    <div class="axis-chips" data-chips="row"></div>
</div>
<div class="filter-group">
    <div class="filter-group-title">Column</div>
    <input type="text" class="axis-input" data-axis="col" placeholder="Search used columns...">
    <div class="axis-chips" data-chips="col"></div>
</div>
<div class="filter-panel-footer">
    <button type="button" class="btn sm secondary" onclick="clearFilters()">Clear</button>
</div>';

$inventoryToolbar = buildToolbar('', $inventorySortOptions, [
    'ids' => [
        'search' => 'searchInput', 'sort' => 'sortSelect',
        'wrap' => 'filterWrap', 'btn' => 'filterBtn',
        'panel' => 'filterPanel', 'badge' => 'filterBadge',
        'export' => 'exportTable',
    ],
    'panel' => $axisPanelHtml,
]);
$inventoryPagination = buildPagination('', ['ids' => [
    'pager' => 'pagination', 'prev' => 'pagePrev', 'next' => 'pageNext',
    'nums' => 'pageNumbers', 'range' => 'pageRange', 'size' => 'pageSizeSelect',
]]);
?>
<?php echo $inventoryToolbar; ?>

<div class="table-container">

    <table class="inventory-table">

        <thead>
            <tr>
                <?php if ($rowSelectable): ?><th class="merge-check-col"></th><?php endif; ?>
                <?php if (col_on($tableColumns, 'no')): ?><th>No.</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'action')): ?><th class="action-col">Action</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'photo')): ?><th>Photo</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'item')): ?><th>Item</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'brand')): ?><th>Brand</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'quantity')): ?><th class="qty-col">QTY</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'location')): ?><th>Location</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'status')): ?><th>Status</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'serial')): ?><th>Serial No.</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'barcode')): ?><th>Barcode No.</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'registered')): ?><th>Ref. Date</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'last_modified')): ?><th>Last Modified</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'modified_by')): ?><th>Modified By</th><?php endif; ?>
                <?php if (col_on($tableColumns, 'bin_note')): ?><th>Bin Note</th><?php endif; ?>
            </tr>
        </thead>

        <tbody id="inventoryTable">

        <?php if (empty($inventory)): ?>

            <tr>
                <td colspan="<?php echo count($tableColumns) + ($rowSelectable ? 1 : 0); ?>" style="text-align:center; color:#9ca3af; padding:40px;">
                    <?php echo e($emptyMessage); ?>
                </td>
            </tr>

        <?php else: ?>

            <?php foreach ($inventory as $item): ?>

                <tr
                    class="inventory-row"
                    data-id="<?php echo (int)$item['id']; ?>"
                    data-status="<?php echo e($item['status']); ?>"
                    data-brand="<?php echo e($item['brand']); ?>"
                    data-loc="<?php echo e($item['location']); ?>"
                >

                    <?php if ($rowSelectable): ?>
                        <td class="merge-check-col" data-label="">
                            <input type="checkbox" class="row-select" value="<?php echo (int)$item['id']; ?>">
                        </td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'no')): ?>
                        <td class="no-col" data-label="No."></td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'action')): ?>
                        <td data-label="Action">
                            <div class="row-actions">
                                <?php echo $renderActions($item); ?>
                            </div>
                        </td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'photo')): ?>
                        <td data-label="Photo">
                            <?php if ($item['photo']): ?>
                                <img
                                    class="item-photo"
                                    src="<?php echo e(BASE_URL . '/' . $item['photo']); ?>"
                                    alt="<?php echo e($item['name']); ?>"
                                    loading="lazy"
                                    decoding="async"
                                    onclick="openItemModal(<?php echo (int)$item['id']; ?>)"
                                >
                            <?php else: ?>
                                <div class="item-photo"></div>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'item')): ?>
                        <td data-label="Item">
                            <div class="item-name"><?php echo e($item['name']); ?></div>
                        </td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'brand')): ?>
                        <td data-label="Brand"><?php echo dash($item['brand']); ?></td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'quantity')): ?>
                        <td data-label="Quantity"><span class="qty"><?php echo (int)$item['quantity']; ?></span></td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'location')): ?>
                        <td data-label="Location">
                            <?php if ($item['location']): ?>
                                <span class="location"><?php echo e($item['location']); ?></span>
                            <?php else: ?>
                                <span class="dash">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'status')): ?>
                        <td data-label="Status">
                            <span class="status <?php echo e($item['status']); ?>">
                                <?php echo $item['status'] === 'available' ? 'ACTIVE' : 'INACTIVE'; ?>
                            </span>
                        </td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'serial')): ?>
                        <td data-label="Serial"><span class="serial"><?php echo e($item['serial']); ?></span></td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'barcode')): ?>
                        <td data-label="Barcode">
                            <?php if ($item['barcode']): ?>
                                <span class="serial" style="font-size:11px;"><?php echo e($item['barcode']); ?></span>
                            <?php else: ?>
                                <span class="dash">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'registered')): ?>
                        <td data-label="Registered">
                            <span class="registered-on"><?php echo e(fmt_datetime($item['created_at'])); ?></span>
                        </td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'last_modified')): ?>
                        <td data-label="Last Modified">
                            <?php if (!empty($item['updated_at'])): ?>
                                <span class="registered-on"><?php echo e(fmt_datetime($item['updated_at'])); ?></span>
                            <?php else: ?>
                                <span class="dash">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'modified_by')): ?>
                        <td data-label="Modified By">
                            <?php echo dash($item['modified_by']); ?>
                        </td>
                    <?php endif; ?>

                    <?php if (col_on($tableColumns, 'bin_note')): ?>
                        <td data-label="Bin Note">
                            <?php echo dash($item['bin_note']); ?>
                        </td>
                    <?php endif; ?>

                </tr>

            <?php endforeach; ?>

        <?php endif; ?>

        </tbody>

    </table>

</div>

<?php echo $inventoryPagination; ?>

<script>
    window.INVENTORY = <?php echo json_encode(array_map(function ($it) {
        return [
            'id'         => (int)$it['id'],
            'serial'     => $it['serial'],
            'name'       => $it['name'],
            'brand'      => $it['brand'],
            'quantity'   => (int)$it['quantity'],
            'location'   => $it['location'],
            'barcode'    => $it['barcode'],
            'photo'      => $it['photo'] ? BASE_URL . '/' . $it['photo'] : '',
            'status'     => $it['status'],
            'created_at' => date('Y-m-d H:i', strtotime($it['created_at'])),
        ];
    }, $inventory)); ?>;
</script>
