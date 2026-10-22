const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

test('collection tables show 5 or 10 rows, navigate, and reset on a limit change', () => {
    const element = () => ({ listeners: {}, addEventListener(name, fn) { this.listeners[name] = fn; } });
    const select = Object.assign(element(), { value: '5' });
    const prev = element(); const next = element(); const status = {};
    const rows = Array.from({ length: 12 }, () => {
        const classes = new Set();
        return { style: {}, querySelector() { return null; }, classes,
            classList: { remove(name) { classes.delete(name); }, toggle(name, on) { if (on) classes.add(name); else classes.delete(name); } } };
    });
    const table = { tagName: 'TABLE', tBodies: [{ rows }], querySelectorAll() { return []; } };
    const controls = { '[data-table-rows-select]': select, '[data-table-rows-prev]': prev,
        '[data-table-rows-next]': next, '[data-table-rows-status]': status };
    const control = { dataset: { server: 'false' }, nextElementSibling: table, querySelector: key => controls[key] };
    let ready; let savedUrl;
    const context = vm.createContext({
        URL, URLSearchParams, location: { href: 'https://example.test/list?search=Juan', search: '?search=Juan' },
        history: { replaceState(a, b, url) { savedUrl = url; } },
        document: { body: {}, querySelectorAll: key => key === '[data-table-rows-control]' ? [control] : [],
            addEventListener(name, fn) { ready = fn; } },
        MutationObserver: class { observe() {} }
    });
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/js/table-rows.js'), 'utf8'), context);
    ready();
    const visible = () => rows.filter(row => !row.classes.has('dtc-row-outside-page')).length;
    assert.equal(visible(), 5);
    assert.equal(status.textContent, '1–5 of 12');
    assert.equal(prev.disabled, true);
    next.listeners.click();
    assert.equal(status.textContent, '6–10 of 12');
    next.listeners.click();
    assert.equal(visible(), 2);
    assert.equal(next.disabled, true);
    select.value = '10'; select.listeners.change();
    assert.equal(visible(), 10);
    assert.equal(status.textContent, '1–10 of 12');
    assert.match(savedUrl, /search=Juan/);
    assert.match(savedUrl, /per_page=10/);
});

test('server limit changes retain filters and reset all named paginator pages', () => {
    let ready; let destination;
    const select = { value: '5', addEventListener(name, fn) { this.change = fn; } };
    const table = { tagName: 'TABLE' };
    const control = { dataset: { server: 'true' }, nextElementSibling: table,
        querySelector(key) { return key === '[data-table-rows-select]' ? select : {}; } };
    const context = vm.createContext({
        URL, URLSearchParams,
        location: { href: 'https://example.test/list?status=active&role=student&page=3&subjects_page=4',
            pathname: '/list', search: '?status=active&role=student&page=3&subjects_page=4',
            assign(url) { destination = new URL(url); } },
        document: { body: {}, forms: [],
            querySelectorAll: key => key === '[data-table-rows-control]' ? [control] : [],
            addEventListener(name, fn) { ready = fn; } },
        MutationObserver: class { observe() {} }
    });
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/js/table-rows.js'), 'utf8'), context);
    ready(); select.change();
    assert.equal(destination.searchParams.get('per_page'), '5');
    assert.equal(destination.searchParams.get('status'), 'active');
    assert.equal(destination.searchParams.get('role'), 'student');
    assert.equal(destination.searchParams.has('page'), false);
    assert.equal(destination.searchParams.has('subjects_page'), false);
});

test('server and local row-count fields move into the existing filter panel', () => {
    for (const server of ['true', 'false']) {
        let ready; let inserted; let removed = false;
        const field = {};
        const actions = {};
        const panel = { querySelector() { return actions; }, insertBefore(node, before) { assert.equal(before, actions); inserted = node; } };
        const filter = { method: 'get', action: 'https://example.test/list', compareDocumentPosition() { return 4; },
            querySelectorAll() { return []; }, querySelector() { return panel; } };
        const select = { value: '5', addEventListener() {} };
        const button = { addEventListener() {} };
        const table = { tagName: 'TABLE', tBodies: [], querySelectorAll() { return []; } };
        const control = { dataset: { server }, nextElementSibling: table,
            querySelector(key) {
                return ({ '[data-table-rows-select]': select, '[data-table-rows-field]': field,
                    '[data-table-rows-options]': { remove() { removed = true; } },
                    '[data-table-rows-prev]': button, '[data-table-rows-next]': button,
                    '[data-table-rows-status]': {} })[key];
            } };
        vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/table-rows.js'), 'utf8'), {
            URL, URLSearchParams, Node: { DOCUMENT_POSITION_FOLLOWING: 4 },
            location: { href: 'https://example.test/list', pathname: '/list', search: '' },
            document: { body: {}, forms: [filter], addEventListener(name, fn) { ready = fn; },
                querySelectorAll(key) { return key === '[data-table-rows-control]' ? [control] : []; } },
            MutationObserver: class { observe() {} }
        });
        ready();
        assert.equal(inserted, field);
        assert.equal(select.name, 'per_page');
        assert.equal(removed, true);
        assert.equal(!!control.hidden, server === 'true');
    }
});

test('explicit filter controls bind to their named table without relocating fields', () => {
    let ready; let bound = false;
    const select = { addEventListener() { bound = true; } };
    const control = { dataset: { server: 'true', fixedPlacement: 'true', tableTarget: 'payment-table' },
        querySelector(key) { return key === '[data-table-rows-select]' ? select : {}; } };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/table-rows.js'), 'utf8'), {
        URLSearchParams, location: { search: '' },
        document: { body: {}, getElementById(id) { assert.equal(id, 'payment-table'); return { tagName: 'TABLE' }; },
            addEventListener(name, fn) { ready = fn; },
            querySelectorAll(key) { return key === '[data-table-rows-control]' ? [control] : []; } },
        MutationObserver: class { observe() {} }
    });
    ready();
    assert.equal(bound, true);
    assert.equal(control.hidden, undefined);
});
