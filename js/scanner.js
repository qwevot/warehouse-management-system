// ============================================================
// SCANNER MODULE: Camera lifecycle and Html5Qrcode integration
// ============================================================
(function () {
    "use strict";

    function ensureHtml5Qrcode() {
        if (window.Html5Qrcode) return Promise.resolve();
        if (window.showLoader) window.showLoader();
        return window.loadScript(window.BASE + "/js/html5-qrcode.min.js")
            .finally(() => { if (window.hideLoader) window.hideLoader(); });
    }
    window.ensureHtml5Qrcode = ensureHtml5Qrcode;

    window.setScanToggle = function (starting) {
        const btn = document.getElementById("scanToggleBtn");
        if (!btn) return;
        btn.textContent = starting ? "Start Camera" : "Stop Camera";
        btn.className = starting ? "btn primary" : "btn danger";
    };
})();
