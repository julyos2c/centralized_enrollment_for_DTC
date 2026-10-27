const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

for (const file of ['public/js/student-booking.js', 'resources/views/alumni/requests/create.blade.php']) {
    test(file + ' restores quantities for checked documents and omits unchecked quantities', () => {
        const rows = [true, false].map(checked => {
            const quantity = { value: '2', disabled: checked };
            const buttons = [{ disabled: checked }, { disabled: checked }];
            const input = { checked, value: 'diploma', getAttribute: () => 'Diploma', closest: () => row };
            const row = { quantity, buttons, input,
                querySelector: selector => selector === '.js-document-type' ? input : selector === '.js-document-copies' ? quantity : null,
                querySelectorAll: () => buttons };
            return row;
        });
        const source = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');
        const copyAmount = { disabled: true, setAttribute(name, value) { this[name] = value; } };
        const fees = { diploma: 100 }, paymentChoices = {}, freeNotice = {}, text = {};
        const start = source.indexOf('    var refresh = function () {');
        const end = source.indexOf('\n    };', start);
        assert.ok(start >= 0 && end > start);
        const context = vm.createContext({
            form: { querySelectorAll: selector => selector === '[data-payment-choices]' ? [paymentChoices] : selector === '[data-no-payment]' ? [freeNotice] : selector === '[data-document-copy-amount]' ? [copyAmount] : selector === '[data-document-option]' ? rows : selector === '.js-document-type:checked' ? rows.filter(r => r.input.checked).map(r => r.input) : [] },
            typeInputs: rows.map(row => row.input),
            selectedDocuments: () => rows.filter(row => row.input.checked).map(row => ({ input: row.input, copies: Number(row.quantity.value), unit: fees.diploma, subtotal: Number(row.quantity.value) * fees.diploma })),
            checked: () => null, $: selector => selector === '[data-slip-total]' ? { classList: { toggle() {} } } : null,
            fees, setText(selector, value) { text[selector] = value; }, peso: String, picked: null,
            document: { getElementById: () => null }
        });
        vm.runInContext(source.slice(start, end + '\n    };'.length), context);
        context.refresh();
        assert.equal(rows[0].quantity.disabled, false);
        assert.equal(rows[1].quantity.disabled, true);
        assert.equal(rows[0].quantity.value, '2');
        assert.equal(copyAmount.disabled, false);
        assert.equal(copyAmount['data-copy-value'], '200.00');
        rows[0].quantity.value = '3';
        context.refresh();
        assert.equal(copyAmount['data-copy-value'], '300.00');
        assert.ok(rows[0].buttons.every(button => !button.disabled));
        assert.ok(rows[1].buttons.every(button => button.disabled));
        rows[0].input.checked = false;
        rows[1].input.checked = true;
        context.refresh();
        assert.equal(rows[0].quantity.disabled, true);
        assert.equal(rows[1].quantity.disabled, false);
        rows[1].input.checked = false;
        context.refresh();
        assert.equal(copyAmount.disabled, true);
        assert.equal(copyAmount['data-copy-value'], '');
        rows[0].input.checked = true;
        fees.diploma = 0;
        context.refresh();
        assert.equal(context.payableTotal, 0);
        assert.equal(paymentChoices.hidden, true);
        assert.equal(freeNotice.hidden, false);
        assert.equal(text['[data-slip="pay"]'], 'No Payment Required');
    });
}
