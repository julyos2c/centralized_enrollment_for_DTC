/* DTC EMS — document request flow (alumni): request form summary + My Requests filters. */
(function () {
    'use strict';

    var peso = function (n) {
        return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    /* ---------------- Request form ---------------- */
    var form = document.getElementById('drForm');
    if (form) {
        var fees = {};
        try { fees = JSON.parse(form.getAttribute('data-fees') || '{}'); } catch (e) { fees = {}; }
        var maxCopies = parseInt(form.getAttribute('data-max-copies'), 10) || 10;

        var typeInputs = form.querySelectorAll('input[name="document_type"]');
        var typeGroup = document.getElementById('drTypeGroup');
        var typeError = document.getElementById('drTypeError');
        var copies = document.getElementById('copies');
        var minus = document.getElementById('drCopiesMinus');
        var plus = document.getElementById('drCopiesPlus');
        var slotInputs = form.querySelectorAll('input[name="appointment_slot_id"]');
        var pickupDate = document.getElementById('pickup-date');
        var payInputs = form.querySelectorAll('input[name="payment_method"]');
        var gcash = document.getElementById('gcashPaymentDetails');
        var ref = document.getElementById('documentPaymentReference');
        var proof = document.getElementById('documentPaymentProof');
        var submit = document.getElementById('drSubmit');

        var el = function (sel) { return form.querySelector(sel); };
        var slipDoc = el('[data-slip-doc]');
        var slipQty = el('[data-slip-qty]');
        var slipPickup = el('[data-slip-pickup]');
        var slipPay = el('[data-slip-pay]');
        var slipTotal = el('[data-slip-total]');
        var totalText = form.querySelector('[data-dr-total-text]');

        var clampCopies = function () {
            var v = parseInt(copies.value, 10);
            if (isNaN(v)) v = 1;
            return Math.min(maxCopies, Math.max(1, v));
        };

        var selectedType = function () {
            for (var i = 0; i < typeInputs.length; i++) if (typeInputs[i].checked) return typeInputs[i];
            return null;
        };
        var selectedPay = function () {
            for (var i = 0; i < payInputs.length; i++) if (payInputs[i].checked) return payInputs[i];
            return null;
        };
        var selectedSlot = function () {
            for (var i = 0; i < slotInputs.length; i++) if (slotInputs[i].checked) return slotInputs[i];
            return null;
        };
        var showPickupDate = function () {
            if (!pickupDate) return;
            var date = pickupDate.value;
            form.querySelectorAll('[data-slot-date-panel]').forEach(function (option) {
                var active = option.getAttribute('data-slot-date-panel') === date;
                option.hidden = !active;
                if (!active) {
                    var input = option.querySelector('input[name="appointment_slot_id"]');
                    if (input && input.checked) input.checked = false;
                }
            });
            if (!selectedSlot()) {
                var later = form.querySelector('input[name="appointment_slot_id"][value=""]');
                if (later) later.checked = true;
            }
            render();
        };

        var render = function () {
            var qty = clampCopies();
            var type = selectedType();
            var pay = selectedPay();
            var opt = selectedSlot();

            minus.disabled = qty <= 1;
            plus.disabled = qty >= maxCopies;

            slipQty.textContent = qty + (qty === 1 ? ' copy' : ' copies');
            slipPickup.textContent = (opt && opt.getAttribute('data-label')) || 'Book later';
            slipPay.textContent = (pay && pay.getAttribute('data-label')) || 'Cashier’s Office';

            if (type) {
                var unit = Number(fees[type.value]) || 0;
                slipDoc.textContent = type.getAttribute('data-label');
                slipDoc.classList.remove('is-empty');
                slipQty.textContent += ' at ' + peso(unit);
                slipTotal.textContent = peso(unit * qty);
                slipTotal.classList.remove('is-empty');
                if (totalText) totalText.textContent = peso(unit * qty);
                typeGroup.classList.remove('is-invalid');
                typeError.classList.remove('is-shown');
            } else {
                slipDoc.textContent = 'Not chosen yet';
                slipDoc.classList.add('is-empty');
                slipTotal.textContent = peso(0);
                slipTotal.classList.add('is-empty');
                if (totalText) totalText.textContent = 'the total shown';
            }

            var useGcash = !!(pay && pay.value === 'gcash_qr');
            if (gcash) {
                gcash.classList.toggle('is-open', useGcash);
                if (ref) ref.required = useGcash;
                if (proof) proof.required = useGcash;
            }
        };

        typeInputs.forEach(function (i) { i.addEventListener('change', render); });
        payInputs.forEach(function (i) { i.addEventListener('change', render); });
        slotInputs.forEach(function (input) { input.addEventListener('change', render); });
        if (pickupDate) pickupDate.addEventListener('change', showPickupDate);

        copies.addEventListener('input', render);
        copies.addEventListener('blur', function () { copies.value = clampCopies(); render(); });
        minus.addEventListener('click', function () { copies.value = Math.max(1, clampCopies() - 1); render(); });
        plus.addEventListener('click', function () { copies.value = Math.min(maxCopies, clampCopies() + 1); render(); });

        if (proof) {
            proof.addEventListener('change', function () {
                var name = form.querySelector('[data-dr-file-name]');
                var box = proof.closest('.dr-file');
                var file = proof.files && proof.files[0];
                if (file && file.size > 5 * 1024 * 1024) {
                    proof.value = '';
                    name.textContent = 'That file is over 5 MB. Choose a smaller one.';
                    box.classList.remove('has-file');
                    return;
                }
                name.textContent = file ? file.name : 'JPG, PNG or PDF, up to 5 MB';
                box.classList.toggle('has-file', !!file);
            });
        }

        // Native validation on the hidden radios: show our message and bring it into view.
        typeInputs.forEach(function (i) {
            i.addEventListener('invalid', function (e) {
                e.preventDefault();
                typeGroup.classList.add('is-invalid');
                typeError.classList.add('is-shown');
                typeGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });

        form.addEventListener('submit', function () {
            if (!form.checkValidity()) return;
            copies.value = clampCopies();
            if (submit) {
                submit.classList.add('is-busy');
                submit.textContent = submit.getAttribute('data-loading-text') || 'Submitting…';
            }
        });

        // Restore button if the user comes back via the browser's back button.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted && submit) { submit.classList.remove('is-busy'); submit.textContent = 'Submit request'; }
        });

        // "What happens next" is open on desktop, collapsed on phones.
        var next = document.getElementById('drNext');
        if (next && window.matchMedia('(min-width: 992px)').matches) next.open = true;

        render();
    }

    /* ---------------- Pickup booking (My Requests): day, then time ---------------- */
    document.querySelectorAll('[data-pickup-form]').forEach(function (pf) {
        var hint = pf.querySelector('[data-pp-hint]');
        var picked = pf.querySelector('[data-pp-picked]');
        var submitBtn = pf.querySelector('[data-pp-submit]');
        var empty = 'No time chosen.';

        var sync = function () {
            var slot = pf.querySelector('input[name="appointment_slot_id"]:checked');
            if (picked) {
                picked.textContent = slot ? slot.getAttribute('data-date') + ', ' + slot.getAttribute('data-time') : empty;
                picked.classList.toggle('has-value', !!slot);
            }
            if (submitBtn) submitBtn.disabled = !slot;
        };

        var showDay = function () {
            var day = pf.querySelector('input[name="pickup_date"]:checked');
            var value = day ? day.value : '';
            pf.querySelectorAll('[data-pp-panel]').forEach(function (panel) {
                var active = panel.getAttribute('data-pp-panel') === value;
                panel.hidden = !active;
                if (!active) {
                    var c = panel.querySelector('input[name="appointment_slot_id"]:checked');
                    if (c) c.checked = false;
                }
            });
            if (hint) hint.hidden = value !== '';
            sync();
        };

        pf.addEventListener('change', function (e) {
            if (e.target.name === 'pickup_date') showDay();
            else if (e.target.name === 'appointment_slot_id') sync();
        });

        // Never submit without a time, even if the button was re-enabled.
        pf.addEventListener('submit', function (e) {
            if (!pf.querySelector('input[name="appointment_slot_id"]:checked')) { e.preventDefault(); return; }
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Requesting…'; }
        });

        window.addEventListener('pageshow', function (e) {
            if (e.persisted && submitBtn) { submitBtn.textContent = 'Request pickup time'; showDay(); }
        });

        showDay();
    });

    /* ---------------- My Requests filters ---------------- */
    var filters = document.querySelector('[data-dr-filters]');
    if (filters) {
        var cards = document.querySelectorAll('.dr-req');
        var emptyMsg = document.getElementById('drFilterEmpty');
        var buttons = filters.querySelectorAll('.dr-filter');

        var apply = function (key) {
            var shown = 0;
            cards.forEach(function (card) {
                var group = card.getAttribute('data-group');
                var match = key === 'all' || group === key;
                card.hidden = !match;
                if (match) shown++;
            });
            buttons.forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-filter') === key ? 'true' : 'false'); });
            if (emptyMsg) emptyMsg.classList.toggle('is-shown', shown === 0);
        };

        buttons.forEach(function (b) {
            b.addEventListener('click', function () { apply(b.getAttribute('data-filter')); });
        });

        // Links like #request-history from elsewhere in the app keep working; #ready opens the Ready filter.
        if (window.location.hash === '#ready') apply('ready');
    }
})();
