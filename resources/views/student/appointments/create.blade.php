@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Request a Document')

@section('content_header')
    <div class="dr dr-head">
        <div>
            <a href="{{ route('portal.appointments.index') }}" class="dr-back">
                <i data-lucide="chevron-left" aria-hidden="true"></i> My appointments
            </a>
            <h1>Request a document</h1>
            <p>Choose the document, pick a time to collect it, and arrange payment. The Registrar confirms your booking.</p>
        </div>
    </div>
@endsection

@php
    $documentOptions = [
        'transcript'    => ['label' => 'Transcript of Records', 'desc' => 'Official academic transcript'],
        'diploma'       => ['label' => 'Diploma',               'desc' => 'Replacement or authentication'],
        'certification' => ['label' => 'Certification',         'desc' => 'Certificate of enrollment or graduation'],
        'tor'           => ['label' => 'True Copy of Records',  'desc' => 'Certified true copy of your records'],
    ];
    $initialStep = $errors->any() ? (old('payment_method') ? 3 : (old('appointment_slot_id') ? 2 : 1)) : 1;
    $oldPay = old('payment_method', 'walk_in');
    $gcashOk = ! empty($gcashQrReady);
    $gcashChosen = $gcashOk && $oldPay === 'gcash';
    $slotGroups = $slots->groupBy(fn ($s) => \Carbon\Carbon::parse($s->date)->format('Y-m-d'));
    $slotLeft = $slots->mapWithKeys(fn ($s) => [$s->id => max(0, (int) $s->availableSlots())]);
    $openByDay = $slotGroups->map(fn ($g) => $g->filter(fn ($s) => $slotLeft[$s->id] > 0)->count());
    $firstOpenDay = $openByDay->filter(fn ($n) => $n > 0)->keys()->first() ?? ($slotGroups->keys()->first() ?? '');
    $oldSlot = $slots->firstWhere('id', old('appointment_slot_id'));
    // Only ever trust a date that is actually in the list the server built.
    $pickedDay = old('pickup_date', $oldSlot ? \Carbon\Carbon::parse($oldSlot->date)->format('Y-m-d') : $firstOpenDay);
    $selectedPickupDate = $slotGroups->has($pickedDay) ? $pickedDay : $firstOpenDay;
    $todayKey = now()->format('Y-m-d');
    $dayParts = ['Morning' => [0, 12], 'Afternoon' => [12, 17], 'Evening' => [17, 24]];
    $selectedFee = $documentFees[old('document_type')] ?? null;
    $feeText = $selectedFee === null ? 'Choose a document' : '₱'.number_format($selectedFee, 2);
@endphp

