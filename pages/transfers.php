<?php
$pageTitle = 'Transfers';
$activeNav = 'transfers';

require_once __DIR__ . '/../includes/auth.php';
require_login();

try {
    $pdo = db();
    $items = fetch_active_items($pdo);
} catch (Exception $e) {
    $items = [];
    $dbError = 'Could not load data from the database.';
}

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/flash.php'; ?>

<section class="inventory-section suggest-section">

    <div class="section-header">
        <div class="section-title">Transfer Item</div>
    </div>

    <?php if (user_can('movement.manage')): ?>
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/process-transfer.php" class="wizard-body" id="transferForm">

        <div class="form-grid">

            <?php include __DIR__ . '/../includes/item-search-field.php'; ?>

            <div class="field">
                <label>Current Location</label>
                <div class="dd" id="transferCurrentLocation" style="padding:10px 12px;">—</div>
            </div>

            <?php include __DIR__ . '/../includes/item-card-select.php'; ?>

            <div class="field">
                <label for="note">Note</label>
                <input type="text" id="note" name="note" placeholder="Optional (e.g. consolidating shelf 2)">
            </div>

            <div class="field">
                <label>Destination Location *</label>
                <?php
                $locPrefix = 'transfer';
                $locUpdateFn = 'updateTransferLocation()';
                $locHiddenName = 'destination';
                $locHiddenId = 'transferDestination';
                include __DIR__ . '/../includes/location-picker.php';
                ?>
            </div>
                        
        </div>

        <div class="add-row">
            <button type="submit" class="btn primary">Transfer Item</button>
        </div>

    </form>
    <?php else: ?>
        <p style="color:var(--muted);">You have view-only access. Transfers require a technician account or higher.</p>
    <?php endif; ?>

</section>

<section class="inventory-section" id="inventory" style="margin-top:20px;">

    <?php
    $autofillTitle = 'Autofill the transfer form with this item';
    include __DIR__ . '/../includes/movement-item-list.php';
    ?>

</section>

<?php include __DIR__ . '/../includes/inventory-card.php'; ?>

<script>
    <?php include __DIR__ . '/../includes/stock-items-data.php'; ?>
</script>

<?php include __DIR__ . '/../includes/scan-modal.php'; ?>

<script>
    // Scanning selects the matched item, fills the transfer card, and shows the
    // current location.
    window.setScanHandler(function (item) {
        document.getElementById("stockItemId").value = item.id;
        const input = document.getElementById("stockItemSearch");
        input.value = item.name + (item.brand ? "  (" + item.brand + ")" : "");
        const cur = document.getElementById("transferCurrentLocation");
        if (cur) cur.textContent = item.location || "—";
        window.showItemCard(item);
        return true;
    });

    // The "Transfer" row button fills the item form with the row's item.
    window.pickStockItem = function (id) {
        const it = (window.STOCK_ITEMS || []).find((i) => i.id === id);
        if (!it) return;
        document.getElementById("stockItemId").value = it.id;
        const input = document.getElementById("stockItemSearch");
        input.value = it.name + (it.brand ? "  (" + it.brand + ")" : "");
        const cur = document.getElementById("transferCurrentLocation");
        if (cur) cur.textContent = it.location || "—";
        window.showItemCard(it);
        const form = document.getElementById("transferForm");
        if (form) form.scrollIntoView({ behavior: "smooth" });
    };
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
