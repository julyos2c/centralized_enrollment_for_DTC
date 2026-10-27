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

    // Keep the native select as a fallback; enhance it with a bounded date list.
    var picker = document.createElement('details');
    picker.className = 'dr-date-picker';
    var summary = document.createElement('summary');
    summary.textContent = 'Keep current schedule';
    var choices = document.createElement('div');
    choices.className = 'dr-date-picker-options';
    Array.from(select.options).forEach(function (option) {
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = option.textContent;
        button.setAttribute('aria-pressed', String(option.selected));
        button.addEventListener('click', function () {
            select.value = option.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            summary.textContent = option.textContent;
            choices.querySelectorAll('button').forEach(function (item) {
                item.setAttribute('aria-pressed', String(item === button));
            });
            picker.open = false;
            summary.focus();
        });
        choices.appendChild(button);
    });
    picker.append(summary, choices);
    select.insertAdjacentElement('afterend', picker);
    select.hidden = true;
    var label = select.previousElementSibling;
    if (label && label.tagName === 'LABEL') {
        summary.id = select.id + '-summary';
        label.htmlFor = summary.id;
        picker.setAttribute('aria-label', label.textContent.trim());
    }
    picker.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            picker.open = false;
            summary.focus();
        }
    });

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
