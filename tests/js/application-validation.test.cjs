const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('validation keeps feedback outside controls, focuses the first missing field, and preserves help descriptions', () => {
    const source = fs.readFileSync('public/js/application-wizard.js', 'utf8');
    function control(id, disabled = false) {
        const attributes = new Map([['aria-describedby', 'existing-help']]);
        const field = {
            error: null,
            querySelector() { return this.error; },
            appendChild(error) { this.error = error; error.remove = () => { this.error = null; }; }
        };
        return { id, value: '', disabled, field, focused: false,
            classList: { add() {}, remove() {} },
            closest() { return field; },
            setAttribute(key, value) { attributes.set(key, value); },
            getAttribute(key) { return attributes.get(key); },
            removeAttribute(key) { attributes.delete(key); },
            focus() { this.focused = true; }
        };
    }
    const first = control('first_name');
    const last = control('last_name');
    const inactive = control('inactive', true);
    const timers = [];
    const context = vm.createContext({
        document: { getElementById: () => ({ querySelectorAll: () => [first, last, inactive] }), createElement: () => ({}) },
        window: {}, setTimeout: fn => timers.push(fn)
    });
    vm.runInContext(source.slice(source.indexOf('    function validateStep(n)'),
        source.indexOf("    document.querySelectorAll('.wiz-input')")), context);
    assert.equal(vm.runInContext('validateStep(2)', context), false);
    timers.forEach(fn => fn());
    assert.equal(first.focused, true);
    assert.equal(last.focused, false);
    assert.equal(inactive.field.error, null);
    assert.equal(first.field.error.id, 'first_name-error');
    assert.equal(first.getAttribute('aria-describedby'), 'existing-help first_name-error');
    assert.equal(first.getAttribute('aria-invalid'), 'true');
    first.value = 'Juan';
    context.input = first;
    vm.runInContext('clearFieldError(input)', context);
    assert.equal(first.field.error, null);
    assert.equal(first.getAttribute('aria-describedby'), 'existing-help');
    assert.equal(first.getAttribute('aria-invalid'), undefined);
});
