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
        in_array($errorKey, ['document_type', 'copies', 'documents', 'documents.0.document_type', 'documents.0.copies'], true) => 1,
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
    $oldDocuments = old('documents', old('document_type') ? [['document_type' => old('document_type'), 'copies' => old('copies', 1)]] : []);
    $oldDocuments = is_array($oldDocuments) ? array_filter($oldDocuments, 'is_array') : [];
    $selectedTotal = collect($oldDocuments)->sum(fn ($item) => ($documentFees[$item['document_type'] ?? ''] ?? 0) * (int) ($item['copies'] ?? 1));
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
          data-current-step="{{ $initialStep }}"
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
                    <legend class="dr-sec-title">Choose documents</legend>
                    <p class="dr-sec-hint">Tap to select. Each document has its own quantity and fee. Combined requests need one pickup time.</p>
                    <div class="dr-document-progress" role="progressbar" aria-label="Request progress" aria-valuemin="1" aria-valuemax="4" aria-valuenow="1">
                        <span></span>
                    </div>
                    <div class="dr-opts dr-document-options" id="drTypeGroup" aria-label="Document types">
                        @foreach ($documentOptions as $value => $doc)
                            @php
                                $documentIcon = ['tor' => 'file-text', 'diploma' => 'award', 'certification' => 'badge-check', 'true_copy' => 'copy'][$value] ?? 'file-text';
                                $oldIndex = collect($oldDocuments)->search(fn ($item) => ($item['document_type'] ?? null) === $value);
                                $selectedDocument = $oldIndex !== false ? $oldDocuments[$oldIndex] : [];
                            @endphp
                            <div class="dr-opt dr-opt--stack" data-document-option>
                                <input id="document-type-{{ $loop->index }}" type="checkbox" name="documents[{{ $loop->index }}][document_type]" value="{{ $value }}"
                                       class="dr-opt-input dr-visually-hidden js-document-type"
                                       data-label="{{ $doc['label'] }}"
                                       {{ $oldIndex !== false || old('document_type') === $value ? 'checked' : '' }}>
                                <label class="dr-document-choice" for="document-type-{{ $loop->index }}">
                                    <span class="dr-opt-icon" aria-hidden="true"><i data-lucide="{{ $documentIcon }}"></i></span>
                                    <span class="dr-opt-text">
                                        <span class="dr-opt-name">{{ $doc['label'] }}</span>
                                        <span class="dr-opt-desc">{{ $doc['desc'] }}</span>
                                    </span>
                                    <span class="dr-opt-price">&#8369;{{ number_format($documentFees[$value], 2) }}<small>per copy</small></span>
                                    <span class="dr-opt-mark" aria-hidden="true"></span>
                                </label>
                                <div class="dr-field dr-document-quantity">
                                    <label class="dr-visually-hidden" for="document-copies-{{ $loop->index }}">Number of {{ $doc['label'] }} copies</label>
                                    <span class="dr-quantity-control">
                                        <button type="button" data-copy-step="-1" aria-label="Remove one {{ $doc['label'] }} copy" {{ $oldIndex === false && old('document_type') !== $value ? 'disabled' : '' }}>-</button>
                                    <input type="number" id="document-copies-{{ $loop->index }}"
                                           name="documents[{{ $loop->index }}][copies]"
                                           class="dr-input js-document-copies" min="1" max="10"
                                           value="{{ $selectedDocument['copies'] ?? 1 }}"
                                           aria-label="Copies of {{ $doc['label'] }}"
                                           {{ $oldIndex === false && old('document_type') !== $value ? 'disabled' : '' }}>
                                        <button type="button" data-copy-step="1" aria-label="Add one {{ $doc['label'] }} copy" {{ $oldIndex === false && old('document_type') !== $value ? 'disabled' : '' }}>+</button>
                                    </span>
                                    <span class="dr-document-subtotal">
                                        <strong data-document-subtotal>&#8369;{{ number_format($documentFees[$value] * (int) ($selectedDocument['copies'] ?? 1), 2) }}</strong>
                                        <small data-document-copy-summary>{{ (int) ($selectedDocument['copies'] ?? 1) }} × &#8369;{{ number_format($documentFees[$value], 2) }}</small>
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="dr-error" id="drTypeError" data-step-error="1" role="alert">Select at least one document and valid copy quantities.</p>
                    @error('documents') <p class="dr-error is-shown">{{ $message }}</p> @enderror
                    @error('documents.*.document_type') <p class="dr-error is-shown">{{ $message }}</p> @enderror
                    @error('documents.*.copies') <p class="dr-error is-shown">{{ $message }}</p> @enderror
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
                    <p class="dr-error" data-step-error="2" role="alert">Choose one pickup time when requesting multiple documents together.</p>
                </fieldset>

                {{-- 3. Purpose + payment --}}
                <fieldset class="dr-sec" data-step-panel="3" hidden>
                    <legend class="dr-sec-title">Purpose and payment</legend>
                    <p class="dr-sec-hint">Combined total: <strong data-total-text>{{ $selectedTotal > 0 ? '₱'.number_format($selectedTotal, 2) : 'Choose documents first' }}</strong>. The Cashier verifies the full payment.</p>

                    <div class="dr-field">
                        <label class="dr-label" for="document-purpose">Purpose <span>(optional)</span></label>
                        <textarea id="document-purpose" name="purpose" class="dr-textarea" rows="2"
                                  placeholder="Employment, board exam, scholarship…">{{ old('purpose') }}</textarea>
                        @error('purpose') <p class="dr-error is-shown">{{ $message }}</p> @enderror
                    </div>

                    <div class="dr-sec-sub">How will you pay?</div>
                    <p class="dr-no-payment" data-no-payment hidden role="status">No Payment Required. This selection is free; no receipt or payment proof is needed.</p>
                    <div data-payment-choices class="dr-opts">
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
                            <div>
                                <a class="dr-gcash-save" href="{{ route('portal.documents.gcash.qr', ['download' => 1]) }}"><i data-lucide="download" aria-hidden="true"></i> Save QR</a>
                                <div class="dr-gcash-qr">
                                    <img src="{{ route('portal.documents.gcash.qr') }}" alt="Official DTC GCash QR code" loading="lazy">
                                </div>
                            </div>
                            <div class="dr-gcash-fields">
                                @include('portal.partials.document-gcash-account', ['gcashAmount' => null])
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
                    <span class="dr-slip-total-meta">
                        <span class="dr-slip-total-label">Document fee</span>
                        <span class="dr-slip-count" data-slip-count>Choose documents</span>
                    </span>
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
    <link rel="stylesheet" href="{{ asset_v('css/document-request-flow.css') }}">
    <link rel="stylesheet" href="{{ asset_v('css/pickup-picker.css') }}">
