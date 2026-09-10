<?php
$pageTitle = 'Stock In';
$activeNav = 'stock-in';

require_once __DIR__ . '/../includes/auth.php';
require_login();

try {
    $pdo = db();
    $items = fetch_active_items($pdo);
    $masterItems = $pdo->query("SELECT product_name, brand FROM master_items WHERE deleted_at IS NULL ORDER BY product_name ASC, brand ASC")->fetchAll();
} catch (Exception $e) {
    $items = [];
    $masterItems = [];
    $dbError = 'Could not load data from the database.';
}

include __DIR__ . '/../includes/header.php';
?>
<script>
window.MASTER_ITEMS = <?php echo json_encode(array_map(function($mi) {
    return ['product_name' => $mi['product_name'], 'brand' => $mi['brand'] ?? ''];
}, $masterItems)); ?>;
</script>

<?php include __DIR__ . '/../includes/flash.php'; ?>

<section class="inventory-section suggest-section">

    <div class="section-header">
        <div class="section-title">Receive Stock</div>
    </div>

    <?php if (user_can('movement.manage')): ?>
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/process-stock-in.php" class="wizard-body" id="stockForm" enctype="multipart/form-data">

        <!-- Inbound Mode Selector -->
        <div class="inbound-mode-selector" style="display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
            <label class="inbound-mode-option" id="modeOptExisting" style="cursor:pointer; display:flex; align-items:center; gap:12px; padding:12px 18px; border:2px solid var(--primary, #4f46e5); border-radius:10px; background:var(--card-bg, #fff); transition:all 0.2s;">
                <input type="radio" name="inbound_type" value="existing" checked onchange="switchInboundMode('existing')" style="accent-color:var(--primary, #4f46e5); width:18px; height:18px;">
                <div>
                    <div style="font-weight:700; color:var(--text, #1e293b);">Add to Existing Lot</div>
                    <div style="font-size:12px; color:var(--muted, #64748b);">Receive more units into an existing inventory batch</div>
                </div>
            </label>
            <label class="inbound-mode-option" id="modeOptNew" style="cursor:pointer; display:flex; align-items:center; gap:12px; padding:12px 18px; border:2px solid var(--border-color, #e2e8f0); border-radius:10px; background:var(--card-bg, #fff); transition:all 0.2s;">
                <input type="radio" name="inbound_type" value="new_lot" onchange="switchInboundMode('new_lot')" style="accent-color:var(--primary, #4f46e5); width:18px; height:18px;">
                <div>
                    <div style="font-weight:700; color:var(--text, #1e293b);">Create / Add as New Lot</div>
                    <div style="font-size:12px; color:var(--muted, #64748b);">Register a new batch with unique barcode &amp; location</div>
                </div>
            </label>
        </div>

        <!-- Panel 1: Existing Lot -->
        <div id="panelExistingLot">
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
        </div>

        <!-- Panel 2: New Lot -->
        <div id="panelNewLot" style="display:none;">
            <input type="hidden" name="photo" id="photoData">

            <div class="form-grid">
                <div class="field">
                    <label for="product_name">Product Name *</label>
                    <div class="suggest-wrap">
                        <input type="text" id="product_name" name="product_name" placeholder="e.g. Network Switch" autocomplete="off">
                        <div class="suggest-box" id="productSuggest"></div>
                    </div>
                    <div class="error-msg">Product name is required.</div>
                </div>

                <div class="field">
                    <label for="brand">Brand</label>
                    <?php include __DIR__ . '/../includes/brand-select.php'; ?>
                </div>

                <div class="field">
                    <label for="new_quantity">Quantity *</label>
                    <input type="number" id="new_quantity" name="new_quantity" min="1" value="1">
                </div>

                <div class="field">
                    <?php
                    $locPrefix = 'loc';
                    $locUpdateFn = 'updateLocation()';
                    $locHiddenName = 'location';
                    $locHiddenId = 'location';
                    include __DIR__ . '/../includes/location-picker.php';
                    ?>
                </div>

                <div class="field full">
                    <label for="new_note">Note</label>
                    <input type="text" id="new_note" name="new_note" placeholder="Optional (e.g. initial shipment from supplier)">
                </div>
            </div>

            <div class="photo-area" style="margin-top:16px;">
                <div class="photo-preview-box">
                    <span class="placeholder" id="photoPlaceholder">No photo yet.<br><br>Use the buttons to take or upload one.</span>
                    <img id="photoPreview" src="" alt="Item photo" style="display:none;">
                </div>
                <div class="photo-actions">
                    <button type="button" class="btn secondary photo-btn" onclick="triggerFilePick()">Upload Photo</button>
                    <button type="button" class="btn upload photo-btn" onclick="triggerCapturePick()">Take Photo</button>
                    <input type="file" id="photoFile" accept="image/*" style="display:none;">
                    <input type="file" id="photoCaptureFile" accept="image/*" capture="environment" style="display:none;">
                </div>
            </div>
        </div>

        <div class="add-row" style="margin-top:20px;">
            <button type="submit" class="btn primary" id="submitBtn">Receive Stock</button>
        </div>

    </form>
    <?php else: ?>
        <p style="color:var(--muted);">You have view-only access. Stock movements require a technician account or higher.</p>
    <?php endif; ?>

