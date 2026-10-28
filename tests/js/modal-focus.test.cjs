const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup() {
    const handlers = {};
    const document = { body: {}, activeElement: null, querySelector: () => null,
        addEventListener(event, callback) { callback(); } };
    const source = fs.readFileSync('public/js/dtc-app.js', 'utf8');
    const start = source.indexOf('// Shared Bootstrap modal focus lifecycle.');
    const end = source.indexOf('// End shared modal focus lifecycle.');
    assert.ok(start >= 0 && end > start);
    vm.runInNewContext(source.slice(start, end), { document, window: { jQuery: () => ({
        on(event, selector, callback) { assert.equal(selector, '.modal'); handlers[event] = callback; }
    }) }, WeakMap });
    const control = { blur() { document.activeElement = document.body; } };
    const modal = { contains: element => element === control };
    const opener = { isConnected: true, disabled: false, getClientRects: () => [1],
        focus() { document.activeElement = opener; } };
    return { handlers, document, control, modal, opener };
}

test('closing a modal clears descendant focus before hiding and restores its opener', () => {
    const { handlers, document, control, modal, opener } = setup();
    handlers['show.bs.modal'].call(modal, { relatedTarget: opener });
    document.activeElement = control;
    handlers['hide.bs.modal'].call(modal, { isDefaultPrevented: () => false });
    assert.equal(document.activeElement, document.body);
    handlers['hidden.bs.modal'].call(modal);
    assert.equal(document.activeElement, opener);
});

test('cancelled dismissal preserves focus', () => {
    const { handlers, document, control, modal } = setup();
    document.activeElement = control;
    handlers['hide.bs.modal'].call(modal, { isDefaultPrevented: () => true });
    assert.equal(document.activeElement, control);
});

test('closing one dialog does not steal focus from a replacement dialog', () => {
    const { handlers, document, modal, opener } = setup();
    handlers['show.bs.modal'].call(modal, { relatedTarget: opener });
    document.activeElement = document.body;
    document.querySelector = () => ({});
    handlers['hidden.bs.modal'].call(modal);
    assert.equal(document.activeElement, document.body);
});

test('removed openers are not focused', () => {
    const { handlers, document, modal, opener } = setup();
    handlers['show.bs.modal'].call(modal, { relatedTarget: opener });
    opener.isConnected = false;
    document.activeElement = document.body;
    handlers['hidden.bs.modal'].call(modal);
    assert.equal(document.activeElement, document.body);
});
