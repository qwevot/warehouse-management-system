<?php
// Shared searchable brand field: a Google-style autocomplete input (same as
// the product-name field), so it filters as you type (handles thousands of
// brands) instead of a long <select>. Used by every item/master-item form.
// Configure before including via:
//   $brandSelectId          (default 'brand')
//   $brandSelectRequired    (default false)
//   $brandSelectPlaceholder (default 'Type or select brand...')
//   $brandOptions           (default null -> load from all_brands())
$brandSelectId = $brandSelectId ?? 'brand';
$brandSelectRequired = $brandSelectRequired ?? false;
$brandSelectPlaceholder = $brandSelectPlaceholder ?? 'Type or select brand...';
$brandOptions = $brandOptions ?? all_brands();
$brandSuggestId = $brandSelectId . '_suggest';
?>
<script>
    window.BRANDS = <?php echo json_encode(array_values($brandOptions)); ?>;
</script>
<div class="suggest-wrap">
    <input
        type="text"
        id="<?php echo e($brandSelectId); ?>"
        name="brand"
        class="js-brand-suggest"
        placeholder="<?php echo e($brandSelectPlaceholder); ?>"
        autocomplete="off"
        <?php echo $brandSelectRequired ? ' required' : ''; ?>
    >
    <div class="suggest-box" id="<?php echo e($brandSuggestId); ?>"></div>
</div>
