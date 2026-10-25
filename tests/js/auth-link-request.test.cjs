const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('link requests show progress, prevent duplicates, and recover on browser back', () => {
    const button = { innerHTML: 'Send reset link' };
    const form = { valid: true, events: {}, querySelector: () => button, checkValidity() { return this.valid; },
        addEventListener(name, fn) { this.events[name] = fn; }, setAttribute(name, value) { this[name] = value; }, removeAttribute(name) { delete this[name]; } };
    let restore;
    vm.runInNewContext(fs.readFileSync('public/js/auth-link-request.js', 'utf8'), {
        document: { querySelectorAll: () => [form] }, window: { addEventListener: (name, fn) => { restore = fn; } }
    });
    form.valid = false; form.events.submit({}); assert.equal(button.disabled, undefined);
    form.valid = true; form.events.submit({ defaultPrevented: true }); assert.equal(button.disabled, undefined);
    form.events.submit({}); assert.equal(button.disabled, true); assert.equal(button.textContent, 'Sending…');
    let prevented = false;
    form.events.submit({ preventDefault() { prevented = true; } }); assert.equal(prevented, true);
    restore(); assert.equal(button.disabled, false); assert.equal(button.innerHTML, 'Send reset link');
    assert.equal(form['aria-busy'], undefined);
});
