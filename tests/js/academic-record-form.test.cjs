const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(type = 'students', value = 'active') {
    const inputs = ['students', 'alumni'].map(value => ({ value, addEventListener(event, handler) { this.change = handler; } }));
    const status = { value, options: ['active', 'inactive', 'alumni'].map(value => ({ value })) };
    const student = {}, alumni = {}, cancel = { href: 'https://college.example/registrar/students?type=' + type };
    const form = { querySelectorAll() { return inputs; }, querySelector(selector) {
        return ({ '#academic_status': status, '[data-student-fields]': student, '[data-alumni-fields]': alumni,
            '[data-record-cancel]': cancel, 'input[name="record_type"]:checked': inputs.find(input => input.value === type) })[selector];
    } };
    vm.runInNewContext(fs.readFileSync('public/js/academic-record-form.js', 'utf8'), {
        URL, location: { href: cancel.href }, document: { querySelector: () => form },
    });
    return { inputs, status, student, alumni, cancel };
}

test('student records retain inactive status and omit graduation fields', () => {
    const app = setup('students', 'inactive');
    assert.equal(app.status.value, 'inactive');
    assert.equal(app.student.disabled, false);
    assert.equal(app.alumni.hidden, true);
    assert.equal(app.alumni.disabled, true);
    assert.equal(app.status.options[2].disabled, true);
});

test('changing record type updates status, applicable fields, and cancel destination', () => {
    const app = setup();
    app.inputs[1].change();
    assert.equal(app.status.value, 'alumni');
    assert.equal(app.student.disabled, true);
    assert.equal(app.alumni.disabled, false);
    assert.equal(app.status.options[0].disabled, true);
    assert.match(app.cancel.href, /type=alumni/);
    app.inputs[0].change();
    assert.equal(app.status.value, 'active');
    assert.equal(app.student.disabled, false);
    assert.equal(app.alumni.disabled, true);
    assert.match(app.cancel.href, /type=students/);
});
