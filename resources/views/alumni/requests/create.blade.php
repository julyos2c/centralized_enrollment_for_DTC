@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Request a Document')

@section('content_header')
    <div class="dr dr-head">
        <div>
            <a href="{{ route('portal.documents.index') }}" class="dr-back">
                <i data-lucide="chevron-left" aria-hidden="true"></i> My requests
            </a>
            <h1>Request a document</h1>
            <p>Choose a document, pickup time and payment method.</p>
        </div>
    </div>
@endsection

@php
    $documentOptions = [
        'tor'           => ['label' => 'Transcript of Records', 'desc' => 'Official academic transcript'],
        'diploma'       => ['label' => 'Diploma',               'desc' => 'Replacement or authentication'],
        'certification' => ['label' => 'Certification',         'desc' => 'Certificate of enrollment or graduation'],
        'true_copy'     => ['label' => 'True Copy of Records',  'desc' => 'Certified true copy of your records'],
    ];

    $oldPayment  = old('payment_method', 'walk_in');
    $gcashOk     = ! empty($gcashQrReady);
    $gcashChosen = $gcashOk && $oldPayment === 'gcash_qr';

    $errorKey = $errors->any() ? $errors->keys()[0] : null;
    $initialStep = match (true) {
        $errorKey === null => 1,
        in_array($errorKey, ['document_type', 'copies'], true) => 1,
        in_array($errorKey, ['appointment_slot_id', 'pickup_date'], true) => 2,
        default => 3,
    };

    $slotGroups = $slots->groupBy(fn ($s) => \Carbon\Carbon::parse($s->date)->format('Y-m-d'));
    $slotLeft   = $slots->mapWithKeys(fn ($s) => [$s->id => max(0, (int) $s->availableSlots())]);
    $openByDay  = $slotGroups->map(fn ($g) => $g->filter(fn ($s) => $slotLeft[$s->id] > 0)->count());
    $oldSlot    = $slots->firstWhere('id', old('appointment_slot_id'));
    $pickedDay  = old('pickup_date', $oldSlot ? \Carbon\Carbon::parse($oldSlot->date)->format('Y-m-d') : '');
    // Only ever trust a date that is actually in the list the server built; '' means "book later".
    $selectedPickupDate = ($pickedDay !== '' && $slotGroups->has($pickedDay)) ? $pickedDay : '';
    $todayKey  = now()->format('Y-m-d');
    $dayParts  = ['Morning' => [0, 12], 'Afternoon' => [12, 17], 'Evening' => [17, 24]];
@endphp

