<?php
$pageTitle = 'Stock In';
$activeNav = 'stock-in';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/inventory-data.php';
require_staff();

$mode   = 'stock-in';
$state  = inventory_state_from_request();
$rows   = [];
$total  = 0;
$facets = ['brand' => [], 'shelf' => [], 'row' => [], 'col' => []];
$items  = [];
$masterItems = [];
$dbError = '';

try {
    $pdo = db();
    purge_stale_staged_units($pdo, 6);
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

<section class="inventory-section suggest-section" id="stockInWizardSection">

    <div class="section-header">
        <div class="section-title">Receive Stock</div>
        <div id="wizardResetWrap" style="display:none;">
            <button type="button" class="btn secondary sm" onclick="resetInboundWizard(true)">Start Fresh</button>
        </div>
    </div>

    <!-- Stepper Progress Header -->
    <div class="inbound-stepper-wrap" style="padding:16px 20px; border-bottom:1px solid var(--border); background:var(--surface-soft, #f8fafc); overflow:hidden;">
        <div class="inbound-steps" style="display:flex; justify-content:space-between; align-items:flex-start; max-width:640px; margin:0 auto; position:relative;">
            <div class="inbound-step-line" style="position:absolute; top:17px; left:16.66%; right:16.66%; height:2px; background:var(--border, #e2e8f0); z-index:1;"></div>
            <div class="inbound-step-line-active" id="stepperProgressLine" style="position:absolute; top:17px; left:16.66%; width:0%; height:2px; background:var(--blue, #2563eb); z-index:2; transition:width 0.3s ease;"></div>

            <!-- Step 1 Indicator -->
            <div class="inbound-step-item active" id="stepIndicator1" style="position:relative; z-index:3; display:flex; flex-direction:column; align-items:center; gap:6px; background:transparent; padding:0 4px; flex:1; max-width:120px;">
                <div class="step-circle" id="stepCircle1" style="width:34px; height:34px; border-radius:50%; background:var(--blue, #2563eb); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; box-shadow:0 0 0 4px #dbeafe;">1</div>
                <div class="step-title" style="font-size:12px; font-weight:600; color:var(--text, #1e293b); text-align:center;">Item Details</div>
            </div>

            <!-- Step 2 Indicator -->
            <div class="inbound-step-item" id="stepIndicator2" style="position:relative; z-index:3; display:flex; flex-direction:column; align-items:center; gap:6px; background:transparent; padding:0 4px; flex:1; max-width:120px;">
                <div class="step-circle" id="stepCircle2" style="width:34px; height:34px; border-radius:50%; background:#fff; border:2px solid var(--border, #cbd5e1); color:var(--muted, #64748b); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px;">2</div>
                <div class="step-title" style="font-size:12px; font-weight:600; color:var(--muted, #64748b); text-align:center;">Print Codes</div>
            </div>

            <!-- Step 3 Indicator -->
            <div class="inbound-step-item" id="stepIndicator3" style="position:relative; z-index:3; display:flex; flex-direction:column; align-items:center; gap:6px; background:transparent; padding:0 4px; flex:1; max-width:120px;">
                <div class="step-circle" id="stepCircle3" style="width:34px; height:34px; border-radius:50%; background:#fff; border:2px solid var(--border, #cbd5e1); color:var(--muted, #64748b); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px;">3</div>
                <div class="step-title" style="font-size:12px; font-weight:600; color:var(--muted, #64748b); text-align:center;">Location &amp; Photo</div>
            </div>
        </div>
    </div>

    <?php if (user_can('movement.manage')): ?>
    <div class="wizard-body" id="stockWizardBody">

        <!-- Disconnection / Recovery Alert Banner -->
        <div id="wizardResumeBanner" style="display:none; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px 16px; margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div style="font-size:13px; color:#1e40af;">
                    <strong>Incomplete inbound session detected.</strong> You were on <span id="resumeBannerStepText" style="font-weight:700;">Step 2</span>.
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn primary sm" onclick="acceptResumeSession()">Resume</button>
                    <button type="button" class="btn secondary sm" onclick="discardResumeSession()">Discard</button>
                </div>
            </div>
        </div>

        <!-- ========================================================
             STEP 1: ITEM DETAILS (Existing Lot or New Lot)
             ======================================================== -->
        <div id="stepContainer1" class="wizard-step-container">

            <!-- Inbound Mode Selector -->
            <div class="inbound-mode-selector" style="display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
                <label class="inbound-mode-option" id="modeOptExisting" style="cursor:pointer; display:flex; align-items:center; gap:12px; padding:12px 18px; border:2px solid var(--primary, --blue); border-radius:10px; background:var(--card-bg, #fff); transition:all 0.2s;">
                    <input type="radio" name="inbound_type" value="existing" checked onchange="switchInboundMode('existing')" style="accent-color:var(--primary, --blue); width:18px; height:18px;">
                    <div>
                        <div style="font-weight:700; color:var(--text, #1e293b);">Add to Existing Lot</div>
                        <div style="font-size:12px; color:var(--muted, #64748b);">Receive more units into an existing inventory batch</div>
                    </div>
                </label>
                <label class="inbound-mode-option" id="modeOptNew" style="cursor:pointer; display:flex; align-items:center; gap:12px; padding:12px 18px; border:2px solid var(--border-color, --blue); border-radius:10px; background:var(--card-bg, #fff); transition:all 0.2s;">
                    <input type="radio" name="inbound_type" value="new_lot" onchange="switchInboundMode('new_lot')" style="accent-color:var(--primary, --blue); width:18px; height:18px;">
                    <div>
                        <div style="font-weight:700; color:var(--text, #1e293b);">Create / Add as New Lot</div>
                        <div style="font-size:12px; color:var(--muted, #64748b);">Register a new batch with unique barcode &amp; location</div>
                    </div>
                </label>
            </div>

            <!-- Panel 1: Existing Lot Form -->
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

            <!-- Panel 2: New Lot Form -->
            <div id="panelNewLot" style="display:none;">
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
                        <label for="new_note">Note</label>
                        <input type="text" id="new_note" name="new_note" placeholder="Optional (e.g. initial shipment from supplier)">
                    </div>
                </div>
            </div>

            <div class="add-row" style="margin-top:24px; display:flex; justify-content:flex-end;">
                <button type="button" class="btn primary" id="btnStep1Ok" onclick="submitStep1()">Next</button>
            </div>

        </div>

        <!-- ========================================================
             STEP 2: BARCODE & QR CODE PRINTING
             ======================================================== -->
        <div id="stepContainer2" class="wizard-step-container" style="display:none;">

            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 16px; margin-bottom:18px; color:#166534; font-size:13px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                <div>
                    <strong>Barcode and unit QR codes generated.</strong>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn secondary sm" onclick="printStep2Labels('lot')">Print Lot Barcode</button>
                    <button type="button" class="btn primary sm" onclick="printStep2Labels('all')">Print All Labels (Barcode + QRs)</button>
                </div>
            </div>

            <!-- Staged Lot Summary Card -->
            <div style="background:#fff; border:1px solid var(--border); border-radius:8px; padding:16px; margin-bottom:18px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                    <div>
                        <h3 id="stagedLotTitle" style="margin:0 0 4px; font-size:16px; color:var(--text);">—</h3>
                        <div id="stagedLotSubtitle" style="font-size:13px; color:var(--muted);">—</div>
                    </div>
                    <span class="status available" id="stagedLotBadge">Available</span>
                </div>

                <!-- Lot Barcode Display Box -->
                <div style="border:1.5px dashed var(--border); border-radius:8px; padding:14px; text-align:center; background:#fafbfc; margin-bottom:16px;" id="stagedBarcodeSection">
                    <div style="font-weight:700; font-size:14px; margin-bottom:2px;" id="stagedBarcodeItemTitle">—</div>
                    <div style="font-size:12px; color:var(--muted); margin-bottom:8px;" id="stagedBarcodeMeta">—</div>
                    <svg id="stagedBarcodeSvg" style="margin:0 auto; max-width:280px; height:60px;"></svg>
                </div>

                <!-- Unit QR Codes Grid -->
                <div style="font-weight:700; font-size:13px; color:var(--text); margin-bottom:10px;">
                    Newly Provisioned Unit QR Codes (<span id="stagedUnitCount">0</span> units):
                </div>
                <div id="stagedUnitsGrid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(170px, 1fr)); gap:12px; max-height:400px; overflow-y:auto; padding:4px;">
                    <!-- Dynamically populated -->
                </div>
            </div>

            <div class="add-row" style="margin-top:24px; display:flex; justify-content:space-between; align-items:center;">
                <button type="button" class="btn secondary" onclick="goToStep(1)">Back</button>
                <button type="button" class="btn primary" id="btnStep2Ok" onclick="submitStep2()">Next</button>
            </div>

        </div>

        <!-- ========================================================
             STEP 3: LOCATION & PHOTO PLACEMENT
             ======================================================== -->
        <div id="stepContainer3" class="wizard-step-container" style="display:none;">

            <div class="form-grid">
                <!-- Location Type Radio Selector -->
                <div class="field full">
                    <span class="field-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text);">Warehouse Location *</span>
                    <div style="display:flex; gap:12px; margin-top:4px; flex-wrap:wrap;">
                        <label style="display:flex; align-items:center; gap:8px; padding:8px 16px; border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; font-size:13px; font-weight:600; background:#ffffff;">
                            <input type="radio" name="location_type_step3" value="shelf" checked onchange="toggleStep3LocationType(this.value)" style="width:auto; height:auto; accent-color:var(--blue);">
                            <span>Put in shelf</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; padding:8px 16px; border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; font-size:13px; font-weight:600; background:#ffffff;">
                            <input type="radio" name="location_type_step3" value="outside" onchange="toggleStep3LocationType(this.value)" style="width:auto; height:auto; accent-color:var(--blue);">
                            <span>Put outside shelf</span>
                        </label>
                    </div>
                </div>

                <!-- Shelf Location Group (S-R-C) -->
                <div id="step3ShelfGroup" class="field full">
                    <span class="field-label" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:var(--text);">Location *</span>
                    <?php
                    $locPrefix = 'locStep3';
                    $locUpdateFn = 'updateStep3Location()';
                    $locHiddenName = 'location_step3';
                    $locHiddenId = 'location_step3';
                    include __DIR__ . '/../includes/location-picker.php';
                    ?>
                    <small style="color:var(--muted); font-size:11.5px; margin-top:4px;">(all 3 fields are required)</small>
                </div>

                <!-- Outside Shelf Free-Text Input Group -->
                <div id="step3OutsideGroup" class="field full" style="display:none;">
                    <label for="locStep3Outside">Location *</label>
                    <input type="text" id="locStep3Outside" placeholder="e.g. Ruang A" maxlength="100" oninput="updateStep3Location()">
                </div>
            </div>

            <!-- Photo Capture Area -->
            <input type="hidden" id="photoDataStep3">
            <div class="photo-area" style="margin-top:18px;">
                <label style="font-weight:600; font-size:13px; color:var(--text); display:block; margin-bottom:6px;">Photo</label>
                <div class="photo-preview-box" style="max-width:360px; height:200px;">
                    <span class="placeholder" id="photoPlaceholderStep3">No photo yet.<br><br>Take or upload a photo of the item on the shelf.</span>
                    <img id="photoPreviewStep3" src="" alt="Item photo" style="display:none; width:100%; height:100%; object-fit:cover; border-radius:6px;">
                </div>
                <div class="photo-actions" style="margin-top:10px;">
                    <button type="button" class="btn secondary photo-btn" onclick="triggerStep3FilePick()">Upload Photo</button>
                    <button type="button" class="btn upload photo-btn" onclick="triggerStep3CapturePick()">Take Photo</button>
                    <input type="file" id="photoFileStep3" accept="image/*" style="display:none;">
                    <input type="file" id="photoCaptureFileStep3" accept="image/*" capture="environment" style="display:none;">
                </div>
            </div>

            <div class="add-row" style="margin-top:24px; display:flex; justify-content:space-between; align-items:center;">
                <button type="button" class="btn secondary" onclick="goToStep(2)">Back</button>
                <button type="button" class="btn primary" id="btnStep3Finish" onclick="submitStep3()">Finish</button>
            </div>

        </div>

    </div>
    <?php else: ?>
        <p style="color:var(--muted); padding:24px;">You have view-only access.</p>
    <?php endif; ?>

</section>

<div id="movementItemListSection">
    <section class="inventory-section" id="inventory" style="margin-top:20px;">
        <?php
        $emptyMessage = 'No items registered yet.';
        include __DIR__ . '/../includes/inventory-table.php';
        ?>
    </section>
</div>

<?php include __DIR__ . '/../includes/scan-modal.php'; ?>
<?php include __DIR__ . '/../includes/inventory-card.php'; ?>
<?php include __DIR__ . '/../includes/print-lot-modal.php'; ?>

<!-- Print Styles for Step 2 Direct Print -->
<style>
@media print {
    body.inbound-print-lot #stockInWizardSection #stagedUnitsGrid,
    body.inbound-print-lot .inbound-stepper-wrap,
    body.inbound-print-lot .section-header,
    body.inbound-print-lot .add-row,
    body.inbound-print-lot #movementItemListSection,
    body.inbound-print-lot .sidebar,
    body.inbound-print-lot .topbar {
        display: none !important;
    }

    body.inbound-print-all .inbound-stepper-wrap,
    body.inbound-print-all .section-header,
    body.inbound-print-all .add-row,
    body.inbound-print-all #movementItemListSection,
    body.inbound-print-all .sidebar,
    body.inbound-print-all .topbar {
        display: none !important;
    }

    body.inbound-print-all #stagedUnitsGrid {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 16px !important;
        max-height: none !important;
        overflow: visible !important;
    }

    body.inbound-print-all .staged-unit-card {
        page-break-inside: avoid !important;
        border: 1px dashed #cbd5e1 !important;
        padding: 10px !important;
    }
}
</style>

