(function () {
    'use strict';
    const form = document.querySelector('[data-academic-record-form]');
    if (!form) return;
    const status = form.querySelector('#academic_status');
    const types = form.querySelectorAll('input[name="record_type"]');
    const studentFields = form.querySelector('[data-student-fields]');
    const alumniFields = form.querySelector('[data-alumni-fields]');
    const cancel = form.querySelector('[data-record-cancel]');
    function update(type, changed) {
        const alumni = type === 'alumni';
        if (changed) status.value = alumni ? 'alumni' : 'active';
        Array.from(status.options).forEach(option => {
            option.hidden = option.disabled = alumni ? option.value !== 'alumni' : option.value === 'alumni';
        });
        studentFields.hidden = studentFields.disabled = alumni;
        alumniFields.hidden = alumniFields.disabled = !alumni;
        const url = new URL(cancel.href, location.href);
        url.searchParams.set('type', type);
        cancel.href = url.href;
    }
    types.forEach(input => input.addEventListener('change', function () { update(input.value, true); }));
    const selected = form.querySelector('input[name="record_type"]:checked');
    if (selected) update(selected.value, false);
})();
