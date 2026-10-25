const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(saved, dark, unavailable = false) {
    const classes = () => ({ values: new Set(), toggle(name, enabled) { enabled ? this.values.add(name) : this.values.delete(name); } });
    const events = {};
    const media = { matches: dark, addEventListener(name, fn) { this.change = fn; } };
    const document = { documentElement: { classList: classes() }, body: null, addEventListener(name, fn) { events[name] = fn; } };
    const window = { matchMedia: () => media, addEventListener(name, fn) { events[name] = fn; } };
    vm.runInNewContext(fs.readFileSync('public/js/auth-theme.js', 'utf8'), { document, window, localStorage: { getItem() { if (unavailable) throw Error('Blocked'); return saved; } } });
    return { document, media, events, saved(value) { saved = value; }, mount() { document.body = { classList: classes() }; events.DOMContentLoaded(); } };
}

test('auth theme applies before body creation and follows the system without a saved choice', () => {
    const app = setup(null, true);
    assert.ok(app.document.documentElement.classList.values.has('dtc-dark'));
    app.mount();
    assert.ok(app.document.body.classList.values.has('dtc-dark'));
    app.media.matches = false;
    app.media.change();
    assert.equal(app.document.body.classList.values.has('dtc-dark'), false);
});

test('saved preferences override the system and synchronize between tabs', () => {
    const app = setup('light', true);
    app.mount();
    assert.equal(app.document.body.classList.values.has('dtc-dark'), false);
    app.saved('dark');
    app.events.storage({ key: 'dtc-ems-theme' });
    assert.ok(app.document.body.classList.values.has('dtc-dark'));
    app.media.matches = false;
    app.media.change();
    assert.ok(app.document.body.classList.values.has('dtc-dark'));
    app.saved(null);
    app.events.storage({ key: null });
    assert.equal(app.document.body.classList.values.has('dtc-dark'), false);
});

test('blocked storage falls back to the system without breaking auth screens', () => {
    const app = setup(null, true, true);
    app.mount();
    assert.ok(app.document.body.classList.values.has('dtc-dark'));
});
