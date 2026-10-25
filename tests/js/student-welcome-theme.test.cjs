const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('existing theme switch applies saved dark/light classes without closing an open welcome', () => {
    const source = fs.readFileSync('public/js/dtc-app.js', 'utf8');
    const classes = () => ({ values: new Set(), toggle(name, enabled) { if (enabled) this.values.add(name); else this.values.delete(name); }, remove(name) { this.values.delete(name); } });
    const html = classes(), body = classes();
    let saved = 'dark';
    const welcome = { hidden: false };
    const context = vm.createContext({
        localStorage: { getItem: () => saved }, window: { matchMedia: () => ({ matches: false }) },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        document: { documentElement: { classList: html }, body: { classList: body },
            getElementById: id => id === 'studentWelcome' ? welcome : null, dispatchEvent() {} }
    });
    vm.runInContext(source.slice(0, source.indexOf('function toggleDtcTheme')), context);
    for (const mode of ['dark', 'light', 'dark']) {
        saved = mode;
        vm.runInContext('applyDtcTheme(getDtcTheme())', context);
        assert.equal(html.values.has('dtc-dark'), mode === 'dark');
        assert.equal(body.values.has('dtc-dark'), mode === 'dark');
        assert.equal(welcome.hidden, false);
    }
});

test('shared theme resolution honors saved choices and otherwise follows the system', () => {
    const source = fs.readFileSync('public/js/dtc-app.js', 'utf8');
    for (const [saved, system, expected] of [['dark', false, 'dark'], ['light', true, 'light'], [null, true, 'dark'], [null, false, 'light']]) {
        const context = vm.createContext({ localStorage: { getItem: () => saved }, window: { matchMedia: () => ({ matches: system }) } });
        vm.runInContext(source.slice(0, source.indexOf('function applyDtcTheme')), context);
        assert.equal(vm.runInContext('getDtcTheme()', context), expected);
        for (const file of ['resources/views/layouts/public.blade.php', 'resources/views/auth/welcome.blade.php', 'resources/views/vendor/adminlte/master.blade.php']) {
            const template = fs.readFileSync(file, 'utf8');
            const start = template.indexOf('(function () {', template.indexOf('theme preload'));
            const end = template.indexOf('</script>', start);
            const classes = new Set();
            vm.runInNewContext(template.slice(start, end), {
                localStorage: { getItem: () => saved }, window: { matchMedia: () => ({ matches: system }) },
                document: { documentElement: { classList: { add: name => classes.add(name) } } }
            });
            assert.equal(classes.has('dtc-dark'), expected === 'dark', file);
        }
    }
});

test('welcome text and button palettes meet WCAG AA in both themes', () => {
    function luminance(hex) {
        const values = hex.match(/[a-f0-9]{2}/gi).map(value => {
            const channel = parseInt(value, 16) / 255;
            return channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4;
        });
        return values[0] * .2126 + values[1] * .7152 + values[2] * .0722;
    }
    const pairs = [['#0f172a', '#ffffff'], ['#475569', '#ffffff'], ['#ffffff', '#0f4cdb'],
        ['#ffffff', '#0b43c1'], ['#f1f5f9', '#1e293b'], ['#cbd5e1', '#1e293b'],
        ['#ffffff', '#2563eb'], ['#ffffff', '#1d4ed8']];
    for (const [text, background] of pairs) {
        const a = luminance(text), b = luminance(background);
        const ratio = (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
        assert.ok(ratio >= 4.5, `${text} on ${background}: ${ratio.toFixed(2)}:1`);
    }
});
