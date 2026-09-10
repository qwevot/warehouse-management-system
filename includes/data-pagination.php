<?php
// Reusable pagination partial matching inventory-table.php UI
// Usage: include 'includes/data-pagination.php'; echo buildPagination($prefix);
function buildPagination($prefix, $options = []) {
    if (!is_array($options)) $options = [];
    $ids = $options['ids'] ?? [];
    $defaultSize = (int)($options['pageSize'] ?? 10);
    $pagerId   = $ids['pager'] ?? ($prefix . 'Pagination');
    $prevId    = $ids['prev']  ?? ($prefix . 'PagePrev');
    $nextId    = $ids['next']  ?? ($prefix . 'PageNext');
    $numsId    = $ids['nums']  ?? ($prefix . 'PageNumbers');
    $rangeId   = $ids['range'] ?? ($prefix . 'PageRange');
    $sizeId    = $ids['size']  ?? ($prefix . 'PageSizeSelect');
    ob_start();
?>
<div class="pagination" id="<?= $pagerId ?>" style="display:none;">
    <div class="page-size">
        <label for="<?= $sizeId ?>">Rows</label>
        <select id="<?= $sizeId ?>">
            <?php foreach ([5, 10, 15, 20, 25, 50] as $sz): ?>
                <option value="<?= $sz ?>"<?php echo $sz === $defaultSize ? ' selected' : ''; ?>><?= $sz ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="button" class="pg-btn" id="<?= $prevId ?>" title="Previous">‹</button>
    <div class="pg-numbers" id="<?= $numsId ?>"></div>
    <span class="page-range" id="<?= $rangeId ?>"></span>
    <?php if (!empty($options['deletePermanentlyAction']) && current_role() === 'super admin'): ?>
        <a href="javascript:void(0)" onclick="<?= htmlspecialchars($options['deletePermanentlyAction']) ?>" class="delete-all-permanently-link" style="color:var(--red, #dc2626); font-size:10px; text-decoration:underline; font-weight:600; margin-left:10px; text-transform:uppercase; letter-spacing:0.3px; cursor:pointer;">Delete All Permanently</a>
    <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}
?>