</section>

<div id="movementItemListSection">
    <section class="inventory-section" id="inventory" style="margin-top:20px;">
        <?php
        $autofillTitle = 'Autofill the stock-in form with this item';
        include __DIR__ . '/../includes/movement-item-list.php';
        ?>
    </section>
</div>

<?php include __DIR__ . '/../includes/scan-modal.php'; ?>
<?php include __DIR__ . '/../includes/inventory-card.php'; ?>
<?php include __DIR__ . '/../includes/print-lot-modal.php'; ?>

<script>
    <?php include __DIR__ . '/../includes/stock-items-data.php'; ?>

    window.switchInboundMode = function (mode) {
        const isNew = mode === 'new_lot';
        const panelExisting = document.getElementById("panelExistingLot");
        const panelNew = document.getElementById("panelNewLot");
        const optExisting = document.getElementById("modeOptExisting");
        const optNew = document.getElementById("modeOptNew");
        const moveList = document.getElementById("movementItemListSection");
        const itemSearch = document.getElementById("stockItemSearch");
        const qtyExisting = document.getElementById("quantity");
        const prodName = document.getElementById("product_name");
        const qtyNew = document.getElementById("new_quantity");

        if (isNew) {
            panelExisting.style.display = "none";
            panelNew.style.display = "block";
            optExisting.style.borderColor = "var(--border-color, #e2e8f0)";
            optNew.style.borderColor = "var(--primary, #4f46e5)";
            if (moveList) moveList.style.display = "none";
            if (itemSearch) itemSearch.removeAttribute("required");
            if (qtyExisting) qtyExisting.removeAttribute("required");
            if (prodName) prodName.setAttribute("required", "required");
            if (qtyNew) qtyNew.setAttribute("required", "required");
        } else {
            panelExisting.style.display = "block";
            panelNew.style.display = "none";
            optExisting.style.borderColor = "var(--primary, #4f46e5)";
            optNew.style.borderColor = "var(--border-color, #e2e8f0)";
            if (moveList) moveList.style.display = "block";
            if (itemSearch) itemSearch.setAttribute("required", "required");
            if (qtyExisting) qtyExisting.setAttribute("required", "required");
            if (prodName) prodName.removeAttribute("required");
            if (qtyNew) qtyNew.removeAttribute("required");
        }
    };

    // Scanning selects the matched item into the form and shows its card.
    window.setScanHandler(function (item) {
        // If in new_lot mode, switch back to existing lot mode when scanning an item
        const existingRadio = document.querySelector('input[name="inbound_type"][value="existing"]');
        if (existingRadio && !existingRadio.checked) {
            existingRadio.checked = true;
            window.switchInboundMode('existing');
        }
        document.getElementById("stockItemId").value = item.id;
        const input = document.getElementById("stockItemSearch");
        input.value = item.name + (item.brand ? "  (" + item.brand + ")" : "");
        window.showItemCard(item);
        return true;
    });

    // The "Inbound" row button fills the item form with the row's item.
    window.pickStockItem = function (id) {
        const existingRadio = document.querySelector('input[name="inbound_type"][value="existing"]');
        if (existingRadio && !existingRadio.checked) {
            existingRadio.checked = true;
            window.switchInboundMode('existing');
        }
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
