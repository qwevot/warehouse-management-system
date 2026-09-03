(function () {
    "use strict";

    const $ = (sel) => document.querySelector(sel);
    const $all = (sel) => Array.from(document.querySelectorAll(sel));

    const BASE = "/Inventory";

    // ---- Page loader (buffering overlay) -------------------------------------
    // Reference-counted so overlapping work (navigation + a fetch, several
    // fetches, a lazy script load) only hides the overlay once everything has
    // settled. A short delay skips a flicker on fast AJAX; navigation shows it
    // immediately for instant feedback.
    const loader = document.getElementById("pageLoader");
    let loaderPending = 0;
    let loaderTimer = null;

    function showLoader(immediate) {
        loaderPending++;
        if (!loader || loader.classList.contains("active")) return;
        if (immediate) {
            loader.classList.add("active");
        } else if (loaderTimer === null) {
            loaderTimer = setTimeout(() => {
                loaderTimer = null;
                if (loaderPending > 0) loader.classList.add("active");
            }, 300);
        }
    }

    function hideLoader() {
        if (loaderPending > 0) loaderPending--;
        if (loaderPending === 0) {
            if (loaderTimer !== null) {
                clearTimeout(loaderTimer);
                loaderTimer = null;
            }
            if (loader) loader.classList.remove("active");
        }
    }

    window.showLoader = showLoader;
    window.hideLoader = hideLoader;
    window.addEventListener("pageshow", hideLoader);

    function post(url, data) {
        return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams(data)
        });
    }

    // Small flash-bar system so AJAX actions can show an update notification
    // after the page reloads (like the ?added/?edited flashes on page load).
    function showFlash(message, type) {
        const content = document.querySelector(".content");
        if (!content || !message) return;
        const el = document.createElement("div");
        el.className = "flash " + (type || "success");
        el.textContent = message;
        el.style.cursor = "pointer";
        el.addEventListener("click", () => el.remove());
        content.prepend(el);
        setTimeout(() => {
            el.style.transition = "opacity .3s";
            el.style.opacity = "0";
            setTimeout(() => el.remove(), 300);
        }, 4500);
    }

    function queueFlash(message, type) {
        if (!message) return;
        sessionStorage.setItem("pendingFlash", JSON.stringify({ message: message, type: type || "success" }));
    }

    window.showFlash = showFlash;

    function consumeFlash() {
        const raw = sessionStorage.getItem("pendingFlash");
        if (!raw) return;
        sessionStorage.removeItem("pendingFlash");
        try {
            const d = JSON.parse(raw);
            showFlash(d.message, d.type);
        } catch (e) { /* ignore malformed flash */ }
    }

    function openModal(id) {
        document.getElementById(id).classList.add("open");
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove("open");
    }

    window.closeModal = closeModal;

    function collectFilters() {
        const f = {};
        for (const a of ["brand", "shelf", "row", "col", "created"]) f[a] = new Set(axisFilters[a]);
        return f;
    }

    // The date buckets a given UNIX timestamp belongs to. A row always matches
    // 'all'; recent rows also match one or more specific ranges so selecting a
    // combination acts as a union (e.g. Today + Past Week).
    function createdBuckets(ts) {
        const t = parseInt(ts, 10);
        if (!t) return ['all'];
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime() / 1000;
        const out = ['all'];
        if (t >= start) out.push('today');
        if (t >= start - 6 * 86400) out.push('week');
        if (t >= start - 29 * 86400) out.push('month');
        return out;
    }

    // A row is shown when it matches every selected group; within a group the
    // selections are OR'd. If a group has nothing selected, it imposes no limit.
    function rowMatches(tr, parts, filters) {
        const axisIdx = { shelf: 0, row: 1, col: 2 };
        for (const g of ["brand", "shelf", "row", "col"]) {
            const set = filters[g];
            if (set.size === 0) continue;
            const ok = g === "brand" ? set.has(tr.dataset.brand) : set.has(parts[axisIdx[g]]);
            if (!ok) return false;
        }
        const dset = filters.created;
        if (dset.size > 0) {
            const buckets = createdBuckets(tr.dataset.created);
            if (!buckets.some((b) => dset.has(b))) return false;
        }
        return true;
    }

    function updateFilterBadge() {
        const n = ["brand", "shelf", "row", "col", "created"].reduce((sum, a) => sum + axisFilters[a].size, 0);
        const badge = $("#filterBadge");
        if (badge) {
            badge.style.display = n ? "inline-flex" : "none";
            badge.textContent = n;
        }
    }

    function sortRows(rows, sort) {
        rows.sort((a, b) => {
            const idA = parseInt(a.dataset.id, 10) || 0;
            const idB = parseInt(b.dataset.id, 10) || 0;
            if (sort === "newest") return idB - idA;
            if (sort === "oldest") return idA - idB;
            const nameOf = (tr) => {
                const el = tr.querySelector(".item-name");
                return el ? el.textContent.trim().toLowerCase() : "";
            };
            if (sort === "name") return nameOf(a).localeCompare(nameOf(b));
            if (sort === "nameDesc") return nameOf(b).localeCompare(nameOf(a));
            return 0;
        });
        return rows;
    }

    const DEFAULT_PAGE_SIZE = 10;
    let PAGE_SIZE = DEFAULT_PAGE_SIZE;
    let currentPage = 1;

    const axisFilters = { brand: new Set(), shelf: new Set(), row: new Set(), col: new Set(), created: new Set() };

    function renderInventory(resetPage) {
        const searchInput = $("#searchInput");
        if (!searchInput) return;

        if (resetPage) currentPage = 1;

        const query = searchInput.value.toLowerCase();
        const sort = $("#sortSelect") ? $("#sortSelect").value : "newest";
        const filters = collectFilters();
        updateFilterBadge();

        const allInvRows = $all(".inventory-row");
        let rows = allInvRows.filter((tr) => {
            if (tr._searchText === undefined) {
                tr._searchText = tr.textContent.toLowerCase();
            }
            const parts = (tr.dataset.loc || "").split("-");
            return (
                tr._searchText.includes(query) &&
                rowMatches(tr, parts, filters)
            );
        });

        sortRows(rows, sort);

        const total = rows.length;
        const pageCount = Math.max(1, Math.ceil(total / PAGE_SIZE));
        if (currentPage > pageCount) currentPage = pageCount;
        sessionStorage.setItem("invPage", currentPage);

        const start = (currentPage - 1) * PAGE_SIZE;
        const pageRows = rows.slice(start, start + PAGE_SIZE);

        allInvRows.forEach((tr) => {
            if (tr.style.display !== "none") tr.style.display = "none";
        });
        pageRows.forEach((tr, i) => {
            tr.style.display = "";
            const cell = tr.querySelector(".no-col");
            if (cell) cell.textContent = start + i + 1;
        });

        const tbody = $("#inventoryTable");
        if (tbody) pageRows.forEach((tr) => tbody.appendChild(tr));

        const prev = $("#pagePrev");
        const next = $("#pageNext");
        if (prev) prev.disabled = currentPage <= 1;
        if (next) next.disabled = currentPage >= pageCount;

        const numbers = $("#pageNumbers");
        if (numbers) {
            numbers.innerHTML = "";
            pageRange(currentPage, pageCount).forEach((p) => {
                if (p === "...") {
                    const s = document.createElement("span");
                    s.className = "pg-ellipsis";
                    s.textContent = "…";
                    numbers.appendChild(s);
                } else {
                    const b = document.createElement("button");
                    b.type = "button";
                    b.className = "pg-btn pg-num" + (p === currentPage ? " active" : "");
                    b.textContent = p;
                    b.onclick = () => { currentPage = p; renderInventory(false); };
                    numbers.appendChild(b);
                }
            });
        }

        const range = $("#pageRange");
        if (range) {
            const from = total === 0 ? 0 : start + 1;
            const to = start + pageRows.length;
            range.textContent = from + "-" + to + " of " + total;
        }

        const wrap = $("#pagination");
        if (wrap) wrap.style.display = "";
    }

    function pageRange(current, total) {
        const delta = 1;
        const set = new Set([1, total]);
        for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
            set.add(i);
        }
        const sorted = Array.from(set).sort((a, b) => a - b);
        const out = [];
        let prev = 0;
        for (const p of sorted) {
            if (p - prev > 1) out.push("...");
            out.push(p);
            prev = p;
        }
        return out;
    }

    window.filterInventory = function () { renderInventory(true); };

    const EXPORT_COLUMNS = [
        { key: "id", label: "No." },
        { key: "name", label: "Item" },
        { key: "brand", label: "Brand" },
        { key: "serial", label: "Serial Number" },
        { key: "quantity", label: "Quantity" },
        { key: "location", label: "Location" },
        { key: "status", label: "Status" },
        { key: "barcode", label: "Barcode" },
        { key: "qr", label: "QR" },
        { key: "created_at", label: "Registered" }
    ];

    function exportRows() {
        return (window.INVENTORY || []).map((it) => {
            const row = {};
            EXPORT_COLUMNS.forEach((c) => {
                row[c.label] = it[c.key] ?? "";
            });
            row["Status"] = it.status === "available" ? "ACTIVE" : "INACTIVE";
            return row;
        });
    }

    function downloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function csvEscape(v) {
        const s = String(v ?? "");
        return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    }

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const s = document.createElement("script");
            s.src = src;
            s.onload = resolve;
            s.onerror = () => reject(new Error("Failed to load " + src));
            document.head.appendChild(s);
        });
    }

    window.exportTable = function (format) {
        const rows = exportRows();
        const labels = EXPORT_COLUMNS.map((c) => c.label);
        if (!rows.length) { alert("Nothing to export."); return; }

        if (format === "csv") {
            const lines = [labels.join(",")].concat(rows.map((r) => labels.map((l) => csvEscape(r[l])).join(",")));
            const blob = new Blob(["\uFEFF" + lines.join("\n")], { type: "text/csv;charset=utf-8;" });
            downloadBlob(blob, "inventory.csv");
        } else if (format === "xlsx") {
            ensureXlsx()
                .then(() => {
                    const ws = XLSX.utils.json_to_sheet(rows);
                    ws["!cols"] = labels.map(() => ({ wch: 20 }));
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, "Inventory");
                    XLSX.writeFile(wb, "inventory.xlsx");
                })
                .catch(() => alert("Could not load the Excel exporter."));
        }
    };

    // ---- Import items from CSV / Excel ----
    function ensureXlsx() {
        if (window.XLSX) return Promise.resolve();
        showLoader();
        return loadScript(BASE + "/js/xlsx.full.min.js").finally(hideLoader);
    }

    // Loads the camera/QR scanner library only when scanning is first used.
    function ensureHtml5Qrcode() {
        if (window.Html5Qrcode) return Promise.resolve();
        showLoader();
        return loadScript(BASE + "/js/html5-qrcode.min.js").finally(hideLoader);
    }
    window.ensureHtml5Qrcode = ensureHtml5Qrcode;

    // Shared "Start/Stop Camera" button toggle used by both the scan modal and
    // the standalone Scan page.
    window.setScanToggle = function (starting) {
        const btn = document.getElementById("scanToggleBtn");
        if (!btn) return;
        btn.textContent = starting ? "Start Camera" : "Stop Camera";
        btn.className = starting ? "btn primary" : "btn danger";
    };

    function mapImportRow(r) {
        const pick = (...keys) => {
            for (const k of keys) {
                const v = r[k];
                if (v !== undefined && v !== null && String(v).trim() !== "") return String(v).trim();
            }
            return "";
        };
        const shelf = pick("shelf", "Shelf");
        const row = pick("row", "Row");
        const col = pick("col", "column", "Column");
        const location = pick("location", "Location") || [shelf, row, col].filter(Boolean).join("-");
        return {
            name: pick("name", "Name", "item", "Item", "item_name", "product_name", "Product Name"),
            brand: pick("brand", "Brand"),
            quantity: parseInt(pick("quantity", "qty", "Quantity", "Qty"), 10) || 1,
            location: location,
            serial: pick("serial", "Serial", "serial_number", "Serial Number"),
            status: pick("status", "Status")
        };
    }

    function handleImportFile(file) {
        const fail = (msg) => alert(msg);
        const reader = new FileReader();
        reader.onload = (e) => {
            ensureXlsx()
                .then(() => {
                    const wb = XLSX.read(e.target.result, { type: "array" });
                    const sheet = wb.Sheets[wb.SheetNames[0]];
                    const rows = XLSX.utils.sheet_to_json(sheet, { defval: "", raw: false });
                    if (!rows.length) return fail("No rows found in the file.");

                    const mapped = rows.map(mapImportRow).filter((r) => r.name !== "");
                    if (!mapped.length) return fail("No valid rows with a product name found.");

                    const ok = confirm("Import " + mapped.length + " item(s)?");
                    if (!ok) return;

                    post(BASE + "/actions/import-items.php", { rows: JSON.stringify(mapped) })
                        .then((res) => res.json())
                        .then((d) => {
                            if (!d.ok) throw new Error(d.message || "Import failed.");
                            queueFlash(d.message || "Items imported.");
                            location.reload();
                        })
                        .catch((err) => alert(err.message));
                })
                .catch(() => fail("Could not load the Excel/CSV reader."));
        };
        reader.readAsArrayBuffer(file);
    }

    const importInput = $("#importFile");
    if (importInput) {
        importInput.addEventListener("change", function () {
            const file = this.files && this.files[0];
            if (file) handleImportFile(file);
            this.value = "";
        });
    }

    window.pagePrev = function () {
        if (currentPage > 1) { currentPage--; renderInventory(false); }
    };

    window.pageNext = function () {
        if (currentPage < Math.max(1, Math.ceil($all(".inventory-row").length / PAGE_SIZE))) {
            currentPage++; renderInventory(false);
        }
    };

    window.changePageSize = function () {
        const sel = $("#pageSizeSelect");
        if (!sel) return;
        const v = parseInt(sel.value, 10);
        if (v > 0) PAGE_SIZE = v;
        currentPage = 1;
        renderInventory(true);
    };

    window.toggleFilterPanel = function () {
        const wrap = $("#filterWrap");
        if (wrap) wrap.classList.toggle("open");
    };

    function renderChips() {
        $all(".axis-chips").forEach((chips) => {
            const axis = chips.dataset.chips;
            const dateRow = chips.querySelector(".axis-date-row");
            chips.innerHTML = "";
            if (dateRow) chips.appendChild(dateRow); // keep the fixed date toggles
            if (axis === "created") return; // date state is shown by the toggle buttons
            [...axisFilters[axis]].sort().forEach((v) => {
                const c = document.createElement("button");
                c.type = "button";
                c.className = "axis-chip";
                c.textContent = v + " ×";
                c.onclick = () => { axisFilters[axis].delete(v); renderChips(); filterInventory(); };
                chips.appendChild(c);
            });
        });
        $all(".axis-date").forEach((b) => {
            b.classList.toggle("active", axisFilters.created.has(b.dataset.value));
        });
    }

    function setupAxisFilters() {
        const used = { brand: new Set(), shelf: new Set(), row: new Set(), col: new Set() };
        (window.INVENTORY || []).forEach((it) => {
            if (it.brand) used.brand.add(it.brand);
            const parts = (it.location || "").split("-");
            if (parts.length === 3) {
                used.shelf.add(parts[0]);
                used.row.add(parts[1]);
                used.col.add(parts[2]);
            }
        });

        $all(".axis-input").forEach((input) => {
            const axis = input.dataset.axis;
            const dl = document.createElement("datalist");
            dl.id = "axisList-" + axis;
            [...used[axis]].sort().forEach((v) => {
                const o = document.createElement("option");
                o.value = v;
                dl.appendChild(o);
            });
            input.setAttribute("list", dl.id);
            document.body.appendChild(dl);

            input.addEventListener("input", () => {
                const v = axis === "brand" ? input.value.trim() : input.value.trim().toUpperCase();
                if (v && used[axis].has(v)) {
                    axisFilters[axis].add(v);
                    input.value = "";
                    renderChips();
                    filterInventory();
                }
            });
        });

        // Date filter: fixed toggle chips for Today / Past Week / Past Month /
        // All. Clicking a chip toggles that bucket in the created filter set.
        const createdWrap = document.querySelector('.axis-chips[data-chips="created"]');
        if (createdWrap && !createdWrap.querySelector(".axis-date") && (window.INVENTORY || []).length) {
            const dateOptions = [
                { value: "today", label: "Today" },
                { value: "week",  label: "Past Week" },
                { value: "month", label: "Past Month" },
                { value: "all",   label: "All" },
            ];
            const row = document.createElement("div");
            row.className = "axis-date-row";
            dateOptions.forEach((opt) => {
                const b = document.createElement("button");
                b.type = "button";
                b.className = "axis-date";
                b.dataset.value = opt.value;
                b.textContent = opt.label;
                b.addEventListener("click", () => {
                    const s = axisFilters.created;
                    if (s.has(opt.value)) s.delete(opt.value); else s.add(opt.value);
                    renderChips();
                    filterInventory();
                });
                row.appendChild(b);
            });
            createdWrap.insertBefore(row, createdWrap.firstChild);
        }

        renderChips();
    }

    window.clearFilters = function () {
        for (const a of ["brand", "shelf", "row", "col", "created"]) axisFilters[a].clear();
        renderChips();
        filterInventory();
    };

    window.toggleFilterPanel = function (e) {
        if (e && e.stopPropagation) e.stopPropagation();
        const wrap = $("#filterWrap");
        if (wrap) wrap.classList.toggle("open");
    };

    // Clicking anywhere outside the panel closes it.
    document.addEventListener("click", (e) => {
        const wrap = $("#filterWrap");
        if (wrap && wrap.classList.contains("open") && !wrap.contains(e.target)) {
            wrap.classList.remove("open");
        }
    });

    window.openItemModal = function (id) {
        const item = (window.INVENTORY || []).find((it) => it.id === id);
        if (!item) return;

        $("#detailPhoto").src = item.photo || "";
        $("#detailPhoto").style.display = item.photo ? "" : "none";
        $("#detailName").textContent = item.name;
        $("#detailSerial").textContent = item.serial;
        $("#detailQty").textContent = item.quantity || 0;
        $("#detailLocation").textContent = item.location || "—";
        $("#detailDate").textContent = item.created_at || "—";

        const status = $("#detailStatus");
        status.textContent = item.status === "available" ? "Active" : "Inactive";
        status.className = "status " + item.status;

        $("#detailBarcode").innerHTML = "";
        if (item.barcode) {
            JsBarcode("#detailBarcode", item.barcode, {
                format: "EAN13", displayValue: true, width: 2, height: 50, fontSize: 13
            });
        }

        $("#detailQr").innerHTML = "";
        if (item.qr) {
            new QRCode($("#detailQr"), { text: item.qr, width: 132, height: 132 });
        }

        openModal("itemModal");
    };

    function runAction(url, data, btn, failMsg, successMsg) {
        if (btn) btn.disabled = true;
        post(url, data)
            .then((res) => res.json())
            .then((d) => {
                if (d.ok) {
                    queueFlash(successMsg || d.message || "Update saved.");
                    location.reload();
                } else {
                    alert(d.message || failMsg);
                    if (btn) btn.disabled = false;
                }
            })
            .catch(() => {
                alert(failMsg);
                if (btn) btn.disabled = false;
            });
    }

    // Submit a hidden POST form (used by the delete flows in the master-data
    // and users pages).
    window.submitPostForm = function (action, data) {
        const f = document.createElement("form");
        f.method = "POST";
        f.action = action;
        Object.keys(data || {}).forEach((key) => {
            const inp = document.createElement("input");
            inp.type = "hidden";
            inp.name = key;
            inp.value = data[key];
            f.appendChild(inp);
        });
        document.body.appendChild(f);
        f.submit();
    };

