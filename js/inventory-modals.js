// ============================================================
// INVENTORY MODALS MODULE: Item details, Add/Edit cards, Photo & Autocomplete
// ============================================================
(function () {
    "use strict";

    const $ = window.$;
    const $all = window.$all;

    // View Item Modal
    window.openItemModal = function (id) {
        const item = (window.INVENTORY || []).find((it) => it.id === id);
        if (!item) return;

        const photoEl = $("#detailPhoto");
        if (photoEl) {
            photoEl.src = item.photo || "";
            photoEl.style.display = item.photo ? "" : "none";
        }
        const nameEl = $("#detailName");
        if (nameEl) nameEl.textContent = item.name;

        const serialBox = document.querySelector("#detailSerial") ? document.querySelector("#detailSerial").closest(".detail-box") : null;
        const serialEl = $("#detailSerial");
        if (serialEl) serialEl.textContent = item.serial || "—";
        if (serialBox) {
            serialBox.style.display = item.serial ? "" : "none";
        }

        const qtyEl = $("#detailQty");
        if (qtyEl) qtyEl.textContent = item.quantity || 0;
        const locEl = $("#detailLocation");
        if (locEl) locEl.textContent = item.location || "—";
        const dateEl = $("#detailDate");
        if (dateEl) dateEl.textContent = item.created_at || "—";

        const status = $("#detailStatus");
        if (status) {
            status.textContent = item.status === "available" ? "Active" : "Inactive";
            status.className = "status " + item.status;
        }

        // Barcode (EAN-13)
        const barcodeBox = document.querySelector("#detailBarcode") ? document.querySelector("#detailBarcode").closest(".detail-box") : null;
        const bcEl = $("#detailBarcode");
        if (bcEl) {
            bcEl.innerHTML = "";
            if (item.barcode && window.JsBarcode) {
                window.JsBarcode("#detailBarcode", item.barcode, {
                    format: "EAN13", displayValue: true, width: 2, height: 50, fontSize: 13
                });
                if (barcodeBox) barcodeBox.style.display = "";
            } else if (barcodeBox) {
                barcodeBox.style.display = "none";
            }
        }

        // QR Code
        const qrBox = document.querySelector("#detailQr") ? document.querySelector("#detailQr").closest(".detail-box") : null;
        const qrEl = $("#detailQr");
        if (qrEl) {
            qrEl.innerHTML = "";
            if (item.serial && item.qr && window.QRCode) {
                new window.QRCode(qrEl, { text: item.qr, width: 132, height: 132 });
                if (qrBox) qrBox.style.display = "";
            } else if (qrBox) {
                qrBox.style.display = "none";
            }
        }

        window.openModal("itemModal");
    };

    window.openPrintUnitModal = function (id) {
        window.openItemModal(id);
    };

    // Shared Item Card (includes/item-card-select.php)
    window.showItemCard = function (it) {
        if (!it) return;
        const nameEl = document.getElementById("selCardName");
        if (nameEl) nameEl.textContent = it.name || "";

        const badgeEl = document.getElementById("selCardBadge");
        if (badgeEl) {
            if (it.is_unit || it.serial) {
                badgeEl.style.display = "inline-block";
                badgeEl.innerHTML = '<span style="background:#4f46e5; color:#fff; font-size:11px; padding:2px 8px; border-radius:12px; font-weight:600; letter-spacing:0.5px;">INDIVIDUAL UNIT</span>';
            } else {
                badgeEl.style.display = "inline-block";
                badgeEl.innerHTML = '<span style="background:var(--card-border, #e2e8f0); color:var(--text, #334155); font-size:11px; padding:2px 8px; border-radius:12px; font-weight:600; letter-spacing:0.5px;">BATCH / LOT</span>';
            }
        }

        const serialRow = document.getElementById("selCardSerialRow");
        const serialEl = document.getElementById("selCardSerial");
        if (serialEl) {
            serialEl.textContent = it.serial ? it.serial : "—";
            if (serialRow) serialRow.style.display = "";
        }

        const brandEl = document.getElementById("selCardBrand");
        if (brandEl) brandEl.textContent = it.brand || "—";

        const qtyRow = document.getElementById("selCardQtyRow");
        const qtyEl = document.getElementById("selCardQty");
        if (qtyEl) {
            if (it.is_unit) {
                qtyEl.textContent = "1 Unit" + (it.batch_quantity !== undefined && it.batch_quantity !== null ? "  (Batch On-Hand: " + it.batch_quantity + ")" : "");
            } else {
                qtyEl.textContent = it.quantity !== undefined ? it.quantity : (it.batch_quantity || 0);
            }
            if (qtyRow) qtyRow.style.display = "";
        }

        const locEl = document.getElementById("selCardLocation");
        if (locEl) locEl.textContent = it.location || "—";

        const statusEl = document.getElementById("selCardStatus");
        if (statusEl) {
            const st = (it.status || "").toLowerCase();
            const isInactive = (st === "inactive" || st === "taken" || !!it.deleted_at);
            if (isInactive) {
                statusEl.textContent = "INACTIVE";
                statusEl.className = "status inactive";
            } else {
                statusEl.textContent = "ACTIVE";
                statusEl.className = "status available";
            }
        }

        const photo = document.getElementById("selCardPhoto");
        const ph = document.getElementById("selCardPhotoPh");
        if (it.photo) {
            if (photo) { photo.src = it.photo; photo.style.display = ""; }
            if (ph) ph.style.display = "none";
        } else {
            if (photo) photo.style.display = "none";
            if (ph) ph.style.display = "";
        }

        const bc = document.getElementById("selCardBarcode");
        if (bc) {
            bc.innerHTML = "";
            if (it.barcode && window.JsBarcode) {
                window.JsBarcode(bc, it.barcode, { format: "EAN13", displayValue: true, width: 2, height: 50, fontSize: 12 });
            }
        }

        const qr = document.getElementById("selCardQr");
        if (qr) {
            qr.innerHTML = "";
            if (it.qr && window.QRCode) {
                new window.QRCode(qr, { text: it.qr, width: 96, height: 96 });
            }
        }

        const selCard = document.getElementById("selCard");
        if (selCard) selCard.style.display = "";
    };

    // Add / Edit Cards
    const addForms = {
        add:    { panel: "#addItemSection",      form: "#addItemForm",      label: "+ Add Item", bodyClass: "add-mode", reset() {
            const ph = $("#photoPlaceholder");
            const prev = $("#photoPreview");
            if (ph) ph.style.display = "";
            if (prev) { prev.src = ""; prev.style.display = "none"; }
            const pd = $("#photoData");
            if (pd) pd.value = "";
        } },
        master: { panel: "#addMasterItemSection", form: "#addMasterItemForm", label: "+ Add Item" },
        brand:  { panel: "#addBrandSection",      form: "#addBrandForm",     label: "+ Add Brand" },
    };

    function setCardVisible(name, show) {
        const cfg = addForms[name];
        if (!cfg) return;
        const panel = $(cfg.panel);
        if (!panel) return;
        panel.style.display = show ? "" : "none";
        if (cfg.bodyClass) document.body.classList.toggle(cfg.bodyClass, show);
        $all(".add-toggle-btn").forEach((btn) => {
            btn.textContent = show ? "Cancel" : cfg.label;
            btn.classList.toggle("cancel", show);
            btn.classList.toggle("primary", !show);
        });
        if (!show) {
            const form = $(cfg.form);
            if (form) form.reset();
            if (cfg.reset) cfg.reset();
        }
    }

    function toggleCard(name) {
        const cfg = addForms[name];
        if (!cfg) return;
        const panel = $(cfg.panel);
        if (!panel) return;
        setCardVisible(name, panel.style.display === "none");
    }

    window.setCardVisible      = setCardVisible;
    window.closeAddForm        = function () { setCardVisible("add", false); };
    window.toggleAddForm       = function () { toggleCard("add"); };
    window.closeMasterAddForm  = function () { setCardVisible("master", false); };
    window.toggleMasterAddForm = function () { toggleCard("master"); };
    window.closeBrandAddForm   = function () { setCardVisible("brand", false); };
    window.toggleBrandAddForm  = function () { toggleCard("brand"); };

    window.toggleProductCodeManual = function () {
        const cb = $("#manualProductCode");
        const input = $("#product_code");
        if (!cb || !input) return;
        input.disabled = !cb.checked;
    };

    window.openEditModal = function (id) {
        const item = (window.INVENTORY || []).find((it) => it.id === id);
        if (!item) return;

        const editId = $("#editId");
        if (editId) editId.value = item.id;
        const nameInp = $("#edit_item_name");
        if (nameInp) {
            nameInp.value = item.name || "";
            nameInp.dataset.valid = item.name || "";
        }
        const brandInp = $("#edit_brand");
        if (brandInp) {
            brandInp.value = item.brand || "";
            brandInp.dataset.valid = item.brand || "";
        }
        const qtyDisp = $("#editQuantityDisplay");
        if (qtyDisp) qtyDisp.textContent = item.quantity || 1;
        const locDisp = $("#editLocationDisplay");
        if (locDisp) locDisp.textContent = item.location || "—";

        const editPhotoData = $("#editPhotoData");
        if (editPhotoData) editPhotoData.value = "";
        const editPrev = $("#editPhotoPreview");
        const editPh = $("#editPhotoPlaceholder");
        if (item.photo) {
            if (editPrev) { editPrev.src = item.photo; editPrev.style.display = "block"; }
            if (editPh) editPh.style.display = "none";
        } else {
            if (editPrev) editPrev.style.display = "none";
            if (editPh) editPh.style.display = "";
        }

        const binBtn = $("#editBinBtn");
        if (binBtn) {
            binBtn.dataset.id = item.id;
        }

        window.openModal("editModal");
    };

    // Photo Management
    const photoTargets = {
        add: { data: "#photoData", file: "#photoFile", preview: "#photoPreview", placeholder: "#photoPlaceholder" },
        edit: { data: "#editPhotoData", file: "#editPhotoFile", preview: "#editPhotoPreview", placeholder: "#editPhotoPlaceholder", status: "#editPhotoStatus" }
    };

    let photoMode = "add";

    function setPhoto(dataUrl) {
        const t = photoTargets[photoMode];
        $(t.data).value = dataUrl;
        $(t.preview).src = dataUrl;
        $(t.preview).style.display = "block";
        $(t.placeholder).style.display = "none";
        const status = $(t.status);
        if (status) status.textContent = "Photo set. You can replace it below.";
    }

    window.triggerFilePick = function (mode) {
        photoMode = mode || "add";
        $(photoTargets[photoMode].file).click();
    };

    window.triggerCapturePick = function () {
        photoMode = "add";
        const input = $("#photoCaptureFile");
        if (input) input.click();
    };

    function canvasToDataUrl(img) {
        const maxW = 768;
        const canvas = document.createElement("canvas");
        const ratio = Math.min(1, maxW / img.naturalWidth);
        canvas.width = img.naturalWidth * ratio;
        canvas.height = img.naturalHeight * ratio;
        canvas.getContext("2d").drawImage(img, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL("image/jpeg", 0.65);
    }

    [["#photoFile", "add"], ["#photoCaptureFile", "add"], ["#editPhotoFile", "edit"]].forEach(([sel, mode]) => {
        const fileInput = $(sel);
        if (fileInput) {
            fileInput.addEventListener("change", function () {
                const file = this.files && this.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = new Image();
                    img.onload = () => {
                        photoMode = mode;
                        setPhoto(canvasToDataUrl(img));
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        }
    });

    const addForm = $("#addItemForm");
    if (addForm) {
        addForm.addEventListener("submit", (e) => {
            const nameField = $("#product_name");
            if (!nameField.value.trim()) {
                e.preventDefault();
                alert("Item name is required.");
                return;
            }
            const items = window.MASTER_ITEMS;
            if (items && items.length) {
                const allowed = items.map((it) => (it.product_name || "").trim().toLowerCase());
                if (!allowed.includes(nameField.value.trim().toLowerCase())) {
                    e.preventDefault();
                    alert("Please choose a product name from the list.");
                    nameField.focus();
                }
            }
        });
    }

    window.onlyDigits = function (el) {
        el.value = el.value.replace(/[^0-9]/g, "");
    };

    function combineLocation(shelfId, rowId, colId, targetId) {
        const sEl = $(shelfId);
        const rEl = $(rowId);
        const cEl = $(colId);
        const tEl = $(targetId);
        if (!sEl || !rEl || !cEl || !tEl) return;
        const rawS = sEl.value.trim();
        const rawR = rEl.value.trim();
        const rawC = cEl.value.trim();
        if (!(rawS && rawR && rawC)) {
            tEl.value = "";
            return;
        }
        const pad = (v) => v.padStart(2, "0");
        tEl.value = "S" + pad(rawS) + "-R" + pad(rawR) + "-C" + pad(rawC);
    }

    window.updateLocation = function () {
        combineLocation("#locShelf", "#locRow", "#locCol", "#location");
    };

    window.updateEditLocation = function () {
        combineLocation("#editLocShelf", "#editLocRow", "#editLocCol", "#edit_location");
    };

    window.updateTransferLocation = function () {
        combineLocation("#transferShelf", "#transferRow", "#transferCol", "#transferDestination");
    };

    // Autocomplete Dropdowns
    function initSuggest(cfg) {
        const input = $(cfg.input);
        const box = $(cfg.box);
        if (!input || !box) return;
        const items = cfg.items || [];
        if (!items.length) return;
        const limit = cfg.limit || 8;

        function closeBox() { box.innerHTML = ""; box.style.display = "none"; }
        function render(list) {
            box.innerHTML = "";
            list.forEach((it) => {
                const d = document.createElement("div");
                d.className = "suggest-item";
                const label = document.createElement("div");
                label.className = "suggest-label";
                label.textContent = cfg.label(it);
                d.appendChild(label);
                if (cfg.sub) {
                    const sub = document.createElement("div");
                    sub.className = "suggest-sub";
                    sub.textContent = cfg.sub(it);
                    d.appendChild(sub);
                }
                d.addEventListener("mousedown", (e) => {
                    e.preventDefault();
                    cfg.pick(it);
                    closeBox();
                });
                d.addEventListener("click", () => {
                    cfg.pick(it);
                    closeBox();
                });
                box.appendChild(d);
            });
            box.style.display = list.length ? "block" : "none";
        }
        function matches(q) {
            return items.filter((it) => cfg.search(it).toLowerCase().includes(q)).slice(0, limit);
        }

        input.addEventListener("focus", () => {
            const q = input.value.trim().toLowerCase();
            render(q ? matches(q) : items.slice(0, limit));
        });
        input.addEventListener("input", () => {
            if (cfg.onInput) cfg.onInput();
            const q = input.value.trim().toLowerCase();
            if (!q) {
                render(items.slice(0, limit));
                return;
            }
            render(matches(q));
        });
        input.addEventListener("blur", () => setTimeout(closeBox, 180));
        document.addEventListener("click", (e) => {
            if (!input.contains(e.target) && !box.contains(e.target)) closeBox();
        });
    }

    window.initSuggest = initSuggest;

    // Stock Movement search (Whole Lot)
    initSuggest({
        input: "#stockItemSearch",
        box: "#stockSuggest",
        items: window.STOCK_ITEMS,
        label: (it) => it.name + (it.brand ? "  ·  " + it.brand : ""),
        sub: (it) => (it.serial || "") + "  —  On hand: " + it.quantity,
        search: (it) => (it.name || "") + " " + (it.brand || "") + " " + (it.serial || ""),
        onInput: () => { const hidden = $("#stockItemId"); if (hidden) hidden.value = ""; },
        pick: (it) => {
            const hidden = $("#stockItemId");
            if (hidden) hidden.value = it.id;
            const input = $("#stockItemSearch");
            if (input) input.value = it.name + (it.brand ? "  (" + it.brand + ")" : "");
            if (window.showItemCard) window.showItemCard(it);
        },
    });

    // Stock Out: Individual Unit Serial autocomplete
    initSuggest({
        input: "#manualSerialInput",
        box: "#serialSuggestBox",
        items: window.AVAILABLE_UNITS || [],
        limit: 30,
        label: (u) => u.serial,
        sub: (u) => (u.name || "") + (u.brand ? "  ·  " + u.brand : "") + (u.location ? "  —  " + u.location : ""),
        search: (u) => (u.serial || "") + " " + (u.name || "") + " " + (u.brand || "") + " " + (u.location || ""),
        onInput: () => {
            const snVal = ($("#manualSerialInput")?.value || "").trim().toLowerCase();
            const exact = (window.AVAILABLE_UNITS || []).find((u) => u.serial && u.serial.toLowerCase() === snVal);
            if (exact && window.selectUnitForStockOut) {
                window.selectUnitForStockOut(exact);
            } else {
                const hiddenUnit = $("#stockUnitId");
                if (hiddenUnit) hiddenUnit.value = "";
            }
        },
        pick: (u) => {
            if (window.selectUnitForStockOut) {
                window.selectUnitForStockOut(u);
            }
        },
    });

    // Add Item search
    initSuggest({
        input: "#product_name",
        box: "#productSuggest",
        items: window.MASTER_ITEMS,
        label: (it) => it.product_name + (it.brand ? "  ·  " + it.brand : ""),
        search: (it) => (it.product_name || "") + " " + (it.brand || ""),
        pick: (it) => {
            const input = $("#product_name");
            if (input) input.value = it.product_name;
            const brandSelect = $("#brand");
            if (brandSelect) brandSelect.value = it.brand || "";
        },
    });

    // Edit Item search
    initSuggest({
        input: "#edit_item_name",
        box: "#editItemNameSuggest",
        items: window.MASTER_ITEMS,
        label: (it) => it.product_name + (it.brand ? "  ·  " + it.brand : ""),
        search: (it) => (it.product_name || "") + " " + (it.brand || ""),
        pick: (it) => {
            const input = $("#edit_item_name");
            if (input) {
                input.value = it.product_name;
                input.dataset.valid = it.product_name;
            }
            const brandInput = $("#edit_brand");
            if (brandInput && it.brand) {
                brandInput.value = it.brand;
                brandInput.dataset.valid = it.brand;
            }
        },
    });

    // Brand autocomplete
    const brandItems = (Array.isArray(window.BRANDS) ? window.BRANDS : []).filter(Boolean).map((name) => ({ name }));
    $all(".js-brand-suggest").forEach((input) => {
        const box = input.parentElement ? input.parentElement.querySelector(".suggest-box") : null;
        if (!box || !brandItems.length) return;
        initSuggest({
            input: "#" + input.id,
            box: "#" + box.id,
            items: brandItems,
            limit: 50,
            label: (b) => b.name,
            search: (b) => b.name,
            pick: (b) => { input.value = b.name; },
        });
    });

    // Edit validation
    const editItemNameEl = $("#edit_item_name");
    if (editItemNameEl) {
        editItemNameEl.addEventListener("change", () => {
            const val = editItemNameEl.value.trim().toLowerCase();
            const match = (window.MASTER_ITEMS || []).find((it) => it.product_name.toLowerCase() === val);
            if (match) {
                editItemNameEl.value = match.product_name;
                editItemNameEl.dataset.valid = match.product_name;
                if (match.brand && $("#edit_brand")) {
                    $("#edit_brand").value = match.brand;
                    $("#edit_brand").dataset.valid = match.brand;
                }
            } else {
                editItemNameEl.value = editItemNameEl.dataset.valid || "";
            }
        });
    }

    const editBrandEl = $("#edit_brand");
    if (editBrandEl) {
        editBrandEl.addEventListener("change", () => {
            const val = editBrandEl.value.trim().toLowerCase();
            const allBrands = (Array.isArray(window.BRANDS) ? window.BRANDS : []);
            const match = allBrands.find((b) => b.toLowerCase() === val);
            if (match) {
                editBrandEl.value = match;
                editBrandEl.dataset.valid = match;
            } else if (val === "") {
                editBrandEl.value = "";
                editBrandEl.dataset.valid = "";
            } else {
                editBrandEl.value = editBrandEl.dataset.valid || "";
            }
        });
    }

    const editFormEl = $("#editItemForm");
    if (editFormEl) {
        editFormEl.addEventListener("submit", (e) => {
            const name = ($("#edit_item_name")?.value || "").trim();
            const brand = ($("#edit_brand")?.value || "").trim();
            const valid = (window.MASTER_ITEMS || []).some(
                (it) => it.product_name.toLowerCase() === name.toLowerCase() && (it.brand || "").toLowerCase() === brand.toLowerCase()
            );
            if (!valid) {
                e.preventDefault();
                alert("Please select a valid Item Name and Brand from Master Data.");
                return false;
            }
        });
    }

    if (window.location.hash === "#add-item") {
        setCardVisible("add", true);
    }
})();
