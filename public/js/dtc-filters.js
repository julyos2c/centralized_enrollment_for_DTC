/* DTC shared filter toolbar: keeps the "Filters" count badge and "Clear all" link in sync
   while the user changes fields (needed on pages that filter live via AJAX, no reload). */
(function () {
    'use strict';

    function init(form) {
        var fields = form.querySelectorAll('[data-dtc-filter]');
        var search = form.querySelector('[data-dtc-filter-search]');
        var toggle = form.querySelector('[data-dtc-filter-toggle]');
        var badge  = form.querySelector('[data-dtc-filter-count]');
        var clear  = form.querySelectorAll('[data-dtc-clear]');
        if (!fields.length) return;

        function sync() {
            var count = 0;
            fields.forEach(function (el) { if (el.value && el.value.trim() !== '') count++; });

            if (badge) {
                badge.textContent = count;
                badge.hidden = count === 0;
            }
            if (toggle) toggle.classList.toggle('has-filters', count > 0);

            var anyActive = count > 0 || !!(search && search.value.trim() !== '');
            clear.forEach(function (el) { el.hidden = !anyActive; });
        }

        form.addEventListener('change', sync);
        form.addEventListener('input', sync);
        sync();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-dtc-filters]').forEach(init);
    });
})();
