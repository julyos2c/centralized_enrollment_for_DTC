@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Appointments')

@section('content_header')
    <div class="dr dr-head">
        <div>
            <h1>My appointments</h1>
            <p>Track your document pickup and payment.</p>
        </div>
        <a href="{{ route('portal.appointments.create') }}" class="dr-btn dr-btn--primary">
            <i data-lucide="plus" aria-hidden="true"></i> Request a document
        </a>
    </div>
@endsection

@php
    $items = method_exists($appointments, 'getCollection') ? $appointments->getCollection() : collect($appointments);
    $docNames = ['transcript' => 'Transcript of Records', 'diploma' => 'Diploma', 'certification' => 'Certification', 'tor' => 'True Copy of Records', 'true_copy' => 'True Copy of Records'];

    // Four-step track driven by the document request; cancelled bookings get their own group.
    $stageOf   = ['submitted' => 0, 'processing' => 1, 'ready' => 2, 'released' => 3];
    $stageName = ['Submitted', 'Preparing', 'Ready', 'Released'];
    $badgeText = ['submitted' => 'Submitted', 'processing' => 'Being prepared', 'ready' => 'Ready for pickup', 'released' => 'Released'];
    $badgeTextAppt = ['pending' => 'waiting for confirmation', 'confirmed' => 'confirmed', 'cancelled' => 'cancelled'];
    $badgeTone = ['submitted' => 'warning', 'processing' => 'info', 'ready' => 'purple', 'released' => 'success'];
    $groupOf = function ($a) {
        if ($a->status === 'cancelled') { return 'cancelled'; }
        $st = $a->documentRequest?->status;
        return $st === 'ready' ? 'ready' : ($st === 'released' ? 'released' : 'progress');
    };

    $counts = ['all' => $items->count(), 'progress' => 0, 'ready' => 0, 'released' => 0, 'cancelled' => 0];
    foreach ($items as $a) { $counts[$groupOf($a)]++; }
    $activeItems = $items->filter(fn ($a) => $a->status !== 'cancelled');
    $pickupNow   = $activeItems->filter(fn ($a) => $a->documentRequest && $a->documentRequest->status === 'ready' && $a->documentRequest->payment_status === 'verified')->count();
    $totalFees   = $activeItems->sum(fn ($a) => (float) ($a->documentRequest->fee ?? 0));
    $confirmed   = $items->where('status', 'confirmed')->count();
@endphp

