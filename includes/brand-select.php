<?php
// Shared brand <select> dropdown. Configure before including via:
//   $brandSelectId          (default 'brand')
//   $brandSelectRequired    (default false)
//   $brandSelectPlaceholder (default '— Select brand —')
//   $brandOptions           (default null -> load from all_brands())
$brandSelectId = $brandSelectId ?? 'brand';
$brandSelectRequired = $brandSelectRequired ?? false;
$brandSelectPlaceholder = $brandSelectPlaceholder ?? '— Select brand —';
$brandOptions = $brandOptions ?? all_brands();
?>
<select id="<?php echo e($brandSelectId); ?>" name="brand"<?php echo $brandSelectRequired ? ' required' : ''; ?>>
    <option value=""><?php echo e($brandSelectPlaceholder); ?></option>
    <?php foreach ($brandOptions as $b): ?>
        <option value="<?php echo e($b); ?>"><?php echo e($b); ?></option>
    <?php endforeach; ?>
</select>