<script>
    // Global Wizard State & Draft Memory Key
    const DRAFT_KEY = "stock_in_wizard_draft_v1";
    let wizardData = {
        step: 1,
        inbound_type: "existing",
        item_id: null,
        product_name: "",
        brand: "",
        quantity: 1,
        note: "",
        lot_id: null,
        barcode: "",
        units: [],
        current_location: "",
        location: "",
        photo: ""
    };

    // ---- Mode Switcher ----
    window.switchInboundMode = function (mode) {
        const isNew = mode === 'new_lot';
        wizardData.inbound_type = mode;
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
            if (panelExisting) panelExisting.style.display = "none";
            if (panelNew) panelNew.style.display = "block";
            if (optExisting) optExisting.style.borderColor = "var(--border-color, #e2e8f0)";
            if (optNew) optNew.style.borderColor = "var(--primary, #4f46e5)";
            if (moveList) moveList.style.display = "none";
            if (itemSearch) itemSearch.removeAttribute("required");
            if (qtyExisting) qtyExisting.removeAttribute("required");
            if (prodName) prodName.setAttribute("required", "required");
            if (qtyNew) qtyNew.setAttribute("required", "required");
        } else {
            if (panelExisting) panelExisting.style.display = "block";
            if (panelNew) panelNew.style.display = "none";
            if (optExisting) optExisting.style.borderColor = "var(--primary, #4f46e5)";
            if (optNew) optNew.style.borderColor = "var(--border-color, #e2e8f0)";
            if (moveList) moveList.style.display = "block";
            if (itemSearch) itemSearch.setAttribute("required", "required");
            if (qtyExisting) qtyExisting.setAttribute("required", "required");
            if (prodName) prodName.removeAttribute("required");
            if (qtyNew) qtyNew.removeAttribute("required");
        }
        saveWizardDraft();
    };

    // ---- Step Navigation & UI Indicators ----
    window.goToStep = function (stepNum) {
        wizardData.step = stepNum;
        saveWizardDraft();

        // Toggle container displays
        document.querySelectorAll(".wizard-step-container").forEach((el) => el.style.display = "none");
        const targetContainer = document.getElementById("stepContainer" + stepNum);
        if (targetContainer) targetContainer.style.display = "block";

        // Update Reset / Start Fresh button
        const resetWrap = document.getElementById("wizardResetWrap");
        if (resetWrap) resetWrap.style.display = stepNum > 1 ? "block" : "none";

        // Hide movement list during Step 2 & 3
        const moveList = document.getElementById("movementItemListSection");
        if (moveList) {
            moveList.style.display = (stepNum === 1 && wizardData.inbound_type === "existing") ? "block" : "none";
        }

        // Update Stepper Line & Circles
        const progressLine = document.getElementById("stepperProgressLine");
        if (progressLine) {
            progressLine.style.width = stepNum === 1 ? "0%" : (stepNum === 2 ? "50%" : "100%");
        }

        for (let i = 1; i <= 3; i++) {
            const circle = document.getElementById("stepCircle" + i);
            const indicator = document.getElementById("stepIndicator" + i);
            if (!circle || !indicator) continue;

            if (i < stepNum) {
                // Completed step
                circle.style.background = "var(--green-ink, #16a34a)";
                circle.style.borderColor = "var(--green-ink, #16a34a)";
                circle.style.color = "#fff";
                circle.innerHTML = "&#10003;";
                circle.style.boxShadow = "none";
            } else if (i === stepNum) {
                // Active step
                circle.style.background = "var(--blue, #2563eb)";
                circle.style.borderColor = "var(--blue, #2563eb)";
                circle.style.color = "#fff";
                circle.textContent = i;
                circle.style.boxShadow = "0 0 0 4px #dbeafe";
            } else {
                // Future step
                circle.style.background = "#fff";
                circle.style.borderColor = "var(--border, #cbd5e1)";
                circle.style.color = "var(--muted, #64748b)";
                circle.textContent = i;
                circle.style.boxShadow = "none";
            }
        }

        // If landing on Step 2, render barcodes & QRs
        if (stepNum === 2) {
            renderStep2Visuals();
        }

        // If landing on Step 3, populate default location
        if (stepNum === 3) {
            populateStep3DefaultLocation();
        }

        window.scrollTo({ top: 0, behavior: "smooth" });
    };

    // ---- STEP 1 SUBMIT: Stages Item & Provisions Barcode/Units ----
    window.submitStep1 = async function () {
        const isNew = wizardData.inbound_type === "new_lot";
        const btn = document.getElementById("btnStep1Ok");

        let payload = {
            inbound_type: wizardData.inbound_type,
            quantity: 1,
            note: ""
        };

        if (isNew) {
            const prodInput = document.getElementById("product_name");
            const brandInput = document.getElementById("brand");
            const qtyInput = document.getElementById("new_quantity");
            const noteInput = document.getElementById("new_note");

            const name = prodInput ? prodInput.value.trim() : "";
            const brand = brandInput ? brandInput.value.trim() : "";
            const qty = qtyInput ? parseInt(qtyInput.value, 10) : 1;
            const note = noteInput ? noteInput.value.trim() : "";

            if (!name) {
                alert("Please enter a product name.");
                if (prodInput) prodInput.focus();
                return;
            }
            if (isNaN(qty) || qty < 1) {
                alert("Quantity must be at least 1.");
                return;
            }

            payload.product_name = name;
            payload.brand = brand;
            payload.quantity = qty;
            payload.note = note;

            wizardData.product_name = name;
            wizardData.brand = brand;
            wizardData.quantity = qty;
            wizardData.note = note;
        } else {
            const itemIdInput = document.getElementById("stockItemId");
            const qtyInput = document.getElementById("quantity");
            const noteInput = document.getElementById("note");

            const itemId = itemIdInput ? parseInt(itemIdInput.value, 10) : 0;
            const qty = qtyInput ? parseInt(qtyInput.value, 10) : 1;
            const note = noteInput ? noteInput.value.trim() : "";

            if (!itemId || itemId <= 0) {
                alert("Please select or scan an existing item.");
                return;
            }
            if (isNaN(qty) || qty < 1) {
                alert("Quantity must be at least 1.");
                return;
            }

            payload.item_id = itemId;
            payload.quantity = qty;
            payload.note = note;

            wizardData.item_id = itemId;
            wizardData.quantity = qty;
            wizardData.note = note;
        }

        // If there was an unfinalized staged session in draft, discard it before initiating a new one
        try {
            const rawDraft = localStorage.getItem(DRAFT_KEY);
            if (rawDraft) {
                const prevDraft = JSON.parse(rawDraft);
                if (prevDraft && prevDraft.lot_id && prevDraft.step > 1) {
                    const unitIds = (prevDraft.units || []).map((u) => u.id).join(",");
                    fetch("<?php echo BASE_URL; ?>/actions/discard-staged-stock-in.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: new URLSearchParams({
                            lot_id: prevDraft.lot_id,
                            inbound_type: prevDraft.inbound_type || "existing",
                            unit_ids: unitIds
                        })
                    }).catch(() => {});
                }
            }
        } catch (_) {}

        // Hide resume banner if present
        const resumeBanner = document.getElementById("wizardResumeBanner");
        if (resumeBanner) resumeBanner.style.display = "none";

        if (btn) {
            btn.disabled = true;
            btn.textContent = "Provisioning Barcode & QRs...";
        }

        try {
            const res = await fetch("<?php echo BASE_URL; ?>/actions/stage-stock-in.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams(payload)
            });
            const data = await res.json();

            if (!data.ok) {
                alert(data.message || "Failed to stage inbound lot.");
                return;
            }

            // Capture returned staged details
            wizardData.lot_id = data.lot_id;
            wizardData.barcode = data.barcode;
            wizardData.product_name = data.name;
            wizardData.brand = data.brand;
            wizardData.quantity = data.quantity;
            wizardData.current_location = data.current_location || "";
            wizardData.existing_photo = data.photo || "";
            wizardData.photo = data.photo || "";
            wizardData.units = data.units || [];

            goToStep(2);
        } catch (err) {
            alert("Network error while communicating with server.");
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = "Next";
            }
        }
    };

    // ---- STEP 2: Render Barcodes and QR Codes ----
    function renderStep2Visuals() {
        const titleEl = document.getElementById("stagedLotTitle");
        const subtitleEl = document.getElementById("stagedLotSubtitle");
        const itemTitleEl = document.getElementById("stagedBarcodeItemTitle");
        const metaEl = document.getElementById("stagedBarcodeMeta");
        const countEl = document.getElementById("stagedUnitCount");
        const svgEl = document.getElementById("stagedBarcodeSvg");
        const gridEl = document.getElementById("stagedUnitsGrid");

        if (titleEl) titleEl.textContent = wizardData.product_name + (wizardData.brand ? " (" + wizardData.brand + ")" : "");
        if (subtitleEl) subtitleEl.textContent = "Lot ID: #" + wizardData.lot_id + "  ·  Inbound Qty: " + wizardData.quantity + (wizardData.current_location ? "  ·  Existing Loc: " + wizardData.current_location : "");
        if (itemTitleEl) itemTitleEl.textContent = wizardData.product_name + (wizardData.brand ? " - " + wizardData.brand : "");
        if (metaEl) metaEl.textContent = "Barcode: " + wizardData.barcode + (wizardData.current_location ? " | Location: " + wizardData.current_location : "");
        if (countEl) countEl.textContent = (wizardData.units || []).length;

        // Render Barcode
        if (svgEl && wizardData.barcode && window.JsBarcode) {
            svgEl.innerHTML = "";
            try {
                window.JsBarcode("#stagedBarcodeSvg", wizardData.barcode, {
                    format: "EAN13", displayValue: true, width: 2, height: 50, fontSize: 13
                });
            } catch (e) {
                // Fallback to Code 128 if not exact 13 digits
                window.JsBarcode("#stagedBarcodeSvg", wizardData.barcode, {
                    format: "CODE128", displayValue: true, width: 2, height: 50, fontSize: 13
                });
            }
        }

        // Render Unit QR Codes
        if (gridEl) {
            gridEl.innerHTML = "";
            const units = wizardData.units || [];
            if (units.length === 0) {
                gridEl.innerHTML = '<div style="grid-column:1/-1; color:var(--muted); font-size:13px; text-align:center; padding:16px;">No individual unit QR codes provisioned for this lot.</div>';
            } else {
                units.forEach((u) => {
                    const card = document.createElement("div");
                    card.className = "staged-unit-card";
                    card.style.border = "1px solid var(--border)";
                    card.style.borderRadius = "8px";
                    card.style.padding = "10px";
                    card.style.textAlign = "center";
                    card.style.background = "#ffffff";

                    const qrWrap = document.createElement("div");
                    qrWrap.style.display = "flex";
                    qrWrap.style.justifyContent = "center";
                    qrWrap.style.margin = "6px 0";

                    card.innerHTML = `
                        <div style="font-weight:700; font-size:12px; color:var(--ink);">${u.serial}</div>
                        <div style="font-size:10.5px; color:var(--muted);">${wizardData.product_name}</div>
                    `;
                    card.appendChild(qrWrap);

                    const locDiv = document.createElement("div");
                    locDiv.style.fontSize = "10px";
                    locDiv.style.color = "var(--muted)";
                    locDiv.textContent = u.location || wizardData.current_location || "In Warehouse";
                    card.appendChild(locDiv);

                    gridEl.appendChild(card);

                    if (window.QRCode && u.qr) {
                        new window.QRCode(qrWrap, { text: u.qr, width: 100, height: 100 });
                    }
                });
            }
        }
    }

    // Direct Printing from Step 2
    window.printStep2Labels = function (scope) {
        document.body.classList.remove("inbound-print-lot", "inbound-print-all");
        if (scope === "lot") {
            document.body.classList.add("inbound-print-lot");
        } else {
            document.body.classList.add("inbound-print-all");
        }
        setTimeout(() => {
            window.print();
            setTimeout(() => {
                document.body.classList.remove("inbound-print-lot", "inbound-print-all");
            }, 500);
        }, 80);
    };

    // Step 2 Next
    window.submitStep2 = function () {
        goToStep(3);
    };

    // ---- STEP 3: Location and Photo Placement ----
    window.toggleStep3LocationType = function (type) {
        const shelfGroup = document.getElementById("step3ShelfGroup");
        const outsideGroup = document.getElementById("step3OutsideGroup");
        wizardData.location_type = type;

        if (type === "outside") {
            if (shelfGroup) shelfGroup.style.display = "none";
            if (outsideGroup) outsideGroup.style.display = "block";
            document.getElementById("locStep3Outside")?.focus();
        } else {
            if (shelfGroup) shelfGroup.style.display = "block";
            if (outsideGroup) outsideGroup.style.display = "none";
            document.getElementById("locStep3Shelf")?.focus();
        }
        updateStep3Location();
    };

    function populateStep3DefaultLocation() {
        const curLoc = wizardData.current_location || "";
        const hiddenLoc = document.getElementById("location_step3");

        if (curLoc) {
            if (hiddenLoc) hiddenLoc.value = curLoc;
            wizardData.location = curLoc;

            const m = curLoc.match(/^S(\d{1,2})-R(\d{1,2})-C(\d{1,2})$/i);
            if (m) {
                const radioShelf = document.querySelector('input[name="location_type_step3"][value="shelf"]');
                if (radioShelf) radioShelf.checked = true;
                toggleStep3LocationType("shelf");

                const s = document.getElementById("locStep3Shelf");
                const r = document.getElementById("locStep3Row");
                const c = document.getElementById("locStep3Col");
                if (s) s.value = m[1].padStart(2, "0");
                if (r) r.value = m[2].padStart(2, "0");
                if (c) c.value = m[3].padStart(2, "0");
            } else {
                const radioOutside = document.querySelector('input[name="location_type_step3"][value="outside"]');
                if (radioOutside) radioOutside.checked = true;
                toggleStep3LocationType("outside");

                const outsideInput = document.getElementById("locStep3Outside");
                if (outsideInput) outsideInput.value = curLoc;
            }
        } else {
            const radioShelf = document.querySelector('input[name="location_type_step3"][value="shelf"]');
            if (radioShelf) radioShelf.checked = true;
            toggleStep3LocationType("shelf");
        }

        // Pre-display existing lot photo if present and no newer photo selected
        const existingPhoto = wizardData.existing_photo || wizardData.photo || "";
        const prev = document.getElementById("photoPreviewStep3");
        const ph = document.getElementById("photoPlaceholderStep3");
        const hiddenPhoto = document.getElementById("photoDataStep3");
        if (existingPhoto) {
            if (prev) {
                const photoSrc = (typeof window.resolvePhotoUrl === 'function')
                    ? window.resolvePhotoUrl(existingPhoto)
                    : (existingPhoto.startsWith("http") || existingPhoto.startsWith("data:") || existingPhoto.startsWith("/") ? existingPhoto : "<?php echo BASE_URL; ?>/" + existingPhoto.replace(/^\/+/, ""));
                prev.src = photoSrc;
                prev.style.display = "block";
            }
            if (ph) ph.style.display = "none";
            if (hiddenPhoto && !hiddenPhoto.value && existingPhoto.startsWith("data:")) {
                hiddenPhoto.value = existingPhoto;
            }
        }
    }

    window.updateStep3Location = function () {
        const type = document.querySelector('input[name="location_type_step3"]:checked')?.value || "shelf";
        const hidden = document.getElementById("location_step3");

        if (type === "outside") {
            const customVal = (document.getElementById("locStep3Outside")?.value || "").trim();
            if (hidden) hidden.value = customVal;
            wizardData.location = customVal;
            wizardData.location_type = "outside";
        } else {
            const s = (document.getElementById("locStep3Shelf")?.value || "").trim();
            const r = (document.getElementById("locStep3Row")?.value || "").trim();
            const c = (document.getElementById("locStep3Col")?.value || "").trim();

            if (s && r && c) {
                const loc = "S" + s.padStart(2, "0") + "-R" + r.padStart(2, "0") + "-C" + c.padStart(2, "0");
                if (hidden) hidden.value = loc;
                wizardData.location = loc;
            } else {
                if (hidden) hidden.value = "";
                wizardData.location = "";
            }
            wizardData.location_type = "shelf";
        }
        saveWizardDraft();
    };

    // Step 3 Photo Pickers
    window.triggerStep3FilePick = function () {
        document.getElementById("photoFileStep3")?.click();
    };

    window.triggerStep3CapturePick = function () {
        document.getElementById("photoCaptureFileStep3")?.click();
    };

    function canvasToDataUrlStep3(img) {
        const maxW = 768;
        const canvas = document.createElement("canvas");
        const ratio = Math.min(1, maxW / img.naturalWidth);
        canvas.width = img.naturalWidth * ratio;
        canvas.height = img.naturalHeight * ratio;
        canvas.getContext("2d").drawImage(img, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL("image/jpeg", 0.65);
    }

    ["photoFileStep3", "photoCaptureFileStep3"].forEach((id) => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener("change", function () {
                const file = this.files && this.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = new Image();
                    img.onload = () => {
                        const dataUrl = canvasToDataUrlStep3(img);
                        wizardData.photo = dataUrl;
                        const hidden = document.getElementById("photoDataStep3");
                        const prev = document.getElementById("photoPreviewStep3");
                        const ph = document.getElementById("photoPlaceholderStep3");
                        if (hidden) hidden.value = dataUrl;
                        if (prev) { prev.src = dataUrl; prev.style.display = "block"; }
                        if (ph) ph.style.display = "none";
                        saveWizardDraft();
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        }
    });

    // STEP 3 FINISH SUBMISSION
    window.submitStep3 = async function () {
        const type = document.querySelector('input[name="location_type_step3"]:checked')?.value || "shelf";
        let loc = "";

        if (type === "outside") {
            loc = (document.getElementById("locStep3Outside")?.value || "").trim();
            if (!loc) {
                alert("Please enter the custom warehouse area or location name.");
                document.getElementById("locStep3Outside")?.focus();
                return;
            }
        } else {
            const s = (document.getElementById("locStep3Shelf")?.value || "").trim();
            const r = (document.getElementById("locStep3Row")?.value || "").trim();
            const c = (document.getElementById("locStep3Col")?.value || "").trim();

            if (!s || !r || !c) {
                alert("Please fill out Shelf (S), Row (R), and Column (C) location (e.g. S01-R01-C01).");
                if (!s) document.getElementById("locStep3Shelf")?.focus();
                else if (!r) document.getElementById("locStep3Row")?.focus();
                else if (!c) document.getElementById("locStep3Col")?.focus();
                return;
            }
            loc = "S" + s.padStart(2, "0") + "-R" + r.padStart(2, "0") + "-C" + c.padStart(2, "0");
        }

        const hiddenLoc = document.getElementById("location_step3");
        if (hiddenLoc) hiddenLoc.value = loc;
        wizardData.location = loc;
        wizardData.location_type = type;

        const photoData = wizardData.photo || document.getElementById("photoDataStep3")?.value || "";

        const btn = document.getElementById("btnStep3Finish");
        if (btn) {
            btn.disabled = true;
            btn.textContent = "Finalizing Registration...";
        }

        try {
            const res = await fetch("<?php echo BASE_URL; ?>/actions/finalize-stock-in.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    lot_id: wizardData.lot_id,
                    location: loc,
                    location_type: type,
                    photo: photoData,
                    quantity: wizardData.quantity || 1,
                    note: wizardData.note || '',
                    inbound_type: wizardData.inbound_type || 'existing',
                    unit_ids: (wizardData.units || []).map(u => u.id).join(",")
                })
            });
            const data = await res.json();

            if (!data.ok) {
                alert(data.message || "Failed to finalize stock-in.");
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Finish & Complete Registration";
                }
                return;
            }

            // Successfully finalized: clear draft memory and redirect
            localStorage.removeItem(DRAFT_KEY);
            window.location.href = "<?php echo BASE_URL; ?>/pages/stock-in.php?added=" + encodeURIComponent("Stock-in completed for " + wizardData.product_name + " at location " + loc + ".");
        } catch (err) {
            alert("Network error while finalizing stock-in.");
            if (btn) {
                btn.disabled = false;
                btn.textContent = "Finish & Complete Registration";
            }
        }
    };

    // ---- Disconnection / Draft State Persistence ----
    function saveWizardDraft() {
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(wizardData));
        } catch (e) { /* quota exceeded or privacy mode */ }
    }

    function checkExistingDraft() {
        try {
            const raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return;
            const draft = JSON.parse(raw);
            if (draft && draft.step > 1 && draft.lot_id) {
                const banner = document.getElementById("wizardResumeBanner");
                const bannerText = document.getElementById("resumeBannerStepText");
                if (banner && bannerText) {
                    bannerText.textContent = "Step " + draft.step + " (" + (draft.product_name || "Staged Lot") + ")";
                    banner.style.display = "block";
                }
            }
        } catch (e) {}
    }

    window.acceptResumeSession = function () {
        try {
            const draft = JSON.parse(localStorage.getItem(DRAFT_KEY));
            if (draft) {
                wizardData = draft;
                document.getElementById("wizardResumeBanner").style.display = "none";
                goToStep(draft.step || 2);
            }
        } catch (e) {}
    };

    window.discardResumeSession = function () {
        resetInboundWizard(false);
    };

    window.resetInboundWizard = async function (promptUser) {
        if (promptUser && !confirm("Discard current inbound progress and start fresh?")) return;
        try {
            const rawDraft = localStorage.getItem(DRAFT_KEY);
            if (rawDraft) {
                const draft = JSON.parse(rawDraft);
                if (draft && draft.lot_id && draft.step > 1) {
                    const unitIds = (draft.units || []).map((u) => u.id).join(",");
                    await fetch("<?php echo BASE_URL; ?>/actions/discard-staged-stock-in.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: new URLSearchParams({
                            lot_id: draft.lot_id,
                            inbound_type: draft.inbound_type || "existing",
                            unit_ids: unitIds
                        })
                    }).catch(() => {});
                }
            }
        } catch (_) {}
        localStorage.removeItem(DRAFT_KEY);
        window.location.href = "<?php echo BASE_URL; ?>/pages/stock-in.php";
    };

    // ---- Quick Scanning & Autofill Integration ----
    window.setScanHandler(function (item) {
        const existingRadio = document.querySelector('input[name="inbound_type"][value="existing"]');
        if (existingRadio && !existingRadio.checked) {
            existingRadio.checked = true;
            window.switchInboundMode('existing');
        }
        document.getElementById("stockItemId").value = item.id;
        const input = document.getElementById("stockItemSearch");
        if (input) input.value = item.name + (item.brand ? "  (" + item.brand + ")" : "");
        window.showItemCard(item);
        wizardData.item_id = item.id;
        saveWizardDraft();
        return true;
    });

    window.pickStockItem = function (id) {
        fetch("<?php echo BASE_URL; ?>/actions/suggest.php?type=get_item&id=" + encodeURIComponent(id))
            .then(res => res.json())
            .then(data => {
                if (!data.ok || !data.item) {
                    alert("Could not retrieve item details.");
                    return;
                }
                const it = data.item;
                const existingRadio = document.querySelector('input[name="inbound_type"][value="existing"]');
                if (existingRadio && !existingRadio.checked) {
                    existingRadio.checked = true;
                    window.switchInboundMode('existing');
                }
                document.getElementById("stockItemId").value = it.id;
                const input = document.getElementById("stockItemSearch");
                if (input) input.value = it.name + (it.brand ? "  (" + it.brand + ")" : "");
                window.showItemCard(it);
                wizardData.item_id = it.id;
                saveWizardDraft();
                const form = document.getElementById("stockInWizardSection");
                if (form) form.scrollIntoView({ behavior: "smooth" });
            })
            .catch(() => {
                alert("Error retrieving item details.");
            });
    };

    document.addEventListener("DOMContentLoaded", function () {
        const urlParams = new URLSearchParams(window.location.search);
        const itemId = parseInt(urlParams.get('item_id'), 10);
        if (itemId) {
            window.pickStockItem(itemId);
        } else {
            checkExistingDraft();
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