@endsection

@section('js')
<script>
/* DTC Nexus — alumni document request wizard (4 steps). Mirrors the student booking flow. */
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
    var typeInputs = form.querySelectorAll('.js-document-type');
    var quantityInputs = form.querySelectorAll('.js-document-copies');
    var typeGroup = document.getElementById('drTypeGroup');
    var typeError = document.getElementById('drTypeError');
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
    var selectedDocuments = function () {
        return Array.prototype.map.call(typeInputs, function (input) {
            if (!input.checked) return null;
            var quantity = input.closest('[data-document-option]').querySelector('.js-document-copies');
            var copies = parseInt(quantity.value, 10) || 1;
            var unit = Number(fees[input.value]) || 0;
            return { input: input, quantity: quantity, copies: copies, unit: unit, subtotal: unit * copies };
        }).filter(Boolean);
    };

    /* ---------- summary (slip + review + totals) ---------- */
    var payableTotal = null;
    var refresh = function () {
        var documents = selectedDocuments();
        var slot = checked('appointment_slot_id');
        var pay  = checked('payment_method');
        var purpose = $('[name="purpose"]');
        var total = documents.length ? documents.reduce(function (sum, doc) { return sum + doc.subtotal; }, 0) : null;
        form.querySelectorAll('[data-document-copy-amount]').forEach(function (button) {
            button.disabled = total === null;
            button.setAttribute('data-copy-value', total === null ? '' : Number(total).toFixed(2));
        });
        setText('[data-document-amount]', total === null ? 'Choose documents first' : peso(total));
        payableTotal = total;
        form.querySelectorAll('[data-payment-choices]').forEach(function (el) { el.hidden = payableTotal === 0; });
        form.querySelectorAll('[data-no-payment]').forEach(function (el) { el.hidden = payableTotal !== 0; });
        if (payableTotal === 0) { setText('[data-slip="pay"]', 'No Payment Required'); setText('[data-review="pay"]', 'No Payment Required'); }
        var selectedCopies = documents.reduce(function (sum, doc) { return sum + doc.copies; }, 0);
        var countSummary = $('[data-slip-count]');
        if (countSummary) {
            countSummary.textContent = documents.length
                ? documents.length + (documents.length === 1 ? ' document' : ' documents') + ' · ' + selectedCopies + (selectedCopies === 1 ? ' copy' : ' copies')
                : 'Choose documents';
        }
        typeInputs.forEach(function (input) {
            var row = input.closest('[data-document-option]');
            var quantity = row.querySelector('.js-document-copies');
            quantity.disabled = !input.checked;
            row.querySelectorAll('[data-copy-step]').forEach(function (button) { button.disabled = !input.checked; });
            var copies = parseInt(quantity.value, 10) || 1;
            var unitFee = Number(fees[input.value]) || 0;
            var subtotal = row.querySelector('[data-document-subtotal]');
            var copySummary = row.querySelector('[data-document-copy-summary]');
            if (subtotal) subtotal.textContent = peso(unitFee * copies);
            if (copySummary) copySummary.textContent = copies + ' × ' + peso(unitFee);
        });
        var docText = documents.map(function (doc) { return doc.input.getAttribute('data-label') + ' × ' + doc.copies + ' — ' + peso(doc.subtotal); }).join(', ');
        var qtyText = documents.map(function (doc) {
            return doc.input.getAttribute('data-label') + ': ' + doc.copies + (doc.copies === 1 ? ' copy' : ' copies') + ' at ' + peso(doc.unit);
        }).join('; ');
        var slotText = slot && slot.value ? slot.getAttribute('data-date') + ', ' + slot.getAttribute('data-time') : '';

        setText('[data-slip="doc"]', docText || 'Not chosen yet', !docText);
        setText('[data-slip="qty"]', qtyText || 'Choose documents');
        setText('[data-slip="pickup"]', slotText || 'Book later');
        setText('[data-slip="pay"]', pay ? pay.getAttribute('data-label') : 'Cashier’s Office');

        var slipTotal = $('[data-slip-total]');
        slipTotal.textContent = total === null ? peso(0) : peso(total);
        slipTotal.classList.toggle('is-empty', total === null);

        setText('[data-review="doc"]', docText || '—');
        setText('[data-review="copies"]', qtyText || '—');
        setText('[data-review="pickup"]', slotText || 'Book later');
        setText('[data-review="pay"]', pay ? (pay.value === 'gcash_qr' ? 'GCash QR' : 'Cashier’s Office') : '—');
        setText('[data-review="purpose"]', purpose && purpose.value.trim() ? purpose.value.trim() : '—');
        var rt = $('[data-review="total"]');
        if (rt) rt.innerHTML = '<strong>' + (total === null ? '—' : peso(total)) + '</strong>';

        var totalText = total === null ? 'Choose documents first' : peso(total);
        var t1 = $('[data-total-text]'); if (t1) t1.textContent = totalText;
        var t2 = $('[data-gcash-total]'); if (t2) t2.textContent = total === null ? 'the total shown' : peso(total);

        if (picked) {
            picked.textContent = slotText || 'No time chosen.';
            picked.classList.toggle('has-value', !!slotText);
        }

        var gcash = document.getElementById('gcashPaymentDetails');
        if (gcash) gcash.classList.toggle('is-open', total > 0 && !!(pay && pay.value === 'gcash_qr'));
        if (payableTotal === 0) { setText('[data-slip="pay"]', 'No Payment Required'); setText('[data-review="pay"]', 'No Payment Required'); }
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
        if (n === 1) {
            var documents = selectedDocuments();
            if (!documents.length) return fail('1');
            for (var i = 0; i < documents.length; i++) {
                if (!documents[i].quantity.value || documents[i].copies < 1 || documents[i].copies > maxCopies) {
                    return fail('1', documents[i].quantity);
                }
            }
        }
        if (n === 2 && selectedDocuments().length > 1) {
            var pickup = checked('appointment_slot_id');
            if (!pickup || !pickup.value) return fail('2');
        }
        if (n === 3) {
            if (payableTotal === 0) return true;
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
        form.setAttribute('data-current-step', String(n));
        var progress = form.querySelector('.dr-document-progress');
        if (progress) progress.setAttribute('aria-valuenow', String(n));
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
        refresh();
        if (nextBtn.classList.contains('is-busy')) return;
        if (step < 4) { if (validate(step)) go(step + 1); return; }
        for (var i = 1; i <= 3; i++) {
            if (!validate(i)) { go(i); validate(i); return; }
        }
        quantityInputs.forEach(function (input) {
            if (!input.disabled) input.value = Math.min(maxCopies, Math.max(1, parseInt(input.value, 10) || 1));
        });
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
        if (e.target.matches('.js-document-type')) {
            var row = e.target.closest('[data-document-option]');
            row.querySelector('.js-document-copies').disabled = !e.target.checked;
            row.querySelectorAll('[data-copy-step]').forEach(function (button) { button.disabled = !e.target.checked; });
            typeGroup.classList.remove('is-invalid');
            typeError.classList.remove('is-shown');
        }
        refresh();
    });
    form.addEventListener('click', function (e) {
        var button = e.target.closest('[data-copy-step]');
        if (!button || button.disabled) return;
        var input = button.closest('[data-document-option]').querySelector('.js-document-copies');
        input.value = Math.min(maxCopies, Math.max(1, (parseInt(input.value, 10) || 1) + parseInt(button.getAttribute('data-copy-step'), 10)));
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    form.addEventListener('input', function (e) {
        if (e.target.id === 'documentPaymentReference') {
            e.target.classList.remove('is-invalid');
            show(errFor('ref'), false);
        }
        if (e.target.matches('.js-document-copies')) {
            e.target.classList.remove('is-invalid');
            typeError.classList.remove('is-shown');
        }
        refresh();
    });

    quantityInputs.forEach(function (input) {
        input.addEventListener('blur', function () {
            if (!input.disabled && input.value) input.value = Math.min(maxCopies, Math.max(1, parseInt(input.value, 10) || 1));
            refresh();
        });
    });

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
