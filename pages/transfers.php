<?php
$pageTitle = 'Transfers';
$activeNav = 'transfers';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/inventory-data.php';
require_staff();

$mode   = 'transfers';
$state  = inventory_state_from_request();
$rows   = [];
$total  = 0;
$facets = ['brand' => [], 'shelf' => [], 'row' => [], 'col' => []];
$items  = [];
$dbError = '';

try {
    $pdo = db();
    $result = inventory_fetch($pdo, $mode, $state, false);
    $rows  = $result['rows'];
    $total = $result['total'];
    $state['page'] = $result['page'];
    $state['size'] = $result['size'];
    $facets = inventory_facets($pdo, $mode);
} catch (Exception $e) {
    $rows = [];
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
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/process-transfer.php" class="wizard-body" id="transferForm" onsubmit="updateTransferLocation()">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="unit_id" id="transferUnitId" value="">

        <div class="form-grid">

            <?php include __DIR__ . '/../includes/item-search-field.php'; ?>

            <div class="field">
                <span class="field-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text);">Current Location</span>
                <div class="dd" id="transferCurrentLocation" style="padding:10px 12px;">—</div>
            </div>

            <?php include __DIR__ . '/../includes/item-card-select.php'; ?>

            <div class="field">
                <label for="note">Note</label>
                <input type="text" id="note" name="note" placeholder="Optional (e.g. consolidating shelf 2)">
            </div>

            <!-- Destination Location Type Selector -->
            <div class="field full">
                <span class="field-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text);">Destination Location *</span>
                <div style="display:flex; gap:12px; margin-top:4px; flex-wrap:wrap;">
                    <label style="display:flex; align-items:center; gap:8px; padding:8px 16px; border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; font-size:13px; font-weight:600; background:#ffffff;">
                        <input type="radio" name="transfer_loc_type" value="shelf" checked onchange="toggleTransferLocationType(this.value)" style="width:auto; height:auto; accent-color:var(--blue);">
                        <span>Put in shelf</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; padding:8px 16px; border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; font-size:13px; font-weight:600; background:#ffffff;">
                        <input type="radio" name="transfer_loc_type" value="outside" onchange="toggleTransferLocationType(this.value)" style="width:auto; height:auto; accent-color:var(--blue);">
                        <span>Put outside shelf</span>
                    </label>
                </div>
            </div>

            <!-- Shelf Location Picker -->
            <div id="transferShelfGroup" class="field full">
                <span class="field-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text);">Destination Shelf *</span>
                <?php
                $locPrefix = 'transfer';
                $locUpdateFn = 'updateTransferLocation()';
                $locHiddenName = 'destination';
                $locHiddenId = 'transferDestination';
                include __DIR__ . '/../includes/location-picker.php';
                ?>
                <small style="color:var(--muted); font-size:11.5px; margin-top:4px;">(all 3 fields are required)</small>
            </div>

            <!-- Outside Shelf Free-Text Destination -->
            <div id="transferOutsideGroup" class="field full" style="display:none;">
                <label for="transferDestinationOutside">Destination Location *</label>
                <input type="text" id="transferDestinationOutside" name="destination_outside" placeholder="e.g. Ruang A, Staging Area" maxlength="100" oninput="updateTransferLocation()">
            </div>
                        
        </div>

        <div class="add-row">
            <button type="submit" class="btn primary">Transfer Item</button>
        </div>

    </form>
    <?php else: ?>
        <p style="color:var(--muted);">You have view-only access.</p>
    <?php endif; ?>

</section>

<section class="inventory-section" id="inventory" style="margin-top:20px;">

    <?php
    $emptyMessage = 'No items registered yet.';
    include __DIR__ . '/../includes/inventory-table.php';
    ?>

</section>

<?php include __DIR__ . '/../includes/inventory-card.php'; ?>

<?php include __DIR__ . '/../includes/scan-modal.php'; ?>
<?php include __DIR__ . '/../includes/print-lot-modal.php'; ?>

<script>
    // Scanning selects the matched item or unit, fills the transfer card, and shows the
    // current location.
    window.setScanHandler(function (item) {
        document.getElementById("stockItemId").value = item.id;
        const uInput = document.getElementById("transferUnitId");
        if (uInput) uInput.value = "";
        const input = document.getElementById("stockItemSearch");
        input.value = item.name + (item.brand ? "  (" + item.brand + ")" : "");
        const cur = document.getElementById("transferCurrentLocation");
        if (cur) cur.textContent = item.location || "—";
        window.showItemCard(item);
        return true;
    });

    // The "Transfer" row button fills the item form with the row's item.
    window.pickStockItem = function (id) {
        fetch("<?php echo BASE_URL; ?>/actions/suggest.php?type=get_item&id=" + encodeURIComponent(id))
            .then(res => res.json())
            .then(data => {
                if (!data.ok || !data.item) {
                    alert("Could not retrieve item details.");
                    return;
                }
                const it = data.item;
                document.getElementById("stockItemId").value = it.id;
                const uInput = document.getElementById("transferUnitId");
                if (uInput) uInput.value = "";
                const input = document.getElementById("stockItemSearch");
                if (input) input.value = it.name + (it.brand ? "  (" + it.brand + ")" : "");
                const cur = document.getElementById("transferCurrentLocation");
                if (cur) cur.textContent = it.location || "—";
                window.showItemCard(it);
                const form = document.getElementById("transferForm");
                if (form) form.scrollIntoView({ behavior: "smooth" });
            })
            .catch(() => {
                alert("Error retrieving item details.");
            });
    };

    window.toggleTransferLocationType = function (type) {
        const shelfGrp = document.getElementById("transferShelfGroup");
        const outGrp = document.getElementById("transferOutsideGroup");
        if (type === "outside") {
            shelfGrp.style.display = "none";
            outGrp.style.display = "block";
            const outInput = document.getElementById("transferDestinationOutside");
            if (outInput) outInput.focus();
        } else {
            shelfGrp.style.display = "block";
            outGrp.style.display = "none";
        }
        window.updateTransferLocation();
    };

    window.updateTransferLocation = function () {
        const locType = document.querySelector('input[name="transfer_loc_type"]:checked')?.value || "shelf";
        const hidden = document.getElementById("transferDestination");
        if (!hidden) return;

        if (locType === "outside") {
            const outVal = (document.getElementById("transferDestinationOutside")?.value || "").trim();
            hidden.value = outVal;
        } else {
            const sEl = document.getElementById("transferShelf");
            const rEl = document.getElementById("transferRow");
            const cEl = document.getElementById("transferCol");
            const s = sEl ? sEl.value.trim() : "";
            const r = rEl ? rEl.value.trim() : "";
            const c = cEl ? cEl.value.trim() : "";
            if (s && r && c) {
                const pad = (v) => v.replace(/\D/g, "").padStart(2, "0");
                hidden.value = "S" + pad(s) + "-R" + pad(r) + "-C" + pad(c);
            } else {
                hidden.value = "";
            }
        }
    };

    document.addEventListener("DOMContentLoaded", function () {
        const form = document.getElementById("transferForm");
        if (form) {
            form.addEventListener("submit", function () {
                window.updateTransferLocation();
            });
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
