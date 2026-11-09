const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function helpers() {
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/address-cascade.js'), 'utf8');
    const window = {};
    // No #addressFields element: the script only exposes its pure helpers.
    vm.runInNewContext(source, { window, document: { getElementById: () => null } });
    return window.DtcAddressCascade;
}

test('a single known postal code fills an empty field and can be overridden', () => {
    const { postalSuggestion } = helpers();
    const codes = [{ code: '6344', area: '' }];

    assert.equal(postalSuggestion(codes, '', false).value, '6344');
    assert.equal(postalSuggestion(codes, '', false).autofilled, true);
    // Replaces a code we filled for a previous city ...
    assert.equal(postalSuggestion(codes, '6000', true).value, '6344');
    // ... but never one the applicant typed.
    const typed = postalSuggestion(codes, '6345', false);
    assert.equal(typed.value, '6345');
    assert.match(typed.hint, /usual postal code here is 6344/);
});

test('several postal codes are suggested, not chosen', () => {
    const { postalSuggestion } = helpers();
    const codes = [{ code: '1000', area: 'Ermita' }, { code: '1001', area: 'Quiapo' }];

    const fresh = postalSuggestion(codes, '', false);
    assert.equal(fresh.value, '');
    assert.equal(fresh.autofilled, false);
    assert.match(fresh.hint, /2 postal codes/);
    assert.equal(postalSuggestion(codes, '6344', true).value, '', 'stale autofill is cleared');
    assert.equal(postalSuggestion(codes, '1001', false).value, '1001');
});

test('no known postal code leaves manual entry', () => {
    const { postalSuggestion } = helpers();
    assert.equal(postalSuggestion([], '', false).hint, 'Type your postal code if you know it.');
    assert.equal(postalSuggestion([], '1234', false).value, '1234');
    assert.equal(postalSuggestion([], '6344', true).value, '');
});

test('only Philippine (or blank) countries use the PSGC picker', () => {
    const { isPhilippines } = helpers();
    assert.equal(isPhilippines('Philippines'), true);
    assert.equal(isPhilippines('  philippines '), true);
    assert.equal(isPhilippines(''), true);
    assert.equal(isPhilippines('Japan'), false);
});
