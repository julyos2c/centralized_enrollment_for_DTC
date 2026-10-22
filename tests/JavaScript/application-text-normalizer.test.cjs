const assert = require('node:assert/strict');
const normalize = require('../../public/js/dtc-name-normalizer.js');

assert.equal(normalize('REMedios danao bohol'), 'Remedios Danao Bohol');
assert.equal(normalize('JUAN DELA CRUZ'), 'Juan Dela Cruz');
assert.equal(
    normalize('  purok 2   remedios danao bohol, near st. john clinic.  '),
    'Purok 2 Remedios Danao Bohol, Near St. John Clinic.'
);
assert.equal(normalize('2ND year, 1st SEMESTER'), '2nd Year, 1st Semester');
assert.equal(normalize(String.fromCharCode(79, 39, 67, 79, 78, 78, 79, 82)), String.fromCharCode(79, 39, 67, 111, 110, 110, 111, 114));

console.log('Application text normalization cases passed.');
