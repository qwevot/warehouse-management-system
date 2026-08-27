<?php
$pageTitle = 'Scan';
$activeNav = 'scan';

require_once __DIR__ . '/../includes/auth.php';
require_login();

include __DIR__ . '/../includes/header.php';
?>

<section class="inventory-section">

    <div class="section-header">
        <div class="section-title">Scan Barcode / QR Code</div>
        <button type="button" class="btn primary" onclick="openScanModal()">Scan Item</button>
    </div>

    <div class="wizard-body">

        <p style="color:var(--muted); margin-bottom:14px;">
            Point your phone camera at a barcode or QR code to view the item's information.
        </p>

        <?php include __DIR__ . '/../includes/item-card-select.php'; ?>

    </div>

</section>

<?php include __DIR__ . '/../includes/scan-modal.php'; ?>

<script>
    // A successful scan shows the item's details in the shared item card.
    window.setScanHandler(function (item) {
        window.showItemCard(item);
        return true;
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
