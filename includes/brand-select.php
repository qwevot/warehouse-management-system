<?php
// Shared brand select component: renders a strict <select> populated from master_brands.
// Configure before including via:
//   $brandSelectId          (default 'brand')
//   $brandSelectRequired    (default false)
//   $brandSelectPlaceholder (default '— Select Brand —')
//   $brandOptions           (default null -> load from all_brands())
$brandSelectId = $brandSelectId ?? 'brand';
$brandSelectRequired = $brandSelectRequired ?? false;
$brandSelectPlaceholder = $brandSelectPlaceholder ?? '— Select Brand —';
$brandOptions = $brandOptions ?? all_brands();
?>
<select
    id="<?php echo e($brandSelectId); ?>"
    name="brand"
    class="form-select"
    <?php echo $brandSelectRequired ? ' required' : ''; ?>
>
    <option value=""><?php echo e($brandSelectPlaceholder); ?></option>
    <?php foreach ($brandOptions as $b): ?>
        <option value="<?php echo e($b); ?>"><?php echo e($b); ?></option>
    <?php endforeach; ?>
</select>
