/* DTC Nexus — student document booking wizard (4 steps). Replaces appointments-create.js on the booking page. */
(function () {
    "use strict";
    var form = document.getElementById("bkForm");
    if (!form) return;

    var fees = {};
    try {
        fees = JSON.parse(form.getAttribute("data-fees") || "{}");
    } catch (e) {
        fees = {};
    }
    var step = 1;
    var reached = 1;
    var nextBtn = document.getElementById("bkNext");
    var backBtn = document.getElementById("bkBack");
    var note = document.getElementById("bkNote");
    var stepsEl = form.querySelector(".dr-steps");
    var panels = form.querySelectorAll("[data-step-panel]");
    var rail = form.querySelector("[data-date-rail]");
    var picked = form.querySelector("[data-picked]");
    var reduceMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
    ).matches;
    var labels = {
        1: "Next: Pickup time",
        2: "Next: Payment",
        3: "Review booking",
        4: "Confirm booking",
    };

    var $ = function (sel, root) {
        return (root || form).querySelector(sel);
    };
    var checked = function (name) {
        return $('input[name="' + name + '"]:checked');
    };
    var show = function (el, on) {
        if (el) el.classList.toggle("is-shown", !!on);
    };
    var errFor = function (key) {
        return $('[data-step-error="' + key + '"]');
    };
    var peso = function (amount) {
        return (
            "₱" +
            Number(amount || 0).toLocaleString("en-PH", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
        );
    };

    /* ---------- summary (slip + review) ---------- */
    var setText = function (sel, text, empty) {
        var el = $(sel);
        if (!el) return;
        el.textContent = text;
        el.classList.toggle("is-empty", !!empty);
    };

    var payableTotal = null;
    var refresh = function () {
        var selectedDocuments = Array.prototype.map.call(form.querySelectorAll('.js-document-type:checked'), function (input) {
            var row = input.closest('[data-document-option]');
            var quantity = row && row.querySelector('.js-document-copies');
            return {
                label: input.getAttribute('data-label'),
                copies: quantity ? parseInt(quantity.value, 10) || 1 : 1,
                subtotal: (Number(fees[input.value]) || 0) * (quantity ? parseInt(quantity.value, 10) || 1 : 1)
            };
        });
        var slot = checked('appointment_slot_id');
        var pay = checked('payment_method');
        var purpose = $('[name="purpose"]');
        var documentLabel = selectedDocuments.map(function (item) {
            return item.label + ' × ' + item.copies + ' — ' + peso(item.subtotal);
        }).join(', ');

        setText('[data-slip="doc"]', documentLabel || 'Not chosen yet', !documentLabel);
        setText('[data-slip="when"]', slot ? slot.getAttribute('data-date') + ', ' + slot.getAttribute('data-time') : 'Not chosen yet', !slot);
        setText('[data-slip="pay"]', pay ? pay.getAttribute('data-label') : 'Cashier’s Office');

        setText('[data-review="doc"]', documentLabel || '—');
        setText('[data-review="date"]', slot ? slot.getAttribute('data-date') : '—');
        setText('[data-review="time"]', slot ? slot.getAttribute('data-time') : '—');
        setText('[data-review="pay"]', pay ? (pay.value === 'gcash' ? 'GCash (receipt will be verified)' : 'Cashier’s Office') : '—');
        setText('[data-review="purpose"]', purpose && purpose.value.trim() ? purpose.value.trim() : '—');

        var documentFee = selectedDocuments.length
            ? selectedDocuments.reduce(function (total, item) { return total + item.subtotal; }, 0)
            : null;
        form.querySelectorAll('[data-document-copy-amount]').forEach(function (button) {
            button.disabled = documentFee === null;
            button.setAttribute('data-copy-value', documentFee === null ? '' : Number(documentFee).toFixed(2));
        });
        setText('[data-document-amount]', documentFee === null ? 'Choose documents first' : peso(documentFee));
        payableTotal = documentFee;
        form.querySelectorAll('[data-payment-choices]').forEach(function (el) { el.hidden = payableTotal === 0; });
        form.querySelectorAll('[data-no-payment]').forEach(function (el) { el.hidden = payableTotal !== 0; });
        if (payableTotal === 0) { setText('[data-slip="pay"]', 'No Payment Required'); setText('[data-review="pay"]', 'No Payment Required'); }
        var selectedCopies = selectedDocuments.reduce(function (total, item) { return total + item.copies; }, 0);
        var countSummary = $('[data-slip-count]');
        if (countSummary) {
            countSummary.textContent = selectedDocuments.length
                ? selectedDocuments.length + (selectedDocuments.length === 1 ? ' document' : ' documents') + ' · ' + selectedCopies + (selectedCopies === 1 ? ' copy' : ' copies')
                : 'Choose documents';
        }
        form.querySelectorAll('[data-document-option]').forEach(function (row) {
            var input = row.querySelector('.js-document-type');
            var quantity = row.querySelector('.js-document-copies');
            quantity.disabled = !input.checked;
            row.querySelectorAll('[data-copy-step]').forEach(function (button) { button.disabled = !input.checked; });
            var unitFee = input ? Number(fees[input.value]) || 0 : 0;
            var copies = quantity ? parseInt(quantity.value, 10) || 1 : 1;
            var subtotal = row.querySelector('[data-document-subtotal]');
            var copySummary = row.querySelector('[data-document-copy-summary]');
            if (subtotal) subtotal.textContent = peso(unitFee * copies);
            if (copySummary) copySummary.textContent = copies + ' × ' + peso(unitFee);
        });
        var feeText = documentFee === null ? 'Choose documents first' : peso(documentFee);
        form.querySelectorAll('[data-document-fee], [data-gcash-fee]').forEach(function (el) {
            el.textContent = documentFee === null ? (el.hasAttribute('data-gcash-fee') ? 'the total fee' : feeText) : feeText;
        });
        var slipFee = $('[data-slip-fee]');
        if (slipFee) {
            slipFee.textContent = documentFee === null ? 'Choose documents' : feeText;
            slipFee.classList.toggle('is-free', documentFee === null);
        }

        if (picked) {
            picked.textContent = slot
                ? slot.getAttribute("data-date") +
                  ", " +
                  slot.getAttribute("data-time")
                : "No time chosen yet.";
            picked.classList.toggle("has-value", !!slot);
        }

        var gcash = document.getElementById('gcash-details');
        var useGcash = documentFee > 0 && !!(pay && pay.value === 'gcash');
        if (gcash) gcash.classList.toggle('is-open', useGcash);
    };

    /* ---------- pickup day strip ---------- */
    var selectedDay = function () {
        var r = rail && rail.querySelector('input[name="pickup_date"]:checked');
        return r ? r.value : "";
    };

    var centerDay = function (instant) {
        var r = rail && rail.querySelector('input[name="pickup_date"]:checked');
        var chip = r && r.closest(".dp-day");
        if (!chip) return;
        // Scroll the strip itself, never the page.
        var left = chip.offsetLeft - (rail.clientWidth - chip.offsetWidth) / 2;
        rail.scrollTo({
            left: Math.max(0, left),
            behavior: instant || reduceMotion ? "auto" : "smooth",
        });
    };

    var syncRailNav = function () {
        var nav = form.querySelector("[data-rail-nav]");
        if (!rail || !nav) return;
        var max = rail.scrollWidth - rail.clientWidth;
        nav.hidden = max <= 2;
        var prev = nav.querySelector("[data-rail-prev]"),
            next = nav.querySelector("[data-rail-next]");
        if (prev) prev.disabled = rail.scrollLeft <= 2;
        if (next) next.disabled = rail.scrollLeft >= max - 2;
    };

    var showPickupDate = function () {
        var day = selectedDay();
        // Compare attribute values directly; nothing from the page is ever put into a selector.
        form.querySelectorAll("[data-slot-date-panel]").forEach(
            function (panel) {
                var active = panel.getAttribute("data-slot-date-panel") === day;
                panel.hidden = !active;
                if (!active) {
                    var checkedTime = panel.querySelector(
                        'input[name="appointment_slot_id"]:checked',
                    );
                    if (checkedTime) checkedTime.checked = false;
                }
            },
        );
        refresh();
    };

    /* ---------- validation ---------- */
    var clearErrors = function () {
        form.querySelectorAll("[data-step-error]").forEach(function (e) {
            e.classList.remove("is-shown");
        });
        form.querySelectorAll(".is-invalid").forEach(function (e) {
            e.classList.remove("is-invalid");
        });
    };

    var fail = function (key, focusEl) {
        show(errFor(key), true);
        if (focusEl) {
            focusEl.classList.add("is-invalid");
            focusEl.focus({ preventScroll: true });
            focusEl.scrollIntoView({ behavior: "smooth", block: "center" });
        } else {
            var e = errFor(key);
            if (e) e.scrollIntoView({ behavior: "smooth", block: "center" });
        }
        return false;
    };

    var validate = function (n) {
        clearErrors();
        if (n === 1) {
            var selected = form.querySelectorAll('.js-document-type:checked');
            if (!selected.length) return fail('1');
            for (var i = 0; i < selected.length; i++) {
                var copiesInput = selected[i].closest('[data-document-option]').querySelector('.js-document-copies');
                var copies = parseInt(copiesInput.value, 10);
                if (!copiesInput.value || isNaN(copies) || copies < 1 || copies > 10) {
                    return fail('1', copiesInput);
                }
            }
        }
        if (n === 2 && !checked('appointment_slot_id')) return fail('2');
        if (n === 3) {
            var purpose = $('[name="purpose"]');
            if (purpose && !purpose.value.trim()) return fail('purpose', purpose);
            if (payableTotal === 0) return true;
            var pay = checked('payment_method');
            if (!pay) return false;
            if (pay.value === "gcash") {
                var ref = $("#gcash-ref"),
                    proof = $("#gcash-proof");
                if (ref && !ref.value.trim()) return fail("ref", ref);
                if (proof && !(proof.files && proof.files.length))
                    return fail("proof");
            }
        }
        return true;
    };

    /* ---------- navigation ---------- */
    var go = function (n, opts) {
        step = n;
        form.setAttribute('data-current-step', String(n));
        var progress = form.querySelector('.dr-document-progress');
        if (progress) progress.setAttribute('aria-valuenow', String(n));
        reached = Math.max(reached, n);
        panels.forEach(function (p) {
            p.hidden = String(p.getAttribute("data-step-panel")) !== String(n);
        });

        form.querySelectorAll("[data-step-item]").forEach(function (li) {
            var i = parseInt(li.getAttribute("data-step-item"), 10);
            li.classList.toggle("is-done", i < n);
            li.classList.toggle("is-current", i === n);
            var btn = li.querySelector("button");
            btn.disabled = i >= n || i > reached;
            if (i === n) btn.setAttribute("aria-current", "step");
            else btn.removeAttribute("aria-current");
        });

        nextBtn.textContent = labels[n];
        backBtn.hidden = n === 1;
        note.textContent = "Step " + n + " of 4";
        refresh();

        if (n === 2) {
            showPickupDate();
            syncRailNav();
            centerDay(true);
        }

        if (!opts || !opts.silent) {
            var legend = $('[data-step-panel="' + n + '"] legend');
            if (legend) {
                legend.setAttribute("tabindex", "-1");
                legend.focus({ preventScroll: true });
            }
            stepsEl.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    };

    var advance = function () {
        refresh();
        if (nextBtn.classList.contains('is-busy')) return; // already submitting: ignore repeat taps and Enter
        if (step < 4) {
            if (validate(step)) go(step + 1);
            return;
        }
        // Final step: re-check everything, jump to the first problem.
        for (var i = 1; i <= 3; i++) {
            if (!validate(i)) {
                go(i);
                validate(i);
                return;
            }
        }
        nextBtn.classList.add("is-busy");
        nextBtn.textContent = "Booking…";
        form.submit();
    };

    nextBtn.addEventListener("click", advance);
    backBtn.addEventListener("click", function () {
        if (step > 1) go(step - 1);
    });
    form.querySelectorAll("[data-step-btn]").forEach(function (b) {
        b.addEventListener("click", function () {
            go(parseInt(b.getAttribute("data-step-btn"), 10));
        });
    });
    form.querySelectorAll("[data-goto]").forEach(function (b) {
        b.addEventListener("click", function () {
            go(parseInt(b.getAttribute("data-goto"), 10));
        });
    });

    // Enter in a text field moves forward instead of submitting early.
    form.addEventListener("keydown", function (e) {
        if (
            e.key === "Enter" &&
            e.target.tagName === "INPUT" &&
            e.target.type !== "file"
        ) {
            e.preventDefault();
            advance();
        }
    });
    form.addEventListener("submit", function (e) {
        if (step < 4) {
            e.preventDefault();
            advance();
        }
    });

    form.addEventListener('change', function (e) {
        if (e.target.matches('input[type="radio"], .js-document-type')) { clearErrors(); }
        if (e.target.matches('.js-document-type')) {
            var row = e.target.closest('[data-document-option]');
            var quantity = row.querySelector('.js-document-copies');
            quantity.disabled = !e.target.checked;
            row.querySelectorAll('[data-copy-step]').forEach(function (button) { button.disabled = !e.target.checked; });
        }
        refresh();
    });
    form.addEventListener('click', function (e) {
        var button = e.target.closest('[data-copy-step]');
        if (!button || button.disabled) return;
        var input = button.closest('[data-document-option]').querySelector('.js-document-copies');
        var max = parseInt(input.max, 10) || 10;
        input.value = Math.min(max, Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(button.getAttribute('data-copy-step'), 10)));
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    if (rail) {
        rail.addEventListener("change", function (e) {
            if (e.target.name === "pickup_date") {
                showPickupDate();
                centerDay();
            }
        });
        rail.addEventListener("scroll", syncRailNav, { passive: true });
        window.addEventListener("resize", syncRailNav);
        var step_ = function () {
            return Math.max(160, rail.clientWidth * 0.8);
        };
        var prevBtn = form.querySelector("[data-rail-prev]"),
            nextRail = form.querySelector("[data-rail-next]");
        if (prevBtn)
            prevBtn.addEventListener("click", function () {
                rail.scrollBy({
                    left: -step_(),
                    behavior: reduceMotion ? "auto" : "smooth",
                });
            });
        if (nextRail)
            nextRail.addEventListener("click", function () {
                rail.scrollBy({
                    left: step_(),
                    behavior: reduceMotion ? "auto" : "smooth",
                });
            });
    }
    form.addEventListener("input", function (e) {
        if (e.target.matches('[name="purpose"], #gcash-ref')) {
            e.target.classList.remove("is-invalid");
            var key = e.target.name === "purpose" ? "purpose" : "ref";
            show(errFor(key), false);
        }
        if (e.target.matches('.js-document-copies')) {
            e.target.classList.remove('is-invalid');
            show(errFor('1'), false);
        }
        refresh();
    });

    /* ---------- receipt file ---------- */
    var proof = document.getElementById("gcash-proof");
    if (proof) {
        proof.addEventListener("change", function () {
            var name = $("[data-dr-file-name]"),
                box = proof.closest(".dr-file");
            var file = proof.files && proof.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                proof.value = "";
                name.textContent =
                    "That file is over 5 MB. Choose a smaller one.";
                box.classList.remove("has-file");
                return;
            }
            name.textContent = file ? file.name : "JPG, PNG or PDF, up to 5 MB";
            box.classList.toggle("has-file", !!file);
            if (file) show(errFor("proof"), false);
        });
    }

    window.addEventListener("pageshow", function (e) {
        if (e.persisted) {
            nextBtn.classList.remove("is-busy");
            go(step, { silent: true });
        }
    });

    var next = document.getElementById("drNext");
    if (next && window.matchMedia("(min-width: 992px)").matches)
        next.open = true;

    var initial = parseInt(form.getAttribute("data-initial-step"), 10) || 1;
    reached = initial;
    go(initial, { silent: true });
})();
