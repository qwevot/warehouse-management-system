<?php
$pageTitle = 'Stock In';
$activeNav = 'stock-in';

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
        <div class="section-title">Receive Stock</div>
    </div>

    <?php if (user_can('movement.manage')): ?>
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/process-stock-in.php" class="wizard-body" id="stockForm">

        <div class="form-grid">

            <?php include __DIR__ . '/../includes/item-search-field.php'; ?>

            <?php include __DIR__ . '/../includes/item-card-select.php'; ?>

            <div class="field">
                <label for="quantity">Quantity to add *</label>
                <input type="number" id="quantity" name="quantity" min="1" value="1" required>
            </div>

            <div class="field">
                <label for="note">Note</label>
                <input type="text" id="note" name="note" placeholder="Optional (e.g. received from supplier)">
            </div>

        </div>

        <div class="add-row">
            <button type="submit" class="btn primary">Add Stock</button>
        </div>

    </form>
    <?php else: ?>
        <p style="color:var(--muted);">You have view-only access. Stock movements require a technician account or higher.</p>
    <?php endif; ?>

</section>

<section class="inventory-section" id="inventory" style="margin-top:20px;">

    <?php
    $autofillTitle = 'Autofill the stock-in form with this item';
    include __DIR__ . '/../includes/movement-item-list.php';
    ?>

</section>

<?php include __DIR__ . '/../includes/scan-modal.php'; ?>
<?php include __DIR__ . '/../includes/inventory-card.php'; ?>

<script>
    <?php include __DIR__ . '/../includes/stock-items-data.php'; ?>

    // Scanning selects the matched item into the form and shows its card.
    window.setScanHandler(function (item) {
        document.getElementById("stockItemId").value = item.id;
        const input = document.getElementById("stockItemSearch");
        input.value = item.name + (item.brand ? "  (" + item.brand + ")" : "");
        window.showItemCard(item);
        return true;
    });

    // The "Inbound" row button fills the item form with the row's item.
    window.pickStockItem = function (id) {
        const it = (window.STOCK_ITEMS || []).find((i) => i.id === id);
        if (!it) return;
        document.getElementById("stockItemId").value = it.id;
        const input = document.getElementById("stockItemSearch");
        input.value = it.name + (it.brand ? "  (" + it.brand + ")" : "");
        window.showItemCard(it);
        const form = document.getElementById("stockForm");
        if (form) form.scrollIntoView({ behavior: "smooth" });
    };

    document.addEventListener("DOMContentLoaded", function () {
        const urlParams = new URLSearchParams(window.location.search);
        const itemId = parseInt(urlParams.get('item_id'), 10);
        if (itemId) {
            window.pickStockItem(itemId);
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
