const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(saved = null, dark = false, blocked = false) {
    const classes = new Set();
    let onSystemChange, onStorage;
    const media = { matches: dark, addEventListener(type, handler) { onSystemChange = handler; } };
    const state = { saved };
    const context = vm.createContext({
        localStorage: {
            getItem() { if (blocked) throw Error('blocked'); return state.saved; },
            setItem(key, value) { if (blocked) throw Error('blocked'); state.saved = value; },
        },
        window: { matchMedia: () => media, addEventListener(type, handler) { onStorage = handler; } },
        document: {
            documentElement: { classList: { toggle(key, enabled) { enabled ? classes.add(key) : classes.delete(key); }, remove() {} } },
            body: { classList: { toggle() {} } }, getElementById: () => null, dispatchEvent() {},
        },
        CustomEvent: class {},
    });
    const source = fs.readFileSync('public/js/dtc-app.js', 'utf8');
    vm.runInContext(source.slice(0, source.indexOf('// ── Chart.js')), context);
    vm.runInContext('applyDtcTheme(getDtcTheme()); initDtcSystemTheme();', context);
    return { state, media, classes, change() { onSystemChange(); }, storage() { onStorage({ key: 'dtc-ems-theme' }); }, toggle() { vm.runInContext('toggleDtcTheme()', context); } };
}

test('system changes update an open page when there is no saved preference', () => {
    const app = setup(null, true);
    assert.ok(app.classes.has('dtc-dark'));
    app.media.matches = false; app.change();
    assert.equal(app.classes.has('dtc-dark'), false);
    assert.equal(app.state.saved, null);
});

test('explicit light and dark choices override system changes', () => {
    for (const choice of ['dark', 'light']) {
        const app = setup(choice);
        app.media.matches = true; app.change();
        assert.equal(app.classes.has('dtc-dark'), choice === 'dark');
        app.media.matches = false; app.change();
        assert.equal(app.classes.has('dtc-dark'), choice === 'dark');
    }
});

test('other-tab preference changes sync and removal resumes system mode', () => {
    const app = setup('light', true);
    app.state.saved = 'dark'; app.storage();
    assert.ok(app.classes.has('dtc-dark'));
    app.state.saved = null; app.media.matches = false; app.storage();
    assert.equal(app.classes.has('dtc-dark'), false);
    app.media.matches = true; app.change();
    assert.ok(app.classes.has('dtc-dark'));
});

test('blocked storage does not break system mode or the manual theme toggle', () => {
    const app = setup(null, true, true);
    assert.ok(app.classes.has('dtc-dark'));
    app.toggle();
    assert.equal(app.classes.has('dtc-dark'), false);
    app.media.matches = false; app.change();
    assert.equal(app.classes.has('dtc-dark'), false);
});
