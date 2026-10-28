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

    // Enhance the server's available dates; never infer availability in the browser.
    var picker = document.createElement('details');
    picker.className = 'dr-date-picker';
    var summary = document.createElement('summary');
    summary.textContent = 'Keep current schedule';
    var calendar = document.createElement('div');
    calendar.className = 'dr-calendar';
    var options = Array.from(select.options).filter(function (option) { return option.value; });
    var months = Array.from(new Set(options.map(function (option) { return option.value.slice(0, 7); })));
    var monthIndex = 0;
    function choose(value) {
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        summary.textContent = value ? select.options[select.selectedIndex].textContent : 'Keep current schedule';
        summary.setAttribute('aria-label', 'Choose a different date: ' + summary.textContent);
        picker.open = false;
        summary.focus();
    }
    function renderCalendar() {
        calendar.replaceChildren();
        var month = months[monthIndex];
        if (!month) return;
        var parts = month.split('-').map(Number);
        var header = document.createElement('div');
        header.className = 'dr-calendar-header';
        var title = document.createElement('strong');
        title.textContent = new Date(parts[0], parts[1] - 1, 1).toLocaleDateString('en', { month: 'long', year: 'numeric' });
        title.setAttribute('aria-live', 'polite');
        title.tabIndex = -1;
        function navigation(label, text, direction) {
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = text;
            button.setAttribute('aria-label', label);
            button.disabled = !months[monthIndex + direction];
            button.addEventListener('click', function () {
                monthIndex += direction;
                renderCalendar();
                calendar.querySelector('strong').focus();
            });
            return button;
        }
        header.append(navigation('Previous month', '‹', -1), title, navigation('Next month', '›', 1));
        var grid = document.createElement('div');
        grid.className = 'dr-calendar-grid';
        ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].forEach(function (day) {
            var label = document.createElement('span');
            label.className = 'dr-calendar-weekday';
            label.textContent = day;
            label.setAttribute('aria-hidden', 'true');
            grid.appendChild(label);
        });
        var offset = new Date(parts[0], parts[1] - 1, 1).getDay();
        for (var blank = 0; blank < offset; blank++) grid.appendChild(document.createElement('span'));
        var days = new Date(parts[0], parts[1], 0).getDate();
        for (var day = 1; day <= days; day++) {
            var value = month + '-' + String(day).padStart(2, '0');
            var option = options.find(function (item) { return item.value === value; });
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = String(day);
            button.disabled = !option;
            button.setAttribute('aria-label', option ? option.textContent : value + ', unavailable');
            button.setAttribute('aria-pressed', String(select.value === value));
            if (option) button.addEventListener('click', choose.bind(null, value));
            grid.appendChild(button);
        }
        var hint = document.createElement('p');
        hint.textContent = 'Select an available date, then choose a time below.';
        var keep = document.createElement('button');
        keep.type = 'button';
        keep.className = 'dr-calendar-keep';
        keep.textContent = 'Keep current schedule';
        keep.addEventListener('click', function () { choose(''); });
        calendar.append(header, grid, hint, keep);
    }
    picker.append(summary, calendar);
    picker.addEventListener('toggle', function () { if (picker.open) renderCalendar(); });
    select.insertAdjacentElement('afterend', picker);
    select.hidden = true;
    var label = select.previousElementSibling;
    if (label && label.tagName === 'LABEL') {
        summary.setAttribute('aria-label', label.textContent.trim() + ': ' + summary.textContent);
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
