const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

test('education profiles switch labels and applicable fields without clearing values', () => {
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/application-wizard.js'), 'utf8');
    const nodes = new Map();
    const node = key => {
        if (!nodes.has(key)) nodes.set(key, { value: 'Saved applicant entry', setAttribute() {} });
        return nodes.get(key);
    };
    const sections = [
        'new_student', 'transferee,shiftee,returnee', 'cross_enrollee',
        'transferee,shiftee,returnee,cross_enrollee'
    ].map(types => ({
        types, hidden: true, field: { value: 'Saved education entry' },
        getAttribute() { return types; },
        classList: { toggle() {} },
        querySelectorAll() { return [this.field]; }
    }));
    const context = vm.createContext({
        document: { getElementById: node, querySelector: node },
        applicantType: { value: '' }, educationSections: sections,
        documentSections: [],
        noDocumentNotice: { classList: { toggle() {} } },
        crossEnrolleeNotice: { classList: { toggle() {} } },
        buildChecklist() {}
    });
    vm.runInContext(source.slice(source.indexOf('    const educationProfiles ='),
        source.indexOf('    function updateMaritalStatus()')), context);
    const cases = [
        ['new_student', 'Senior High School Background', 'Senior High School Name'],
        ['transferee', 'Previous College Information', 'Previous College or University'],
        ['shiftee', 'Current Academic Information', 'Current College or University'],
        ['returnee', 'Previous Enrollment Information', 'College or University Last Attended'],
        ['cross_enrollee', 'Home Institution Information', 'Most Recent School Attended'],
        ['new_student', 'Senior High School Background', 'Senior High School Name']
    ];
    for (const [type, heading, school] of cases) {
        context.applicantType.value = type;
        vm.runInContext('updateApplicantType()', context);
        assert.equal(node('educationHeading').textContent, heading);
        assert.equal(node('#step-4 label[for="most_recent_school"]').textContent, school);
        assert.equal(node('most_recent_school').value, 'Saved applicant entry');
        for (const section of sections) {
            assert.equal(section.field.disabled, !section.types.split(',').includes(type));
            assert.equal(section.field.value, 'Saved education entry');
        }
    }
});
