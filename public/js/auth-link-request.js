(function () {
    'use strict';
    document.querySelectorAll('form[data-link-request]').forEach(function (form) {
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;
        const original = button.innerHTML;
        let busy = false;
        form.addEventListener('submit', function (event) {
            if (busy) { event.preventDefault(); return; }
            if (event.defaultPrevented || !form.checkValidity()) return;
            busy = true;
            button.disabled = true;
            button.textContent = 'Sending…';
            form.setAttribute('aria-busy', 'true');
        });
        window.addEventListener('pageshow', function () {
            busy = false;
            button.disabled = false;
            button.innerHTML = original;
            form.removeAttribute('aria-busy');
        });
    });
})();
