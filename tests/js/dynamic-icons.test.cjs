const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function icon() { return { tag: 'i', style: {}, setAttribute(key, value) { this[key] = value; } }; }

test('sign-in password eye keeps updating on repeated clicks after SVG rendering', () => {
    const source = fs.readFileSync('public/js/signin-modal.js', 'utf8');
    const start = source.indexOf("            const btn = e.target.closest");
    const code = source.slice(start, source.indexOf('\n        });', start));
    const input = { type: 'password' };
    const button = { child: { tag: 'svg' }, closest: () => ({ querySelector: () => input }),
        replaceChildren(node) { this.child = node; }, setAttribute(key, value) { this[key] = value; } };
    const lucide = { createIcons() { assert.equal(button.child.tag, 'i'); button.child.tag = 'svg'; } };
    const context = vm.createContext({ document: { createElement: icon }, window: { lucide }, lucide,
        event: { target: { closest: () => button } } });
    for (const [type, glyph] of [['text', 'eye-off'], ['password', 'eye'], ['text', 'eye-off']]) {
        vm.runInContext('(function(e) {' + code + '})(event)', context);
        assert.equal(input.type, type);
        assert.equal(button.child['data-lucide'], glyph);
    }
});

test('profile password buttons rebuild the icon and keep accessible state in sync', () => {
    const source = fs.readFileSync('public/js/profile.js', 'utf8');
    const start = source.indexOf("document.querySelectorAll('.ps-pw-toggle')");
    const code = source.slice(start, source.indexOf('\n});', start) + 4);
    const input = { type: 'password' };
    const button = { dataset: { target: 'password' }, addEventListener(type, fn) { this.click = fn; },
        replaceChildren(node) { this.child = node; }, setAttribute(key, value) { this[key] = value; } };
    const lucide = { createIcons() { assert.equal(button.child.tag, 'i'); button.child.tag = 'svg'; } };
    vm.runInNewContext(code, { document: { querySelectorAll: () => [button], getElementById: () => input, createElement: icon }, lucide });
    button.click.call(button); assert.equal(button.child['data-lucide'], 'eye-off');
    assert.equal(button['aria-label'], 'Hide password');
    button.click.call(button); assert.equal(button.child['data-lucide'], 'eye');
    assert.equal(button['aria-pressed'], 'false');
});

test('theme and confirmation icons replace rendered SVG nodes rather than reuse old paths', () => {
    let current = icon(); current.tag = 'svg';
    function install(node) { current = node; current.replaceWith = install; }
    install(current);
    const lucide = { createIcons() { assert.equal(current.tag, 'i'); current.tag = 'svg'; } };
    const document = { createElement: icon, getElementById: id => id === 'darkModeIcon' ? current : null,
        documentElement: { classList: { toggle() {}, remove() {} } }, body: { classList: { toggle() {} } }, dispatchEvent() {} };
    const context = vm.createContext({ document, window: { lucide }, lucide, CustomEvent: class {} });
    const source = fs.readFileSync('public/js/dtc-app.js', 'utf8');
    vm.runInContext(source.slice(source.indexOf('function applyDtcTheme'), source.indexOf('function toggleDtcTheme')), context);
    for (const [theme, glyph] of [['dark', 'sun'], ['light', 'moon'], ['dark', 'sun']]) {
        context.theme = theme; vm.runInContext('applyDtcTheme(theme)', context);
        assert.equal(current['data-lucide'], glyph);
    }
    const feedback = fs.readFileSync('public/js/dtc-feedback.js', 'utf8');
    const start = feedback.indexOf("        var icon = confirmModal.querySelector('#dtcConfirmIcon')");
    const code = feedback.slice(start, feedback.indexOf('        confirmModal.style.display', start));
    context.confirmModal = { querySelector: () => current };
    context.iconNames = { success: 'check-circle', danger: 'alert-triangle' };
    context.options = {};
    for (const tone of ['success', 'danger', 'success']) {
        context.tone = tone; vm.runInContext(code, context);
        assert.equal(current['data-lucide'], context.iconNames[tone]);
    }
    context.confirmModal = { querySelector: () => null };
    vm.runInContext(code, context);
});
