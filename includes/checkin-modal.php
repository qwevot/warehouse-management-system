<?php
/**
 * Shared Check-In / Custody Location Modal.
 * Included on pages that perform location updates upon scan or selection.
 */
require_once __DIR__ . '/auth.php';

$currUser = current_user();
$currRole = $currUser['role'] ?? 'guest';
$currComp = $currUser['company'] ?? '';
$isCustomer = ($currRole === 'customer');
?>
<div class="modal-backdrop" id="checkInModal">
    <div class="modal" style="max-width: 520px;">
        <div class="modal-header">
            <div class="modal-title" style="display:flex; align-items:center; gap:8px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" style="color:var(--primary);"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <span>Check In / Update Custody</span>
            </div>
            <button type="button" class="modal-close" onclick="closeCheckInModal()">×</button>
        </div>

        <form id="checkInForm" onsubmit="handleCheckInSubmit(event)">
            <div class="modal-body" style="display:flex; flex-direction:column; gap:16px;">

                <!-- Specimen Info Card -->
                <div style="background:var(--bg-card, #f9fafb); border:1px solid var(--border-color, #e5e7eb); border-radius:8px; padding:12px 14px; font-size:13px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                        <span style="font-weight:600; font-size:14px; color:var(--text, #111827);" id="ciItemName">Item Name</span>
                        <span class="status available" id="ciItemStatus" style="font-size:11px; padding:2px 8px;">Available</span>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; color:var(--muted, #6b7280);">
                        <div><strong style="color:var(--text,#374151);">Brand:</strong> <span id="ciItemBrand">-</span></div>
                        <div><strong style="color:var(--text,#374151);">Identifier:</strong> <span id="ciItemIdentifier">-</span></div>
                    </div>
                    <div style="margin-top:6px; padding-top:6px; border-top:1px dashed var(--border-color, #e5e7eb); color:var(--muted,#6b7280);">
                        <strong style="color:var(--text,#374151);">Current Location:</strong> <span id="ciCurrentLocation" style="color:var(--primary,#2563eb); font-weight:500;">-</span>
                    </div>
                </div>

                <input type="hidden" id="ciInventoryId" name="inventory_id">
                <input type="hidden" id="ciUnitId" name="unit_id">

                <?php if ($isCustomer): ?>
                    <!-- Customer View: Locked Company + Site Input -->
                    <input type="hidden" name="destination_type" value="customer">
                    <div class="field">
                        <label>Company</label>
                        <input type="text" id="ciCustCompanyReadonly" value="<?php echo e($currComp); ?>" readonly style="background:#f3f4f6; color:#4b5563; cursor:not-allowed;" tabindex="-1">
                        <small style="color:var(--muted); font-size:11px;">Fixed to your company profile</small>
                    </div>
                    <div class="field">
                        <label for="ciCustSiteInput">Site Name *</label>
                        <input type="text" id="ciCustSiteInput" name="site" required placeholder="e.g. Jakarta DC 1, Room 302" maxlength="100" autocomplete="off">
                    </div>
                    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:8px 12px; font-size:12px; color:#1e40af;">
                        <strong>Target Location:</strong> <span id="ciCustPreview"><?php echo e($currComp ? $currComp . ' - ' : ''); ?><em style="color:#93c5fd;">(enter site)</em></span>
                    </div>

                <?php else: ?>
                    <!-- Internal Staff View: Warehouse / Main Office / Customer -->
                    <div class="field">
                        <label>Check In Destination *</label>
                        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; margin-top:4px;">
                            <label style="display:flex; align-items:center; gap:6px; padding:10px 12px; border:1.5px solid var(--border-color, #e5e7eb); border-radius:8px; cursor:pointer; font-size:13px; font-weight:500;" class="dest-radio-label">
                                <input type="radio" name="destination_type" value="warehouse" checked onchange="onDestTypeChange(this.value)">
                                <span>Warehouse</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:6px; padding:10px 12px; border:1.5px solid var(--border-color, #e5e7eb); border-radius:8px; cursor:pointer; font-size:13px; font-weight:500;" class="dest-radio-label">
                                <input type="radio" name="destination_type" value="office" onchange="onDestTypeChange(this.value)">
                                <span>Main Office</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:6px; padding:10px 12px; border:1.5px solid var(--border-color, #e5e7eb); border-radius:8px; cursor:pointer; font-size:13px; font-weight:500;" class="dest-radio-label">
                                <input type="radio" name="destination_type" value="customer" onchange="onDestTypeChange(this.value)">
                                <span>Customer</span>
                            </label>
                        </div>
                    </div>

                    <!-- Warehouse Sub-field -->
                    <div id="ciDestWarehouseGroup" class="field">
                        <label for="ciBinLocation">Bin / Shelf Location</label>
                        <input type="text" id="ciBinLocation" name="bin_location" placeholder="e.g. A-01-1 or In Warehouse" maxlength="100">
                    </div>

                    <!-- Office Sub-field -->
                    <div id="ciDestOfficeGroup" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px 12px; font-size:12px; color:#166534;">
                        Custody will be assigned to <strong>Main Office</strong>.
                    </div>

                    <!-- Customer Sub-fields -->
                    <div id="ciDestCustomerGroup" style="display:none; display:flex; flex-direction:column; gap:10px;">
                        <div class="field">
                            <label for="ciStaffCompany">Company Name *</label>
                            <input type="text" id="ciStaffCompany" name="company" placeholder="e.g. PT Acme Corp" maxlength="100" autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="ciStaffSite">Site Name *</label>
                            <input type="text" id="ciStaffSite" name="site" placeholder="e.g. Headquarter / DC-2" maxlength="100" autocomplete="off">
                        </div>
                        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:8px 12px; font-size:12px; color:#1e40af;">
                            <strong>Target Location:</strong> <span id="ciStaffCustPreview"><em style="color:#93c5fd;">(Company - Site)</em></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="field">
                    <label for="ciNotes">Notes (Optional)</label>
                    <input type="text" id="ciNotes" name="notes" placeholder="e.g. Received in good condition" maxlength="255">
                </div>

                <div id="ciErrorMsg" style="display:none; background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:8px 12px; border-radius:6px; font-size:12px;"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn cancel" onclick="closeCheckInModal()">Cancel</button>
                <button type="submit" class="btn primary" id="ciSubmitBtn">Confirm Check In</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    "use strict";

    const isCustomer = <?php echo json_encode($isCustomer); ?>;
    const userCompany = <?php echo json_encode($currComp); ?>;

    window.openCheckInModal = function (item) {
        if (!item) return;

        document.getElementById("ciInventoryId").value = item.id || "";
        document.getElementById("ciUnitId").value = item.unit_id || "";
        document.getElementById("ciItemName").textContent = item.name || "Item";
        document.getElementById("ciItemBrand").textContent = item.brand || "—";
        document.getElementById("ciItemIdentifier").textContent = item.serial ? ("SN: " + item.serial) : (item.barcode ? ("Barcode: " + item.barcode) : "Lot #" + item.id);
        document.getElementById("ciCurrentLocation").textContent = item.location || "(Not set)";
        
        const statusEl = document.getElementById("ciItemStatus");
        if (statusEl) {
            statusEl.textContent = (item.status || "available").toUpperCase();
            statusEl.className = "status " + (item.status === "available" ? "available" : "taken");
        }

        // Reset inputs
        const errEl = document.getElementById("ciErrorMsg");
        if (errEl) { errEl.style.display = "none"; errEl.textContent = ""; }

        const notesEl = document.getElementById("ciNotes");
        if (notesEl) notesEl.value = "";

        if (isCustomer) {
            const siteInput = document.getElementById("ciCustSiteInput");
            if (siteInput) siteInput.value = "";
            updateCustomerPreview();
        } else {
            // Select warehouse as default
            const radio = document.querySelector('input[name="destination_type"][value="warehouse"]');
            if (radio) { radio.checked = true; }
            onDestTypeChange("warehouse");
            const binInput = document.getElementById("ciBinLocation");
            if (binInput) {
                const prevLoc = (item.location || "").trim();
                // If it was at customer site or main office, reset cleanly to 'In Warehouse'
                if (prevLoc && prevLoc !== "Main Office" && !prevLoc.includes(" - ")) {
                    binInput.value = prevLoc;
                } else {
                    binInput.value = "In Warehouse";
                }
            }
            const compInput = document.getElementById("ciStaffCompany");
            if (compInput) compInput.value = "";
            const staffSite = document.getElementById("ciStaffSite");
            if (staffSite) staffSite.value = "";
            updateStaffPreview();
        }

        document.getElementById("checkInModal").classList.add("open");
        setTimeout(() => {
            if (isCustomer) {
                document.getElementById("ciCustSiteInput")?.focus();
            } else {
                document.getElementById("ciBinLocation")?.focus();
            }
        }, 150);
    };

    window.closeCheckInModal = function () {
        document.getElementById("checkInModal").classList.remove("open");
    };

    window.onDestTypeChange = function (val) {
        const whGroup = document.getElementById("ciDestWarehouseGroup");
        const offGroup = document.getElementById("ciDestOfficeGroup");
        const custGroup = document.getElementById("ciDestCustomerGroup");

        if (whGroup) whGroup.style.display = (val === "warehouse") ? "block" : "none";
        if (offGroup) offGroup.style.display = (val === "office") ? "block" : "none";
        if (custGroup) custGroup.style.display = (val === "customer") ? "flex" : "none";
    };

    function updateCustomerPreview() {
        const siteVal = document.getElementById("ciCustSiteInput")?.value.trim();
        const prevEl = document.getElementById("ciCustPreview");
        if (!prevEl) return;
        if (siteVal) {
            prevEl.textContent = userCompany + " - " + siteVal;
        } else {
            prevEl.innerHTML = userCompany + ' - <em style="color:#93c5fd;">(enter site)</em>';
        }
    }

    function updateStaffPreview() {
        const compVal = document.getElementById("ciStaffCompany")?.value.trim();
        const siteVal = document.getElementById("ciStaffSite")?.value.trim();
        const prevEl = document.getElementById("ciStaffCustPreview");
        if (!prevEl) return;
        if (compVal || siteVal) {
            prevEl.textContent = (compVal || "Company") + " - " + (siteVal || "Site");
        } else {
            prevEl.innerHTML = '<em style="color:#93c5fd;">(Company - Site)</em>';
        }
    }

    document.getElementById("ciCustSiteInput")?.addEventListener("input", updateCustomerPreview);
    document.getElementById("ciStaffCompany")?.addEventListener("input", updateStaffPreview);
    document.getElementById("ciStaffSite")?.addEventListener("input", updateStaffPreview);

    window.handleCheckInSubmit = async function (e) {
        e.preventDefault();
        const errEl = document.getElementById("ciErrorMsg");
        const submitBtn = document.getElementById("ciSubmitBtn");
        if (errEl) errEl.style.display = "none";

        const form = document.getElementById("checkInForm");
        const formData = new FormData(form);

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Processing...";
        }

        try {
            const res = await fetch("<?php echo BASE_URL; ?>/actions/process-checkin.php", {
                method: "POST",
                body: formData
            });
            const data = await res.json();
            if (!data.ok) {
                if (errEl) {
                    errEl.textContent = data.message || "Failed to process check-in.";
                    errEl.style.display = "block";
                }
                return;
            }

            closeCheckInModal();
            if (window.showFlash) {
                window.showFlash(data.message, "success");
            } else {
                alert(data.message);
            }
            
            // Allow page components to react (e.g. reload or refresh table)
            window.dispatchEvent(new CustomEvent("checkin:success", { detail: data }));
            setTimeout(() => { window.location.reload(); }, 900);
        } catch (err) {
            if (errEl) {
                errEl.textContent = "A network error occurred. Please try again.";
                errEl.style.display = "block";
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = "Confirm Check In";
            }
        }
    };
})();
</script>
