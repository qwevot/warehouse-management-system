<?php
// Shared Shelf/Row/Column location picker. Configure before including via:
//   $locPrefix     id prefix, e.g. 'loc', 'editLoc', 'transfer'
//   $locUpdateFn   JS called on input, e.g. 'updateLocation()'
//   $locHiddenName hidden input name,  e.g. 'location' or 'destination'
//   $locHiddenId   hidden input id,    e.g. 'location' or 'edit_location'
?>
<div class="location-picker">
    <div class="loc-input">
        <label for="<?php echo $locPrefix; ?>Shelf">Shelf</label>
        <input type="text" id="<?php echo $locPrefix; ?>Shelf" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); <?php echo $locUpdateFn; ?>">
    </div>
    <div class="loc-input">
        <label for="<?php echo $locPrefix; ?>Row">Row</label>
        <input type="text" id="<?php echo $locPrefix; ?>Row" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); <?php echo $locUpdateFn; ?>">
    </div>
    <div class="loc-input">
        <label for="<?php echo $locPrefix; ?>Col">Column</label>
        <input type="text" id="<?php echo $locPrefix; ?>Col" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); <?php echo $locUpdateFn; ?>">
    </div>
</div>
<input type="hidden" name="<?php echo $locHiddenName; ?>" id="<?php echo $locHiddenId; ?>">
