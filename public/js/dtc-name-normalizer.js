(function (root, factory) {
    var normalizeName = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = normalizeName;
    } else {
        root.dtcNormalizeName = normalizeName;
    }
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    function normalizeName(value) {
        var normalized = String(value || '')
            .trim()
            .replace(/\s+/gu, ' ')
            .toLowerCase()
            .replace(/[\p{L}\p{M}]+/gu, function (word) {
                return word.charAt(0).toUpperCase() + word.slice(1);
            });

        return normalized.replace(/(\d)(st|nd|rd|th)\b/giu, function (match, number, suffix) {
            return number + suffix.toLowerCase();
        });
    }
    normalizeName.affix = function (value, field) {
        value = String(value || '').trim().replace(/\s+/gu, ' ');
        if (field === 'suffix') {
            if (/^(jr|sr)\.?$/i.test(value)) return value.charAt(0).toUpperCase() + 'r.';
            if (/^(ii|iii|iv|v|vi|vii|viii|ix|x)$/i.test(value)) return value.toUpperCase();
        }
        if (field === 'honorific') {
            var titles = { mr: 'Mr', mrs: 'Mrs', ms: 'Ms', mx: 'Mx', dr: 'Dr', prof: 'Prof',
                engr: 'Engr', atty: 'Atty', rev: 'Rev', fr: 'Fr', sr: 'Sr', br: 'Br', hon: 'Hon',
                capt: 'Capt', gen: 'Gen', lt: 'Lt', col: 'Col', maj: 'Maj', sgt: 'Sgt' };
            value = value.replace(/^([a-z]+)\.?(?=\s|$)/i, function (match, word) {
                return titles[word.toLowerCase()] ? titles[word.toLowerCase()] + '.' : match;
            });
        }
        return value;
    };
    return normalizeName;
}));
