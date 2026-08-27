<!-- Shared barcode/QR scanner modal + logic. Include once per page that needs
     it. Pages set a handler with window.setScanHandler(fn) where fn(item)
     receives the looked-up item and returns true to accept (closes the modal)
     or false to reject (keeps the modal open). Open it with openScanModal(). -->
<div class="modal-backdrop" id="scanModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Scan Item</div>
            <button type="button" class="modal-close" onclick="closeScanModal()">×</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--muted); margin-bottom:12px;">Point your camera at the item's barcode or QR code.</p>
            <div class="scan-actions">
                <button type="button" class="btn primary" id="scanToggleBtn" onclick="toggleScan()">Start Camera</button>
            </div>
            <div id="scanReader" class="scan-reader"></div>
            <div class="scan-or">— or —</div>
            <label class="btn upload scan-upload" for="scanFile">Take / Upload Photo to Scan</label>
            <input type="file" id="scanFile" accept="image/*" capture="environment" style="display:none;">
            <div id="scanMsg" class="scan-result" style="display:none;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn secondary" onclick="closeScanModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
(function () {
    "use strict";
    let scanScanner = null;
    let scanSuccessHandler = null;

    function setMsg(html, isError) {
        const el = document.getElementById("scanMsg");
        if (!el) return;
        el.style.display = "block";
        el.className = "scan-result" + (isError ? " error" : "");
        el.innerHTML = html;
    }

    window.setScanHandler = function (fn) { scanSuccessHandler = fn; };

    window.openScanModal = function () {
        document.getElementById("scanModal").classList.add("open");
        setMsg("");
    };

    window.closeScanModal = function () {
        document.getElementById("scanModal").classList.remove("open");
        stopScan();
    };

    window.toggleScan = function () {
        if (scanScanner) { stopScan(); return; }
        startScan();
    };

    async function startScan() {
        if (scanScanner) return;

        try {
            await window.ensureHtml5Qrcode();
        } catch (e) {
            setMsg("Scanner library failed to load.", true);
            return;
        }
        if (!window.Html5Qrcode) { setMsg("Scanner library failed to load.", true); return; }

        const reader = document.getElementById("scanReader");
        reader.innerHTML = "";
        setMsg("Starting camera...");
        window.setScanToggle(false);

        let scanFrames = 0;

        const scanner = new Html5Qrcode("scanReader");
        scanner.start(
            { facingMode: "environment" },
            {
                fps: 15,
                qrbox: { width: 300, height: 300 }
            },
            function (decodedText) {
                window.setScanToggle(true);
                if (scanScanner) { try { scanner.stop(); } catch (e) {} scanScanner = null; }
                handleScan(decodedText);
            },
            function (errName) {
                // ZXing reports "No MultiFormat Readers..." whenever a frame has
                // no detectable code yet; this counter confirms frames are flowing.
                scanFrames++;
                if (scanFrames === 1 || scanFrames % 15 === 0) {
                    setMsg("Line up the code fully inside the box... (frames: " + scanFrames + ")");
                }
            }
        ).then(() => {
            scanScanner = scanner;
            setMsg("Camera on. Line up the code fully inside the box.");
        }).catch((err) => {
            scanScanner = null;
            window.setScanToggle(true);
            setMsg("Could not start camera: " + err, true);
        });
    }

    function stopScan() {
        const s = scanScanner;
        scanScanner = null;
        window.setScanToggle(true);
        if (s) { try { s.stop(); s.clear(); } catch (e) {} }
    }

    async function handleScan(code) {
        setMsg("Detected: <strong>" + code + "</strong>. Looking up...");
        try {
            const res = await fetch("<?php echo BASE_URL; ?>/actions/scan-lookup.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ code: code })
            });
            const data = await res.json();
            if (!data.ok) {
                setMsg("<strong>Not found.</strong> " + (data.message || ""), true);
                return;
            }
            // Notify whenever an inactive / out-of-warehouse item is scanned.
            if (data.item && data.item.status !== "available" && window.showFlash) {
                window.showFlash("Scanned item is inactive / out of the warehouse: " + data.item.name, "error");
            }
            if (scanSuccessHandler && (await scanSuccessHandler(data.item)) === false) {
                return;
            }
            closeScanModal();
        } catch (e) {
            setMsg("Lookup failed. Please try again.", true);
        }
    }

    const scanFile = document.getElementById("scanFile");
    if (scanFile) {
        scanFile.addEventListener("change", async () => {
            const file = scanFile.files[0];
            scanFile.value = "";
            if (!file) return;
            setMsg("Scanning image...");
            try {
                await window.ensureHtml5Qrcode();
                if (!window.Html5Qrcode) throw new Error("Scanner library not loaded.");
                const el = document.getElementById("scanReader");
                const scanner = new Html5Qrcode(el ? el.id : "scanReader");
                let text = "";
                if (typeof scanner.scanFileV2 === "function") {
                    const r = await scanner.scanFileV2(file, false);
                    text = r ? r.decodedText : "";
                } else if (typeof scanner.scanFile === "function") {
                    text = await scanner.scanFile(file, false);
                }
                if (!text) throw new Error("No code found.");
                await handleScan(text);
            } catch (e) {
                setMsg("Could not read a barcode/QR code from that image.", true);
            }
        });
    }
})();
</script>