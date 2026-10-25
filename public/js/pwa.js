(function () {
    'use strict';
    const script = document.currentScript;
    if (!script || !script.dataset.worker || !window.isSecureContext || !('serviceWorker' in navigator)) return;
    const worker = new URL(script.dataset.worker, location.href);
    if (worker.origin !== location.origin) return;
    window.addEventListener('load', function () {
        navigator.serviceWorker.register(worker.href, { scope: new URL('./', worker).pathname, updateViaCache: 'none' })
            .catch(function () { /* Installation support is optional; regular portal use continues. */ });
    });
})();
