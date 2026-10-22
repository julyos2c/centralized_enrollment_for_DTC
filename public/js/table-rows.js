/* Shared row limits for server-paginated and collection-backed account tables. */
(function () {
    'use strict';
    function init(control) {
        if (control.dataset.rowsReady) return;
        const table = control.dataset.tableTarget ? document.getElementById(control.dataset.tableTarget) : control.nextElementSibling;
        if (!table || table.tagName !== 'TABLE') return;
        control.dataset.rowsReady = 'true';
        const select = control.querySelector('[data-table-rows-select]');
        const server = control.dataset.server === 'true';
        if (control.dataset.fixedPlacement !== 'true') {
            const filter = Array.from(document.forms || []).reverse().find(form =>
                form.method.toLowerCase() === 'get' &&
                new URL(form.action || location.href, location.href).pathname === location.pathname &&
                (form.compareDocumentPosition(control) & Node.DOCUMENT_POSITION_FOLLOWING)
            );
            const field = control.querySelector('[data-table-rows-field]');
            if (filter && field) {
                select.name = 'per_page';
                filter.querySelectorAll('[data-table-rows-field]').forEach(old => old.remove());
                filter.querySelectorAll('input[type="hidden"][name="per_page"]').forEach(input => input.remove());
                const panel = filter.querySelector('.dtc-fl-panel, .dtc-fl-fields, .ph-advanced') || filter;
                const actions = panel.querySelector('.dtc-fl-actions, .ph-filter-actions');
                if (actions) panel.insertBefore(field, actions);
                else panel.appendChild(field);
                const options = control.querySelector('[data-table-rows-options]');
                if (options) options.remove();
                if (server) control.hidden = true;
            }
        }
        const status = control.querySelector('[data-table-rows-status]');
        let page = 0;
        const prev = control.querySelector('[data-table-rows-prev]');
        const next = control.querySelector('[data-table-rows-next]');
        function render() {
            if (server) return;
            const rows = Array.from(table.tBodies).flatMap(body => Array.from(body.rows));
            const data = rows.filter(row => !row.querySelector('td[colspan]') && row.style.display !== 'none');
            const limit = Number(select.value);
            page = Math.max(0, Math.min(page, Math.ceil(data.length / limit) - 1));
            rows.forEach(row => row.classList.remove('dtc-row-outside-page'));
            data.forEach((row, index) => row.classList.toggle('dtc-row-outside-page', index < page * limit || index >= (page + 1) * limit));
            prev.disabled = page === 0;
            next.disabled = (page + 1) * limit >= data.length;
            status.textContent = data.length ? `${page * limit + 1}–${Math.min((page + 1) * limit, data.length)} of ${data.length}` : '0 rows';
        }
        select.addEventListener('change', function () {
            page = 0;
            if (!server) {
                const url = new URL(location.href);
                url.searchParams.set('per_page', select.value);
                history.replaceState(null, '', url.href);
                scan();
                render(); return;
            }
            const url = new URL(location.href);
            // Include current filter values, even if the user has not applied them yet.
            document.querySelectorAll('form').forEach(form => {
                if (form.method.toLowerCase() !== 'get') return;
                const action = new URL(form.action || location.href, location.href);
                if (action.pathname !== url.pathname) return;
                new FormData(form).forEach((value, key) => { if (typeof value === 'string') url.searchParams.set(key, value); });
            });
            url.searchParams.set('per_page', select.value);
            Array.from(url.searchParams.keys()).forEach(key => {
                if (key !== 'per_page' && (key === 'page' || key.endsWith('_page'))) url.searchParams.delete(key);
            });
            location.assign(url.href);
        });
        if (!server) {
            prev.addEventListener('click', () => { page--; render(); });
            next.addEventListener('click', () => { page++; render(); });
            const observer = new MutationObserver(render);
            table.querySelectorAll('tbody').forEach(body => observer.observe(body, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] }));
        }
        render();
    }
    function scan() {
        document.querySelectorAll('[data-table-rows-control]').forEach(init);
        const limit = new URLSearchParams(location.search).get('per_page');
        if (limit) document.querySelectorAll('form[method="GET"], form[method="get"]').forEach(form => {
            const existing = form.querySelector('[name="per_page"]');
            if (existing) {
                if (existing.type === 'hidden') existing.value = limit;
                return;
            }
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'per_page'; input.value = limit;
            form.appendChild(input);
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        scan();
        new MutationObserver(mutations => {
            if (mutations.some(mutation => Array.from(mutation.addedNodes).some(node => node.nodeType === 1))) scan();
        }).observe(document.body, { childList: true, subtree: true });
    });
})();
