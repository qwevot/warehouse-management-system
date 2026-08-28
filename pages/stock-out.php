<?php
$pageTitle = 'Stock Out';
$activeNav = 'stock-out';

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
        <div class="section-title">Issue Stock</div>
    </div>

    <?php if (user_can('movement.manage')): ?>
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/process-stock-out.php" class="wizard-body" id="stockForm">

        <div class="form-grid">

            <?php include __DIR__ . '/../includes/item-search-field.php'; ?>

            <?php include __DIR__ . '/../includes/item-card-select.php'; ?>

            <div class="field">
                <label for="quantity">Quantity to issue *</label>
                <input type="number" id="quantity" name="quantity" min="1" value="1" required>
            </div>

            <div class="field">
                <label for="issued_to">Issued To *</label>
                <input type="text" id="issued_to" name="issued_to" placeholder="Who is this stock being issued to?" required>
            </div>

            <div class="field">
                <label for="note">Note</label>
                <input type="text" id="note" name="note" placeholder="Optional (e.g. issued to department)">
            </div>

        </div>

        <div class="add-row">
            <button type="submit" class="btn primary">Issue Stock</button>
        </div>

    </form>
    <?php else: ?>
        <p style="color:var(--muted);">You have view-only access. Stock movements require a technician account or higher.</p>
    <?php endif; ?>

</section>

<section class="inventory-section" id="inventory" style="margin-top:20px;">

    <?php
    $autofillTitle = 'Autofill the stock-out form with this item';
    include __DIR__ . '/../includes/movement-item-list.php';
    ?>

</section>

<?php include __DIR__ . '/../includes/scan-modal.php'; ?>
<?php include __DIR__ . '/../includes/inventory-card.php'; ?>

<script>
    <?php include __DIR__ . '/../includes/stock-items-data.php'; ?>

    // Scanning selects the matched item, but rejects inactive / out-of-stock
    // items (the shared scanner already shows a notification for inactive ones).
    window.setScanHandler(function (item) {
        if (item.status !== "available") {
            const msg = document.getElementById("scanMsg");
            msg.className = "scan-result error";
            msg.innerHTML = "<strong>Rejected.</strong> This item is inactive / out of the warehouse.";
            return false;
        }
        document.getElementById("stockItemId").value = item.id;
        const input = document.getElementById("stockItemSearch");
        input.value = item.name + (item.brand ? "  (" + item.brand + ")" : "");
        window.showItemCard(item);
        return true;
    });

    // The "Outbound" row button fills the item form with the row's item,
    // rejecting inactive / out-of-warehouse items like the scanner does.
    window.pickStockItem = function (id) {
        const it = (window.STOCK_ITEMS || []).find((i) => i.id === id);
        if (!it) return;
        if (it.status !== "available") {
            window.showFlash("Cannot issue this item: inactive / out of the warehouse.", "error");
            return;
        }
        document.getElementById("stockItemId").value = it.id;
        const input = document.getElementById("stockItemSearch");
        input.value = it.name + (it.brand ? "  (" + it.brand + ")" : "");
        window.showItemCard(it);
        const form = document.getElementById("stockForm");
        if (form) form.scrollIntoView({ behavior: "smooth" });
    };
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
