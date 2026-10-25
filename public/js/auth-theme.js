(function () {
    'use strict';
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    function apply() {
        let saved;
        try { saved = localStorage.getItem('dtc-ems-theme'); } catch (error) {}
        const dark = saved === 'dark' || (saved !== 'light' && system.matches);
        document.documentElement.classList.toggle('dtc-dark', dark);
        if (document.body) document.body.classList.toggle('dtc-dark', dark);
    }
    apply();
    document.addEventListener('DOMContentLoaded', apply);
    if (system.addEventListener) system.addEventListener('change', apply);
    else if (system.addListener) system.addListener(apply);
    window.addEventListener('storage', function (event) {
        if (event.key === 'dtc-ems-theme' || event.key === null) apply();
    });
})();
