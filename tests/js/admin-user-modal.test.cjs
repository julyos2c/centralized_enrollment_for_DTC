const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('password visibility toggles independently and resets on modal lifecycle events', () => {
    const fields = Object.fromEntries(['reset-new-password', 'reset-confirm-password'].map(id => [id, { id, type: 'password', value: 'Unchanged123' }]));
    const buttons = Object.keys(fields).map(id => ({ dataset: { passwordToggle: id }, attrs: {},
        setAttribute(key, value) { this.attrs[key] = value; },
        addEventListener(event, fn) { this.click = fn; }
    }));
    const modal = { querySelectorAll() { return buttons; } };
    let ready; let reset;
    const context = vm.createContext({
        document: { addEventListener(name, fn) { ready = fn; }, getElementById(id) { return id === 'editUserModal' ? modal : fields[id]; } },
        window: { jQuery() { return { on(events, fn) { reset = fn; } }; } }
    });
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/js/admin-user-modal.js'), 'utf8'), context);
    ready();
    buttons[0].click();
    assert.equal(fields['reset-new-password'].type, 'text');
    assert.equal(fields['reset-confirm-password'].type, 'password');
    assert.equal(buttons[0].attrs['aria-label'], 'Hide new password');
    assert.equal(buttons[0].attrs['aria-pressed'], 'true');
    buttons[1].click(); buttons[0].click();
    assert.equal(fields['reset-new-password'].type, 'password');
    assert.equal(fields['reset-confirm-password'].type, 'text');
    reset();
    for (const field of Object.values(fields)) {
        assert.equal(field.type, 'password');
        assert.equal(field.value, 'Unchanged123');
    }
    assert.equal(buttons[1].attrs['aria-label'], 'Show confirm password');
});
