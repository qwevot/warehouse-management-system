<?php
$pageTitle = 'Check In';
$activeNav = 'check-in';

require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$role = $user['role'] ?? 'guest';
$company = $user['company'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/flash.php'; ?>

<section class="inventory-section">

    <div class="section-header">
        <div class="section-title">Check In / Location Update</div>
        <button type="button" class="btn primary" onclick="openScanModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" style="margin-right:6px;"><path d="M3 7V5a2 2 0 0 1 2-2h2"></path><path d="M17 3h2a2 2 0 0 1 2 2v2"></path><path d="M21 17v2a2 2 0 0 1-2 2h-2"></path><path d="M7 21H5a2 2 0 0 1-2-2v-2"></path><line x1="7" y1="12" x2="17" y2="12"></line></svg>
            <span>Scan with Camera</span>
        </button>
    </div>

    <div class="wizard-body">
        <p style="color:var(--muted); margin-bottom:20px;">
            Scan a barcode, QR code, or enter a serial number below to record receipt and update the specimen's custody location.
        </p>

        <div style="display:flex; gap:12px; max-width:600px; margin-bottom:24px;">
            <input type="text" id="manualLookupCode" placeholder="Enter Serial Number (SN-...) or Barcode..." class="form-input" style="flex:1;" autocomplete="off">
            <button type="button" class="btn secondary" onclick="lookupManualCode()">Lookup Item</button>
        </div>

        <div id="manualLookupMsg" style="display:none; max-width:600px; margin-bottom:20px; padding:10px 14px; border-radius:8px; font-size:13px;"></div>

        <?php include __DIR__ . '/../includes/item-card-select.php'; ?>
    </div>

</section>

<?php include __DIR__ . '/../includes/scan-modal.php'; ?>
<?php include __DIR__ . '/../includes/checkin-modal.php'; ?>

<script>
    window.setScanHandler(function (item) {
        if (window.openCheckInModal) {
            window.openCheckInModal(item);
        } else {
            window.showItemCard(item);
        }
        return true;
    });

    async function lookupManualCode() {
        const codeInput = document.getElementById("manualLookupCode");
        const msgEl = document.getElementById("manualLookupMsg");
        const code = codeInput ? codeInput.value.trim() : "";
        if (!code) return;

        if (msgEl) {
            msgEl.style.display = "block";
            msgEl.style.background = "#eff6ff";
            msgEl.style.color = "#1e40af";
            msgEl.style.border = "1px solid #bfdbfe";
            msgEl.textContent = "Looking up code: " + code + "...";
        }

        try {
            const res = await fetch("<?php echo BASE_URL; ?>/actions/scan-lookup.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ code: code })
            });
            const data = await res.json();
            if (!data.ok) {
                if (msgEl) {
                    msgEl.style.background = "#fef2f2";
                    msgEl.style.color = "#b91c1c";
                    msgEl.style.border = "1px solid #fecaca";
                    msgEl.textContent = data.message || "Item not found.";
                }
                return;
            }

            if (msgEl) { msgEl.style.display = "none"; }
            if (codeInput) codeInput.value = "";
            window.openCheckInModal(data.item);
        } catch (e) {
            if (msgEl) {
                msgEl.style.background = "#fef2f2";
                msgEl.style.color = "#b91c1c";
                msgEl.style.border = "1px solid #fecaca";
                msgEl.textContent = "Error communicating with server.";
            }
        }
    }

    document.getElementById("manualLookupCode")?.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            lookupManualCode();
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
