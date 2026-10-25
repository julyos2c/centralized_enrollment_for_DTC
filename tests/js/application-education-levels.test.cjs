const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('education levels follow applicant type, preserve compatible values, and clear invalid draft values', () => {
    const source = fs.readFileSync('public/js/application-wizard.js', 'utf8');
    const select = { value: 'Senior High School', options: [], changes: 0,
        replaceChildren() { this.options = []; }, appendChild(option) { this.options.push(option); },
        dispatchEvent() { this.changes++; } };
    const hint = {};
    const levels = { new_student: ['Senior High School', 'Other'], transferee: ['College'],
        shiftee: ['College'], returnee: ['College'], cross_enrollee: ['College'] };
    const context = vm.createContext({ Event,
        window: { dtcApplicationEducationLevels: levels },
        document: { getElementById: id => id === 'education_level' ? select : hint, createElement: () => ({}) }
    });
    vm.runInContext(source.slice(source.indexOf('    function updateEducationLevels(type)'), source.indexOf('    function updateApplicantType()')), context);
    const update = type => { context.type = type; vm.runInContext('updateEducationLevels(type)', context); };
    update('new_student');
    assert.deepEqual(select.options.map(option => option.value), ['', 'Senior High School', 'Other']);
    assert.equal(select.value, 'Senior High School');
    update('transferee');
    assert.equal(select.value, '');
    assert.equal(select.changes, 1);
    assert.match(hint.textContent, /Choose an education level again/);
    for (const type of ['transferee', 'shiftee', 'returnee', 'cross_enrollee']) {
        select.value = 'College'; update(type);
        assert.deepEqual(select.options.map(option => option.value), ['', 'College']);
        assert.equal(select.value, 'College');
    }
    update('new_student');
    assert.equal(select.value, '');
    select.value = 'Other'; update('new_student');
    assert.equal(select.value, 'Other');
    update('');
    assert.equal(select.disabled, true);
    assert.equal(select.value, '');
});
