/* appointments/create.blade.php — extracted scripts */

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

(function () {
    // ── Slot data injected by Blade into window.dtcApptData ─────────
    var slotData   = (window.dtcApptData && window.dtcApptData.slots)        || {};
    var initialStep = (window.dtcApptData && window.dtcApptData.initialStep) || 1;

    var docLabels = {
        transcript   : 'Transcript of Records',
        diploma      : 'Diploma',
        certification: 'Certification',
        tor          : 'True Copy of Records',
    };

    var payLabels = {
        walk_in: 'Walk-in (Pay at Cashier)',
        gcash  : 'GCash (Online Payment)',
    };

    // ── Step navigation ─────────────────────────────────────────────
    var currentStep = 1;

    function showStep(n) {
        document.querySelectorAll('.appt-step-panel').forEach(function (p) {
            p.classList.add('d-none');
        });
        var panel = document.getElementById('step-panel-' + n);
        if (panel) panel.classList.remove('d-none');

        // Update indicators
        for (var i = 1; i <= 4; i++) {
            var ind = document.getElementById('step-indicator-' + i);
            if (!ind) continue;
            ind.classList.remove('is-active', 'is-done');
            if (i < n)        ind.classList.add('is-done');
            else if (i === n) ind.classList.add('is-active');
        }
        // Update lines
        document.querySelectorAll('.dtc-appt-step-line').forEach(function (line, idx) {
            line.classList.toggle('is-done', idx < n - 1);
        });

        currentStep = n;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ── Toast notification ──────────────────────────────────────────
    function showToast(message, type) {
        var existing = document.getElementById('dtc-step-toast');
        if (existing) existing.remove();

        var colors = {
            error  : { bg: '#fff1f2', border: '#fca5a5', icon: '#ef4444', text: '#991b1b' },
            warning: { bg: '#fffbeb', border: '#fcd34d', icon: '#f59e0b', text: '#92400e' },
            success: { bg: '#f0fdf4', border: '#86efac', icon: '#22c55e', text: '#166534' },
        };
        var c = colors[type] || colors.error;

        var icons = {
            error  : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
            warning: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            success: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 13.01 9 10.01"/></svg>',
        };

        var toast = document.createElement('div');
        toast.id = 'dtc-step-toast';
        toast.style.cssText = [
            'position:fixed', 'top:20px', 'right:20px', 'z-index:9999',
            'display:flex', 'align-items:center', 'gap:10px',
            'padding:12px 16px', 'border-radius:12px', 'max-width:320px',
            'box-shadow:0 4px 20px rgba(0,0,0,0.12)',
            'border:1px solid ' + c.border,
            'background:' + c.bg,
            'color:' + c.text,
            'font-size:13px', 'font-weight:500', 'line-height:1.4',
            'transition:opacity 0.3s ease',
            'opacity:0',
        ].join(';');

        var iconEl = document.createElement('span');
        iconEl.style.cssText = 'flex-shrink:0; color:' + c.icon + '; display:flex;';
        iconEl.innerHTML = icons[type] || icons.error;

        var msgEl = document.createElement('span');
        msgEl.textContent = message;

        toast.appendChild(iconEl);
        toast.appendChild(msgEl);
        document.body.appendChild(toast);

        // Fade in
        requestAnimationFrame(function () {
            toast.style.opacity = '1';
        });

        // Auto-dismiss after 3.5s
        setTimeout(function () {
            toast.style.opacity = '0';
            setTimeout(function () { if (toast.parentNode) toast.remove(); }, 300);
        }, 3500);
    }

    // ── Step validation ──────────────────────────────────────────────
    function validateStep(step) {
        if (step === 1) {
            var docSelected = document.querySelector('.doc-radio:checked');
            if (!docSelected) {
                showToast('Please select a document type before continuing.', 'error');
                return false;
            }
        }
        if (step === 2) {
            var slotSelected = document.querySelector('.slot-radio:checked');
            if (!slotSelected) {
                showToast('Please select an available time slot before continuing.', 'error');
                return false;
            }
        }
        if (step === 3) {
            var paySelected = document.querySelector('.pay-radio:checked');
            if (!paySelected) {
                showToast('Please select a payment method before continuing.', 'error');
                return false;
            }
            var purposeField = document.querySelector('[name="purpose"]');
            if (purposeField && !purposeField.value.trim()) {
                purposeField.focus();
                purposeField.style.borderColor = '#ef4444';
                showToast('Please enter the purpose of your appointment.', 'error');
                purposeField.addEventListener('input', function clearErr() {
                    purposeField.style.borderColor = '';
                    purposeField.removeEventListener('input', clearErr);
                });
                return false;
            }
            // GCash-specific validation
            if (paySelected.value === 'gcash') {
                var ref   = document.getElementById('gcash-ref');
                var proof = document.getElementById('gcash-proof');
                if (ref && !ref.value.trim()) {
                    ref.focus();
                    showToast('Please enter your GCash reference number.', 'error');
                    return false;
                }
                if (proof && !proof.files.length) {
                    showToast('Please upload your GCash proof of payment.', 'error');
                    return false;
                }
            }
        }
        return true;
    }

    // Next buttons
    document.querySelectorAll('.btn-step-next').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var next = parseInt(this.dataset.next);
            if (!validateStep(next - 1)) return;
            if (next === 4) updateSummary();
            showStep(next);
        });
    });

    // Back buttons
    document.querySelectorAll('.btn-step-back').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showStep(parseInt(this.dataset.back));
        });
    });

    // ── Payment method toggle ────────────────────────────────────────
    document.querySelectorAll('.pay-radio').forEach(function (r) {
        r.addEventListener('change', function () {
            var isGcash = this.value === 'gcash';
            document.getElementById('gcash-details').style.display = isGcash ? '' : 'none';
            document.getElementById('walkin-details').style.display = isGcash ? 'none' : '';

            // Toggle required on gcash fields
            var refInput   = document.getElementById('gcash-ref');
            var proofInput = document.getElementById('gcash-proof');
            if (refInput)   refInput.required   = isGcash;
            if (proofInput) proofInput.required  = isGcash;
        });
        // Init
        if (r.checked) r.dispatchEvent(new Event('change'));
    });

    // ── Build review summary ─────────────────────────────────────────
    function updateSummary() {
        var docRadio  = document.querySelector('.doc-radio:checked');
        var slotRadio = document.querySelector('.slot-radio:checked');
        var payRadio  = document.querySelector('.pay-radio:checked');
        var purpose   = document.querySelector('[name="purpose"]');

        var docEl     = document.getElementById('summary-doc');
        var dateEl    = document.getElementById('summary-date');
        var timeEl    = document.getElementById('summary-time');
        var payEl     = document.getElementById('summary-payment');
        var purpEl    = document.getElementById('summary-purpose');

        if (docRadio && docEl)
            docEl.textContent = docLabels[docRadio.value] || docRadio.value;

        if (slotRadio && slotData[slotRadio.value]) {
            var s = slotData[slotRadio.value];
            if (dateEl) dateEl.textContent = s.date;
            if (timeEl) timeEl.textContent = s.start + ' — ' + s.end;
        }

        if (payRadio && payEl)
            payEl.textContent = payLabels[payRadio.value] || payRadio.value;

        var purposeVal = purpose ? purpose.value.trim() : '';
        if (purpEl) purpEl.textContent = purposeVal || '—';
    }

    // ── Restore step on validation redirect ─────────────────────────
    // initialStep is set by Blade via window.dtcApptData.initialStep
    if (initialStep > 1) {
        showStep(initialStep);
    }

    // Re-render lucide icons in dynamically shown panels
    document.querySelectorAll('.btn-step-next, .btn-step-back').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setTimeout(function () {
                if (window.lucide) lucide.createIcons();
            }, 50);
        });
    });

})();
