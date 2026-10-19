/* appointments/index.blade.php — extracted scripts */

// Re-render lucide icons inside modals when they open
$(document).on('shown.bs.modal', '.modal', function () {
    if (window.lucide) lucide.createIcons();
});

// Update filename label when a file is chosen
function dtcFileChange(input) {
    var nameEl = input.closest('.dtc-file-upload').querySelector('.dtc-file-name');
    if (input.files && input.files.length) {
        nameEl.textContent = input.files[0].name;
        nameEl.classList.add('has-file');
    } else {
        nameEl.textContent = 'No file chosen';
        nameEl.classList.remove('has-file');
    }
}

// Filter reschedule choices by date instead of showing every slot at once.
document.querySelectorAll('[data-reschedule-date-select]').forEach(function (select) {
    var id = select.getAttribute('data-reschedule-date-select');
    var list = document.querySelector('[data-reschedule-slot-list="' + id + '"]');
    if (!list) return;

    select.addEventListener('change', function () {
        var date = select.value;
        list.hidden = !date;
        var hiddenSelection = false;
        list.querySelectorAll('[data-reschedule-date]').forEach(function (slot) {
            var visible = slot.getAttribute('data-reschedule-date') === date;
            slot.hidden = !visible;
            var input = slot.querySelector('input[name="appointment_slot_id"]');
            if (!visible && input && input.checked) {
                input.checked = false;
                hiddenSelection = true;
            }
        });
        if (hiddenSelection) {
            var current = select.closest('.modal').querySelector('[data-reschedule-current]');
            if (current) current.checked = true;
        }
    });
});
