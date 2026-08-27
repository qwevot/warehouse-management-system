<?php
$pageTitle = 'Transfers';
$activeNav = 'transfers';

require_once __DIR__ . '/../includes/auth.php';
require_login();

try {
    $pdo = db();
    $items = $pdo->query("SELECT id, name, brand, serial, quantity, location, barcode, qr, status, photo, created_at FROM inventory_list WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    $items = [];
    $dbError = 'Could not load data from the database.';
}

include __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['added'])): ?>
    <div class="flash success"><?php echo e($_GET['added']); ?></div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="flash error"><?php echo e($_GET['error']); ?></div>
<?php endif; ?>

<?php if (!empty($dbError)): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>

<section class="inventory-section suggest-section">

    <div class="section-header">
        <div class="section-title">Transfer Item</div>
    </div>

    <?php if (user_can('movement.manage')): ?>
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/process-transfer.php" class="wizard-body" id="transferForm">

        <div class="form-grid">

            <div class="field">
                <label for="stockItemSearch">Item *</label>
                <div class="suggest-wrap">
                    <input
                        type="text"
                        id="stockItemSearch"
                        placeholder="Search item name, brand, or serial..."
                        autocomplete="off"
                        required
                    >
                    <input type="hidden" name="item_id" id="stockItemId">
                    <div class="suggest-box" id="stockSuggest"></div>
                    <button type="button" class="btn sm upload photo-btn scan-inline" onclick="openScanModal()" title="Scan Item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><line x1="7" y1="12" x2="17" y2="12"></line></svg>
                        Scan
                    </button>
                </div>
            </div>

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
                <div class="location-picker">
                    <div class="loc-input">
                        <label for="transferShelf">Shelf</label>
                        <input type="text" id="transferShelf" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); updateTransferLocation()">
                    </div>
                    <div class="loc-input">
                        <label for="transferRow">Row</label>
                        <input type="text" id="transferRow" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); updateTransferLocation()">
                    </div>
                    <div class="loc-input">
                        <label for="transferCol">Column</label>
                        <input type="text" id="transferCol" maxlength="2" inputmode="numeric" placeholder="01" oninput="onlyDigits(this); updateTransferLocation()">
                    </div>
                </div>
                <input type="hidden" name="destination" id="transferDestination">
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
    $inventory = $items;
    $rowSelectable = false;
    $tableColumns = ['no', 'item', 'brand', 'serial', 'quantity', 'location', 'status', 'action'];
    $renderActions = function ($item) { ?>
        <button class="btn icon-btn view" title="View" onclick="openItemModal(<?php echo (int)$item['id']; ?>)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="8" r="1" fill="currentColor" stroke="none"/></svg>
        </button>
        <?php if (user_can('movement.manage')): ?>
        <button class="btn icon-btn view" title="Autofill the transfer form with this item" onclick="pickStockItem(<?php echo (int)$item['id']; ?>)">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M4 17V15.8C4 14.1198 4 13.2798 4.32698 12.638C4.6146 12.0735 5.07354 11.6146 5.63803 11.327C6.27976 11 7.11984 11 8.8 11H20M20 11L16 7M20 11L16 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
        </button>
        <?php endif; ?>
    <?php };
    $emptyMessage = 'No items registered yet.';
    include __DIR__ . '/../includes/inventory-table.php';
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
