const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function load(file) {
    const source = fs.readFileSync(path.join(__dirname, '../../public/js', file), 'utf8');
    const window = {};
    // No form on the page: the scripts only expose their pure helpers.
    vm.runInNewContext(source, { window, document: { getElementById: () => null, querySelectorAll: () => [] } });
    return window;
}

const curricula = [
    { id: 1, name: 'K to 12', choice_label: 'Strand', first: 2017, last: null, classifications: [
        { id: 11, track: 'Academic Track', name: 'STEM', offering: 'STEM', label: 'STEM (Academic Track)' },
        { id: 12, track: 'Academic Track', name: 'ABM', offering: 'ABM', label: 'ABM (Academic Track)' },
        { id: 13, track: 'Sports Track', name: 'Sports Track', offering: 'Sports', label: 'Sports Track' },
    ] },
    { id: 2, name: 'Strengthened', choice_label: 'Elective cluster', first: 2026, last: null, classifications: [
        { id: 21, track: 'Academic Track', name: 'Business and Entrepreneurship', offering: null, label: 'Business and Entrepreneurship (Academic Track)' },
    ] },
];

test('school years parse only consecutive YYYY-YYYY pairs', () => {
    const { DtcEducation } = load('education-background.js');
    assert.equal(DtcEducation.startYear('2017-2018'), 2017);
    assert.equal(DtcEducation.startYear(' 2017 – 2018 '), 2017);
    assert.equal(DtcEducation.startYear('2017-2019'), null);
    assert.equal(DtcEducation.startYear('Batch 2017'), null);
});

test('curricula are filtered by graduation year', () => {
    const { DtcEducation } = load('education-background.js');
    const names = (year) => DtcEducation.curriculaForYear(curricula, year).map((c) => c.name);
    assert.deepEqual(names(2012), []);
    assert.deepEqual(names(2019), ['K to 12']);
    assert.deepEqual(names(2026), ['K to 12', 'Strengthened']);
    assert.deepEqual(names(null), ['K to 12', 'Strengthened']);
});

test('strands offered by the school come first; other choices are always last', () => {
    const { DtcEducation } = load('education-background.js');
    const options = DtcEducation.strandOptions(curricula[0], ['ABM']);
    assert.deepEqual(options.map((o) => o.value), ['12', '11', '13', 'other', 'not_sure']);
    assert.equal(options[0].group, 'Offered by your school (DepEd list)');
    assert.equal(options[1].group, 'Academic Track');

    const clusters = DtcEducation.strandOptions(curricula[1], []);
    assert.match(clusters.at(-2).label, /elective cluster is not listed/);
    assert.match(clusters.at(-1).label, /don't remember my elective cluster/);
    assert.equal(DtcEducation.strandOptions(null, []).length, 0);
});

test('searchable dropdown filtering ignores case, accents, and word order', () => {
    const { DtcCombobox } = load('dtc-combobox.js');
    const items = [{ label: 'Science City of Muñoz' }, { label: 'Santo Niño' }, { label: 'City of Cebu' }];
    assert.deepEqual(DtcCombobox.filterItems(items, 'munoz').map((i) => i.label), ['Science City of Muñoz']);
    assert.deepEqual(DtcCombobox.filterItems(items, 'cebu city').map((i) => i.label), ['City of Cebu']);
    assert.equal(DtcCombobox.filterItems(items, '').length, 3);
});
