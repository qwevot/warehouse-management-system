// ============================================================
// CORE SYSTEM MODULE: DOM Helpers, UI State, Modals & Actions
// ============================================================
(function () {
    "use strict";

    const $ = (sel) => document.querySelector(sel);
    const $all = (sel) => Array.from(document.querySelectorAll(sel));

    window.$ = $;
    window.$all = $all;

    const BASE = "/Inventory";
    window.BASE = BASE;

    // ---- Page Loader (Buffering Overlay) ----
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

    // ---- CSRF Token Helper & Fetch Interceptor ----
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute("content") : "";
    }
    window.getCsrfToken = getCsrfToken;

    const nativeFetch = window.fetch;
    window.fetch = function (resource, init) {
        init = init || {};
        const method = (init.method || "GET").toUpperCase();
        if (method !== "GET" && method !== "HEAD" && method !== "OPTIONS") {
            const token = getCsrfToken();
            if (token) {
                init.headers = init.headers || {};
                if (init.headers instanceof Headers) {
                    if (!init.headers.has("X-CSRF-Token")) {
                        init.headers.set("X-CSRF-Token", token);
                    }
                } else if (Array.isArray(init.headers)) {
                    init.headers.push(["X-CSRF-Token", token]);
                } else {
                    init.headers["X-CSRF-Token"] = token;
                }
            }
        }
        return nativeFetch.call(this, resource, init);
    };

    // ---- AJAX Post Helper ----
    function post(url, data) {
        const token = getCsrfToken();
        const params = new URLSearchParams(data);
        if (!params.has("csrf_token") && token) {
            params.append("csrf_token", token);
        }
        return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "X-CSRF-Token": token
            },
            body: params
        });
    }
    window.post = post;

    // ---- Flash Notification System ----
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

    function consumeFlash() {
        const raw = sessionStorage.getItem("pendingFlash");
        if (!raw) return;
        sessionStorage.removeItem("pendingFlash");
        try {
            const d = JSON.parse(raw);
            showFlash(d.message, d.type);
        } catch (e) { /* ignore malformed flash */ }
    }

    window.showFlash = showFlash;
    window.queueFlash = queueFlash;
    window.consumeFlash = consumeFlash;

    // ---- Generic Modal Controllers ----
    function openModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.add("open");
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove("open");
    }

    window.openModal = openModal;
    window.closeModal = closeModal;

    $all(".modal-close").forEach((btn) => {
        btn.addEventListener("click", () => {
            const backdrop = btn.closest(".modal-backdrop");
            if (backdrop) backdrop.classList.remove("open");
        });
    });

    // Reusable Prompt / Confirmation Modal
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

    // ---- Standard Action Runners ----
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
    window.runAction = runAction;

    window.submitPostForm = function (action, data) {
        const f = document.createElement("form");
        f.method = "POST";
        f.action = action;
        const payload = Object.assign({}, data, { csrf_token: getCsrfToken() });
        Object.keys(payload).forEach((key) => {
            const inp = document.createElement("input");
            inp.type = "hidden";
            inp.name = key;
            inp.value = payload[key];
            f.appendChild(inp);
        });
        document.body.appendChild(f);
        f.submit();
    };

    window.runDeleteItem = async function (id, type) {
        const entityType = type || (window.INVENTORY_MODE === 'units' || window.INVENTORY_MODE === 'bin' ? 'unit' : 'lot');
        const isLot = entityType === 'lot';
        const note = await promptModal({
            title: isLot ? "Move lot to the bin?" : "Move item to the bin?",
            message: isLot ? "This lot and every unit in this lot will be moved to the bin." : "This item will be moved to the bin.",
            placeholder: "Optional bin note",
            confirmLabel: "Move to Bin",
        });
        if (note === null) return;
        runAction(BASE + "/actions/delete-item.php", { id: id, note: note, type: entityType }, null, "Delete failed.", isLot ? "Lot and all its units moved to the bin." : "Item moved to the bin.");
    };

    window.runRestoreItem = function (id, type) {
        const entityType = type || (window.INVENTORY_MODE === 'units' || window.INVENTORY_MODE === 'bin' ? 'unit' : 'lot');
        if (!confirm("Restore this item to the inventory?")) return;
        runAction(BASE + "/actions/restore-item.php", { id: id, type: entityType }, null, "Restore failed.", "Item restored.");
    };

    window.runPermanentDelete = function (id, type) {
        const entityType = type || (window.INVENTORY_MODE === 'units' || window.INVENTORY_MODE === 'bin' ? 'unit' : 'lot');
        if (!confirm("Permanently delete this item? This cannot be undone.")) return;
        runAction(BASE + "/actions/delete-permanent.php", { id: id, type: entityType }, null, "Delete failed.", "Item permanently deleted.");
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

    // ---- Sidebar Navigation ----
    window.toggleSidebar = function () {
        if (window.innerWidth <= 768) {
            document.body.classList.toggle("sidebar-open");
        } else {
            document.body.classList.toggle("sidebar-collapsed");
        }
    };

    document.addEventListener("click", (e) => {
        if (window.innerWidth <= 768 && document.body.classList.contains("sidebar-open")) {
            const sidebar = document.querySelector(".sidebar");
            const toggleBtn = document.querySelector(".sidebar-toggle");
            if (sidebar && !sidebar.contains(e.target) && toggleBtn && !toggleBtn.contains(e.target)) {
                document.body.classList.remove("sidebar-open");
            }
        }
    });

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

    // Restore sidebar state
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

    // Helper Utilities
    window.loadScript = function (src) {
        return new Promise((resolve, reject) => {
            const s = document.createElement("script");
            s.src = src;
            s.onload = resolve;
            s.onerror = () => reject(new Error("Failed to load " + src));
            document.head.appendChild(s);
        });
    };

    window.downloadBlob = function (blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    window.csvEscape = function (v) {
        const s = String(v ?? "");
        return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    };

    window.createdBuckets = function (ts) {
        const t = parseInt(ts, 10);
        if (!t) return ['all'];
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime() / 1000;
        const out = ['all'];
        if (t >= start) out.push('today');
        if (t >= start - 6 * 86400) out.push('week');
        if (t >= start - 29 * 86400) out.push('month');
        return out;
    };

    window.pageRange = function (current, total) {
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
    };

    // ---- Live Sidebar Server Clock Ticker ----
    function initSidebarClock() {
        const clockEl = document.getElementById("sidebarServerClock");
        if (!clockEl) return;
        const initialTs = parseInt(clockEl.dataset.serverTs, 10);
        if (!initialTs) return;

        let currentMs = initialTs * 1000;
        const pad = (n) => String(n).padStart(2, "0");

        function tick() {
            currentMs += 1000;
            const d = new Date(currentMs);
            clockEl.textContent = pad(d.getHours()) + ":" + pad(d.getMinutes()) + ":" + pad(d.getSeconds());
        }

        setInterval(tick, 1000);
    }
    initSidebarClock();

    // Auto-consume pending flash on load
    consumeFlash();
})();
