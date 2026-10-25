const { test } = require('node:test');
const assert = require('node:assert/strict');
const normalize = require('../../public/js/dtc-name-normalizer.js');

test('honorific and suffix formatting adds missing periods without duplicating punctuation', () => {
    for (const [value, field, expected] of [
        ['Mr', 'honorific', 'Mr.'], ['mrs.', 'honorific', 'Mrs.'], ['Dr Emerita', 'honorific', 'Dr. Emerita'],
        ['Professor', 'honorific', 'Professor'], ['Miss', 'honorific', 'Miss'], ['jr', 'suffix', 'Jr.'],
        ['Sr.', 'suffix', 'Sr.'], ['iii', 'suffix', 'III'], ['', 'honorific', '']
    ]) {
        assert.equal(normalize.affix(value, field), expected);
        assert.equal(normalize.affix(expected, field), expected);
    }
});