@section('content')
<div class="dr dr-create">
    @if ($errors->any())
        <div class="dr-alert dr-alert--error" role="alert">
            <i data-lucide="circle-alert" aria-hidden="true"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('portal.documents.store') }}" enctype="multipart/form-data"
          id="drForm" class="dr-grid" novalidate
          data-initial-step="{{ $initialStep }}"
          data-fees='@json($documentFees)'
          data-max-copies="10">
        @csrf
        {{-- Sent as empty when no time is picked; a checked time radio below overrides it. --}}
        <input type="hidden" name="appointment_slot_id" value="">

        <div class="dr-form">
            <ol class="dr-steps" aria-label="Request steps">
                @foreach (['Document', 'Pickup time', 'Payment', 'Review'] as $i => $name)
                    <li data-step-item="{{ $i + 1 }}">
                        <button type="button" data-step-btn="{{ $i + 1 }}" disabled>
                            <span class="dr-steps-dot" aria-hidden="true">{{ $i + 1 }}</span>
                            <span class="dr-steps-name">{{ $name }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>

            <div class="dr-sheet">

                {{-- 1. Document + copies --}}
                <fieldset class="dr-sec" data-step-panel="1">
                    <legend class="dr-sec-title">Choose a document</legend>
                    <div class="dr-opts" role="radiogroup" aria-label="Document type">
                        @foreach ($documentOptions as $value => $doc)
                            <label class="dr-opt">
                                <input type="radio" name="document_type" value="{{ $value }}"
                                       class="dr-opt-input dr-visually-hidden"
                                       data-label="{{ $doc['label'] }}"
                                       {{ old('document_type') === $value ? 'checked' : '' }}>
                                <span class="dr-opt-mark" aria-hidden="true"></span>
                                <span class="dr-opt-text">
                                    <span class="dr-opt-name">{{ $doc['label'] }}</span>
                                    <span class="dr-opt-desc">{{ $doc['desc'] }}</span>
                                </span>
                                <span class="dr-opt-price">&#8369;{{ number_format($documentFees[$value], 2) }}<small>per copy</small></span>
                            </label>
                        @endforeach
                    </div>
                    <p class="dr-error" data-step-error="1" role="alert">Choose a document to continue.</p>

                    <div class="dr-field" style="margin-top:20px;">
                        <label class="dr-label" for="copies">Copies</label>
                        <div class="dr-stepper">
                            <button type="button" id="drCopiesMinus" aria-label="Fewer copies">&minus;</button>
                            <input type="number" name="copies" id="copies" value="{{ old('copies', 1) }}"
                                   min="1" max="10" inputmode="numeric" aria-describedby="copies-help">
                            <button type="button" id="drCopiesPlus" aria-label="More copies">+</button>
                        </div>
                        <p class="dr-help" id="copies-help">Maximum of 10.</p>
                        @error('copies') <p class="dr-error is-shown">{{ $message }}</p> @enderror
                    </div>
                </fieldset>

                {{-- 2. Pickup time (optional) --}}
                <fieldset class="dr-sec" data-step-panel="2" hidden>
                    <legend class="dr-sec-title">Pickup schedule</legend>
                    <p class="dr-sec-hint">Optional. You can also book once your document is ready.</p>
                    @if ($slotGroups->isNotEmpty())
                        <div class="dp" data-picker>
                            <div class="dp-dates">
                                <div class="dp-head">
                                    <span class="dp-label" id="pickup-date-label">Choose a day</span>
                                    <div class="dp-nav" data-rail-nav hidden>
                                        <button type="button" data-rail-prev aria-label="Earlier days"><i data-lucide="chevron-left" aria-hidden="true"></i></button>
                                        <button type="button" data-rail-next aria-label="Later days"><i data-lucide="chevron-right" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                                <div class="dp-rail" role="radiogroup" aria-labelledby="pickup-date-label" aria-controls="pickup-time-options" data-date-rail>
                                    <label class="dp-day">
                                        <input type="radio" name="pickup_date" value="" class="dp-input dr-visually-hidden"
                                               aria-label="Book a pickup time later"
                                               {{ $selectedPickupDate === '' ? 'checked' : '' }}>
                                        <span class="dp-day-wd">Skip</span>
                                        <span class="dp-day-num">&ndash;</span>
                                        <span class="dp-day-mo">for now</span>
                                        <span class="dp-day-count">Book later</span>
                                    </label>
                                    @foreach ($slotGroups as $date => $dateSlots)
                                        @php $d = \Carbon\Carbon::parse($date); $openN = $openByDay[$date]; @endphp
                                        <label class="dp-day {{ $openN === 0 ? 'is-full' : '' }}">
                                            <input type="radio" name="pickup_date" value="{{ $date }}"
                                                   class="dp-input dr-visually-hidden"
                                                   aria-label="{{ $d->format('l, F j') }}, {{ $openN === 0 ? 'no open times' : $openN.' open '.($openN === 1 ? 'time' : 'times') }}"
                                                   {{ $selectedPickupDate === $date ? 'checked' : '' }} {{ $openN === 0 ? 'disabled' : '' }}>
                                            <span class="dp-day-wd">{{ $date === $todayKey ? 'Today' : $d->format('D') }}</span>
                                            <span class="dp-day-num">{{ $d->format('j') }}</span>
                                            <span class="dp-day-mo">{{ $d->format('M') }}</span>
                                            <span class="dp-day-count">{{ $openN === 0 ? 'Full' : $openN.' open' }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div id="pickup-time-options" class="dp-times" aria-live="polite">
                                @foreach ($slotGroups as $day => $daySlots)
                                    <div class="dp-panel" data-slot-date-panel="{{ $day }}" {{ $selectedPickupDate !== $day ? 'hidden' : '' }}>
                                        <h3 class="dp-panel-title">
                                            {{ \Carbon\Carbon::parse($day)->format('l, F j') }}
                                            <span class="dp-count">{{ $openByDay[$day] }} open {{ $openByDay[$day] === 1 ? 'time' : 'times' }}</span>
                                        </h3>
                                        @foreach ($dayParts as $period => [$from, $to])
                                            @php
                                                $list = $daySlots->filter(function ($s) use ($from, $to) {
                                                    $h = (int) \Carbon\Carbon::parse($s->start_time)->format('G');
                                                    return $h >= $from && $h < $to;
                                                });
                                            @endphp
                                            @if ($list->isNotEmpty())
                                                <div class="dp-period" role="group" aria-label="{{ $period }}">
                                                    <h4 class="dp-period-title">{{ $period }}</h4>
                                                    <div class="dp-grid">
                                                        @foreach ($list as $slot)
                                                            @php
                                                                $left  = $slotLeft[$slot->id];
                                                                $start = \Carbon\Carbon::parse($slot->start_time)->format('g:i A');
                                                                $end   = \Carbon\Carbon::parse($slot->end_time)->format('g:i A');
                                                            @endphp
                                                            <label class="dp-time {{ $left === 0 ? 'is-full' : '' }}">
                                                                <input type="radio" name="appointment_slot_id" value="{{ $slot->id }}"
                                                                       class="dp-input dr-visually-hidden"
                                                                       data-date="{{ \Carbon\Carbon::parse($slot->date)->format('D, M j, Y') }}"
                                                                       data-time="{{ $start }} to {{ $end }}"
                                                                       {{ old('appointment_slot_id') == $slot->id ? 'checked' : '' }} {{ $left === 0 ? 'disabled' : '' }}>
                                                                <span class="dp-time-start">{{ $start }}</span>
                                                                <span class="dp-time-end">to {{ $end }}</span>
                                                                <span class="dp-time-flag {{ $left === 0 ? 'is-full' : ($left <= 2 ? 'is-low' : '') }}">
                                                                    {{ $left === 0 ? 'Full' : ($left === 1 ? '1 spot left' : $left.' spots left') }}
                                                                </span>
                                                                <span class="dp-time-check" aria-hidden="true"></span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>

                            <p class="dp-picked" data-picked aria-live="polite">No time chosen.</p>
                        </div>
                        <p class="dr-help" style="margin-top:10px;">Slots may fill up before you submit.</p>
                    @else
                        <div class="dr-empty dr-empty--inline">
                            <h2>No pickup times open</h2>
                            <p>Submit now and book later from My requests.</p>
                        </div>
                    @endif
                    @error('appointment_slot_id') <p class="dr-error is-shown">{{ $message }}</p> @enderror
                </fieldset>

                {{-- 3. Purpose + payment --}}
                <fieldset class="dr-sec" data-step-panel="3" hidden>
                    <legend class="dr-sec-title">Purpose and payment</legend>
                    <p class="dr-sec-hint">Total: <strong data-total-text>Choose a document first</strong>. The Cashier verifies every payment.</p>

                    <div class="dr-field">
                        <label class="dr-label" for="document-purpose">Purpose <span>(optional)</span></label>
                        <textarea id="document-purpose" name="purpose" class="dr-textarea" rows="2"
                                  placeholder="Employment, board exam, scholarship…">{{ old('purpose') }}</textarea>
                        @error('purpose') <p class="dr-error is-shown">{{ $message }}</p> @enderror
                    </div>

                    <div class="dr-sec-sub">How will you pay?</div>
                    <div class="dr-opts">
                        <label class="dr-opt dr-opt--stack">
                            <input type="radio" name="payment_method" id="paymentWalkIn" value="walk_in"
                                   class="dr-opt-input dr-visually-hidden" data-label="Cashier’s Office"
                                   {{ ! $gcashChosen ? 'checked' : '' }}>
                            <span class="dr-opt-mark" aria-hidden="true"></span>
                            <span class="dr-opt-text">
                                <span class="dr-opt-name">Pay at the Cashier’s Office</span>
                                <span class="dr-opt-desc">Unpaid until the Cashier records it.</span>
                            </span>
                        </label>

                        @if ($gcashOk)
                            <label class="dr-opt dr-opt--stack">
                                <input type="radio" name="payment_method" id="paymentGcash" value="gcash_qr"
                                       class="dr-opt-input dr-visually-hidden" data-label="GCash QR"
                                       aria-controls="gcashPaymentDetails"
                                       {{ $gcashChosen ? 'checked' : '' }}>
                                <span class="dr-opt-mark" aria-hidden="true"></span>
                                <span class="dr-opt-text">
                                    <span class="dr-opt-name">Pay with GCash QR</span>
                                    <span class="dr-opt-desc">Pay the exact total, then upload your receipt.</span>
                                </span>
                            </label>
                        @endif
                    </div>

                    @if ($gcashOk)
                        <div id="gcashPaymentDetails" class="dr-gcash {{ $gcashChosen ? 'is-open' : '' }}">
                            <div class="dr-gcash-qr">
                                <img src="{{ route('portal.documents.gcash.qr') }}" alt="Official DTC GCash QR code" loading="lazy">
                            </div>
                            <div class="dr-gcash-fields">
                                <p class="dr-gcash-note">Send exactly <strong data-gcash-total>the total shown</strong>. Proof is verified by the Cashier.</p>
                                <div class="dr-field">
                                    <label class="dr-label" for="documentPaymentReference">GCash reference number</label>
                                    <input id="documentPaymentReference" type="text" name="reference_number" class="dr-input"
                                           maxlength="100" value="{{ old('reference_number') }}" autocomplete="off" inputmode="numeric">
                                    <p class="dr-error" data-step-error="ref" role="alert">Enter the reference number from your GCash receipt.</p>
                                    @error('reference_number') <p class="dr-error is-shown">{{ $message }}</p> @enderror
                                </div>
                                <div class="dr-field">
                                    <span class="dr-label" id="proofLabel">Receipt screenshot</span>
                                    <label class="dr-file" for="documentPaymentProof">
                                        <input id="documentPaymentProof" type="file" name="proof_of_payment"
                                               class="dr-visually-hidden" accept="image/jpeg,image/png,application/pdf"
                                               aria-labelledby="proofLabel">
                                        <span class="dr-file-btn">Choose file</span>
                                        <span class="dr-file-name" data-dr-file-name>JPG, PNG or PDF, up to 5 MB</span>
                                    </label>
                                    <p class="dr-error" data-step-error="proof" role="alert">Upload a screenshot or PDF of your receipt.</p>
                                    @error('proof_of_payment') <p class="dr-error is-shown">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="dr-help" style="margin-top:12px;">GCash is unavailable. Pay at the Cashier’s Office.</p>
                    @endif
                </fieldset>

                {{-- 4. Review --}}
                <fieldset class="dr-sec" data-step-panel="4" hidden>
                    <legend class="dr-sec-title">Review and submit</legend>
                    <p class="dr-sec-hint">Your total is locked once submitted.</p>
                    <dl class="dr-review">
                        <div><dt>Document</dt><dd data-review="doc">—</dd><button type="button" class="dr-change" data-goto="1">Change<span class="dr-visually-hidden"> document</span></button></div>
                        <div><dt>Copies</dt><dd data-review="copies">—</dd><button type="button" class="dr-change" data-goto="1">Change<span class="dr-visually-hidden"> copies</span></button></div>
                        <div><dt>Pickup</dt><dd data-review="pickup">Book later</dd><button type="button" class="dr-change" data-goto="2">Change<span class="dr-visually-hidden"> pickup time</span></button></div>
                        <div><dt>Payment</dt><dd data-review="pay">—</dd><button type="button" class="dr-change" data-goto="3">Change<span class="dr-visually-hidden"> payment</span></button></div>
                        <div><dt>Purpose</dt><dd data-review="purpose">—</dd><button type="button" class="dr-change" data-goto="3">Change<span class="dr-visually-hidden"> purpose</span></button></div>
                        <div><dt>Total</dt><dd data-review="total"><strong>—</strong></dd></div>
                    </dl>
                </fieldset>
            </div>
        </div>

        <aside class="dr-aside" aria-label="Request summary">
            <div class="dr-slip dr-slip--wizard">
                <div class="dr-slip-body">
                    <h2 class="dr-slip-title">Your request</h2>
                    <dl class="dr-slip-rows">
                        <div class="dr-slip-row"><dt>Document</dt><dd data-slip="doc" class="is-empty">Not chosen yet</dd></div>
                        <div class="dr-slip-row"><dt>Copies</dt><dd data-slip="qty">1 copy</dd></div>
                        <div class="dr-slip-row"><dt>Pickup</dt><dd data-slip="pickup">Book later</dd></div>
                        <div class="dr-slip-row"><dt>Payment</dt><dd data-slip="pay">Cashier’s Office</dd></div>
                    </dl>
                </div>
                <div class="dr-slip-tear" aria-hidden="true"></div>
                <div class="dr-slip-total" aria-live="polite">
                    <span class="dr-slip-total-label">Total</span>
                    <span class="dr-slip-total-amount is-empty" data-slip-total>&#8369;0.00</span>
                </div>
                <div class="dr-slip-foot">
                    <button type="button" class="dr-slip-back" id="drBack" hidden>Back</button>
                    <button type="button" class="dr-btn dr-btn--gold" id="drNextBtn">Next: Pickup time</button>
                    <p class="dr-slip-note" id="drNote">Step 1 of 4</p>
                    <a href="{{ route('portal.documents.index') }}" class="dr-slip-cancel">Cancel</a>
                </div>
            </div>

            <details class="dr-next" id="drNext">
                <summary>What happens next</summary>
                <ul class="dr-next-list">
                    <li><strong>The Registrar prepares it</strong>You’ll be notified of each update.</li>
                    <li><strong>The Cashier confirms payment</strong>A GCash screenshot isn’t an official receipt.</li>
                    <li><strong>You pick it up</strong>Bring your receipt and a valid ID.</li>
                </ul>
            </details>
        </aside>
    </form>
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/document-request-flow.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pickup-picker.css') }}">
@endsection

@section('js')
<script>
/* DTC EMS — alumni document request wizard (4 steps). Mirrors the student booking flow. */
(function () {
    'use strict';
    var form = document.getElementById('drForm');
    if (!form) return;

    var fees = {};
    try { fees = JSON.parse(form.getAttribute('data-fees') || '{}'); } catch (e) { fees = {}; }
    var maxCopies = parseInt(form.getAttribute('data-max-copies'), 10) || 10;

    var step = 1, reached = 1;
    var nextBtn = document.getElementById('drNextBtn');
    var backBtn = document.getElementById('drBack');
    var note    = document.getElementById('drNote');
    var stepsEl = form.querySelector('.dr-steps');
    var panels  = form.querySelectorAll('[data-step-panel]');
    var rail    = form.querySelector('[data-date-rail]');
    var picked  = form.querySelector('[data-picked]');
    var copies  = document.getElementById('copies');
    var minus   = document.getElementById('drCopiesMinus');
    var plus    = document.getElementById('drCopiesPlus');
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var labels = { 1: 'Next: Pickup time', 2: 'Next: Payment', 3: 'Review request', 4: 'Submit request' };

    var $ = function (sel, root) { return (root || form).querySelector(sel); };
    var checked = function (name) { return $('input[name="' + name + '"]:checked'); };
    var show = function (el, on) { if (el) el.classList.toggle('is-shown', !!on); };
    var errFor = function (key) { return $('[data-step-error="' + key + '"]'); };
    var peso = function (n) {
        return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    var setText = function (sel, text, empty) {
        var el = $(sel); if (!el) return;
        el.textContent = text;
        el.classList.toggle('is-empty', !!empty);
    };
    var clampCopies = function () {
        var v = parseInt(copies.value, 10);
        if (isNaN(v)) v = 1;
        return Math.min(maxCopies, Math.max(1, v));
    };

    /* ---------- summary (slip + review + totals) ---------- */
    var refresh = function () {
        var qty  = clampCopies();
        var doc  = checked('document_type');
        var slot = checked('appointment_slot_id');
        var pay  = checked('payment_method');
        var purpose = $('[name="purpose"]');
        var unit  = doc ? Number(fees[doc.value]) || 0 : null;
        var total = unit === null ? null : unit * qty;
        var qtyText = qty + (qty === 1 ? ' copy' : ' copies');
        var slotText = slot && slot.value ? slot.getAttribute('data-date') + ', ' + slot.getAttribute('data-time') : '';

        minus.disabled = qty <= 1;
        plus.disabled = qty >= maxCopies;

        setText('[data-slip="doc"]', doc ? doc.getAttribute('data-label') : 'Not chosen yet', !doc);
        setText('[data-slip="qty"]', qtyText + (unit === null ? '' : ' at ' + peso(unit)));
        setText('[data-slip="pickup"]', slotText || 'Book later');
        setText('[data-slip="pay"]', pay ? pay.getAttribute('data-label') : 'Cashier’s Office');

        var slipTotal = $('[data-slip-total]');
        slipTotal.textContent = total === null ? peso(0) : peso(total);
        slipTotal.classList.toggle('is-empty', total === null);

        setText('[data-review="doc"]', doc ? doc.getAttribute('data-label') : '—');
        setText('[data-review="copies"]', qtyText + (unit === null ? '' : ' at ' + peso(unit) + ' each'));
        setText('[data-review="pickup"]', slotText || 'Book later');
        setText('[data-review="pay"]', pay ? (pay.value === 'gcash_qr' ? 'GCash QR' : 'Cashier’s Office') : '—');
        setText('[data-review="purpose"]', purpose && purpose.value.trim() ? purpose.value.trim() : '—');
        var rt = $('[data-review="total"]');
        if (rt) rt.innerHTML = '<strong>' + (total === null ? '—' : peso(total)) + '</strong>';

        var totalText = total === null ? 'Choose a document first' : peso(total);
        var t1 = $('[data-total-text]'); if (t1) t1.textContent = totalText;
        var t2 = $('[data-gcash-total]'); if (t2) t2.textContent = total === null ? 'the total shown' : peso(total);

        if (picked) {
            picked.textContent = slotText || 'No time chosen.';
            picked.classList.toggle('has-value', !!slotText);
        }

        var gcash = document.getElementById('gcashPaymentDetails');
        if (gcash) gcash.classList.toggle('is-open', !!(pay && pay.value === 'gcash_qr'));
    };

    /* ---------- pickup day strip ---------- */
    var selectedDay = function () {
        var r = rail && rail.querySelector('input[name="pickup_date"]:checked');
        return r ? r.value : '';
    };
    var centerDay = function (instant) {
        var r = rail && rail.querySelector('input[name="pickup_date"]:checked');
        var chip = r && r.closest('.dp-day');
        if (!chip) return;
        var left = chip.offsetLeft - (rail.clientWidth - chip.offsetWidth) / 2;
        rail.scrollTo({ left: Math.max(0, left), behavior: instant || reduceMotion ? 'auto' : 'smooth' });
    };
    var syncRailNav = function () {
        var nav = form.querySelector('[data-rail-nav]');
        if (!rail || !nav) return;
        var max = rail.scrollWidth - rail.clientWidth;
        nav.hidden = max <= 2;
        var prev = nav.querySelector('[data-rail-prev]'), next = nav.querySelector('[data-rail-next]');
        if (prev) prev.disabled = rail.scrollLeft <= 2;
        if (next) next.disabled = rail.scrollLeft >= max - 2;
    };
    var showPickupDate = function () {
        var day = selectedDay();
        form.querySelectorAll('[data-slot-date-panel]').forEach(function (panel) {
            var active = day !== '' && panel.getAttribute('data-slot-date-panel') === day;
            panel.hidden = !active;
            if (!active) {
                var c = panel.querySelector('input[name="appointment_slot_id"]:checked');
                if (c) c.checked = false;
            }
        });
        refresh();
    };

    /* ---------- validation ---------- */
    var clearErrors = function () {
        form.querySelectorAll('[data-step-error]').forEach(function (e) { e.classList.remove('is-shown'); });
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
    };
    var fail = function (key, focusEl) {
        show(errFor(key), true);
        if (focusEl) {
            focusEl.classList.add('is-invalid');
            focusEl.focus({ preventScroll: true });
            focusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            var e = errFor(key);
            if (e) e.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    };
    var validate = function (n) {
        clearErrors();
        if (n === 1 && !checked('document_type')) return fail('1');
        if (n === 3) {
            var pay = checked('payment_method');
            if (pay && pay.value === 'gcash_qr') {
                var ref = $('#documentPaymentReference'), proof = $('#documentPaymentProof');
                if (ref && !ref.value.trim()) return fail('ref', ref);
                if (proof && !(proof.files && proof.files.length)) return fail('proof');
            }
        }
        return true;
    };

    /* ---------- navigation ---------- */
    var go = function (n, opts) {
        step = n;
        reached = Math.max(reached, n);
        panels.forEach(function (p) { p.hidden = String(p.getAttribute('data-step-panel')) !== String(n); });

        form.querySelectorAll('[data-step-item]').forEach(function (li) {
            var i = parseInt(li.getAttribute('data-step-item'), 10);
            li.classList.toggle('is-done', i < n);
            li.classList.toggle('is-current', i === n);
            var btn = li.querySelector('button');
            btn.disabled = i >= n || i > reached;
            if (i === n) btn.setAttribute('aria-current', 'step'); else btn.removeAttribute('aria-current');
        });

        nextBtn.textContent = labels[n];
        backBtn.hidden = n === 1;
        note.textContent = 'Step ' + n + ' of 4';
        refresh();
        if (n === 2) { showPickupDate(); syncRailNav(); centerDay(true); }

        if (!opts || !opts.silent) {
            var legend = $('[data-step-panel="' + n + '"] legend');
            if (legend) { legend.setAttribute('tabindex', '-1'); legend.focus({ preventScroll: true }); }
            stepsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    var advance = function () {
        if (nextBtn.classList.contains('is-busy')) return;
        if (step < 4) { if (validate(step)) go(step + 1); return; }
        for (var i = 1; i <= 3; i++) {
            if (!validate(i)) { go(i); validate(i); return; }
        }
        copies.value = clampCopies();
        nextBtn.classList.add('is-busy');
        nextBtn.textContent = 'Submitting…';
        form.submit();
    };

    nextBtn.addEventListener('click', advance);
    backBtn.addEventListener('click', function () { if (step > 1) go(step - 1); });
    form.querySelectorAll('[data-step-btn]').forEach(function (b) {
        b.addEventListener('click', function () { go(parseInt(b.getAttribute('data-step-btn'), 10)); });
    });
    form.querySelectorAll('[data-goto]').forEach(function (b) {
        b.addEventListener('click', function () { go(parseInt(b.getAttribute('data-goto'), 10)); });
    });

    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'file') {
            e.preventDefault();
            advance();
        }
    });
    form.addEventListener('submit', function (e) { if (step < 4) { e.preventDefault(); advance(); } });

    form.addEventListener('change', function (e) {
        if (e.target.matches('input[type="radio"]')) clearErrors();
        refresh();
    });
    form.addEventListener('input', function (e) {
        if (e.target.id === 'documentPaymentReference') {
            e.target.classList.remove('is-invalid');
            show(errFor('ref'), false);
        }
        refresh();
    });

    /* copies stepper */
    copies.addEventListener('blur', function () { copies.value = clampCopies(); refresh(); });
    minus.addEventListener('click', function () { copies.value = Math.max(1, clampCopies() - 1); refresh(); });
    plus.addEventListener('click', function () { copies.value = Math.min(maxCopies, clampCopies() + 1); refresh(); });

    /* day rail */
    if (rail) {
        rail.addEventListener('change', function (e) {
            if (e.target.name === 'pickup_date') { showPickupDate(); centerDay(); }
        });
        rail.addEventListener('scroll', syncRailNav, { passive: true });
        window.addEventListener('resize', syncRailNav);
        var stepPx = function () { return Math.max(160, rail.clientWidth * 0.8); };
        var pb = form.querySelector('[data-rail-prev]'), nb = form.querySelector('[data-rail-next]');
        if (pb) pb.addEventListener('click', function () { rail.scrollBy({ left: -stepPx(), behavior: reduceMotion ? 'auto' : 'smooth' }); });
        if (nb) nb.addEventListener('click', function () { rail.scrollBy({ left: stepPx(), behavior: reduceMotion ? 'auto' : 'smooth' }); });
    }

    /* receipt file */
    var proofInput = document.getElementById('documentPaymentProof');
    if (proofInput) {
        proofInput.addEventListener('change', function () {
            var name = $('[data-dr-file-name]'), box = proofInput.closest('.dr-file');
            var file = proofInput.files && proofInput.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                proofInput.value = '';
                name.textContent = 'That file is over 5 MB. Choose a smaller one.';
                box.classList.remove('has-file');
                return;
            }
            name.textContent = file ? file.name : 'JPG, PNG or PDF, up to 5 MB';
            box.classList.toggle('has-file', !!file);
            if (file) show(errFor('proof'), false);
        });
    }

    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { nextBtn.classList.remove('is-busy'); go(step, { silent: true }); }
    });

    var nextInfo = document.getElementById('drNext');
    if (nextInfo && window.matchMedia('(min-width: 992px)').matches) nextInfo.open = true;

    var initial = parseInt(form.getAttribute('data-initial-step'), 10) || 1;
    reached = initial;
    go(initial, { silent: true });
})();
</script>
@endsection
