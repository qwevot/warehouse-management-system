// ============================================================
// INVENTORY MODALS MODULE: Item details, Add/Edit cards, Photo & Autocomplete
// ============================================================
(function () {
    "use strict";

    const $ = window.$;
    const $all = window.$all;

    function resolvePhotoUrl(src) {
        if (!src) return "";
        if (src.startsWith("http://") || src.startsWith("https://") || src.startsWith("data:") || src.startsWith("/")) {
            return src;
        }
        const base = (window.BASE || window.BASE_URL || "/Inventory").replace(/\/+$/, "");
        return base + "/" + src.replace(/^\/+/, "");
    }
    window.resolvePhotoUrl = resolvePhotoUrl;

    // View Item Modal
    window.openItemModal = function (id) {
        const item = (window.INVENTORY || []).find((it) => it.id === id);
        if (!item) return;

        const photoEl = $("#detailPhoto");
        if (photoEl) {
            photoEl.src = resolvePhotoUrl(item.photo);
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
            const rawStatus = (item.status || "available").toString().trim();
            const formattedText = rawStatus.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
            status.textContent = formattedText;
            status.className = "status " + rawStatus.toLowerCase().replace(/\s+/g, "_");
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

        const isTransferPage = !!document.getElementById("transferForm") || (window.INVENTORY_MODE === "transfers");

        const badgeEl = document.getElementById("selCardBadge");
        if (badgeEl) {
            if (isTransferPage) {
                badgeEl.style.display = "inline-block";
                badgeEl.innerHTML = '<span style="background:var(--card-border, #e2e8f0); color:var(--text, #334155); font-size:11px; padding:2px 8px; border-radius:12px; font-weight:600; letter-spacing:0.5px;">WAREHOUSE LOT</span>';
            } else if (it.is_unit || it.serial) {
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
            if (isTransferPage) {
                if (serialRow) serialRow.style.display = "none";
            } else {
                serialEl.textContent = it.serial ? it.serial : "—";
                if (serialRow) serialRow.style.display = "";
            }
        }

        const brandEl = document.getElementById("selCardBrand");
        if (brandEl) brandEl.textContent = it.brand || "—";

        const qtyRow = document.getElementById("selCardQtyRow");
        const qtyEl = document.getElementById("selCardQty");
        if (qtyEl) {
            if (isTransferPage) {
                const q = (it.batch_quantity !== undefined && it.batch_quantity !== null && it.batch_quantity > 0)
                    ? it.batch_quantity
                    : ((it.quantity !== undefined && it.quantity > 0) ? it.quantity : 1);
                qtyEl.textContent = q + (q === 1 ? " Unit" : " Units");
            } else if (it.is_unit) {
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
            if (photo) { photo.src = resolvePhotoUrl(it.photo); photo.style.display = ""; }
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
            nameInp.classList.remove("input-error");
        }
        const brandInp = $("#edit_brand");
        if (brandInp) {
            brandInp.value = item.brand || "";
            brandInp.dataset.valid = item.brand || "";
        }
        const errEl = $("#editItemNameError");
        if (errEl) {
            errEl.style.display = "none";
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
            if (editPrev) { editPrev.src = resolvePhotoUrl(item.photo); editPrev.style.display = "block"; }
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

    function canvasToDataUrl(img) {
        const maxW = 768;
        const canvas = document.createElement("canvas");
        const ratio = Math.min(1, maxW / img.naturalWidth);
        canvas.width = img.naturalWidth * ratio;
        canvas.height = img.naturalHeight * ratio;
        canvas.getContext("2d").drawImage(img, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL("image/jpeg", 0.65);
    }

    [["#photoFile", "add"], ["#editPhotoFile", "edit"]].forEach(([sel, mode]) => {
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
            if (!nameField || !nameField.value.trim()) {
                e.preventDefault();
                alert("Item name is required.");
            }
        });
    }

    const editForm = $("#editItemForm");
    if (editForm) {
        editForm.addEventListener("submit", async function (e) {
            const nameInp = $("#edit_item_name");
            const brandInp = $("#edit_brand");
            const errEl = $("#editItemNameError");
            if (!nameInp) return;

            const trimmedName = nameInp.value.trim();
            const trimmedBrand = brandInp ? brandInp.value.trim() : "";

            if (!trimmedName) {
                e.preventDefault();
                if (errEl) {
                    errEl.textContent = "Item name is required.";
                    errEl.style.display = "block";
                }
                nameInp.classList.add("input-error");
                nameInp.focus();
                return;
            }

            // If already explicitly marked valid from autocomplete or initial load
            if (nameInp.dataset.valid && nameInp.dataset.valid.toLowerCase() === trimmedName.toLowerCase()) {
                if (errEl) errEl.style.display = "none";
                nameInp.classList.remove("input-error");
                return;
            }

            // Otherwise verify against master items catalog
            e.preventDefault();
            try {
                const baseUrl = window.BASE_URL || "/Inventory";
                const res = await fetch(`${baseUrl}/actions/suggest.php?type=master&q=${encodeURIComponent(trimmedName)}&limit=10`);
                const data = await res.json();
                const exact = (data.items || []).find(it => 
                    it.product_name && it.product_name.toLowerCase() === trimmedName.toLowerCase() &&
                    (!trimmedBrand || !it.brand || it.brand.toLowerCase() === trimmedBrand.toLowerCase())
                );

                if (exact) {
                    nameInp.value = exact.product_name;
                    nameInp.dataset.valid = exact.product_name;
                    if (brandInp && exact.brand) {
                        brandInp.value = exact.brand;
                        brandInp.dataset.valid = exact.brand;
                    }
                    if (errEl) errEl.style.display = "none";
                    nameInp.classList.remove("input-error");
                    editForm.submit();
                } else {
                    if (errEl) {
                        errEl.textContent = "⚠️ Product '" + trimmedName + "' does not exist in Master Items. Please select an existing item.";
                        errEl.style.display = "block";
                    }
                    nameInp.classList.add("input-error");
                    nameInp.focus();
                    if (window.showFlash) {
                        window.showFlash("Please choose an existing product from Master Items.", "error");
                    }
                }
            } catch (err) {
                if (errEl) {
                    errEl.textContent = "⚠️ Please select an existing item from Master Items.";
                    errEl.style.display = "block";
                }
                nameInp.classList.add("input-error");
                nameInp.focus();
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

    // Autocomplete Dropdowns
    function initSuggest(cfg) {
        const input = $(cfg.input);
        const box = $(cfg.box);
        if (!input || !box) return;
        const items = cfg.items || [];
        if (!cfg.ajaxType && (!cfg.items || !cfg.items.length)) return;
        const limit = cfg.limit || 15;
        let debounceTimer = null;
        let activeFetchAbort = null;

        function closeBox() { 
            box.innerHTML = ""; 
            box.style.display = "none"; 
        }

        function render(list) {
            box.innerHTML = "";
            if (!list || !list.length) {
                box.style.display = "none";
                return;
            }
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
            box.style.display = "block";
        }

        function query(q) {
            if (cfg.ajaxType) {
                if (activeFetchAbort) {
                    activeFetchAbort.abort();
                }
                activeFetchAbort = new AbortController();
                const baseUrl = window.BASE_URL || "/Inventory";
                fetch(`${baseUrl}/actions/suggest.php?type=${encodeURIComponent(cfg.ajaxType)}&q=${encodeURIComponent(q)}&limit=${limit}`, {
                    signal: activeFetchAbort.signal
                })
                .then(res => res.json())
                .then(data => {
                    if (data.ok && data.items) {
                        render(data.items);
                    } else {
                        render([]);
                    }
                })
                .catch(err => {
                    if (err.name !== 'AbortError') render([]);
                });
            } else if (Array.isArray(cfg.items)) {
                const list = q ? cfg.items.filter((it) => cfg.search(it).toLowerCase().includes(q)).slice(0, limit) : cfg.items.slice(0, limit);
                render(list);
            }
        }

        input.addEventListener("focus", () => {
            const q = input.value.trim().toLowerCase();
            query(q);
        });

        input.addEventListener("input", () => {
            if (cfg.onInput) cfg.onInput();
            const q = input.value.trim().toLowerCase();
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                query(q);
            }, 180);
        });

        input.addEventListener("blur", () => setTimeout(closeBox, 200));
        document.addEventListener("click", (e) => {
            if (!input.contains(e.target) && !box.contains(e.target)) closeBox();
        });
    }

    window.initSuggest = initSuggest;

    // Stock Movement search (Whole Lot)
    initSuggest({
        input: "#stockItemSearch",
        box: "#stockSuggest",
        ajaxType: "stock_items",
        label: (it) => it.name + (it.brand ? "  ·  " + it.brand : ""),
        sub: (it) => (it.barcode ? "Barcode: " + it.barcode + "  —  " : "") + "On hand: " + it.quantity,
        onInput: () => {
            const hidden = $("#stockItemId");
            if (hidden) hidden.value = "";
            const uHidden = $("#transferUnitId");
            if (uHidden) uHidden.value = "";
        },
        pick: (it) => {
            const hidden = $("#stockItemId");
            if (hidden) hidden.value = it.id;
            const uHidden = $("#transferUnitId");
            if (uHidden) uHidden.value = "";
            const input = $("#stockItemSearch");
            if (input) input.value = it.name + (it.brand ? "  (" + it.brand + ")" : "");
            if (window.showItemCard) window.showItemCard(it);
            if (typeof wizardData !== 'undefined') {
                wizardData.item_id = it.id;
                if (typeof saveWizardDraft === 'function') saveWizardDraft();
            }
        },
    });

    // Stock Out: Individual Unit Serial autocomplete
    initSuggest({
        input: "#manualSerialInput",
        box: "#serialSuggestBox",
        ajaxType: "available_units",
        limit: 30,
        label: (u) => u.serial,
        sub: (u) => (u.name || "") + (u.brand ? "  ·  " + u.brand : "") + (u.location ? "  —  " + u.location : ""),
        onInput: () => {
            const snVal = ($("#manualSerialInput")?.value || "").trim();
            if (snVal && window.selectUnitForStockOut) {
                const baseUrl = window.BASE_URL || "/Inventory";
                fetch(`${baseUrl}/actions/suggest.php?type=available_units&q=${encodeURIComponent(snVal)}&limit=1`)
                    .then(res => res.json())
                    .then(data => {
                        const exact = (data.items || []).find(u => u.serial && u.serial.toLowerCase() === snVal.toLowerCase());
                        if (exact) {
                            window.selectUnitForStockOut(exact);
                        } else {
                            const hiddenUnit = $("#stockUnitId");
                            if (hiddenUnit) hiddenUnit.value = "";
                        }
                    })
                    .catch(() => {});
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
        ajaxType: "master",
        label: (it) => it.product_name + (it.brand ? "  ·  " + it.brand : ""),
        pick: (it) => {
            const input = $("#product_name");
            if (input) {
                input.value = it.product_name;
                input.dataset.valid = it.product_name;
            }
            const brandSelect = $("#brand");
            if (brandSelect) brandSelect.value = it.brand || "";
        },
    });

    // Edit Item search
    initSuggest({
        input: "#edit_item_name",
        box: "#editItemNameSuggest",
        ajaxType: "master",
        label: (it) => it.product_name + (it.brand ? "  ·  " + it.brand : ""),
        onInput: () => {
            const input = $("#edit_item_name");
            if (input && input.value.trim().toLowerCase() !== (input.dataset.valid || "").toLowerCase()) {
                input.dataset.valid = "";
            }
        },
        pick: (it) => {
            const input = $("#edit_item_name");
            if (input) {
                input.value = it.product_name;
                input.dataset.valid = it.product_name;
                input.classList.remove("input-error");
            }
            const errEl = $("#editItemNameError");
            if (errEl) {
                errEl.style.display = "none";
            }
            const brandInput = $("#edit_brand");
            if (brandInput && it.brand) {
                brandInput.value = it.brand;
                brandInput.dataset.valid = it.brand;
            }
        },
    });

    // Brand autocomplete
    $all(".js-brand-suggest").forEach((input) => {
        const box = input.parentElement ? input.parentElement.querySelector(".suggest-box") : null;
        if (!box) return;
        initSuggest({
            input: "#" + input.id,
            box: "#" + box.id,
            ajaxType: "brands",
            limit: 30,
            label: (b) => b.name,
            pick: (b) => { input.value = b.name; },
        });
    });

    if (window.location.hash === "#add-item") {
        setCardVisible("add", true);
    }
})();
