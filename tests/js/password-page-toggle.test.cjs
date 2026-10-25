const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('set/reset password toggles work after Lucide has replaced the eye with an SVG', () => {
    for (const page of ['set-password', 'reset-password']) {
        const source = fs.readFileSync(`resources/views/auth/${page}.blade.php`, 'utf8');
        const start = source.indexOf('function togglePw(');
        const code = source.slice(start, source.indexOf('</script>', start));
        const inputs = { password: { type: 'password' }, password_confirmation: { type: 'password' } };
        const button = { icon: { tag: 'svg' }, replaceChildren(icon) { this.icon = icon; }, setAttribute(name, value) { this[name] = value; } };
        const lucide = { createIcons() { button.icon.tag = 'svg'; } };
        const context = vm.createContext({ button, window: { lucide }, lucide,
            document: { getElementById: id => inputs[id], createElement: () => ({ setAttribute(name, value) { this[name] = value; } }) }
        });
        vm.runInContext(code, context);
        for (const field of ['password', 'password_confirmation']) {
            context.field = field;
            vm.runInContext('togglePw(field, button)', context);
            assert.equal(inputs[field].type, 'text');
            assert.equal(button.icon['data-lucide'], 'eye-off');
            assert.equal(button['aria-label'], 'Hide password');
            vm.runInContext('togglePw(field, button)', context);
            assert.equal(inputs[field].type, 'password');
            assert.equal(button.icon['data-lucide'], 'eye');
            assert.equal(button['aria-label'], 'Show password');
        }
    }
});
