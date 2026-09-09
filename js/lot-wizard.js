// ============================================================
// LOT WIZARD MODULE: Barcode/QR generator, Lot Split & Merge
// ============================================================
(function () {
    "use strict";

    const $ = window.$;
    const $all = window.$all;
    const BASE = window.BASE;

    let genItemId = 0;

    window.openGenModal = function (id) {
        const item = (window.INVENTORY || []).find((it) => it.id === id);
        if (!item) return;

        genItemId = item.id;
        const nameEl = $("#genItemName");
        if (nameEl) nameEl.textContent = item.name + "  ·  " + item.serial;
        const locEl = $("#genItemLocation");
        if (locEl) locEl.textContent = item.location || "—";

        if (item.barcode && item.qr) {
            const gn = $("#genName"); if (gn) gn.textContent = item.name;
            const gs = $("#genSerial"); if (gs) gs.textContent = item.serial;
            const gl = $("#genLocationValue"); if (gl) gl.textContent = item.location || "N/A";
            
            const gb = $("#genBarcode");
            if (gb) {
                gb.innerHTML = "";
                if (window.JsBarcode) {
                    window.JsBarcode("#genBarcode", item.barcode, {
                        format: "EAN13", displayValue: true, width: 2, height: 60, fontSize: 14
                    });
                }
            }

            const gq = $("#genQr");
            if (gq) {
                gq.innerHTML = "";
                if (window.QRCode) {
                    new window.QRCode(gq, { text: item.qr, width: 132, height: 132 });
                }
            }

            const preview = $("#labelsPreview"); if (preview) preview.style.display = "";
            const genBtn = $("#genGenerateBtn"); if (genBtn) genBtn.style.display = "none";
            const printBtns = $("#genPrintButtons"); if (printBtns) printBtns.style.display = "inline-flex";
        } else {
            const preview = $("#labelsPreview"); if (preview) preview.style.display = "none";
            const genBtn = $("#genGenerateBtn"); if (genBtn) genBtn.style.display = "";
            const printBtns = $("#genPrintButtons"); if (printBtns) printBtns.style.display = "none";
        }
        window.openModal("genModal");
    };

    window.printScoped = function (scope) {
        document.body.classList.remove("print-barcode", "print-qr", "print-both");
        document.body.classList.add("print-" + scope);
        setTimeout(() => window.print(), 50);
    };

    window.generateItemLabel = function () {
        const btn = $("#genGenerateBtn");
        if (btn) {
            btn.disabled = true;
            btn.textContent = "Generating...";
        }

        window.post(BASE + "/actions/generate-label.php", { item_id: genItemId })
            .then((res) => res.json())
            .then((data) => {
                if (!data.ok) throw new Error(data.message || "Could not generate labels.");
                setTimeout(() => location.reload(), 400);
            })
            .catch((err) => alert("Could not generate labels.\n\n" + err.message))
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Generate Barcode & QR Code";
                }
            });
    };

    // ---- Split & Merge Operations ----
    function updateSelectionUI() {
        const isSplit = document.body.classList.contains("split-mode");
        const isMerge = document.body.classList.contains("merge-mode");
        const banner = $("#selectionBanner");
        const bannerText = $("#selectionBannerText");

        $all(".inventory-row").forEach((tr) => {
            const cb = tr.querySelector(".row-select");
            if (cb) {
                if (isSplit) {
                    cb.type = "radio";
                    cb.name = "splitRowSelect";
                } else if (isMerge) {
                    cb.type = "checkbox";
                    cb.removeAttribute("name");
                }
                tr.classList.toggle("selected", cb.checked);
            }
        });

        if (!banner) return;

        if (isSplit) {
            banner.style.display = "flex";
            const checked = $all(".row-select:checked");
            if (checked.length === 1) {
                const id = parseInt(checked[0].value, 10);
                const item = (window.INVENTORY || []).find((it) => it.id === id);
                if (item) {
                    if (bannerText) bannerText.textContent = "Selected: " + item.name + " (" + (item.serial || item.barcode || ("ID #" + item.id)) + ") — Qty: " + item.quantity + ". Ready to split.";
                }
            } else {
                if (bannerText) bannerText.textContent = "Click an item in the table below to select it for splitting.";
            }
        } else if (isMerge) {
            banner.style.display = "flex";
            const checkedCount = $all(".row-select:checked").length;
            if (checkedCount > 0) {
                if (bannerText) bannerText.textContent = checkedCount + " item(s) selected. Ready to merge.";
            } else {
                if (bannerText) bannerText.textContent = "Click 2 or more items from the table below to merge.";
            }
        } else {
            banner.style.display = "none";
        }
    }

    function createModeToggle(cfg) {
        function setBtn(on) {
            const btn = $(cfg.btn);
            if (!btn) return;
            btn.textContent = on ? "Cancel" : cfg.label;
            btn.classList.toggle("cancel", on);
            btn.classList.toggle("warning", !on);
        }
        function clearSelection() {
            $all(".row-select").forEach((cb) => (cb.checked = false));
        }
        return {
            deactivate() {
                document.body.classList.remove(cfg.bodyClass);
                const panel = $(cfg.panel);
                if (panel) panel.style.display = "none";
                setBtn(false);
                clearSelection();
                updateSelectionUI();
                if (cfg.reset) cfg.reset();
            },
            toggle() {
                const on = !document.body.classList.contains(cfg.bodyClass);
                if (on && cfg.deactivateOther) cfg.deactivateOther();
                document.body.classList.toggle(cfg.bodyClass, on);
                const panel = $(cfg.panel);
                if (panel) panel.style.display = on ? "" : "none";
                setBtn(on);
                if (on) {
                    if (cfg.activate) cfg.activate();
                } else {
                    clearSelection();
                    if (cfg.reset) cfg.reset();
                }
                updateSelectionUI();
            }
        };
    }

    let splitBaseQty = 0;
    let splitBaseLocation = "";

    function refreshSplitBase() {
        const checked = $all(".row-select:checked");
        const id = checked.length === 1 ? parseInt(checked[0].value, 10) : 0;
        const item = (window.INVENTORY || []).find((it) => it.id === id);
        splitBaseQty = item ? parseInt(item.quantity, 10) || 0 : 0;
        splitBaseLocation = item ? (item.location || "") : "";
        renderSplitQtyInputs();
    }

    function equalSplitValue(index, parts) {
        if (splitBaseQty <= 0) return 1;
        const base = Math.floor(splitBaseQty / parts);
        const extra = splitBaseQty % parts;
        return index < extra ? base + 1 : base;
    }

    function updateSplitQtySum() {
        const sum = $all(".split-qty").reduce((acc, inp) => acc + (parseInt(inp.value, 10) || 0), 0);
        const el = $("#splitQtySum");
        if (el) el.textContent = "Sum: " + sum + " / " + splitBaseQty;
    }

    function renderSplitQtyInputs() {
        const container = $("#splitPartsContainer") || $("#splitQtyInputs");
        if (!container) return;
        const parts = Math.min(10, Math.max(2, parseInt($("#splitParts")?.value, 10) || 2));
        container.innerHTML = "";

        for (let i = 0; i < parts; i++) {
            const row = document.createElement("div");
            row.className = "split-part-row";

            const badge = document.createElement("div");
            badge.className = "part-badge";
            badge.textContent = "Part " + (i + 1);

            const qtyField = document.createElement("div");
            qtyField.className = "field qty-field";
            const qtyLabel = document.createElement("label");
            qtyLabel.textContent = "Quantity";
            const qtyInput = document.createElement("input");
            qtyInput.type = "number";
            qtyInput.min = "1";
            qtyInput.value = equalSplitValue(i, parts);
            qtyInput.className = "split-qty";
            qtyInput.addEventListener("input", updateSplitQtySum);
            qtyField.appendChild(qtyLabel);
            qtyField.appendChild(qtyInput);

            const locField = document.createElement("div");
            locField.className = "field loc-field";
            const locLabel = document.createElement("label");
            locLabel.textContent = "Location";
            const locInput = document.createElement("input");
            locInput.type = "text";
            locInput.className = "split-location";
            locInput.value = splitBaseLocation;
            locInput.placeholder = "Location";
            locField.appendChild(locLabel);
            locField.appendChild(locInput);

            row.appendChild(badge);
            row.appendChild(qtyField);
            row.appendChild(locField);

            container.appendChild(row);
        }
        updateSplitQtySum();
    }
    window.renderSplitQtyInputs = renderSplitQtyInputs;

    const splitMode = createModeToggle({
        bodyClass: "split-mode",
        panel: "#splitPanel",
        btn: "#splitToggleBtn",
        label: "Split",
        deactivateOther: () => mergeMode.deactivate(),
        activate() {
            refreshSplitBase();
            renderSplitQtyInputs();
        },
        reset() {
            const sp = $("#splitParts");
            if (sp) sp.value = 2;
            splitBaseQty = 0;
            refreshSplitBase();
            renderSplitQtyInputs();
        }
    });

    const mergeMode = createModeToggle({
        bodyClass: "merge-mode",
        panel: "#mergePanel",
        btn: "#mergeToggleBtn",
        label: "Merge",
        deactivateOther: () => splitMode.deactivate(),
        reset() {
            refreshMergeTargets();
            const mb = $("#mergeBarcode");
            if (mb) mb.value = "";
            const rb = document.querySelector('input[name="mergeMode"][value="new"]');
            if (rb) rb.checked = true;
            updateMergeDestination();
        }
    });

    window.toggleSplitSelection = function () { splitMode.toggle(); };
    window.toggleMergeSelection = function () { mergeMode.toggle(); };

    function updateMergeDestination() {
        const checkedRadio = document.querySelector('input[name="mergeMode"]:checked');
        if (!checkedRadio) return;
        const mode = checkedRadio.value;
        const tw = $("#mergeTargetWrap");
        if (tw) tw.style.display = mode === "existing" ? "" : "none";
        const bw = $("#mergeBarcodeWrap");
        if (bw) bw.style.display = mode === "new" ? "" : "none";
    }
    window.updateMergeDestination = updateMergeDestination;

    function canSelectForMerge(itemId) {
        const checked = $all(".row-select:checked").map((cb) => parseInt(cb.value, 10)).filter((id) => id !== itemId);
        if (checked.length === 0) return true;
        const items = window.INVENTORY || [];
        const firstItem = items.find((it) => checked.includes(it.id));
        const targetItem = items.find((it) => it.id === itemId);
        if (!firstItem || !targetItem) return true;
        const nameMatch = (firstItem.name || "").trim().toLowerCase() === (targetItem.name || "").trim().toLowerCase();
        const brandMatch = (firstItem.brand || "").trim().toLowerCase() === (targetItem.brand || "").trim().toLowerCase();
        return nameMatch && brandMatch;
    }

    function refreshMergeTargets() {
        const sel = $("#mergeTargetSelect");
        if (!sel) return;
        sel.innerHTML = "";
        const checked = $all(".row-select:checked").map((cb) => parseInt(cb.value, 10));
        (window.INVENTORY || []).forEach((it) => {
            if (!checked.includes(it.id)) return;
            const opt = document.createElement("option");
            opt.value = it.id;
            opt.textContent = it.name + (it.brand ? "  ·  " + it.brand : "") + "  —  " + (it.barcode || it.serial);
            sel.appendChild(opt);
        });
    }

    window.submitSplit = function () {
        const checked = $all(".row-select:checked").map((cb) => parseInt(cb.value, 10));
        if (checked.length !== 1) return alert("Select exactly one item to split.");
        const quantities = $all(".split-qty").map((inp) => parseInt(inp.value, 10) || 0);
        if (quantities.length < 2) return alert("You need at least 2 parts.");
        if (quantities.some((q) => q < 1)) return alert("Each part quantity must be at least 1.");
        const sum = quantities.reduce((a, b) => a + b, 0);
        if (sum !== splitBaseQty) return alert("Part quantities must add up to the item's quantity (" + splitBaseQty + ").");
        const locations = $all(".split-location").map((inp) => inp.value.trim() || splitBaseLocation);
        if (!confirm("Split this item into " + quantities.length + " parts?")) return;

        window.post(BASE + "/actions/split-item.php", { item_id: checked[0], quantities: quantities.join(","), locations: locations.join("||") })
            .then((res) => res.json())
            .then((d) => {
                if (!d.ok) throw new Error(d.message || "Split failed.");
                window.queueFlash(d.message || "Item split.");
                location.reload();
            })
            .catch((err) => alert(err.message));
    };

    window.submitMerge = function () {
        const ids = $all(".row-select:checked").map((cb) => parseInt(cb.value, 10));
        if (ids.length < 2) return alert("Select at least 2 items to merge.");

        const selectedItems = (window.INVENTORY || []).filter((it) => ids.includes(it.id));
        if (selectedItems.length > 0) {
            const first = selectedItems[0];
            const mismatch = selectedItems.some((it) =>
                (it.name || "").trim().toLowerCase() !== (first.name || "").trim().toLowerCase() ||
                (it.brand || "").trim().toLowerCase() !== (first.brand || "").trim().toLowerCase()
            );
            if (mismatch) return alert("You can only merge items with the same product name and brand.");
        }

        const modeRadio = document.querySelector('input[name="mergeMode"]:checked');
        const mode = modeRadio ? modeRadio.value : "new";
        const data = { mode: mode, ids: ids };
        if (mode === "existing") {
            const target = parseInt($("#mergeTargetSelect")?.value, 10) || 0;
            if (!target) return alert("Choose a target item to merge into.");
            data.target_id = target;
        } else {
            data.barcode = $("#mergeBarcode")?.value.trim();
        }

        const label = mode === "existing"
            ? "Merge " + ids.length + " items into the target item? This cannot be undone."
            : "Merge " + ids.length + " items into a new item? This cannot be undone.";
        if (!confirm(label)) return;

        window.post(BASE + "/actions/merge-items.php", data)
            .then((res) => res.json())
            .then((d) => {
                if (!d.ok) throw new Error(d.message || "Merge failed.");
                window.queueFlash(d.message || "Items merged.");
                location.reload();
            })
            .catch((err) => alert(err.message));
    };

    document.addEventListener("change", (e) => {
        if (e.target && e.target.classList && e.target.classList.contains("row-select")) {
            if (document.body.classList.contains("merge-mode") && e.target.checked) {
                if (!canSelectForMerge(parseInt(e.target.value, 10))) {
                    e.target.checked = false;
                    alert("You can only merge items with the same product name and brand.");
                    return;
                }
            }
            if (document.body.classList.contains("split-mode") && e.target.checked) {
                $all(".row-select").forEach((cb) => {
                    if (cb !== e.target) cb.checked = false;
                });
            }
            refreshMergeTargets();
            if (document.body.classList.contains("split-mode")) refreshSplitBase();
            updateSelectionUI();
        }
    });

    document.addEventListener("click", (e) => {
        const isSplit = document.body.classList.contains("split-mode");
        const isMerge = document.body.classList.contains("merge-mode");
        if (!isSplit && !isMerge) return;

        if (e.target.closest("button, a, img, input, select, textarea")) return;

        const tr = e.target.closest(".inventory-row");
        if (!tr) return;

        const input = tr.querySelector(".row-select");
        if (!input) return;

        if (isSplit) {
            input.checked = true;
            $all(".row-select").forEach((cb) => {
                if (cb !== input) cb.checked = false;
            });
            refreshSplitBase();
        } else if (isMerge) {
            const nextChecked = !input.checked;
            if (nextChecked && !canSelectForMerge(parseInt(input.value, 10))) {
                alert("You can only merge items with the same product name and brand.");
                return;
            }
            input.checked = nextChecked;
            refreshMergeTargets();
        }

        updateSelectionUI();
    });
})();
