(function () {
    'use strict';
    // Release installed-app startup feedback when DOM is usable, independent
    // of service-worker registration, image loads or third-party scripts.
    function ready() { document.documentElement.classList.add('dtc-pwa-ready'); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready, { once: true });
    else ready();
    window.addEventListener('pageshow', ready);
    window.setTimeout(ready, 8000);
    const script = document.currentScript;
    if (!script || !script.dataset.worker || !window.isSecureContext || !('serviceWorker' in navigator)) return;
    const worker = new URL(script.dataset.worker, location.href);
    if (worker.origin !== location.origin) return;
    window.addEventListener('load', function () {
        navigator.serviceWorker.register(worker.href, { scope: new URL('./', worker).pathname, updateViaCache: 'none' })
            .catch(function () { /* Installation support is optional; regular portal use continues. */ });
    });
})();
