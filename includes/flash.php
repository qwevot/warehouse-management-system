<?php
// Shared flash-message renderer. Reads ?added / ?edited / ?error from the query
// string and the optional $dbError variable. Customize per page by setting
// $flashAdded / $flashEdited (pre-rendered HTML) and $flashAfterError before
// including this file.
$dbError = $dbError ?? '';

if (isset($_GET['added'])): ?>
    <div class="flash success"><?php echo $flashAdded ?? e($_GET['added']); ?></div>
<?php endif;

if (isset($_GET['edited'])): ?>
    <div class="flash success"><?php echo $flashEdited ?? e($_GET['edited']); ?></div>
<?php endif;

if (isset($_GET['error'])): ?>
    <div class="flash error"><?php echo e($_GET['error']); ?><?php echo $flashAfterError ?? ''; ?></div>
<?php endif;

if ($dbError !== ''): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>
