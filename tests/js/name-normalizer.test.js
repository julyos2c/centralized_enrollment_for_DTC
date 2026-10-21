'use strict';

const assert = require('node:assert/strict');
const normalizeName = require('../../public/js/dtc-name-normalizer.js');

const cases = [
    ['DELA CRUZ', 'Dela Cruz'],
    ['dela cruz', 'Dela Cruz'],
    ['jUAN dELA cRUZ', 'Juan Dela Cruz'],
    ['  MARIA   ANGELICA SANTOS  ', 'Maria Angelica Santos'],
    ['DE LOS SANTOS', 'De Los Santos'],
    ['VAN DER MEER', 'Van Der Meer'],
    ['ANNE-MARIE', 'Anne-Marie'],
    ["O'CONNOR", "O'Connor"],
];

cases.forEach(([input, expected]) => {
    assert.equal(normalizeName(input), expected, input);
});

console.log(`${cases.length} JavaScript name normalization cases passed.`);
