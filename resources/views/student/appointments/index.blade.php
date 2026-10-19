@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Appointments')

@section('content_header')
    <div class="dr dr-head">
        <div>
            <h1>My appointments</h1>
            <p>Your pickup times and payment status for document requests.</p>
        </div>
        <a href="{{ route('portal.appointments.create') }}" class="dr-btn dr-btn--primary">
            <i data-lucide="plus" aria-hidden="true"></i> Request a document
        </a>
    </div>
@endsection

@php
    $items = method_exists($appointments, 'getCollection') ? $appointments->getCollection() : collect($appointments);
    $counts = ['all' => $items->count(), 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
    foreach ($items as $a) { $counts[$a->status] = ($counts[$a->status] ?? 0) + 1; }
    $groupOf = fn ($s) => in_array($s, ['confirmed', 'cancelled'], true) ? $s : 'pending';
    $docNames = ['transcript' => 'Transcript of Records', 'diploma' => 'Diploma', 'certification' => 'Certification', 'tor' => 'True Copy of Records', 'true_copy' => 'True Copy of Records'];
    $badgeText = ['pending' => 'Waiting for confirmation', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled'];
    $badgeTone = ['pending' => 'warning', 'confirmed' => 'success', 'cancelled' => 'neutral'];
@endphp

@section('content')
<div class="dr dr-index dtc-appointments-page">

    @if (session('success'))
        <div class="dr-alert dr-alert--ok" role="status"><i data-lucide="circle-check" aria-hidden="true"></i><div>{{ session('success') }}</div></div>
    @endif
    @if (session('error'))
        <div class="dr-alert dr-alert--error" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><div>{{ session('error') }}</div></div>
    @endif
    @if ($errors->any())
        <div class="dr-alert dr-alert--error" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><div>{{ $errors->first() }}</div></div>
    @endif

    @if ($items->isEmpty())
        <div class="dr-empty">
            <h2>No appointments yet</h2>
            <p>Request a Transcript, Diploma, Certification or True Copy and pick a time to collect it from the Registrar’s Office.</p>
            <a href="{{ route('portal.appointments.create') }}" class="dr-btn dr-btn--primary">
                <i data-lucide="plus" aria-hidden="true"></i> Request a document
            </a>
        </div>
    @else
        <div class="dr-filters" role="group" aria-label="Filter appointments" data-dr-filters>
            <button type="button" class="dr-filter" data-filter="all" aria-pressed="true">All <span class="dr-filter-count">{{ $counts['all'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="pending" aria-pressed="false">Waiting <span class="dr-filter-count">{{ $counts['pending'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="confirmed" aria-pressed="false">Confirmed <span class="dr-filter-count">{{ $counts['confirmed'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="cancelled" aria-pressed="false">Cancelled <span class="dr-filter-count">{{ $counts['cancelled'] }}</span></button>
        </div>

        <ul class="dr-list">
            @foreach ($items as $appointment)
                @php
                    $pay = $appointment->latestPayment;
                    $when = \Carbon\Carbon::parse($appointment->slot->date);
                    $time = \Carbon\Carbon::parse($appointment->slot->start_time)->format('g:i A').' to '.\Carbon\Carbon::parse($appointment->slot->end_time)->format('g:i A');
                    $cancelled = $appointment->status === 'cancelled';
                    $payRejected = $pay && $pay->status === 'rejected';
                    $payTone = $pay ? ($pay->status === 'verified' ? 'success' : ($pay->status === 'rejected' ? 'danger' : ($pay->isGcash() ? 'info' : 'warning'))) : 'neutral';
                    $payLabel = $pay
                        ? ($pay->status === 'verified' ? 'Paid' : ($pay->status === 'rejected' ? 'Payment not verified' : ($pay->isGcash() ? 'Payment under review' : 'Pay at Cashier')))
                        : 'No payment yet';
                @endphp
                <li class="dr-req {{ $payRejected ? 'needs-fix' : ($appointment->status === 'confirmed' ? 'needs-action' : '') }} {{ $cancelled ? 'is-muted' : '' }}"
                    data-group="{{ $groupOf($appointment->status) }}">
                    <div class="dr-req-top">
                        <div class="dr-when">
                            <span class="dr-when-leaf" aria-hidden="true"><span>{{ $when->format('M') }}</span><strong>{{ $when->format('j') }}</strong></span>
                            <div>
                                <h2 class="dr-req-name">{{ $docNames[$appointment->document_type] ?? ucwords(str_replace('_', ' ', $appointment->document_type)) }}</h2>
                                <p class="dr-req-meta">{{ $when->format('l') }}, {{ $time }}</p>
                                @if ($appointment->purpose)
                                    <p class="dr-req-purpose">Purpose: {{ $appointment->purpose }}</p>
                                @endif
                            </div>
                        </div>
                        <span class="dtc-status-badge is-{{ $badgeTone[$appointment->status] ?? 'neutral' }}">{{ $badgeText[$appointment->status] ?? ucfirst($appointment->status) }}</span>
                    </div>

                    @if ($appointment->status === 'confirmed')
                        <p class="dr-step-note">Your appointment is confirmed. Be at the Registrar’s Office on time and bring a valid ID.</p>
                    @elseif ($cancelled)
                        @if ($appointment->remarks)
                            <div class="dr-remark" style="margin-top:14px;"><i data-lucide="circle-x" aria-hidden="true"></i><span><strong>Cancelled:</strong> {{ $appointment->remarks }}</span></div>
                        @else
                            <p class="dr-step-note">This appointment was cancelled.</p>
                        @endif
                    @else
                        <p class="dr-step-note">Waiting for the Registrar to confirm your time. You’ll get a notification.</p>
                    @endif

                    @if (! $cancelled)
                        <div class="dr-pay">
                            <div class="dr-pay-line">
                                <span class="dtc-status-badge is-{{ $payTone }}">{{ $payLabel }}</span>
                                @if ($pay)
                                    <span class="dr-pay-method">{{ $pay->isGcash() ? 'GCash' : 'Cashier’s Office' }}@if ($pay->reference_number), ref {{ $pay->reference_number }}@endif</span>
                                @endif
                            </div>

                            @if (! $pay)
                                <p class="dr-pay-text">Pay at the Cashier’s Office, or send the fee by GCash and upload your receipt.</p>
                                <div class="dr-actions">
                                    <button type="button" class="dr-btn dr-btn--primary dr-btn--sm" data-toggle="modal" data-target="#gcashModal{{ $appointment->id }}">Pay with GCash</button>
                                </div>
                            @elseif ($payRejected)
                                <div class="dr-remark" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><span>{{ $pay->rejection_reason ?: 'The Cashier couldn’t verify this payment. Contact the Cashier for next steps.' }}</span></div>
                                <div class="dr-actions">
                                    <button type="button" class="dr-btn dr-btn--primary dr-btn--sm" data-toggle="modal" data-target="#gcashModal{{ $appointment->id }}">Resubmit GCash proof</button>
                                </div>
                            @elseif ($pay->status === 'verified')
                                <p class="dr-pay-text">The Cashier has verified your payment.</p>
                            @elseif ($pay->isGcash())
                                <p class="dr-pay-text">
                                    Your receipt is a claim until the Cashier confirms the funds arrived.
                                    @if ($pay->proof_of_payment)
                                        <a href="{{ route('portal.appointments.payment.proof', $pay) }}" target="_blank" rel="noopener">View submitted proof</a>
                                    @endif
                                </p>
                            @else
                                <p class="dr-pay-text">Pay at the Cashier’s Office. Your payment counts once the Cashier records it.</p>
                            @endif
                        </div>
                    @endif

                    @if ($appointment->status === 'pending')
                        <div class="dr-actions dr-actions--split">
                            <button type="button" class="dr-btn dr-btn--ghost dr-btn--sm" data-toggle="modal" data-target="#editModal{{ $appointment->id }}">
                                <i data-lucide="pencil" aria-hidden="true"></i> Edit booking
                            </button>
                            <form id="cancel-appt-{{ $appointment->id }}" method="POST" action="{{ route('portal.appointments.cancel', $appointment) }}">
                                @csrf
                                <button type="button" class="dr-btn dr-btn--danger dr-btn--sm"
                                        data-dtc-confirm
                                        data-dtc-confirm-title="Cancel Appointment?"
                                        data-dtc-confirm-message="Are you sure you want to cancel this appointment? You may need to book a new slot."
                                        data-dtc-confirm-ok="Cancel Appointment"
                                        data-dtc-confirm-cancel="Keep Appointment"
                                        data-dtc-confirm-form="#cancel-appt-{{ $appointment->id }}">
                                    Cancel booking
                                </button>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="dr-empty dr-empty--filter" id="drFilterEmpty" role="status">
            <p>Nothing here yet. Try another filter.</p>
        </div>

        <details class="dr-next dr-next--flat">
            <summary>Who handles what</summary>
            <ul class="dr-next-list">
                <li><strong>The Registrar</strong>Manages appointment times and confirms bookings. Contact them about schedules.</li>
                <li><strong>The Cashier</strong>Verifies GCash receipts or records walk-in payments. Contact them about payment records.</li>
            </ul>
        </details>

        @if (method_exists($appointments, 'links'))
            <div style="margin-top:20px;">{{ $appointments->links() }}</div>
        @endif

        {{-- Modals (unchanged behaviour): pay with GCash, edit booking --}}
        @foreach ($items as $appointment)
        {{-- ── GCash Upload Modal (per appointment) ───────────────────── --}}
        @if($appointment->status !== 'cancelled')
        <div class="modal fade" id="gcashModal{{ $appointment->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST"
                          action="{{ route('portal.appointments.payment.gcash', $appointment) }}"
                          enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:14px; font-weight:700;">
                                <i data-lucide="smartphone" style="width:16px;height:16px;margin-right:6px;"></i>
                                Submit GCash Payment
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            {{-- GCash account info --}}
                            <div style="background:var(--dtc-surface-soft); border-radius:10px;
                                        padding:14px 16px; margin-bottom:16px; font-size:12px;
                                        color:var(--dtc-text-secondary); text-align:center;">
                                <div style="font-size:11px; color:var(--dtc-text-muted); margin-bottom:4px;">
                                    Send payment to
                                </div>
                                <img src="{{ route('portal.appointments.gcash.qr') }}"
                                     alt="GCash QR"
                                     onerror="this.style.display='none'"
                                     style="width:120px; height:120px; object-fit:contain;
                                            border:1px solid var(--dtc-border); border-radius:10px;
                                            padding:4px; display:block; margin:0 auto 10px;">
                                <div style="font-weight:700; font-size:15px; color:var(--dtc-text);">
                                    {{ \App\Models\Setting::get('gcash_name', '—') }}
                                </div>
                                <div style="font-size:13px; font-family:monospace; color:var(--dtc-primary);">
                                    {{ \App\Models\Setting::get('gcash_number', '—') }}
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label style="font-size:12px; font-weight:600; color:var(--dtc-text);">
                                    GCash Reference Number <span style="color:var(--dtc-danger);">*</span>
                                </label>
                                <input type="text" name="reference_number"
                                       class="form-control" required
                                       placeholder="e.g. 1234567890"
                                       style="border-radius:8px; font-size:13px;">
                            </div>
                            <div class="form-group mb-0">
                                <label style="font-size:12px; font-weight:600; color:var(--dtc-text);">
                                    Proof of Payment
                                    <span style="color:var(--dtc-text-muted); font-weight:400;">(screenshot or PDF, max 5 MB)</span>
                                    <span style="color:var(--dtc-danger);">*</span>
                                </label>
                                <div class="dtc-file-upload" onclick="this.querySelector('input').click()">
                                    <input type="file" name="proof_of_payment"
                                           class="dtc-file-input" required
                                           accept=".jpg,.jpeg,.png,.pdf"
                                           onchange="dtcFileChange(this)">
                                    <i data-lucide="upload-cloud" class="dtc-file-icon"></i>
                                    <div class="dtc-file-label">
                                        <span class="dtc-file-btn">Choose file</span>
                                        <span class="dtc-file-name">No file chosen</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="upload" style="width:13px;height:13px;margin-right:4px;"></i>
                                Submit Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Edit Appointment Modal ─────────────────────────────────── --}}
        @if($appointment->status === 'pending')
        <div class="modal fade" id="editModal{{ $appointment->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST"
                          action="{{ route('portal.appointments.update', $appointment) }}">
                        @csrf
                        @method('PUT')

                        <div class="modal-header">
                            <h5 class="modal-title" style="font-size:14px; font-weight:700;">
                                <i data-lucide="pencil" style="width:16px;height:16px;margin-right:6px;"></i>
                                Edit Appointment
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>

                        <div class="modal-body">

                            {{-- Document Type --}}
                            <div class="mb-4">
                                <label class="font-weight-bold d-block mb-2" style="font-size:13px; color:var(--dtc-text);">
                                    Document Type <span style="color:var(--dtc-danger,#dc3545);">*</span>
                                </label>
                                <div class="row">
                                    @foreach([
                                        ['value' => 'transcript',    'label' => 'Transcript of Records', 'icon' => 'file-text'],
                                        ['value' => 'diploma',       'label' => 'Diploma',               'icon' => 'graduation-cap'],
                                        ['value' => 'certification', 'label' => 'Certification',          'icon' => 'award'],
                                        ['value' => 'tor',           'label' => 'True Copy of Records',  'icon' => 'copy'],
                                    ] as $doc)
                                    <div class="col-6 col-sm-3 mb-2">
                                        <label class="dtc-doc-card" style="cursor:pointer; display:block;">
                                            <input type="radio"
                                                   name="document_type"
                                                   value="{{ $doc['value'] }}"
                                                   class="edit-doc-radio-{{ $appointment->id }}"
                                                   style="position:absolute; opacity:0; width:0; height:0;"
                                                   {{ $appointment->document_type === $doc['value'] ? 'checked' : '' }}>
                                            <div class="dtc-doc-option" style="padding:10px 6px;">
                                                <div class="dtc-doc-icon">
                                                    <i data-lucide="{{ $doc['icon'] }}"></i>
                                                </div>
                                                <div class="dtc-doc-label" style="font-size:11px;">{{ $doc['label'] }}</div>
                                            </div>
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Schedule --}}
                            <div class="mb-4">
                                <label class="font-weight-bold d-block mb-2" style="font-size:13px; color:var(--dtc-text);">
                                    Schedule <span style="color:var(--dtc-danger,#dc3545);">*</span>
                                </label>
                                <div style="max-height:240px; overflow-y:auto; padding-right:4px;">
                                    {{-- Current slot always shown first --}}
                                    <label class="dtc-slot-card mb-2" style="cursor:pointer; display:block;">
                                        <input type="radio"
                                               name="appointment_slot_id"
                                               value="{{ $appointment->appointment_slot_id }}"
                                               data-reschedule-current
                                               style="position:absolute; opacity:0; width:0; height:0;"
                                               checked>
                                        <div class="dtc-slot-option">
                                            <div class="dtc-date-chip">
                                                <span class="dtc-date-chip-month">{{ \Carbon\Carbon::parse($appointment->slot->date)->format('M') }}</span>
                                                <span class="dtc-date-chip-day">{{ \Carbon\Carbon::parse($appointment->slot->date)->format('d') }}</span>
                                            </div>
                                            <div class="dtc-slot-info">
                                                <div class="dtc-slot-date">
                                                    {{ \Carbon\Carbon::parse($appointment->slot->date)->format('l, F d, Y') }}
                                                    <span style="font-size:10px; color:var(--dtc-primary); font-weight:600; margin-left:6px;">Current</span>
                                                </div>
                                                <div class="dtc-slot-time">
                                                    <i data-lucide="clock" style="width:12px;height:12px;"></i>
                                                    {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') }}
                                                    &mdash;
                                                    {{ \Carbon\Carbon::parse($appointment->slot->end_time)->format('h:i A') }}
                                                </div>
                                            </div>
                                        </div>
                                    </label>

                                    @php
                                        $otherSlotGroups = $slots->where('id', '!=', $appointment->appointment_slot_id)
                                            ->groupBy(fn ($slot) => \Carbon\Carbon::parse($slot->date)->format('Y-m-d'));
                                    @endphp
                                    @if ($otherSlotGroups->isNotEmpty())
                                        <label class="dr-label d-block mt-3" for="reschedule-date-{{ $appointment->id }}">Choose a different date</label>
                                        <select id="reschedule-date-{{ $appointment->id }}" class="dr-select mb-3" data-reschedule-date-select="{{ $appointment->id }}">
                                            <option value="">Keep the current time, or choose a date</option>
                                            @foreach ($otherSlotGroups as $date => $dateSlots)
                                                <option value="{{ $date }}">{{ \Carbon\Carbon::parse($date)->format('l, F j') }} · {{ $dateSlots->count() }} {{ $dateSlots->count() === 1 ? 'time' : 'times' }}</option>
                                            @endforeach
                                        </select>
                                        <div class="dr-reschedule-time-grid" data-reschedule-slot-list="{{ $appointment->id }}" hidden>
                                    {{-- Other available slots --}}
                                    @foreach($otherSlotGroups as $date => $dateSlots)
                                        @foreach($dateSlots as $slot)
                                    @php $left = $slot->availableSlots(); @endphp
                                    <label class="dtc-slot-card mb-2 dr-reschedule-slot" data-reschedule-date="{{ $date }}" hidden style="cursor:pointer; display:block;">
                                        <input type="radio"
                                               name="appointment_slot_id"
                                               value="{{ $slot->id }}"
                                               style="position:absolute; opacity:0; width:0; height:0;">
                                        <div class="dtc-slot-option">
                                            <div class="dtc-date-chip">
                                                <span class="dtc-date-chip-month">{{ \Carbon\Carbon::parse($slot->date)->format('M') }}</span>
                                                <span class="dtc-date-chip-day">{{ \Carbon\Carbon::parse($slot->date)->format('d') }}</span>
                                            </div>
                                            <div class="dtc-slot-info">
                                                <div class="dtc-slot-date">{{ \Carbon\Carbon::parse($slot->date)->format('l, F d, Y') }}</div>
                                                <div class="dtc-slot-time">
                                                    <i data-lucide="clock" style="width:12px;height:12px;"></i>
                                                    {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }}
                                                    &mdash;
                                                    {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}
                                                </div>
                                            </div>
                                            <div class="dtc-slot-badge {{ $left <= 2 ? 'is-warning' : 'is-ok' }}">
                                                <i data-lucide="{{ $left <= 2 ? 'alert-circle' : 'users' }}" style="width:12px;height:12px;"></i>
                                                {{ $left }} left
                                            </div>
                                        </div>
                                    </label>
                                        @endforeach
                                    @endforeach
                                        </div>
                                    @endif

                                    @if($slots->isEmpty())
                                    <div class="text-center py-2" style="font-size:12px; color:var(--dtc-text-muted);">
                                        No other slots available — keeping current schedule.
                                    </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Purpose --}}
                            <div class="mb-1">
                                <label class="font-weight-bold d-block mb-1" style="font-size:13px; color:var(--dtc-text);">
                                    Purpose <span style="color:var(--dtc-danger,#dc3545);">*</span>
                                </label>
                                <textarea name="purpose"
                                          class="form-control"
                                          rows="2"
                                          placeholder="e.g. For employment application, scholarship, board exam..."
                                          style="border-radius:10px; font-size:13px; resize:none;"
                                          required>{{ $appointment->purpose }}</textarea>
                            </div>

                        </div>

                        <div class="modal-footer" style="justify-content:space-between;">
                            <div style="font-size:11px; color:var(--dtc-text-muted); display:flex; align-items:center; gap:6px;">
                                <i data-lucide="bell" style="width:12px;height:12px;"></i>
                                The Registrar will be notified of your changes.
                            </div>
                            <div style="display:flex; gap:8px;">
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Discard</button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i data-lucide="save" style="width:13px;height:13px;margin-right:4px;"></i>
                                    Save Changes
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
        @endif

        @endforeach
    @endif
</div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/appointments-shared.css') }}">
    <link rel="stylesheet" href="{{ asset('css/appointments-index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/document-requests.css') }}">
    <link rel="stylesheet" href="{{ asset('css/document-request-flow.css') }}">
@endsection

@section('js')
    <script src="{{ asset('js/appointments-index.js') }}"></script>
    <script src="{{ asset('js/document-request.js') }}" defer></script>
@endsection