@section('content')
<div class="dr dr-create">
    @if ($errors->any())
        <div class="dr-alert dr-alert--error" role="alert">
            <i data-lucide="circle-alert" aria-hidden="true"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('portal.appointments.store') }}" enctype="multipart/form-data"
          id="bkForm" class="dr-grid" novalidate
          data-initial-step="{{ $initialStep }}"
          data-fees='@json($documentFees)'>
        @csrf

        <div class="dr-form">
            <ol class="dr-steps" aria-label="Booking steps">
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

                {{-- 1. Document --}}
                <fieldset class="dr-sec" data-step-panel="1">
                    <legend class="dr-sec-title">Which document do you need?</legend>
                    <p class="dr-sec-hint">You can book one document type per appointment.</p>
                    <div class="dr-opts">
                        @foreach ($documentOptions as $value => $doc)
                            <label class="dr-opt dr-opt--stack">
                                <input type="radio" name="document_type" value="{{ $value }}"
                                       class="dr-opt-input dr-visually-hidden"
                                       data-label="{{ $doc['label'] }}"
                                       data-fee="{{ $documentFees[$value] }}"
                                       {{ old('document_type') === $value ? 'checked' : '' }}>
                                <span class="dr-opt-mark" aria-hidden="true"></span>
                                <span class="dr-opt-text">
                                    <span class="dr-opt-name">{{ $doc['label'] }}</span>
                                    <span class="dr-opt-desc">{{ $doc['desc'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="dr-error" data-step-error="1" role="alert">Choose a document to continue.</p>
                </fieldset>

                {{-- 2. Pickup time --}}
                <fieldset class="dr-sec" data-step-panel="2" hidden>
                    <legend class="dr-sec-title">When can you come in?</legend>
                    <p class="dr-sec-hint">These are the Registrar’s open times. Bring a valid ID.</p>
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
                                    @foreach ($slotGroups as $date => $dateSlots)
                                        @php
                                            $d = \Carbon\Carbon::parse($date);
                                            $openN = $openByDay[$date];
                                        @endphp
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
                                                                $left = $slotLeft[$slot->id];
                                                                $start = \Carbon\Carbon::parse($slot->start_time)->format('g:i A');
                                                                $end = \Carbon\Carbon::parse($slot->end_time)->format('g:i A');
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

                            <p class="dp-picked" data-picked aria-live="polite">No time chosen yet.</p>
                        </div>
                    @else
                        <div class="dr-empty dr-empty--inline">
                            <h2>No times are open right now</h2>
                            <p>The Registrar hasn’t opened any pickup times. Check back later or contact the Registrar’s Office.</p>
                        </div>
                    @endif
                    <p class="dr-error" data-step-error="2" role="alert">Choose a pickup time to continue.</p>
                </fieldset>

                {{-- 3. Payment --}}
                <fieldset class="dr-sec" data-step-panel="3" hidden>
                    <legend class="dr-sec-title">Purpose and payment</legend>
                    <p class="dr-sec-hint">Document fee: <strong data-document-fee>{{ $selectedFee === null ? 'Choose a document first' : '₱'.number_format($selectedFee, 2) }}</strong>. The Cashier verifies every payment.</p>

                    <div class="dr-field">
                        <label class="dr-label" for="bk-purpose">What is it for?</label>
                        <textarea id="bk-purpose" name="purpose" class="dr-textarea" rows="2" aria-describedby="bk-purpose-error"
                                  placeholder="Employment, scholarship, board exam…">{{ old('purpose') }}</textarea>
                        <p class="dr-error" data-step-error="purpose" id="bk-purpose-error" role="alert">Tell the Registrar what the document is for.</p>
                        @error('purpose') <p class="dr-error is-shown">{{ $message }}</p> @enderror
                    </div>

                    <div class="dr-sec-sub">How will you pay?</div>
                    <div class="dr-opts">
                        <label class="dr-opt dr-opt--stack">
                            <input type="radio" name="payment_method" id="pm-walkin" value="walk_in"
                                   class="dr-opt-input dr-visually-hidden" data-label="Cashier’s Office"
                                   {{ ! $gcashChosen ? 'checked' : '' }}>
                            <span class="dr-opt-mark" aria-hidden="true"></span>
                            <span class="dr-opt-text">
                                <span class="dr-opt-name">Pay at the Cashier’s Office</span>
                                <span class="dr-opt-desc">Pay in person before or on your appointment date.</span>
                            </span>
                        </label>
                        <label class="dr-opt dr-opt--stack {{ $gcashOk ? '' : 'is-disabled' }}">
                            <input type="radio" name="payment_method" id="pm-gcash" value="gcash"
                                   class="dr-opt-input dr-visually-hidden" data-label="GCash"
                                   aria-controls="gcash-details"
                                   {{ $gcashChosen ? 'checked' : '' }} {{ $gcashOk ? '' : 'disabled' }}>
                            <span class="dr-opt-mark" aria-hidden="true"></span>
                            <span class="dr-opt-text">
                                <span class="dr-opt-name">Pay with GCash</span>
                                <span class="dr-opt-desc">{{ $gcashOk ? 'Scan the QR, then upload your receipt. The Cashier still verifies it.' : 'Not available right now. Please pay at the Cashier’s Office.' }}</span>
                            </span>
                        </label>
                    </div>

                    @if ($gcashOk)
                        <div id="gcash-details" class="dr-gcash {{ $gcashChosen ? 'is-open' : '' }}">
                            <div>
                                <div class="dr-gcash-qr">
                                    <img src="{{ route('portal.appointments.gcash.qr') }}" alt="Official DTC GCash QR code" loading="lazy">
                                </div>
                                @if (! empty($gcashName) || ! empty($gcashNumber))
                                    <p class="dr-gcash-account"><strong>{{ $gcashName ?? '' }}</strong>{{ $gcashNumber ?? '' }}</p>
                                @endif
                            </div>
                            <div class="dr-gcash-fields">
                                <p class="dr-gcash-note">
                                    Send exactly <strong data-gcash-fee>{{ $selectedFee === null ? 'the document fee' : '₱'.number_format($selectedFee, 2) }}</strong>, then add the reference number and a screenshot of the receipt.
                                </p>
                                <div class="dr-field">
                                    <label class="dr-label" for="gcash-ref">GCash reference number</label>
                                    <input type="text" name="reference_number" id="gcash-ref" class="dr-input" maxlength="100"
                                           value="{{ old('reference_number') }}" autocomplete="off" inputmode="numeric">
                                    <p class="dr-error" data-step-error="ref" role="alert">Enter the reference number from your GCash receipt.</p>
                                    @error('reference_number') <p class="dr-error is-shown">{{ $message }}</p> @enderror
                                </div>
                                <div class="dr-field">
                                    <span class="dr-label" id="gcash-proof-label">Receipt screenshot</span>
                                    <label class="dr-file" for="gcash-proof">
                                        <input type="file" name="proof_of_payment" id="gcash-proof" class="dr-visually-hidden"
                                               accept="image/jpeg,image/png,application/pdf" aria-labelledby="gcash-proof-label">
                                        <span class="dr-file-btn">Choose file</span>
                                        <span class="dr-file-name" data-dr-file-name>JPG, PNG or PDF, up to 5 MB</span>
                                    </label>
                                    <p class="dr-error" data-step-error="proof" role="alert">Upload a screenshot or PDF of your receipt.</p>
                                    @error('proof_of_payment') <p class="dr-error is-shown">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    @endif
                </fieldset>

                {{-- 4. Review --}}
                <fieldset class="dr-sec" data-step-panel="4" hidden>
                    <legend class="dr-sec-title">Check your booking</legend>
                    <p class="dr-sec-hint">Submitting books your slot. The Registrar confirms your appointment within 1 to 2 business days.</p>
                    <dl class="dr-review">
                        <div><dt>Document</dt><dd data-review="doc">—</dd><button type="button" class="dr-change" data-goto="1">Change<span class="dr-visually-hidden"> document</span></button></div>
                        <div><dt>Date</dt><dd data-review="date">—</dd><button type="button" class="dr-change" data-goto="2">Change<span class="dr-visually-hidden"> pickup time</span></button></div>
                        <div><dt>Time</dt><dd data-review="time">—</dd><button type="button" class="dr-change" data-goto="2">Change<span class="dr-visually-hidden"> pickup time</span></button></div>
                        <div><dt>Payment</dt><dd data-review="pay">—</dd><button type="button" class="dr-change" data-goto="3">Change<span class="dr-visually-hidden"> payment</span></button></div>
                        <div><dt>Purpose</dt><dd data-review="purpose">—</dd><button type="button" class="dr-change" data-goto="3">Change<span class="dr-visually-hidden"> purpose</span></button></div>
                    </dl>
                </fieldset>
            </div>
        </div>

        <aside class="dr-aside" aria-label="Booking summary">
            <div class="dr-slip dr-slip--wizard">
                <div class="dr-slip-body">
                    <h2 class="dr-slip-title">Your booking</h2>
                    <dl class="dr-slip-rows">
                        <div class="dr-slip-row"><dt>Document</dt><dd data-slip="doc" class="is-empty">Not chosen yet</dd></div>
                        <div class="dr-slip-row"><dt>Pickup</dt><dd data-slip="when" class="is-empty">Not chosen yet</dd></div>
                        <div class="dr-slip-row"><dt>Payment</dt><dd data-slip="pay">Cashier’s Office</dd></div>
                    </dl>
                </div>
                <div class="dr-slip-tear" aria-hidden="true"></div>
                <div class="dr-slip-total">
                    <span class="dr-slip-total-label">Document fee</span>
                    <span class="dr-slip-total-amount {{ $selectedFee === null ? 'is-free' : '' }}" data-slip-fee>{{ $feeText }}</span>
                </div>
                <div class="dr-slip-foot">
                    <button type="button" class="dr-slip-back" id="bkBack" hidden>Back</button>
                    <button type="button" class="dr-btn dr-btn--gold" id="bkNext">Next: Pickup time</button>
                    <p class="dr-slip-note" id="bkNote">Step 1 of 4</p>
                </div>
            </div>

            <details class="dr-next" id="drNext">
                <summary>What happens next</summary>
                <ul class="dr-next-list">
                    <li><strong>The Registrar confirms</strong>You’ll get a notification once your time is confirmed.</li>
                    <li><strong>The Cashier verifies payment</strong>They record walk-in payments or check your GCash receipt.</li>
                    <li><strong>You visit the office</strong>Registrar’s Office, DTC Main Building. Bring a valid ID.</li>
                </ul>
                <p class="dr-fees">A class suspension alone doesn’t close the office. Available times are set by the Registrar.</p>
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
    <script src="{{ asset('js/student-booking.js') }}" defer></script>
@endsection
