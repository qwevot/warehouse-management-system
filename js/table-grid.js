// ============================================================
// TABLE GRID MODULE: Search, Sort, Filters, Pagination & Excel Export
// ============================================================
(function () {
    "use strict";

    const $ = window.$;
    const $all = window.$all;
    const BASE = window.BASE;

    function ensureXlsx() {
        if (window.XLSX) return Promise.resolve();
        if (window.showLoader) window.showLoader();
        return window.loadScript(BASE + "/js/xlsx.full.min.js")
            .finally(() => { if (window.hideLoader) window.hideLoader(); });
    }
    window.ensureXlsx = ensureXlsx;

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

                    window.post(BASE + "/actions/import-items.php", { rows: JSON.stringify(mapped) })
                        .then((res) => res.json())
                        .then((d) => {
                            if (!d.ok) throw new Error(d.message || "Import failed.");
                            window.queueFlash(d.message || "Items imported.");
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

    // Generic Data Table Controller
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
            if (cfg.customFilterMatch && !cfg.customFilterMatch(tr)) return false;
            return true;
        }

        let currentSortKey = cfg.defaultSortKey || null;
        let currentSortDir = cfg.defaultSortDir || 'desc';

        function sortRows(rows) {
            let spec = null;
            if (currentSortKey && cfg.sorters) {
                const combinedKey = currentSortKey + '_' + currentSortDir;
                const dashKey     = currentSortKey + '-' + currentSortDir;
                if (cfg.sorters[combinedKey]) {
                    spec = cfg.sorters[combinedKey];
                } else if (cfg.sorters[dashKey]) {
                    spec = cfg.sorters[dashKey];
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
                if (cfg.customDataMatch && !cfg.customDataMatch(row)) return false;
                return true;
            });
        }

        function updateBadge() {
            if (!filterBadge) return;
            let count = filterSets.reduce((n, s) => n + s.size, 0);
            if (cfg.customBadgeCount) count += cfg.customBadgeCount();
            filterBadge.textContent = count;
            filterBadge.style.display = count > 0 ? "" : "none";
        }

        let renderTimer = null;
        function debouncedRender(resetPage) {
            if (renderTimer) clearTimeout(renderTimer);
            renderTimer = setTimeout(() => render(resetPage), 80);
        }

        let totalCount = (typeof window.INVENTORY_TOTAL === 'number') ? window.INVENTORY_TOTAL : 0;
        let isInitialServerRender = true;

        function updatePaginationControls(page, pageCount, total, pageRowsCount) {
            if (pagePrev) pagePrev.disabled = page <= 1;
            if (pageNext) pageNext.disabled = page >= pageCount;

            if (pageNumbers) {
                pageNumbers.innerHTML = "";
                window.pageRange(page, pageCount).forEach((p) => {
                    if (p === "...") {
                        const s = document.createElement("span");
                        s.className = "pg-ellipsis";
                        s.textContent = "…";
                        pageNumbers.appendChild(s);
                    } else {
                        const b = document.createElement("button");
                        b.type = "button";
                        b.className = "pg-btn pg-num" + (p === page ? " active" : "");
                        b.textContent = p;
                        b.onclick = () => { currentPage = p; render(false); };
                        pageNumbers.appendChild(b);
                    }
                });
            }

            if (rangeEl) {
                const start = (page - 1) * pageSize;
                const from  = total === 0 ? 0 : start + 1;
                const to    = start + pageRowsCount;
                rangeEl.textContent = from + "-" + to + " of " + total;
            }
            if (pagination) pagination.style.display = "";
        }

        function fetchServerPage(resetPage) {
            if (resetPage) currentPage = 1;

            if (isInitialServerRender) {
                isInitialServerRender = false;
                totalCount = (typeof window.INVENTORY_TOTAL === 'number') ? window.INVENTORY_TOTAL : allRows().length;
                const pageCount = Math.max(1, Math.ceil(totalCount / pageSize));
                updatePaginationControls(currentPage, pageCount, totalCount, allRows().length);
                updateBadge();
                return;
            }

            const rawQuery = searchInput ? searchInput.value.trim() : "";
            const sortVal  = (sortSelect && sortSelect.value) ? sortSelect.value : (currentSortKey ? (currentSortKey + (currentSortDir === 'asc' ? '-asc' : '-desc')) : 'newest');
            const params = {
                mode: window.INVENTORY_MODE || 'list',
                page: currentPage,
                size: pageSize,
                q: rawQuery,
                sort: sortVal
            };
            if (cfg.getFilterParams) {
                Object.assign(params, cfg.getFilterParams());
            }

            if (tbody) tbody.style.opacity = '0.5';
            window.post(cfg.ajaxUrl, params)
                .then((r) => r.json())
                .then((res) => {
                    if (tbody) {
                        tbody.style.opacity = '1';
                        tbody.innerHTML = res.rows_html || '';
                    }
                    cachedRows = null;
                    window.INVENTORY = res.rows || [];
                    window.INVENTORY_TOTAL = res.total || 0;
                    totalCount = res.total || 0;
                    currentPage = res.page || 1;
                    pageSize = res.size || 10;
                    const pageCount = Math.max(1, Math.ceil(totalCount / pageSize));
                    updatePaginationControls(currentPage, pageCount, totalCount, (res.rows || []).length);
                    updateBadge();
                })
                .catch((err) => {
                    if (tbody) tbody.style.opacity = '1';
                    console.error('Server-side pagination error:', err);
                });
        }

        function render(resetPage) {
            if (resetPage) currentPage = 1;

            if (cfg.ajaxUrl) {
                fetchServerPage(resetPage);
                return;
            }

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

            updatePaginationControls(currentPage, pageCount, total, pageRows.length);
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
            let cols = cfg.exportCols;
            if (typeof cols === 'function') {
                cols = cols();
            }
            if (!cols) {
                cols = (window.INVENTORY_MODE === 'units' || window.INVENTORY_MODE === 'bin') ? [
                    { key: 'name', label: 'Item Name' },
                    { key: 'brand', label: 'Brand' },
                    { key: 'location', label: 'Location' },
                    { key: 'status', label: 'Status' },
                    { key: 'serial', label: 'Serial' },
                    { key: 'barcode', label: 'Barcode' },
                    { key: 'created_at', label: 'Date Registered' },
                ] : [
                    { key: 'name', label: 'Item Name' },
                    { key: 'brand', label: 'Brand' },
                    { key: 'quantity', label: 'Quantity' },
                    { key: 'barcode', label: 'Barcode' },
                    { key: 'location', label: 'Location' },
                    { key: 'status', label: 'Status' },
                    { key: 'created_at', label: 'Date Registered' },
                ];
            }
            const exportFileName = (typeof cfg.exportName === 'function' ? cfg.exportName() : cfg.exportName) || (window.INVENTORY_MODE === 'units' ? 'units' : 'inventory');
            const performExport = (rows) => {
                if (!rows.length) { alert("Nothing to export."); return; }
                if (format === "csv") {
                    const lines = [cols.map((c) => c.label).join(",")].concat(
                        rows.map((r) => cols.map((c) => window.csvEscape(r[c.key])).join(","))
                    );
                    window.downloadBlob(new Blob(["\uFEFF" + lines.join("\n")], { type: "text/csv;charset=utf-8;" }), exportFileName + ".csv");
                } else if (format === "xlsx") {
                    const doXlsx = () => {
                        const data = rows.map((r) => { const o = {}; cols.forEach((c) => o[c.label] = r[c.key]); return o; });
                        const ws = XLSX.utils.json_to_sheet(data);
                        ws["!cols"] = cols.map(() => ({ wch: 20 }));
                        const wb = XLSX.utils.book_new();
                        XLSX.utils.book_append_sheet(wb, ws, "Data");
                        XLSX.writeFile(wb, exportFileName + ".xlsx");
                    };
                    if (window.XLSX) { doXlsx(); }
                    else { ensureXlsx().then(doXlsx).catch(() => alert("Could not load the Excel exporter.")); }
                }
            };

            if (cfg.ajaxUrl) {
                const rawQuery = searchInput ? searchInput.value.trim() : "";
                const sortVal  = (sortSelect && sortSelect.value) ? sortSelect.value : (currentSortKey ? (currentSortKey + (currentSortDir === 'asc' ? '-asc' : '-desc')) : 'newest');
                const params = {
                    mode: window.INVENTORY_MODE || 'list',
                    format: 'all',
                    q: rawQuery,
                    sort: sortVal
                };
                if (cfg.getFilterParams) {
                    Object.assign(params, cfg.getFilterParams());
                }
                window.post(cfg.ajaxUrl, params)
                    .then((r) => r.json())
                    .then((res) => {
                        performExport(res.rows || []);
                    })
                    .catch((err) => {
                        console.error("Export error:", err);
                        performExport(visibleData());
                    });
                return;
            }

            performExport(visibleData());
        }

        if (cfg.exportFn) window[cfg.exportFn] = doExport;

        if (searchInput) searchInput.addEventListener("input", () => debouncedRender(true));
        if (sortSelect) sortSelect.addEventListener("change", () => {
            const val = sortSelect.value || "";
            currentSortKey = null;
            sortHeaders.forEach((h) => h.classList.remove('active', 'asc', 'desc'));
            const matchedHeader = sortHeaders.find((h) => val.startsWith(h.dataset.sort));
            if (matchedHeader) {
                currentSortKey = matchedHeader.dataset.sort;
                currentSortDir = (val.endsWith('asc') || val.endsWith('_asc')) ? 'asc' : 'desc';
                matchedHeader.classList.add('active', currentSortDir);
            }
            render(false);
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
                if (sortSelect) {
                    const candidate1 = sortKey + '_' + currentSortDir;
                    const candidate2 = sortKey + '-' + currentSortDir;
                    const match = Array.from(sortSelect.options).find((opt) => opt.value === candidate1 || opt.value === candidate2);
                    if (match) sortSelect.value = match.value;
                }
                render(false);
            });
        });

        if (pageSizeSelect) {
            pageSizeSelect.addEventListener("change", () => {
                const v = parseInt(pageSizeSelect.value, 10);
                if (v > 0) pageSize = v;
                render(true);
            });
        }

        if (pagePrev) {
            pagePrev.addEventListener("click", () => {
                if (currentPage > 1) {
                    currentPage--;
                    render(false);
                }
            });
        }

        if (pageNext) {
            pageNext.addEventListener("click", () => {
                let pageCount;
                if (cfg.ajaxUrl) {
                    pageCount = Math.max(1, Math.ceil(totalCount / pageSize));
                } else {
                    const list = allRows();
                    const rawQuery = searchInput ? searchInput.value.trim() : "";
                    const query = caseSensitive ? rawQuery : rawQuery.toLowerCase();
                    const total = list.filter((tr) => {
                        const text = tr._searchText !== undefined ? tr._searchText : (caseSensitive ? tr.textContent : tr.textContent.toLowerCase());
                        return text.includes(query) && rowMatchesFilters(tr);
                    }).length;
                    pageCount = Math.max(1, Math.ceil(total / pageSize));
                }
                if (currentPage < pageCount) {
                    currentPage++;
                    render(false);
                }
            });
        }

        if (filterBtn && filterWrap) {
            filterBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                filterWrap.classList.toggle("open");
            });
            document.addEventListener("click", (e) => {
                if (filterWrap.classList.contains("open") && !filterWrap.contains(e.target)) {
                    filterWrap.classList.remove("open");
                }
            });
        }

        buildFilters();
        if (cfg.onInit) {
            cfg.onInit({
                render: render,
                debouncedRender: debouncedRender,
                allRows: allRows,
                updateBadge: updateBadge
            });
        }
        render(true);
    };

    window.initHistoryTable = function () {
        window.initDataTable({
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
                        { value: 'check_in', label: 'CHECK IN' },
                        { value: 'return',   label: 'RETURN' },
                        { value: 'transfer', label: 'TRANSFER' },
                    ],
                },
                {
                    title: 'Date',
                    key: 'created',
                    value: (tr) => window.createdBuckets(tr.dataset.created),
                    dataValue: (r) => window.createdBuckets(r.created_ts),
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

    window.initInventoryTable = function () {
        if (!document.getElementById("inventoryTable")) return;

        const selectedBrands = new Set();
        let brandSearchText = "";
        let selectedLocation = "";
        let selectedDate = "all";


        function getRowLocCategory(tr) {
            if (tr.dataset && tr.dataset.locCategory) return tr.dataset.locCategory;
            const dep = (tr.dataset && tr.dataset.deploymentSite ? tr.dataset.deploymentSite : "").trim();
            const loc = (tr.dataset && tr.dataset.loc ? tr.dataset.loc : "").trim();
            const st  = (tr.dataset && tr.dataset.status ? tr.dataset.status : "").toLowerCase().trim();
            if (dep.startsWith("In Transit") || loc.startsWith("In Transit") || st === "in_transit") {
                return "transit";
            }
            if (dep === "Main Office" || loc === "Main Office") {
                return "office";
            }
            if (dep === "In Warehouse" || loc.startsWith("S") || loc === "In Warehouse") {
                return "warehouse";
            }
            return "customer";
        }

        function inventoryRowMatch(tr) {
            // Brand filter check
            if (selectedBrands.size > 0) {
                const b = (tr.dataset.brand || "").trim();
                if (!selectedBrands.has(b)) return false;
            } else if (brandSearchText) {
                const b = (tr.dataset.brand || "").toLowerCase().trim();
                if (!b.includes(brandSearchText)) return false;
            }

            // Location filter check
            if (selectedLocation) {
                const cat = getRowLocCategory(tr);
                if (cat !== selectedLocation) return false;
            }

            // Date filter check
            if (selectedDate && selectedDate !== "all") {
                const createdTs = parseInt(tr.dataset.created, 10) || 0;
                if (typeof window.createdBuckets === "function") {
                    const buckets = window.createdBuckets(createdTs);
                    if (!buckets.includes(selectedDate)) return false;
                }
            }

            return true;
        }

        function inventoryDataMatch(row) {
            if (selectedBrands.size > 0) {
                const b = (row.brand || "").trim();
                if (!selectedBrands.has(b)) return false;
            } else if (brandSearchText) {
                const b = (row.brand || "").toLowerCase().trim();
                if (!b.includes(brandSearchText)) return false;
            }

            if (selectedLocation) {
                const cat = row.loc_category || (function () {
                    const dep = (row.deployment_site || "").trim();
                    const loc = (row.location || "").trim();
                    const st  = (row.status || "").toLowerCase().trim();
                    if (dep.startsWith("In Transit") || loc.startsWith("In Transit") || st === "in_transit") return "transit";
                    if (dep === "Main Office" || loc === "Main Office") return "office";
                    if (dep === "In Warehouse" || loc.startsWith("S") || loc === "In Warehouse") return "warehouse";
                    return "customer";
                })();
                if (cat !== selectedLocation) return false;
            }

            if (selectedDate && selectedDate !== "all") {
                const createdTs = parseInt(row.created_ts, 10) || (row.created_at ? Math.floor(new Date(row.created_at).getTime() / 1000) : 0);
                if (typeof window.createdBuckets === "function") {
                    const buckets = window.createdBuckets(createdTs);
                    if (!buckets.includes(selectedDate)) return false;
                }
            }

            return true;
        }

        function inventoryBadgeCount() {
            let count = selectedBrands.size + (brandSearchText ? 1 : 0);
            if (selectedLocation) count += 1;
            if (selectedDate && selectedDate !== "all") count += 1;
            return count;
        }

        function setupInventoryFilterControls(tableCtrl) {
            const brandInput     = document.getElementById("unitBrandFilterInput");
            const suggestBox     = document.getElementById("unitBrandSuggestBox");
            const chipsContainer = document.getElementById("unitBrandChips");
            const locationSelect = document.getElementById("unitLocationFilterSelect");
            const resetBtn       = document.getElementById("unitFilterResetBtn");
            const applyBtn       = document.getElementById("unitFilterApplyBtn");
            const closeBtn       = document.getElementById("filterCloseBtn") || document.querySelector("#filterPanel .filter-close-btn");
            const filterWrap     = document.getElementById("filterWrap");
            const dateChipsWrap  = document.getElementById("invDateChips");

            const facetBrands = (window.INVENTORY_FACETS && Array.isArray(window.INVENTORY_FACETS.brand)) ? window.INVENTORY_FACETS.brand : [];
            const rowBrands   = tableCtrl.allRows().map((r) => (r.dataset.brand || "").trim()).filter(Boolean);
            const allBrands   = Array.from(new Set(facetBrands.concat(rowBrands))).filter(Boolean).sort((a, b) => a.localeCompare(b));

            function closeSuggestions() {
                if (suggestBox) {
                    suggestBox.innerHTML = "";
                    suggestBox.style.display = "none";
                }
            }

            function renderChips() {
                if (!chipsContainer) return;
                chipsContainer.innerHTML = "";
                selectedBrands.forEach((b) => {
                    const chip = document.createElement("span");
                    chip.className = "filter-chip active";
                    chip.style.display = "inline-flex";
                    chip.style.alignItems = "center";
                    chip.style.gap = "6px";
                    chip.style.cursor = "default";
                    
                    const labelSpan = document.createElement("span");
                    labelSpan.textContent = b;
                    chip.appendChild(labelSpan);

                    const removeBtn = document.createElement("span");
                    removeBtn.innerHTML = "&times;";
                    removeBtn.style.cursor = "pointer";
                    removeBtn.style.fontWeight = "bold";
                    removeBtn.style.fontSize = "14px";
                    removeBtn.style.lineHeight = "1";
                    removeBtn.title = "Remove brand";
                    removeBtn.onclick = (e) => {
                        e.stopPropagation();
                        selectedBrands.delete(b);
                        renderChips();
                        tableCtrl.render(true);
                    };
                    chip.appendChild(removeBtn);

                    chipsContainer.appendChild(chip);
                });
            }

            function renderSuggestions() {
                if (!suggestBox || !brandInput) return;
                const q = brandInput.value.trim().toLowerCase();
                const matches = allBrands.filter((b) => {
                    if (selectedBrands.has(b)) return false;
                    return !q || b.toLowerCase().includes(q);
                });

                if (!matches.length) {
                    if (q) {
                        suggestBox.innerHTML = '<div class="suggest-item" style="color:var(--muted); font-size:12px; cursor:default; padding:8px 12px;">No matching brand</div>';
                        suggestBox.style.display = "block";
                    } else {
                        closeSuggestions();
                    }
                    return;
                }

                suggestBox.innerHTML = "";
                matches.slice(0, 20).forEach((brandName) => {
                    const item = document.createElement("div");
                    item.className = "suggest-item";
                    item.style.padding = "8px 12px";
                    item.style.cursor = "pointer";

                    const lbl = document.createElement("div");
                    lbl.className = "suggest-label";
                    lbl.textContent = brandName;
                    item.appendChild(lbl);

                    item.onmousedown = (e) => {
                        e.preventDefault();
                        selectedBrands.add(brandName);
                        brandInput.value = "";
                        brandSearchText = "";
                        closeSuggestions();
                        renderChips();
                        tableCtrl.render(true);
                    };

                    suggestBox.appendChild(item);
                });
                suggestBox.style.display = "block";
            }

            if (brandInput) {
                brandInput.addEventListener("focus", renderSuggestions);
                brandInput.addEventListener("input", () => {
                    brandSearchText = brandInput.value.trim().toLowerCase();
                    renderSuggestions();
                    tableCtrl.render(true);
                });
                brandInput.addEventListener("keydown", (e) => {
                    if (e.key === "Enter") {
                        e.preventDefault();
                        const q = brandInput.value.trim();
                        if (q) {
                            const exact = allBrands.find((b) => b.toLowerCase() === q.toLowerCase());
                            selectedBrands.add(exact || q);
                            brandInput.value = "";
                            brandSearchText = "";
                            closeSuggestions();
                            renderChips();
                            tableCtrl.render(true);
                        }
                    } else if (e.key === "Escape") {
                        closeSuggestions();
                    }
                });
                brandInput.addEventListener("blur", () => {
                    setTimeout(closeSuggestions, 200);
                });
            }

            if (locationSelect) {
                locationSelect.addEventListener("change", () => {
                    selectedLocation = locationSelect.value;
                    tableCtrl.render(true);
                });
            }

            if (dateChipsWrap) {
                dateChipsWrap.querySelectorAll(".filter-chip").forEach((btn) => {
                    btn.addEventListener("click", () => {
                        const dateVal = btn.dataset.date || "all";
                        if (btn.classList.contains("active")) {
                            btn.classList.remove("active");
                            selectedDate = "all";
                        } else {
                            dateChipsWrap.querySelectorAll(".filter-chip").forEach((c) => c.classList.remove("active"));
                            btn.classList.add("active");
                            selectedDate = dateVal;
                        }
                        tableCtrl.render(true);
                    });
                });
            }

            if (resetBtn) {
                resetBtn.addEventListener("click", () => {
                    selectedBrands.clear();
                    brandSearchText = "";
                    if (brandInput) brandInput.value = "";
                    closeSuggestions();
                    renderChips();
                    if (locationSelect) locationSelect.value = "";
                    selectedLocation = "";
                    selectedDate = "all";
                    if (dateChipsWrap) {
                        dateChipsWrap.querySelectorAll(".filter-chip").forEach((btn) => {
                            btn.classList.remove("active");
                        });
                    }
                    tableCtrl.render(true);
                });
            }

            if (applyBtn) {
                applyBtn.addEventListener("click", (e) => {
                    e.stopPropagation();
                    closeSuggestions();
                    if (filterWrap) filterWrap.classList.remove("open");
                });
            }

            if (closeBtn) {
                closeBtn.addEventListener("click", (e) => {
                    e.stopPropagation();
                    closeSuggestions();
                    if (filterWrap) filterWrap.classList.remove("open");
                });
            }

            document.addEventListener("click", (e) => {
                if (suggestBox && !suggestBox.contains(e.target) && brandInput && !brandInput.contains(e.target)) {
                    closeSuggestions();
                }
            });
        }

        window.initDataTable({
            ajaxUrl:           BASE + '/actions/inventory-page.php',
            getFilterParams:   function () {
                const brands = Array.from(selectedBrands);
                return {
                    brand: brands.length > 0 ? brands : (brandSearchText ? [brandSearchText] : []),
                    location: selectedLocation || '',
                    date: selectedDate || 'all'
                };
            },
            searchInput:       '#searchInput',
            sortSelect:        '#sortSelect',
            tbody:             '#inventoryTable',
            row:               '.inventory-row',
            filterWrap:        '#filterWrap',
            filterBtn:         '#filterBtn',
            filterPanel:       '#filterPanel',
            filterBadge:       '#filterBadge',
            pagePrev:          '#pagePrev',
            pageNext:          '#pageNext',
            pageNumbers:       '#pageNumbers',
            pageRange:         '#pageRange',
            pagination:        '#pagination',
            pageSizeSelect:    '#pageSizeSelect',
            exportFn:          'exportTable',
            exportName:        function () {
                return (window.INVENTORY_MODE === 'units') ? 'units' : ((window.INVENTORY_MODE === 'bin') ? 'bin' : 'inventory');
            },
            exportCols:        function () {
                return (window.INVENTORY_MODE === 'units' || window.INVENTORY_MODE === 'bin') ? [
                    { key: 'name', label: 'Item Name' },
                    { key: 'brand', label: 'Brand' },
                    { key: 'location', label: 'Location' },
                    { key: 'status', label: 'Status' },
                    { key: 'serial', label: 'Serial' },
                    { key: 'barcode', label: 'Barcode' },
                    { key: 'created_at', label: 'Date Registered' },
                ] : [
                    { key: 'name', label: 'Item Name' },
                    { key: 'brand', label: 'Brand' },
                    { key: 'quantity', label: 'Quantity' },
                    { key: 'barcode', label: 'Barcode' },
                    { key: 'location', label: 'Location' },
                    { key: 'status', label: 'Status' },
                    { key: 'created_at', label: 'Date Registered' },
                ];
            },
            defaultSortKey:    'newest',
            defaultSortDir:    'desc',
            customFilterMatch: inventoryRowMatch,
            customDataMatch:   inventoryDataMatch,
            customBadgeCount:  inventoryBadgeCount,
            onInit:            setupInventoryFilterControls,
            sorters: {
                'newest': function (a, b) { return (parseInt(b.dataset.id, 10) || 0) - (parseInt(a.dataset.id, 10) || 0); },
                'oldest': function (a, b) { return (parseInt(a.dataset.id, 10) || 0) - (parseInt(b.dataset.id, 10) || 0); },
                'name': function (a, b) {
                    const elA = a.querySelector(".item-name");
                    const elB = b.querySelector(".item-name");
                    return (elA ? elA.textContent.trim() : "").localeCompare(elB ? elB.textContent.trim() : "");
                },
                'nameDesc': function (a, b) {
                    const elA = a.querySelector(".item-name");
                    const elB = b.querySelector(".item-name");
                    return (elB ? elB.textContent.trim() : "").localeCompare(elA ? elA.textContent.trim() : "");
                },
                'created_desc':  function (a, b) { return ((parseInt(b.dataset.created, 10) || 0) - (parseInt(a.dataset.created, 10) || 0)) || ((parseInt(b.dataset.id, 10) || 0) - (parseInt(a.dataset.id, 10) || 0)); },
                'created_asc':   function (a, b) { return ((parseInt(a.dataset.created, 10) || 0) - (parseInt(b.dataset.created, 10) || 0)) || ((parseInt(a.dataset.id, 10) || 0) - (parseInt(b.dataset.id, 10) || 0)); },
                'created-desc':  function (a, b) { return ((parseInt(b.dataset.created, 10) || 0) - (parseInt(a.dataset.created, 10) || 0)) || ((parseInt(b.dataset.id, 10) || 0) - (parseInt(a.dataset.id, 10) || 0)); },
                'created-asc':   function (a, b) { return ((parseInt(a.dataset.created, 10) || 0) - (parseInt(b.dataset.created, 10) || 0)) || ((parseInt(a.dataset.id, 10) || 0) - (parseInt(b.dataset.id, 10) || 0)); },
                'modified_desc': function (a, b) {
                    const tA = parseInt(a.dataset.modified, 10) || parseInt(a.dataset.created, 10) || 0;
                    const tB = parseInt(b.dataset.modified, 10) || parseInt(b.dataset.created, 10) || 0;
                    return (tB - tA) || ((parseInt(b.dataset.id, 10) || 0) - (parseInt(a.dataset.id, 10) || 0));
                },
                'modified_asc':  function (a, b) {
                    const tA = parseInt(a.dataset.modified, 10) || parseInt(a.dataset.created, 10) || 0;
                    const tB = parseInt(b.dataset.modified, 10) || parseInt(b.dataset.created, 10) || 0;
                    return (tA - tB) || ((parseInt(b.dataset.id, 10) || 0) - (parseInt(a.dataset.id, 10) || 0));
                },
                'modified-desc': function (a, b) {
                    const tA = parseInt(a.dataset.modified, 10) || parseInt(a.dataset.created, 10) || 0;
                    const tB = parseInt(b.dataset.modified, 10) || parseInt(b.dataset.created, 10) || 0;
                    return (tB - tA) || ((parseInt(b.dataset.id, 10) || 0) - (parseInt(a.dataset.id, 10) || 0));
                },
                'modified-asc':  function (a, b) {
                    const tA = parseInt(a.dataset.modified, 10) || parseInt(a.dataset.created, 10) || 0;
                    const tB = parseInt(b.dataset.modified, 10) || parseInt(b.dataset.created, 10) || 0;
                    return (tA - tB) || ((parseInt(b.dataset.id, 10) || 0) - (parseInt(a.dataset.id, 10) || 0));
                },
            },
            data: window.INVENTORY,
            dataText: function (r) {
                return (r.name || '') + ' ' + (r.brand || '') + ' ' + (r.serial || '') + ' ' + (r.barcode || '') + ' ' + (r.location || '');
            },
        });
    };

    if (document.getElementById("inventoryTable")) {
        window.initInventoryTable();
    }
})();