@section('content')
<div class="dr dr-index dtc-appointments-page dr-alumni-history dr-appointment-history">

    @if ($errors->any())
        <div class="dr-alert dr-alert--error" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <details class="dr-history-summary">
        <summary>Appointment overview <span>{{ $counts['all'] }} total &middot; {{ $confirmed }} confirmed</span></summary>
    <div class="dtc-dashboard-overview dtc-dashboard-overview--four mb-3" aria-label="Appointment summary">
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Total appointments</span>
            <strong>{{ $counts['all'] }}</strong>
            <span class="dtc-overview-note">All bookings</span>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Ready for pickup</span>
            <strong>{{ $pickupNow }}</strong>
            <span class="dtc-overview-note">Payment verified</span>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Confirmed pickups</span>
            <strong>{{ $confirmed }}</strong>
            <span class="dtc-overview-note">Registrar confirmed</span>
        </div>
        <div class="dtc-overview-item">
            <span class="dtc-overview-label">Total fees</span>
            <strong>&#8369;{{ number_format($totalFees, 2) }}</strong>
            <span class="dtc-overview-note">Across active requests</span>
        </div>
    </div>

    </details>

    <h2 class="dr-section-title">Your appointments</h2>

    @if ($items->isEmpty())
        <div class="dr-empty">
            <h2>No appointments yet</h2>
            <p>Request a Transcript, Diploma, Certification or True Copy and pick a time to collect it from the Registrar’s Office.</p>
            <a href="{{ route('portal.appointments.create') }}" class="dr-btn dr-btn--primary">
                <i data-lucide="plus" aria-hidden="true"></i> Request a document
            </a>
        </div>
    @else
        @if ($pickupNow > 0)
            <p class="dr-collection-hint">For pickup, bring your receipt and a valid ID.</p>
        @endif

        <div class="dr-filters" role="group" aria-label="Filter appointments" data-dr-filters>
            <button type="button" class="dr-filter" data-filter="all" aria-pressed="true">All <span class="dr-filter-count">{{ $counts['all'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="progress" aria-pressed="false">In progress <span class="dr-filter-count">{{ $counts['progress'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="ready" aria-pressed="false">Ready <span class="dr-filter-count">{{ $counts['ready'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="released" aria-pressed="false">Released <span class="dr-filter-count">{{ $counts['released'] }}</span></button>
            <button type="button" class="dr-filter" data-filter="cancelled" aria-pressed="false">Cancelled <span class="dr-filter-count">{{ $counts['cancelled'] }}</span></button>
        </div>

        <ul class="dr-list">
            @foreach ($items as $appointment)
                @php
                    $pay = $appointment->latestPayment;
                    $docRequest = $appointment->documentRequest;
                    $when = \Carbon\Carbon::parse($appointment->slot->date);
                    $time = \Carbon\Carbon::parse($appointment->slot->start_time)->format('g:i A').' to '.\Carbon\Carbon::parse($appointment->slot->end_time)->format('g:i A');
                    $cancelled = $appointment->status === 'cancelled';
                    $st = \Carbon\Carbon::parse($appointment->slot->start_time); $en = \Carbon\Carbon::parse($appointment->slot->end_time);
                    $timeShort = $st->format('g:i').($st->format('A') === $en->format('A') ? '' : ' '.$st->format('A')).'–'.$en->format('g:i A');
                    $payRejected = $docRequest
                        ? $docRequest->payment_status === 'rejected'
                        : ($pay && $pay->status === 'rejected');
                    $docPayStatus = $docRequest?->payment_status;
                    $payTone = $docRequest
                        ? (['verified' => 'success', 'pending_verification' => 'info', 'rejected' => 'danger', 'unpaid' => 'warning'][$docPayStatus] ?? 'neutral')
                        : ($pay ? ($pay->status === 'verified' ? 'success' : ($pay->status === 'rejected' ? 'danger' : ($pay->isGcash() ? 'info' : 'warning'))) : 'neutral');
                    $payLabel = $docRequest
                        ? (['verified' => 'Verified', 'pending_verification' => 'Pending verification', 'rejected' => 'Payment rejected', 'unpaid' => 'Unpaid'][$docPayStatus] ?? ucfirst((string) $docPayStatus))
                        : ($pay
                            ? ($pay->status === 'verified' ? 'Paid' : ($pay->status === 'rejected' ? 'Payment not verified' : ($pay->isGcash() ? 'Payment under review' : 'Pay at Cashier')))
                            : 'No payment yet');
                @endphp
                @php
                    $docStatus = $docRequest?->status;
                    $stage     = $docStatus ? ($stageOf[$docStatus] ?? 0) : null;
                    $copiesTx  = $docRequest ? $docRequest->copies.($docRequest->copies > 1 ? ' copies' : ' copy') : null;
                    $collect   = $docRequest && $docStatus === 'ready' && $docRequest->payment_status === 'verified';
                    $docLabel  = $docNames[$appointment->document_type] ?? ucwords(str_replace('_', ' ', $appointment->document_type));
                    $topBadgeText = $cancelled ? 'Cancelled' : ($docStatus ? ($badgeText[$docStatus] ?? ucfirst($docStatus)) : 'Waiting for confirmation');
                    $topBadgeTone = $cancelled ? 'neutral' : ($docStatus ? ($badgeTone[$docStatus] ?? 'neutral') : 'warning');
                @endphp
                <li class="dr-req {{ $payRejected ? 'needs-fix' : ($collect ? 'needs-action' : '') }} {{ $cancelled ? 'is-muted' : '' }}"
                    data-group="{{ $groupOf($appointment) }}">
                    <div class="dr-req-top">
                        <div>
                            <h2 class="dr-req-name">{{ $docRequest->document_label ?? $docLabel }}</h2>
                            <p class="dr-req-meta">
                                @if ($copiesTx){{ $copiesTx }}, &#8369;{{ number_format($docRequest->fee, 2) }}, @endif
                                requested {{ $appointment->created_at->format('M j, Y') }}
                            </p>
                        </div>
                        @if ($cancelled)
                            <span class="dtc-status-badge is-{{ $topBadgeTone }}">{{ $topBadgeText }}</span>
                        @endif
                    </div>

                    @if (! $cancelled && $docRequest)
                        <ol class="dr-track" aria-label="Progress: step {{ $stage + 1 }} of 4, {{ $stageName[$stage] }}">
                            @foreach ($stageName as $i => $name)
                                <li class="{{ $i < $stage || ($i === $stage && $docStatus === 'released') ? 'is-done' : '' }} {{ $i === $stage ? 'is-current' : '' }}"
                                    @if ($i === $stage) aria-current="step" @endif>{{ $name }}</li>
                            @endforeach
                        </ol>
                    @endif

                    @if (!$cancelled && !$docRequest)
                        <p class="dr-step-note">{{ $appointment->status === 'confirmed' ? 'Appointment confirmed' : 'Awaiting appointment confirmation' }}</p>
                    @endif

                    <div class="dr-pickup-summary" aria-label="Pickup appointment">
                        <i data-lucide="calendar-days" aria-hidden="true"></i>
                        <span>
                            <strong>{{ $when->format('D, M j') }}</strong> · {{ $timeShort }}
                            · {{ ucfirst($badgeTextAppt[$appointment->status] ?? $appointment->status) }}
                            @if ($cancelled && $appointment->remarks)<br>{{ $appointment->remarks }}@endif
                        </span>
                    </div>

                    @if ($appointment->purpose)
                        <details class="dr-request-details">
                            <summary>Request details</summary>
                            <p class="dr-req-purpose">Purpose: {{ $appointment->purpose }}</p>
                        </details>
                    @endif

                    @if (! $cancelled)
                        <div class="dr-pay">
                            <div class="dr-pay-line">
                                <span class="dtc-status-badge is-{{ $payTone }}">{{ $payLabel }}</span>
                                @if ($docRequest)
                                    <span class="dr-pay-method">{{ $docRequest->payment_method === 'gcash_qr' ? 'GCash' : 'Cashier’s Office' }} · ₱{{ number_format($docRequest->fee, 2) }}</span>
                                @elseif ($pay)
                                    <span class="dr-pay-method">{{ $pay->isGcash() ? 'GCash' : 'Cashier’s Office' }}@if ($pay->reference_number), ref {{ $pay->reference_number }}@endif</span>
                                @endif
                            </div>

                            @if ($docRequest)
                                @if ($docRequest->payment_status === 'verified')

                                @elseif ($docRequest->payment_status === 'pending_verification')
                                    <p class="dr-pay-text">GCash proof submitted. Awaiting Cashier verification.@if($docRequest->proof_of_payment) <a href="{{ route('portal.appointments.document-payment.proof', $docRequest) }}" target="_blank" rel="noopener">View submitted proof</a>@endif</p>
                                @else
                                    @if ($docRequest->payment_status === 'rejected')
                                        <div class="dr-remark" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><span>{{ $docRequest->payment_rejection_reason ?: 'The Cashier could not verify this payment. Submit corrected details or pay at the Cashier.' }}</span></div>
                                    @else
                                        <p class="dr-pay-text">Pay ₱{{ number_format($docRequest->fee, 2) }} at the Cashier’s Office.</p>
                                    @endif
                                    @if ($gcashQrReady && $docRequest->status !== 'released')
                                        <details class="dr-gcash-inline">
                                            <summary>{{ $docRequest->payment_status === 'rejected' ? 'Resubmit GCash proof' : 'Pay by GCash instead' }}</summary>
                                            <div class="dr-gcash-inline-body">
                                                <img src="{{ route('portal.appointments.gcash.qr') }}" alt="Official DTC GCash QR code" width="120" height="120" loading="lazy">
                                                <p>Send exactly <strong>₱{{ number_format($docRequest->fee, 2) }}</strong> to {{ \App\Models\Setting::get('gcash_name', 'DTC') }} ({{ \App\Models\Setting::get('gcash_number', 'see official QR') }}), then submit the reference and receipt.</p>
                                                <form method="POST" action="{{ route('portal.appointments.document-payment.gcash', $docRequest) }}" enctype="multipart/form-data">
                                                    @csrf
                                                    <label class="dr-label">GCash reference number<input class="dr-input" name="reference_number" maxlength="100" required></label>
                                                    <label class="dr-label">Receipt image or PDF<input class="dr-input" type="file" name="proof_of_payment" accept=".jpg,.jpeg,.png,.pdf" required></label>
                                                    <button class="dr-btn dr-btn--primary dr-btn--sm" type="submit">Submit proof for verification</button>
                                                </form>
                                            </div>
                                        </details>
                                    @endif
                                @endif
                            @elseif (! $pay)
                                <p class="dr-pay-text">Pay at the Cashier or by GCash.</p>
                                <div class="dr-actions">
                                    <button type="button" class="dr-btn dr-btn--primary dr-btn--sm" data-toggle="modal" data-target="#gcashModal{{ $appointment->id }}">Pay with GCash</button>
                                </div>
                            @elseif ($payRejected)
                                <div class="dr-remark" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i><span>{{ $pay->rejection_reason ?: 'The Cashier couldn’t verify this payment. Contact the Cashier for next steps.' }}</span></div>
                                <div class="dr-actions">
                                    <button type="button" class="dr-btn dr-btn--primary dr-btn--sm" data-toggle="modal" data-target="#gcashModal{{ $appointment->id }}">Resubmit GCash proof</button>
                                </div>
                            @elseif ($pay->status === 'verified')
                                <p class="dr-pay-text">Payment verified.</p>
                            @elseif ($pay->isGcash())
                                <p class="dr-pay-text">
                                    GCash receipt submitted. Awaiting Cashier verification.
                                    @if ($pay->proof_of_payment)
                                        <a href="{{ route('portal.appointments.payment.proof', $pay) }}" target="_blank" rel="noopener">View submitted proof</a>
                                    @endif
                                </p>
                            @else
                                <p class="dr-pay-text">Pay at the Cashier’s Office.</p>
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
                                        data-dtc-confirm-title="Cancel appointment?"
                                        data-dtc-confirm-message="Are you sure you want to cancel this appointment? You may need to book a new slot."
                                        data-dtc-confirm-ok="Cancel"
                                        data-dtc-confirm-cancel="Keep Appointment"
                                        data-dtc-confirm-tone="danger"
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
        @if($appointment->status !== 'cancelled' && ! $appointment->documentRequest)
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
    <link rel="stylesheet" href="{{ asset('css/alumni-request-history.css') }}">
@endsection

@section('js')
    <script src="{{ asset('js/appointments-index.js') }}"></script>
    <script src="{{ asset('js/document-request.js') }}" defer></script>
@endsection
