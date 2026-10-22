(function (root, factory) {
    var normalizeName = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = normalizeName;
    } else {
        root.dtcNormalizeName = normalizeName;
    }
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    return function (value) {
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
    };
}));