// Reusable modal that asks a question and optionally collects a single
    // text value. Resolves with the trimmed text on OK (possibly empty), or
    // null if the user cancels/closes. withNote=false hides the note field so
    // the same modal can be reused for a plain confirmation.
    function promptModal({ title, message, placeholder, confirmLabel, withNote = true, noteLabel = "Bin note (optional)" }) {
        return new Promise((resolve) => {
            const backdrop = document.createElement("div");
            backdrop.className = "modal-backdrop open";
            backdrop.innerHTML =
                '<div class="modal">' +
                    '<div class="modal-header">' +
                        '<div class="modal-title">' + title + '</div>' +
                        '<button type="button" class="modal-close" aria-label="Close">&times;</button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<p style="margin:0 0 12px; color:var(--red);">' + message + '</p>' +
                        (withNote
                            ? '<div class="field">' +
                                '<label for="promptNote">' + noteLabel + '</label>' +
                                '<input type="text" id="promptNote" placeholder="' + placeholder + '" maxlength="255">' +
                              '</div>'
                            : '') +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn secondary modal-cancel">Cancel</button>' +
                        '<button type="button" class="btn danger modal-ok">' + confirmLabel + '</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(backdrop);

            const close = (result) => {
                backdrop.classList.remove("open");
                backdrop.remove();
                resolve(result);
            };

            backdrop.querySelector(".modal-close").addEventListener("click", () => close(null));
            backdrop.querySelector(".modal-cancel").addEventListener("click", () => close(null));
            backdrop.querySelector(".modal-ok").addEventListener("click", () => {
                const note = withNote ? backdrop.querySelector("#promptNote").value.trim() : "";
                close(note);
            });
            backdrop.addEventListener("click", (e) => {
                if (e.target === backdrop) close(null);
            });
            const field = backdrop.querySelector("#promptNote");
            if (field) field.focus(); else backdrop.querySelector(".modal-ok").focus();
        });
    }
    window.promptModal = promptModal;

    window.runDeleteItem = async function (id) {
        const note = await promptModal({
            title: "Move item to the bin?",
            message: "This item will be moved to the bin.",
            placeholder: "Optional bin note",
            confirmLabel: "Move to Bin",
        });
        if (note === null) return;
        runAction(BASE + "/actions/delete-item.php", { id: id, note: note }, null, "Delete failed.", "Item moved to the bin.");
    };

    window.runRestoreItem = function (id) {
        if (!confirm("Restore this item to the inventory?")) return;
        runAction(BASE + "/actions/restore-item.php", { id: id }, null, "Restore failed.", "Item restored.");
    };

    window.runPermanentDelete = function (id) {
        if (!confirm("Permanently delete this item? This cannot be undone.")) return;
        runAction(BASE + "/actions/delete-permanent.php", { id: id }, null, "Delete failed.", "Item permanently deleted.");
    };

    window.runEditMoveToBin = function () {
        const btn = $("#editBinBtn");
        const id = btn ? btn.dataset.id : null;
        if (!id) return;
        closeModal("editModal");
        window.runDeleteItem(id);
    };

    window.runEditStatus = function () {
        const btn = $("#editStatusBtn");
        if (!btn) return;
        const label = btn.dataset.action === "taken"
            ? "Move this item out of the warehouse?"
            : "Return this item to the warehouse?";
        if (!confirm(label)) return;
        runAction(BASE + "/actions/update-status.php", { id: btn.dataset.id, action: btn.dataset.action }, btn, "Update failed.", "Status updated.");
    };

    $all(".modal-close").forEach((btn) => {
        btn.addEventListener("click", () => {
            btn.closest(".modal-backdrop").classList.remove("open");
        });
    });

    // The add-item / master-item / brand forms are cards that toggle below the
    // page header; the header button flips to a red Cancel while open.
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
        const panel = $(addForms[name].panel);
        if (!panel) return;
        setCardVisible(name, panel.style.display === "none");
    }

    window.closeAddForm        = function () { setCardVisible("add", false); };
    window.toggleAddForm       = function () { toggleCard("add"); };
    window.closeMasterAddForm  = function () { setCardVisible("master", false); };
    window.toggleMasterAddForm = function () { toggleCard("master"); };
    window.closeBrandAddForm   = function () { setCardVisible("brand", false); };
    window.toggleBrandAddForm  = function () { toggleCard("brand"); };

    // Add Master Item card: the product code is auto-filled with the next
    // available code and locked by default. Checking the box unlocks it for
    // manual entry.
    window.toggleProductCodeManual = function () {
        const cb = $("#manualProductCode");
        const input = $("#product_code");
        if (!cb || !input) return;
        input.disabled = !cb.checked;
    };

    window.openEditModal = function (id) {
        const item = (window.INVENTORY || []).find((it) => it.id === id);
        if (!item) return;

        $("#editId").value = item.id;
        $("#edit_item_name").value = item.name;
        const brandSel = $("#edit_brand");
        brandSel.value = item.brand || "";
        $("#edit_quantity").value = item.quantity || 1;
        $("#edit_serial").value = item.serial || "";

        const loc = item.location || "";
        const locMatch = loc.match(/^S(\d+)-R(\d+)-C(\d+)$/);
        if (locMatch) {
            $("#editLocShelf").value = locMatch[1];
            $("#editLocRow").value = locMatch[2];
            $("#editLocCol").value = locMatch[3];
        } else {
            $("#editLocShelf").value = "";
            $("#editLocRow").value = "";
            $("#editLocCol").value = "";
        }
        updateEditLocation();

        $("#editPhotoData").value = "";
        if (item.photo) {
            $("#editPhotoPreview").src = item.photo;
            $("#editPhotoPreview").style.display = "block";
            $("#editPhotoPlaceholder").style.display = "none";
        } else {
            $("#editPhotoPreview").style.display = "none";
            $("#editPhotoPlaceholder").style.display = "";
        }

        const binBtn = $("#editBinBtn");
        if (binBtn) {
            binBtn.dataset.id = item.id;
        }

        openModal("editModal");
    };

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
                    return;
                }
            }
        });
    }

    window.onlyDigits = function (el) {
        el.value = el.value.replace(/[^0-9]/g, "");
    };

    function combineLocation(shelfId, rowId, colId, targetId) {
        const rawS = $(shelfId).value.trim();
        const rawR = $(rowId).value.trim();
        const rawC = $(colId).value.trim();
        if (!(rawS && rawR && rawC)) {
            $(targetId).value = "";
            return;
        }
        const pad = (v) => v.padStart(2, "0");
        $(targetId).value = "S" + pad(rawS) + "-R" + pad(rawR) + "-C" + pad(rawC);
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

    // Fills the shared item-select card (includes/item-card-select.php) with an
    // item's details, barcode, and QR. Used by Scan, Stock In, Stock Out, Transfers.
    window.showItemCard = function (it) {
        if (!it) return;
        document.getElementById("selCardName").textContent = it.name || "";
        document.getElementById("selCardSerial").textContent = it.serial || "";
        document.getElementById("selCardBrand").textContent = it.brand || "—";
        document.getElementById("selCardQty").textContent = it.quantity || 0;
        document.getElementById("selCardLocation").textContent = it.location || "—";
        const statusEl = document.getElementById("selCardStatus");
        const isActive = it.status === "available";
        statusEl.textContent = isActive ? "ACTIVE" : "INACTIVE";
        statusEl.className = "status " + (isActive ? "available" : "taken");

        const photo = document.getElementById("selCardPhoto");
        const ph = document.getElementById("selCardPhotoPh");
        if (it.photo) {
            photo.src = it.photo;
            photo.style.display = "";
            if (ph) ph.style.display = "none";
        } else {
            photo.style.display = "none";
            if (ph) ph.style.display = "";
        }

        const bc = document.getElementById("selCardBarcode");
        bc.innerHTML = "";
        if (it.barcode && window.JsBarcode) {
            JsBarcode(bc, it.barcode, { format: "EAN13", displayValue: true, width: 2, height: 50, fontSize: 12 });
        }

        const qr = document.getElementById("selCardQr");
        qr.innerHTML = "";
        if (it.qr && window.QRCode) {
            new QRCode(qr, { text: it.qr, width: 96, height: 96 });
        }

        document.getElementById("selCard").style.display = "";
    };


    // Collapsible sidebar groups: clicking the toggle opens/closes the children.
    // Desktop: toggles .sidebar-collapsed | Mobile: toggles .sidebar-open
    window.toggleSidebar = function () {
        if (window.innerWidth <= 768) {
            document.body.classList.toggle("sidebar-open");
        } else {
            document.body.classList.toggle("sidebar-collapsed");
        }
    };

    // Close mobile sidebar when clicking outside or clicking any navigation link
    document.addEventListener("click", (e) => {
        if (window.innerWidth <= 768 && document.body.classList.contains("sidebar-open")) {
            const sidebar = document.querySelector(".sidebar");
            const toggleBtn = document.querySelector(".sidebar-toggle");
            if (sidebar && !sidebar.contains(e.target) && toggleBtn && !toggleBtn.contains(e.target)) {
                document.body.classList.remove("sidebar-open");
            }
        }
    });

    // Sidebar category dropdowns: toggle the group that follows the header.
    window.toggleNavGroup = function (btn) {
        btn.classList.toggle("open");
        const group = btn.nextElementSibling;
        if (group) group.classList.toggle("open");
        try {
            const open = [];
            document.querySelectorAll(".nav-group-toggle[data-group]").forEach((t) => {
                if (t.classList.contains("open")) open.push(t.dataset.group);
            });
            localStorage.setItem("sidebar-open-groups", JSON.stringify(open));
        } catch (_) {}
    };

    // Restore sidebar group open/closed state from localStorage on page load.
    (function () {
        let saved = null;
        try { saved = JSON.parse(localStorage.getItem("sidebar-open-groups")); } catch (_) {}
        const activeItem = document.querySelector(".nav-item.active");
        const activeGroup = activeItem ? activeItem.closest(".nav-group") : null;
        const activeToggle = activeGroup ? activeGroup.previousElementSibling : null;
        const activeKey = activeToggle ? activeToggle.dataset.group : null;
        document.querySelectorAll(".nav-group-toggle[data-group]").forEach((btn) => {
            const key = btn.dataset.group;
            const group = btn.nextElementSibling;
            const open = (saved && saved.includes(key)) || key === activeKey;
            btn.classList.toggle("open", open);
            if (group) group.classList.toggle("open", open);
        });
    })();

    // The Barcode & QR Code Generator page: pick an item from the inventory
    // list and generate its barcode + QR labels at the recorded location.
    let genItemId = 0;

    window.openGenModal = function (id) {
        const item = (window.INVENTORY || []).find((it) => it.id === id);
        if (!item) return;

        genItemId = item.id;
        $("#genItemName").textContent = item.name + "  ·  " + item.serial;
        $("#genItemLocation").textContent = item.location || "—";

        if (item.barcode && item.qr) {
            // Already generated: render the existing labels and show the print options.
            $("#genName").textContent = item.name;
            $("#genSerial").textContent = item.serial;
            $("#genLocationValue").textContent = item.location || "N/A";
            $("#genBarcode").innerHTML = "";
            JsBarcode("#genBarcode", item.barcode, {
                format: "EAN13", displayValue: true, width: 2, height: 60, fontSize: 14
            });
            $("#genQr").innerHTML = "";
            new QRCode($("#genQr"), { text: item.qr, width: 132, height: 132 });
            $("#labelsPreview").style.display = "";
            $("#genGenerateBtn").style.display = "none";
            $("#genPrintButtons").style.display = "inline-flex";
        } else {
            $("#labelsPreview").style.display = "none";
            $("#genGenerateBtn").style.display = "";
            $("#genPrintButtons").style.display = "none";
        }
        openModal("genModal");
    };

    window.printScoped = function (scope) {
        document.body.classList.remove("print-barcode", "print-qr", "print-both");
        document.body.classList.add("print-" + scope);
        setTimeout(() => window.print(), 50);
    };

    // ---- Split & Merge (Barcode & QR Code Generator page) ----

    // Split and Merge can't be active at the same time; opening one closes
    // the other. Both use the same checkbox column in the table.

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

    // Generic mutually-exclusive toggle helper shared by Split and Merge: each
    // flips the header button between "Cancel" and its label, shows/hides its
    // panel, clears the selection checkboxes when deactivated, and runs
    // mode-specific reset/activate hooks.
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
            $("#splitParts").value = 2;
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
            $("#mergeBarcode").value = "";
            document.querySelector('input[name="mergeMode"][value="new"]').checked = true;
            updateMergeDestination();
        }
    });

    window.toggleSplitSelection = function () { splitMode.toggle(); };

    // Custom per-part quantities. The inputs are pre-filled with an equal
    // split of the selected item's stock, but the user can edit each freely.
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

    window.renderSplitQtyInputs = function () {
        const container = $("#splitPartsContainer") || $("#splitQtyInputs");
        if (!container) return;
        const parts = Math.min(10, Math.max(2, parseInt($("#splitParts").value, 10) || 2));
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
    };

    function updateSplitQtySum() {
        const sum = $all(".split-qty").reduce((acc, inp) => acc + (parseInt(inp.value, 10) || 0), 0);
        const el = $("#splitQtySum");
        if (el) el.textContent = "Sum: " + sum + " / " + splitBaseQty;
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

        post(BASE + "/actions/split-item.php", { item_id: checked[0], quantities: quantities.join(","), locations: locations.join("||") })
            .then((res) => res.json())
            .then((d) => {
                if (!d.ok) throw new Error(d.message || "Split failed.");
                queueFlash(d.message || "Item split.");
                location.reload();
            })
            .catch((err) => alert(err.message));
    };

    // Merge works in place: toggling Merge shows a checkbox column in the
    // table and a panel above it to pick the destination (new or existing
    // barcode number). The button flips to "Cancel" while active.
    window.toggleMergeSelection = function () { mergeMode.toggle(); };

    window.updateMergeDestination = function () {
        const mode = document.querySelector('input[name="mergeMode"]:checked').value;
        $("#mergeTargetWrap").style.display = mode === "existing" ? "" : "none";
        $("#mergeBarcodeWrap").style.display = mode === "new" ? "" : "none";
    };

    document.addEventListener("change", (e) => {
        if (e.target && e.target.classList && e.target.classList.contains("row-select")) {
            // Split takes exactly one item: checking one unchecks the rest.
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

    // Whole-row click target for Split and Merge modes:
    document.addEventListener("click", (e) => {
        const isSplit = document.body.classList.contains("split-mode");
        const isMerge = document.body.classList.contains("merge-mode");
        if (!isSplit && !isMerge) return;

        // Don't toggle selection if the user clicked interactive controls/modals/buttons inside the row
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
            input.checked = !input.checked;
            refreshMergeTargets();
        }

        updateSelectionUI();
    });

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

    window.submitMerge = function () {
        const ids = $all(".row-select:checked").map((cb) => parseInt(cb.value, 10));
        if (ids.length < 2) return alert("Select at least 2 items to merge.");

        const mode = document.querySelector('input[name="mergeMode"]:checked').value;
        const data = { mode: mode, ids: ids };
        if (mode === "existing") {
            const target = parseInt($("#mergeTargetSelect").value, 10) || 0;
            if (!target) return alert("Choose a target item to merge into.");
            data.target_id = target;
        } else {
            data.barcode = $("#mergeBarcode").value.trim();
        }

        const label = mode === "existing"
            ? "Merge " + ids.length + " items into the target item? This cannot be undone."
            : "Merge " + ids.length + " items into a new item? This cannot be undone.";
        if (!confirm(label)) return;

        post(BASE + "/actions/merge-items.php", data)
            .then((res) => res.json())
            .then((d) => {
                if (!d.ok) throw new Error(d.message || "Merge failed.");
                queueFlash(d.message || "Items merged.");
                location.reload();
            })
            .catch((err) => alert(err.message));
    };

    window.generateItemLabel = function () {
        const btn = $("#genGenerateBtn");
        btn.disabled = true;
        btn.textContent = "Generating...";

        post(BASE + "/actions/generate-label.php", { item_id: genItemId })
            .then((res) => res.json())
            .then((data) => {
                if (!data.ok) throw new Error(data.message || "Could not generate labels.");
                // No need to show a print popup here; the page reloads so the
                // row updates to View/Print buttons. Print via those instead.
                setTimeout(() => location.reload(), 400);
            })
            .catch((err) => alert("Could not generate labels.\n\n" + err.message))
            .finally(() => {
                btn.disabled = false;
                btn.textContent = "Generate Barcode & QR Code";
            });
    };

    // Loading the page with #add-item opens the add-item form.
    if (window.location.hash === "#add-item") {
        setCardVisible("add", true);
    }

    // Google-style autocomplete dropdown, shared by the stock-movement forms
    // (STOCK_ITEMS) and the add-item form (MASTER_ITEMS).
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
                if (cfg.sub) {
                    const sub = document.createElement("div");
                    sub.className = "suggest-sub";
                    sub.textContent = cfg.sub(it);
                    d.appendChild(sub);
                }
                d.appendChild(label);
                d.addEventListener("click", () => { cfg.pick(it); closeBox(); });
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
            if (!q) { closeBox(); return; }
            render(matches(q));
        });
        input.addEventListener("blur", () => setTimeout(closeBox, 150));
        document.addEventListener("click", (e) => {
            if (!input.contains(e.target) && !box.contains(e.target)) closeBox();
        });
    }

    // Stock In / Stock Out / Transfers item search.
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

    // Add-item product-name search (Items and Generator pages).
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

    // Brand autocomplete for every brand field (brand-select.php). Each brand
    // input is wrapped in a .suggest-wrap with a sibling .suggest-box.
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

    // Populate the axis filter search inputs with the used values.
    setupAxisFilters();

    // Populate the table on first load, restoring the page the user was on
    // so actions (delete, split, merge, import) don't jump back to page 1.
    // The page size follows whatever is selected in the Rows dropdown.
    const pageSizeSel = $("#pageSizeSelect");
    if (pageSizeSel) PAGE_SIZE = parseInt(pageSizeSel.value, 10) || DEFAULT_PAGE_SIZE;

    currentPage = parseInt(sessionStorage.getItem("invPage"), 10) || 1;
    renderInventory(false);

    // Wire the shared toolbar/pagination controls (rendered by buildToolbar /
    // buildPagination) to the inventory renderer. These exist only on pages
    // that include inventory-table.php.
    (function wireInventoryToolbar() {
        const bind = (sel, evt, fn) => { const el = $(sel); if (el) el.addEventListener(evt, fn); };
        bind("#searchInput", "input", window.filterInventory);
        bind("#sortSelect", "change", window.filterInventory);
        bind("#pagePrev", "click", window.pagePrev);
        bind("#pageNext", "click", window.pageNext);
        bind("#pageSizeSelect", "change", window.changePageSize);
        bind("#filterBtn", "click", (e) => window.toggleFilterPanel(e));
    })();

    // Show any flash queued by a previous AJAX action (split/merge/etc.).
    consumeFlash();

    // ─── Generic data table controller ───────────────────────────────────────
    // Handles search, sort, checkbox filters, pagination, lazy CSV/XLSX export.
    // cfg: {
    //   root, searchInput, sortSelect, tbody, row,
    //   filterWrap, filterBtn, filterPanel, filterBadge,
    //   pagePrev, pageNext, pageNumbers, pageRange, pageSizeSelect, pagination,
    //   exportFn, exportName, exportCols, data, dataText,
    //   filterGroups: [{title, key, value, dataValue, options}],
    //   sorters: {"value": (trA, trB) => -1|0|1}
    // }
    window.initDataTable = function (cfg) {
        const root = cfg.root || document;
        const q  = (s) => root.querySelector(s);
        const qa = (s) => Array.from(root.querySelectorAll(s));

        const searchInput    = q(cfg.searchInput);
        const sortSelect     = q(cfg.sortSelect);
        const tbody          = q(cfg.tbody);
        const filterWrap     = q(cfg.filterWrap);
        const filterPanel    = q(cfg.filterPanel);
        const filterBadge    = q(cfg.filterBadge);
        const filterBtn      = q(cfg.filterBtn);
        const pagePrev       = q(cfg.pagePrev);
        const pageNext       = q(cfg.pageNext);
        const pageNumbers    = q(cfg.pageNumbers);
        const rangeEl        = q(cfg.pageRange);
        const pagination     = q(cfg.pagination);
        const pageSizeSelect = q(cfg.pageSizeSelect);

        let pageSize    = cfg.pageSize || 10;
        if (pageSizeSelect) {
            const v = parseInt(pageSizeSelect.value, 10);
            if (v > 0) pageSize = v;
        }
        let currentPage = 1;

        const groups     = cfg.filterGroups || [];
        const filterSets = groups.map(() => new Set());
        const caseSensitive = !!cfg.caseSensitive;

        // ── helpers ────────────────────────────────────────────────────────────
        let cachedRows = null;
        function allRows() {
            if (!cachedRows) {
                cachedRows = qa(cfg.row);
                cachedRows.forEach((tr) => {
                    if (tr._searchText === undefined) {
                        tr._searchText = caseSensitive ? tr.textContent : tr.textContent.toLowerCase();
                    }
                });
            }
            return cachedRows;
        }

        function getFilterVal(group, tr) {
            const v = group.value !== undefined ? group.value : group.key;
            if (typeof v === "function") return v(tr);
            if (typeof v === "string") {
                if (tr.dataset && tr.dataset[v] !== undefined) return tr.dataset[v];
                if (group.key && tr.dataset && tr.dataset[group.key] !== undefined) return tr.dataset[group.key];
            }
            return String(tr.textContent);
        }

        function rowMatchesFilters(tr) {
            for (let i = 0; i < groups.length; i++) {
                if (!filterSets[i].size) continue;
                const val = getFilterVal(groups[i], tr);
                const arr = Array.isArray(val) ? val : [val];
                if (!arr.some((v) => filterSets[i].has(String(v)))) return false;
            }
            return true;
        }

        let currentSortKey = cfg.defaultSortKey || null;
        let currentSortDir = cfg.defaultSortDir || 'desc';

        function sortRows(rows) {
            let spec = null;
            if (currentSortKey && cfg.sorters) {
                const combinedKey = currentSortKey + '_' + currentSortDir;
                if (cfg.sorters[combinedKey]) {
                    spec = cfg.sorters[combinedKey];
                } else if (cfg.sorters[currentSortKey]) {
                    spec = cfg.sorters[currentSortKey];
                }
            }
            if (!spec && sortSelect && sortSelect.value) {
                spec = (cfg.sorters || {})[sortSelect.value];
            }
            if (!spec) return;

            if (typeof spec === "function") {
                rows.sort(spec);
                return;
            }
            const getVal = (tr) => {
                if (typeof spec === "string") return String(tr.dataset[spec] || tr.textContent);
                return tr.textContent;
            };
            const numeric = (a, b) => {
                const na = parseFloat(a), nb = parseFloat(b);
                const isNum = !isNaN(na) && !isNaN(nb) && String(na) === a.trim() && String(nb) === b.trim();
                const diff = isNum ? (na - nb) : a.localeCompare(b);
                return currentSortDir === 'asc' ? diff : -diff;
            };
            rows.sort((a, b) => numeric(getVal(a), getVal(b)));
        }

        function visibleData() {
            const rawQuery = searchInput ? searchInput.value.trim() : "";
            const query = caseSensitive ? rawQuery : rawQuery.toLowerCase();
            return (cfg.data || []).filter((row) => {
                if (query) {
                    const rawText = (cfg.dataText ? cfg.dataText(row) : JSON.stringify(row));
                    const text = caseSensitive ? rawText : rawText.toLowerCase();
                    if (!text.includes(query)) return false;
                }
                for (let i = 0; i < groups.length; i++) {
                    if (!filterSets[i].size) continue;
                    const vals = groups[i].dataValue ? groups[i].dataValue(row) : row[groups[i].key];
                    const arr = Array.isArray(vals) ? vals : [vals];
                    if (!arr.some((v) => filterSets[i].has(String(v)))) return false;
                }
                return true;
            });
        }

        function updateBadge() {
            if (!filterBadge) return;
            const count = filterSets.reduce((n, s) => n + s.size, 0);
            filterBadge.textContent = count;
            filterBadge.style.display = count > 0 ? "" : "none";
        }

        let renderTimer = null;
        function debouncedRender(resetPage) {
            if (renderTimer) clearTimeout(renderTimer);
            renderTimer = setTimeout(() => render(resetPage), 80);
        }

        function render(resetPage) {
            if (resetPage) currentPage = 1;

            const rawQuery = searchInput ? searchInput.value.trim() : "";
            const query = caseSensitive ? rawQuery : rawQuery.toLowerCase();
            const list = allRows();
            let rows = list.filter((tr) => {
                const text = tr._searchText !== undefined ? tr._searchText : (caseSensitive ? tr.textContent : tr.textContent.toLowerCase());
                return text.includes(query) && rowMatchesFilters(tr);
            });
            sortRows(rows);

            const total     = rows.length;
            const pageCount = Math.max(1, Math.ceil(total / pageSize));
            if (currentPage > pageCount) currentPage = pageCount;

            const start    = (currentPage - 1) * pageSize;
            const pageRows = rows.slice(start, start + pageSize);

            list.forEach((tr) => {
                if (tr.style.display !== "none") tr.style.display = "none";
            });
            pageRows.forEach((tr, i) => {
                tr.style.display = "";
                const cell = tr.querySelector(".no-col");
                if (cell) cell.textContent = start + i + 1;
            });
            if (tbody) pageRows.forEach((tr) => tbody.appendChild(tr));

            if (pagePrev) pagePrev.disabled = currentPage <= 1;
            if (pageNext) pageNext.disabled = currentPage >= pageCount;

            if (pageNumbers) {
                pageNumbers.innerHTML = "";
                pageRange(currentPage, pageCount).forEach((p) => {
                    if (p === "...") {
                        const s = document.createElement("span");
                        s.className = "pg-ellipsis";
                        s.textContent = "…";
                        pageNumbers.appendChild(s);
                    } else {
                        const b = document.createElement("button");
                        b.type = "button";
                        b.className = "pg-btn pg-num" + (p === currentPage ? " active" : "");
                        b.textContent = p;
                        b.onclick = () => { currentPage = p; render(false); };
                        pageNumbers.appendChild(b);
                    }
                });
            }

            if (rangeEl) {
                const from = total === 0 ? 0 : start + 1;
                const to   = start + pageRows.length;
                rangeEl.textContent = from + "-" + to + " of " + total;
            }
            if (pagination) pagination.style.display = "";
            updateBadge();
        }

        function buildFilters() {
            if (!filterPanel || !groups.length) return;
            filterPanel.innerHTML = "";

            const header = document.createElement("div");
            header.className = "filter-panel-header";
            header.innerHTML = `
                <span class="filter-panel-title">Filter Options</span>
                <button type="button" class="filter-close-btn" aria-label="Close filters">×</button>
            `;
            const closeBtn = header.querySelector(".filter-close-btn");
            if (closeBtn && filterWrap) {
                closeBtn.onclick = (e) => {
                    e.stopPropagation();
                    filterWrap.classList.remove("open");
                };
            }
            filterPanel.appendChild(header);

            const content = document.createElement("div");
            content.className = "filter-panel-content";

            groups.forEach((group, i) => {
                let options = [];
                if (group.options) {
                    options = group.options.map((o) => typeof o === "string" ? { value: o, label: o } : o);
                } else {
                    const distinct = new Set();
                    allRows().forEach((tr) => {
                        const vals = getFilterVal(group, tr);
                        (Array.isArray(vals) ? vals : [vals]).forEach((v) => distinct.add(String(v)));
                    });
                    options = [...distinct].filter(Boolean).sort().map((v) => ({ value: v, label: v }));
                }

                const wrap  = document.createElement("div");
                wrap.className = "filter-group";
                const title = document.createElement("div");
                title.className = "filter-group-title";
                title.textContent = group.title;
                wrap.appendChild(title);

                const opts = document.createElement("div");
                opts.className = "filter-options-chips";
                options.forEach((opt) => {
                    const chip = document.createElement("button");
                    chip.type = "button";
                    chip.className = "filter-chip" + (filterSets[i].has(opt.value) ? " active" : "");
                    chip.textContent = opt.label;
                    chip.onclick = () => {
                        if (filterSets[i].has(opt.value)) {
                            filterSets[i].delete(opt.value);
                            chip.classList.remove("active");
                        } else {
                            filterSets[i].add(opt.value);
                            chip.classList.add("active");
                        }
                        render(true);
                    };
                    opts.appendChild(chip);
                });
                wrap.appendChild(opts);
                content.appendChild(wrap);
            });
            filterPanel.appendChild(content);

            const footer = document.createElement("div");
            footer.className = "filter-panel-footer";
            
            const clear = document.createElement("button");
            clear.type = "button";
            clear.className = "btn sm secondary";
            clear.textContent = "Reset All";
            clear.onclick = () => {
                filterSets.forEach((s) => s.clear());
                filterPanel.querySelectorAll(".filter-chip").forEach((c) => c.classList.remove("active"));
                render(true);
            };

            const apply = document.createElement("button");
            apply.type = "button";
            apply.className = "btn sm primary filter-apply-btn";
            apply.textContent = "Apply Filters";
            apply.onclick = (e) => {
                e.stopPropagation();
                if (filterWrap) filterWrap.classList.remove("open");
            };

            footer.appendChild(clear);
            footer.appendChild(apply);
            filterPanel.appendChild(footer);
        }

        function doExport(format) {
            const rows = visibleData();
            if (!rows.length) { alert("Nothing to export."); return; }
            const cols = cfg.exportCols || [];
            if (format === "csv") {
                const lines = [cols.map((c) => c.label).join(",")].concat(
                    rows.map((r) => cols.map((c) => csvEscape(r[c.key])).join(","))
                );
                downloadBlob(new Blob(["\uFEFF" + lines.join("\n")], { type: "text/csv;charset=utf-8;" }), cfg.exportName + ".csv");
            } else if (format === "xlsx") {
                const doXlsx = () => {
                    const data = rows.map((r) => { const o = {}; cols.forEach((c) => o[c.label] = r[c.key]); return o; });
                    const ws = XLSX.utils.json_to_sheet(data);
                    ws["!cols"] = cols.map(() => ({ wch: 20 }));
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, "Data");
                    XLSX.writeFile(wb, cfg.exportName + ".xlsx");
                };
                if (window.XLSX) { doXlsx(); }
                else { ensureXlsx().then(doXlsx).catch(() => alert("Could not load the Excel exporter.")); }
            }
        }

        if (cfg.exportFn) window[cfg.exportFn] = doExport;

        if (searchInput) searchInput.addEventListener("input", () => debouncedRender(true));
        if (sortSelect)  sortSelect.addEventListener("change", () => render(false));
        if (filterBtn && filterWrap) {
            filterBtn.addEventListener("click", (e) => { e.stopPropagation(); filterWrap.classList.toggle("open"); });
        }
        document.addEventListener("click", (e) => {
            if (filterWrap && !filterWrap.contains(e.target)) filterWrap.classList.remove("open");
        });
        if (pagePrev) pagePrev.addEventListener("click", () => { if (currentPage > 1) { currentPage--; render(false); } });
        if (pageNext) pageNext.addEventListener("click", () => { if (currentPage < Math.max(1, Math.ceil(allRows().length / pageSize))) { currentPage++; render(false); } });
        if (pageSizeSelect) pageSizeSelect.addEventListener("change", () => {
            const v = parseInt(pageSizeSelect.value, 10);
            if (v > 0) pageSize = v;
            currentPage = 1;
            render(true);
        });

        const sortHeaders = qa(cfg.sortHeaderSelector || 'th.sortable');
        sortHeaders.forEach((th) => {
            const sortKey = th.dataset.sort;
            if (!sortKey) return;
            if (currentSortKey === sortKey) {
                th.classList.add('active', currentSortDir);
            }
            th.addEventListener('click', () => {
                if (currentSortKey === sortKey) {
                    currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSortKey = sortKey;
                    currentSortDir = th.dataset.defaultDir || 'desc';
                }
                sortHeaders.forEach((h) => h.classList.remove('active', 'asc', 'desc'));
                th.classList.add('active', currentSortDir);
                render(false);
            });
        });

        buildFilters();
        render(true);
    };

    window.initHistoryTable = function () {
        initDataTable({
            searchInput:    '#histSearchInput',
            sortSelect:     '#histSortSelect',
            tbody:          '#histBody',
            row:            '.hist-row',
            filterWrap:     '#histFilterWrap',
            filterBtn:      '#histFilterBtn',
            filterPanel:    '#histFilterPanel',
            filterBadge:    '#histFilterBadge',
            pagePrev:       '#histPagePrev',
            pageNext:       '#histPageNext',
            pageNumbers:    '#histPageNumbers',
            pageRange:      '#histPageRange',
            pagination:     '#histPagination',
            pageSizeSelect: '#histPageSizeSelect',
            exportFn:       'histExport',
            exportName:     'movement-history',
            exportCols: [
                { key: 'type',        label: 'Type' },
                { key: 'item_name',   label: 'Item' },
                { key: 'quantity',    label: 'Quantity' },
                { key: 'issued_to',   label: 'Issued To' },
                { key: 'note',        label: 'Details / Note' },
                { key: 'modified_by', label: 'Modified By' },
                { key: 'created_at',  label: 'Date' },
            ],
            filterGroups: [
                {
                    title: 'Type',
                    key: 'type',
                    dataValue: (r) => r.type,
                    options: [
                        { value: 'in',       label: 'INBOUND' },
                        { value: 'out',      label: 'OUTBOUND' },
                        { value: 'transfer', label: 'TRANSFER' },
                    ],
                },
                {
                    title: 'Date',
                    key: 'created',
                    value: (tr) => createdBuckets(tr.dataset.created),
                    dataValue: (r) => createdBuckets(r.created_ts),
                    options: [
                        { value: 'today', label: 'Today' },
                        { value: 'week',  label: 'Past Week' },
                        { value: 'month', label: 'Past Month' },
                        { value: 'all',   label: 'All' },
                    ],
                },
            ],
            sorters: {
                'newest': function (a, b) { return (parseInt(b.dataset.created, 10) || 0) - (parseInt(a.dataset.created, 10) || 0); },
                'oldest': function (a, b) { return (parseInt(a.dataset.created, 10) || 0) - (parseInt(b.dataset.created, 10) || 0); },
            },
            data:     window.HIST_DATA,
            dataText: function (r) { return r.item_name + ' ' + r.type + ' ' + (r.note || '') + ' ' + (r.issued_to || '') + ' ' + (r.modified_by || ''); },
        });
    };
})();
