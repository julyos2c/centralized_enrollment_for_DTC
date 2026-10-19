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
            <p>Pick what you need and how you’ll pay. The Registrar prepares it, and you collect it on campus.</p>
        </div>
    </div>
@endsection

@php
    $documentOptions = [
        'tor'           => ['label' => 'Transcript of Records', 'desc' => 'Official academic transcript'],
        'diploma'       => ['label' => 'Diploma',               'desc' => 'Replacement or authentication'],
        'certification' => ['label' => 'Certification',         'desc' => 'Certificate of enrollment or graduation'],
        'true_copy'     => ['label' => 'True Copy of Records',   'desc' => 'Certified true copy of your records'],
    ];
    $oldPayment = old('payment_method', 'walk_in');
    $gcashChosen = $gcashQrReady && $oldPayment === 'gcash_qr';
    $slotGroups = $slots->groupBy(fn ($slot) => \Carbon\Carbon::parse($slot->date)->format('Y-m-d'));
    $oldSlot = $slots->firstWhere('id', old('appointment_slot_id'));
    $selectedPickupDate = old('pickup_date', $oldSlot
        ? \Carbon\Carbon::parse($oldSlot->date)->format('Y-m-d')
        : '');
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
          id="drForm" class="dr-grid"
          data-fees='@json($documentFees)'
          data-max-copies="10">
        @csrf

        <div class="dr-form">
            <div class="dr-sheet">

                {{-- Document --}}
                <fieldset class="dr-sec" aria-describedby="drTypeError">
                    <legend class="dr-sec-title">What do you need?</legend>
                    <p class="dr-sec-hint">Fees are charged per copy.</p>
                    <div class="dr-opts" id="drTypeGroup" role="radiogroup" aria-label="Document type">
                        @foreach ($documentOptions as $value => $doc)
                            <label class="dr-opt">
                                <input type="radio" name="document_type" value="{{ $value }}"
                                       class="dr-opt-input dr-visually-hidden"
                                       data-label="{{ $doc['label'] }}" required
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
                    <p class="dr-error" id="drTypeError" role="alert">Choose the document you need to continue.</p>
                </fieldset>

                {{-- Copies and purpose --}}
                <div class="dr-sec">
                    <div class="dr-field">
                        <label class="dr-label" for="copies">Copies</label>
                        <div class="dr-stepper">
                            <button type="button" id="drCopiesMinus" aria-label="Fewer copies">&minus;</button>
                            <input type="number" name="copies" id="copies" value="{{ old('copies', 1) }}"
                                   min="1" max="10" inputmode="numeric" required aria-describedby="copies-help">
                            <button type="button" id="drCopiesPlus" aria-label="More copies">+</button>
                        </div>
                        <p class="dr-help" id="copies-help">Up to 10 copies per request.</p>
                    </div>
                    <div class="dr-field">
                        <label class="dr-label" for="document-purpose">Purpose <span>(optional)</span></label>
                        <textarea id="document-purpose" name="purpose" class="dr-textarea" rows="3"
                                  placeholder="Employment, board exam, scholarship…">{{ old('purpose') }}</textarea>
                    </div>
                </div>

                {{-- Pickup --}}
                <div class="dr-sec">
                    <div class="dr-field">
                        <label class="dr-label" for="pickup-date">Pickup date <span>(optional)</span></label>
                        <select id="pickup-date" name="pickup_date" class="dr-select">
                            <option value="" {{ $selectedPickupDate === '' ? 'selected' : '' }}>I’ll book a pickup time later</option>
                            @foreach ($slotGroups as $date => $dateSlots)
                                <option value="{{ $date }}" {{ $selectedPickupDate === $date ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::parse($date)->format('l, F j') }} · {{ $dateSlots->count() }} {{ $dateSlots->count() === 1 ? 'time' : 'times' }}
                                </option>
                            @endforeach
                        </select>
                        <label class="dr-label dr-pickup-time-label">Available times <span>(optional)</span></label>
                        <select id="appointment-slot-fallback" class="dr-select" hidden disabled>
                            <option value="" data-label="Book later">I’ll book a pickup time later</option>
                            @foreach ($slots as $slot)
                                @php
                                    $slotDate = \Carbon\Carbon::parse($slot->date);
                                    $slotText = $slotDate->format('D, M j').', '
                                        .\Carbon\Carbon::parse($slot->start_time)->format('g:i A').' to '
                                        .\Carbon\Carbon::parse($slot->end_time)->format('g:i A');
                                @endphp
                                <option value="{{ $slot->id }}" data-date="{{ \Carbon\Carbon::parse($slot->date)->format('Y-m-d') }}" data-label="{{ $slotText }}"
                                    {{ old('appointment_slot_id') == $slot->id ? 'selected' : '' }}>
                                    {{ $slotText }} ({{ $slot->availableSlots() }} left)
                                </option>
                            @endforeach
                        </select>
                        <div class="dr-opts dr-time-grid" role="radiogroup" aria-label="Pickup time">
                            <label class="dr-opt dr-time-opt dr-opt--stack" data-slot-date-panel="">
                                <input type="radio" name="appointment_slot_id" value="" class="dr-opt-input dr-visually-hidden"
                                       data-label="Book later" {{ old('appointment_slot_id') ? '' : 'checked' }}>
                                <span class="dr-opt-mark" aria-hidden="true"></span>
                                <span class="dr-opt-text">
                                    <span class="dr-opt-name">I’ll book later</span>
                                    <span class="dr-opt-desc">Pickup appointment is optional.</span>
                                </span>
                            </label>
                            @foreach ($slots as $slot)
                                @php
                                    $slotDate = \Carbon\Carbon::parse($slot->date);
                                    $slotTime = \Carbon\Carbon::parse($slot->start_time)->format('g:i A').' to '
                                        .\Carbon\Carbon::parse($slot->end_time)->format('g:i A');
                                    $slotText = $slotDate->format('D, M j').', '.$slotTime;
                                    $left = $slot->availableSlots();
                                @endphp
                                <label class="dr-opt dr-time-opt" data-slot-date-panel="{{ $slotDate->format('Y-m-d') }}" {{ $selectedPickupDate !== $slotDate->format('Y-m-d') ? 'hidden' : '' }}>
                                    <input type="radio" name="appointment_slot_id" value="{{ $slot->id }}"
                                           class="dr-opt-input dr-visually-hidden" data-label="{{ $slotText }}"
                                           {{ old('appointment_slot_id') == $slot->id ? 'checked' : '' }}>
                                    <span class="dr-opt-mark" aria-hidden="true"></span>
                                    <span class="dr-opt-text"><span class="dr-opt-name">{{ $slotTime }}</span></span>
                                    <span class="dr-opt-price {{ $left <= 2 ? 'is-low' : '' }}">{{ $left }} {{ $left === 1 ? 'spot' : 'spots' }} left</span>
                                </label>
                            @endforeach
                        </div>
                        @if ($slots->isEmpty())
                            <p class="dr-help">No pickup times are open right now. Submit your request, then book from My Appointments.</p>
                        @else
                            <p class="dr-help">Slots can fill up before you submit. If one is gone, you’ll be asked to pick another.</p>
                        @endif
                    </div>
                </div>

                {{-- Payment --}}
                <fieldset class="dr-sec">
                    <legend class="dr-sec-title">How will you pay?</legend>
                    <p class="dr-sec-hint">Either way, the Cashier checks your payment before the document is released.</p>
                    <div class="dr-opts">
                        <label class="dr-opt dr-opt--stack">
                            <input type="radio" name="payment_method" id="paymentWalkIn" value="walk_in"
                                   class="dr-opt-input dr-visually-hidden"
                                   data-label="Cashier’s Office"
                                   {{ ! $gcashChosen ? 'checked' : '' }}>
                            <span class="dr-opt-mark" aria-hidden="true"></span>
                            <span class="dr-opt-text">
                                <span class="dr-opt-name">Pay at the Cashier’s Office</span>
                                <span class="dr-opt-desc">Your request stays unpaid until the Cashier records your payment.</span>
                            </span>
                        </label>

                        @if ($gcashQrReady)
                            <label class="dr-opt dr-opt--stack">
                                <input type="radio" name="payment_method" id="paymentGcash" value="gcash_qr"
                                       class="dr-opt-input dr-visually-hidden"
                                       data-label="GCash QR"
                                       aria-controls="gcashPaymentDetails"
                                       {{ $gcashChosen ? 'checked' : '' }}>
                                <span class="dr-opt-mark" aria-hidden="true"></span>
                                <span class="dr-opt-text">
                                    <span class="dr-opt-name">Pay online using the official GCash QR</span>
                                    <span class="dr-opt-desc">Scan, pay the exact total, then upload your receipt. The Cashier still verifies it.</span>
                                </span>
                            </label>
                        @endif
                    </div>

                    @if ($gcashQrReady)
                        <div id="gcashPaymentDetails" class="dr-gcash {{ $gcashChosen ? 'is-open' : '' }}">
                            <div class="dr-gcash-qr">
                                <img src="{{ route('portal.documents.gcash.qr') }}" alt="Official DTC GCash QR code" loading="lazy">
                            </div>
                            <div class="dr-gcash-fields">
                                <p class="dr-gcash-note">Send exactly <strong data-dr-total-text>the total shown</strong>. Uploading proof doesn’t confirm payment on its own.</p>
                                <div class="dr-field">
                                    <label class="dr-label" for="documentPaymentReference">GCash reference number</label>
                                    <input id="documentPaymentReference" type="text" name="reference_number" class="dr-input"
                                           maxlength="100" value="{{ old('reference_number') }}" autocomplete="off" inputmode="numeric">
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
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="dr-help" style="margin-top:12px;">GCash payment isn’t available right now. Please pay at the Cashier’s Office.</p>
                    @endif
                </fieldset>
            </div>

            {{-- Mobile spacing before the fixed bar; keeps the last section clear --}}
        </div>

        <aside class="dr-aside" aria-label="Request summary">
            <div class="dr-slip">
                <div class="dr-slip-body">
                    <h2 class="dr-slip-title">Your request</h2>
                    <dl class="dr-slip-rows">
                        <div class="dr-slip-row">
                            <dt>Document</dt>
                            <dd data-slip-doc class="is-empty">Not chosen yet</dd>
                        </div>
                        <div class="dr-slip-row">
                            <dt>Copies</dt>
                            <dd data-slip-qty>1</dd>
                        </div>
                        <div class="dr-slip-row">
                            <dt>Pickup</dt>
                            <dd data-slip-pickup>Book later</dd>
                        </div>
                        <div class="dr-slip-row">
                            <dt>Payment</dt>
                            <dd data-slip-pay>Cashier’s Office</dd>
                        </div>
                    </dl>
                </div>
                <div class="dr-slip-tear" aria-hidden="true"></div>
                <div class="dr-slip-total" aria-live="polite">
                    <span class="dr-slip-total-label">Total</span>
                    <span class="dr-slip-total-amount is-empty" data-slip-total>&#8369;0.00</span>
                </div>
                <div class="dr-slip-foot">
                    <button type="submit" class="dr-btn dr-btn--gold" id="drSubmit" data-loading-text="Submitting…">Submit request</button>
                    <p class="dr-slip-note">Your total is saved with this request. Later fee changes won’t affect it.</p>
                    <a href="{{ route('portal.documents.index') }}" class="dr-slip-cancel">Cancel</a>
                </div>
            </div>

            <details class="dr-next" id="drNext">
                <summary>What happens after you submit</summary>
                <ul class="dr-next-list">
                    <li><strong>The Registrar prepares it</strong>You’ll get a notification each time the status changes.</li>
                    <li><strong>The Cashier confirms payment</strong>They record walk-in payments or verify your GCash receipt. A screenshot isn’t an official receipt.</li>
                    <li><strong>You pick it up</strong>Bring your receipt and a valid ID once it’s ready and paid.</li>
                </ul>
            </details>
        </aside>
    </form>
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/document-request-flow.css') }}">
@endsection

@section('js')
    <script src="{{ asset('js/document-request.js') }}" defer></script>
@endsection
