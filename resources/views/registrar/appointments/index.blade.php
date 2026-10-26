@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Appointment Requests')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Appointment Requests</h4>
            <p class="mb-0 u-text-secondary-sm">Review bookings and confirm appointments.</p>
        </div>
        <a href="{{ route('registrar.appointments.slots') }}" class="btn btn-primary dtc-header-btn rg-manage-slots">
            <i data-lucide="clock" class="mr-1"></i>
            <span class="dtc-header-btn-label">Manage Slots</span>
        </a>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/dtc-filters.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
@endpush
@section('content')
<div class="registrar-workspace rg-appointments">

<form method="GET" action="{{ route('registrar.appointments.index') }}" class="dtc-fl rg-appointment-search" aria-label="Search and filter appointments">
    <input type="hidden" name="status" value="{{ $status }}">
    <div class="dtc-fl-toolbar">
        <div class="dtc-pay-search dtc-fl-search">
            <i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i>
            <input type="search" name="search" class="dtc-pay-search-input" value="{{ request('search') }}" placeholder="Search appointments" aria-label="Search appointments">
        </div>
        <button type="button" class="dtc-fl-toggle" data-toggle="collapse" data-target="#appointmentFilters" aria-expanded="false" aria-controls="appointmentFilters">
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i><span>Filters</span>
        </button>
        <button type="submit" class="sr-only">Search appointments</button>
    </div>
    <div class="collapse" id="appointmentFilters">
        <div class="dtc-fl-panel">
            @include('partials.table-rows', ['tableRecords' => $appointments, 'tableId' => 'registrar-table-2', 'fixedRows' => true])
            <div class="dtc-fl-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="{{ route('registrar.appointments.index', ['status' => $status]) }}" class="dtc-fl-clear">Reset</a>
            </div>
        </div>
    </div>
</form>



{{-- ── Pending GCash Payments Alert ──────────────────────────────────────── --}}
@if($pendingPayments->isNotEmpty())
<div class="card mb-3" style="border-left: 4px solid var(--dtc-warning, #F59E0B);">
    <div class="card-header d-flex align-items-center" style="gap:10px;">
        <div class="dtc-icon-swatch is-warning">
            <i data-lucide="smartphone"></i>
        </div>
        <div class="flex-1">
            <div class="font-weight-bold u-text" style="font-size:13px;">
                GCash Payments Awaiting Verification
            </div>
            <div class="u-text-secondary-sm">{{ $pendingPayments->count() }} submission{{ $pendingPayments->count() !== 1 ? 's' : '' }} need review</div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            @include('partials.table-rows', ['tableRecords' => $pendingPayments, 'tableId' => 'registrar-table-1', 'fixedRows' => true])
<table id="registrar-table-1" class="rg-record-table table mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Document</th>
                        <th>Date</th>
                        <th>Reference No.</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingPayments as $payment)
                    <tr>
                        <td data-reg-label="Student">
                            <div class="u-flex-center-gap-10">
                                <x-dtc.user-avatar :user="$payment->appointment->user" style="width:30px;height:30px;border-radius:50%;
                                            background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                            display:flex;align-items:center;justify-content:center;
                                            color:#fff;font-weight:700;font-size:11px;flex-shrink:0;" />
                                <div>
                                    <div class="u-text-sm-bold-primary">{{ $payment->appointment->user->name }}</div>
                                    <div class="u-text-xs-secondary">{{ $payment->appointment->user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td data-reg-label="Document">
                            <span class="dtc-status-badge is-info" style="font-weight:600;">
                                {{ ucfirst($payment->appointment->document_type) }}
                            </span>
                        </td>
                        <td data-reg-label="Date" style="font-size:12px;color:var(--dtc-text);">
                            {{ \Carbon\Carbon::parse($payment->appointment->slot->date)->format('M d, Y') }}
                        </td>
                        <td data-reg-label="Reference No.">
                            <span style="font-size:12px;font-weight:700;font-family:monospace;
                                         color:var(--dtc-text);letter-spacing:0.5px;">
                                {{ $payment->reference_number }}
                            </span>
                        </td>
                        <td data-reg-label="Submitted" style="font-size:11px;color:var(--dtc-text-muted);">
                            {{ $payment->created_at->diffForHumans() }}
                        </td>
                        <td data-reg-label="Actions">
                            <div class="u-actions-gap">
                                {{-- View Proof --}}
                                <a href="{{ route('registrar.appointments.payments.proof', $payment) }}"
                                   target="_blank"
                                   class="dtc-icon-btn"
                                   title="View proof of payment">
                                    <i data-lucide="eye"></i>
                                </a>

                                {{-- Verify --}}
                                <form id="verify-pay-{{ $payment->id }}"
                                      method="POST"
                                      action="{{ route('registrar.appointments.payments.verify', $payment) }}">
                                    @csrf
                                    <button type="button"
                                            class="dtc-icon-btn success"
                                            title="Verify payment"
                                            data-dtc-confirm
                                            data-dtc-confirm-title="Verify GCash payment?"
                                            data-dtc-confirm-message="Verify the GCash payment from {subject} after confirming reference {{ $payment->reference_number }} in the payment records."
                                            data-dtc-confirm-subject="{{ $payment->appointment->user->name }}"
                                            data-dtc-confirm-ok="Verify"
                                            data-dtc-confirm-tone="success"
                                            data-dtc-confirm-form="#verify-pay-{{ $payment->id }}">
                                        <i data-lucide="check-circle"></i>
                                    </button>
                                </form>

                                {{-- Reject --}}
                                <button type="button"
                                        class="dtc-icon-btn danger"
                                        title="Reject payment"
                                        data-toggle="modal"
                                        data-target="#rejectPayModal{{ $payment->id }}">
                                    <i data-lucide="x-circle"></i>
                                </button>
                            </div>

                            {{-- Reject Payment Modal --}}
                            <div class="modal fade" id="rejectPayModal{{ $payment->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" id="reject-appointment-payment-{{ $payment->id }}"
                                              action="{{ route('registrar.appointments.payments.reject', $payment) }}">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject GCash Payment</h5>
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="d-flex align-items-center mb-3"
                                                     style="gap:10px; background:var(--dtc-surface-soft);
                                                            border-radius:10px; padding:12px;">
                                                    <i data-lucide="smartphone"
                                                       style="color:var(--dtc-primary);width:18px;height:18px;flex-shrink:0;"></i>
                                                    <div style="font-size:12px;color:var(--dtc-text-secondary);">
                                                        <strong style="color:var(--dtc-text);">{{ $payment->appointment->user->name }}</strong>
                                                        submitted ref <strong>#{{ $payment->reference_number }}</strong>
                                                        {{ $payment->created_at->diffForHumans() }}
                                                    </div>
                                                </div>
                                                <div class="form-group mb-0">
                                                    <label style="font-size:13px;font-weight:600;color:var(--dtc-text);">
                                                        Rejection Reason <span style="color:var(--dtc-danger);">*</span>
                                                    </label>
                                                    <textarea name="rejection_reason"
                                                              class="form-control" rows="3" required
                                                              placeholder="e.g. Reference number does not match our records..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary"
                                                        data-dismiss="modal">Back</button>
                                                <button type="button" class="btn btn-danger" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Reject payment?" data-dtc-confirm-message="The GCash payment from {subject} will be rejected with the entered reason." data-dtc-confirm-subject="{{ $payment->appointment->user->name }}" data-dtc-confirm-ok="Reject" data-dtc-confirm-form="#reject-appointment-payment-{{ $payment->id }}">
                                                    Reject Payment
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ── Status Filter Tabs ─────────────────────────────────────────────────── --}}
<div class="card rg-appointment-queue">
    <div class="card-header rg-appointment-toolbar">
        <nav class="rg-appointment-tabs" aria-label="Appointment status">
            @foreach([
                ['key' => 'pending',   'label' => 'Pending',   'icon' => 'hourglass',      'badge' => 'is-warning'],
                ['key' => 'confirmed', 'label' => 'Confirmed', 'icon' => 'check-circle',   'badge' => 'is-success'],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'icon' => 'x-circle',       'badge' => 'is-danger'],
                ['key' => 'all',       'label' => 'All',       'icon' => 'list',            'badge' => 'is-info'],
            ] as $tab)
            <a href="{{ route('registrar.appointments.index', array_merge(request()->only(['search', 'per_page']), ['status' => $tab['key']])) }}"
               aria-current="{{ $status === $tab['key'] ? 'page' : 'false' }}"
               class="rg-appointment-tab {{ $status === $tab['key'] ? 'is-active' : '' }}"
               >
                <i data-lucide="{{ $tab['icon'] }}" style="width:11px;height:11px;"></i>
                {{ $tab['label'] }}
                @if(isset($counts[$tab['key']]) && $counts[$tab['key']] > 0)
                    <span style="background:rgba(0,0,0,0.15); border-radius:20px;
                                 padding:1px 7px; margin-left:4px; font-size:10px;">
                        {{ $counts[$tab['key']] }}
                    </span>
                @endif
            </a>
            @endforeach
        </nav>
        <div class="rg-appointment-options">
            <span class="u-text-secondary-sm">{{ $appointments->total() }} {{ $appointments->total() === 1 ? 'booking' : 'bookings' }}</span>

        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
<table id="registrar-table-2" class="rg-record-table table mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Document</th>
                        <th>Date &amp; Time</th>
                        <th>Purpose</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($appointments as $appointment)
                    @php $pay = $appointment->latestPayment; @endphp
                    <tr>
                        <td data-reg-label="Student">
                            <div class="u-flex-center-gap-10">
                                <x-dtc.user-avatar :user="$appointment->user" style="width:34px;height:34px;border-radius:50%;
                                            background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                            display:flex;align-items:center;justify-content:center;
                                            color:#fff;font-weight:700;font-size:12px;flex-shrink:0;" />
                                <div>
                                    <div class="u-text-sm-bold-primary">{{ $appointment->user->name }}</div>
                                    <div class="u-text-xs-secondary">{{ $appointment->user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td data-reg-label="Document">
                            <span class="dtc-status-badge is-info" style="font-weight:600;">
                                {{ ucfirst($appointment->document_type) }}
                            </span>
                        </td>
                        <td data-reg-label="Date &amp; Time">
                            <div style="font-size:13px;font-weight:600;color:var(--dtc-text);">
                                {{ \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y') }}
                            </div>
                            <div style="font-size:11px;color:var(--dtc-text-muted);">
                                {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') }}
                            </div>
                        </td>
                        <td data-reg-label="Purpose" style="font-size:12px;color:var(--dtc-text-secondary);max-width:140px;">
                            {{ $appointment->purpose ?? '—' }}
                        </td>
                        <td data-reg-label="Payment">
                            @if(!$pay)
                                <span style="font-size:11px;color:var(--dtc-text-muted);">—</span>
                            @elseif($pay->payment_method === 'walk_in')
                                <span class="dtc-status-badge is-info" style="font-size:10px;">
                                    <i data-lucide="building-2" style="width:10px;height:10px;"></i> Walk-in
                                </span>
                            @elseif($pay->status === 'verified')
                                <span class="dtc-status-badge is-success" style="font-size:10px;">
                                    <i data-lucide="check-circle" style="width:10px;height:10px;"></i> GCash Verified
                                </span>
                            @elseif($pay->status === 'pending')
                                <span class="dtc-status-badge is-warning" style="font-size:10px;">
                                    <i data-lucide="clock" style="width:10px;height:10px;"></i> GCash Pending
                                </span>
                            @elseif($pay->status === 'rejected')
                                <span class="dtc-status-badge is-danger" style="font-size:10px;">
                                    <i data-lucide="x-circle" style="width:10px;height:10px;"></i> GCash Rejected
                                </span>
                            @endif
                        </td>
                        <td data-reg-label="Status">
                            <x-dtc.status-badge :status="ucfirst($appointment->status)" />
                        </td>
                        <td data-reg-label="Actions">
                            <div class="u-actions-gap">
                                @if($appointment->status === 'pending')
                                    {{-- Confirm --}}
                                    <form id="confirm-appt-{{ $appointment->id }}"
                                          method="POST"
                                          action="{{ route('registrar.appointments.confirm', $appointment) }}">
                                        @csrf
                                        <button type="button"
                                                class="dtc-icon-btn success rg-booking-confirm"
                                                title="Confirm appointment"
                                                data-dtc-confirm
                                                data-dtc-confirm-title="Confirm appointment?"
                                                data-dtc-confirm-message="The appointment for {subject} will be confirmed."
                                                data-dtc-confirm-subject="{{ $appointment->user->name }}"
                                                data-dtc-confirm-ok="Confirm"
                                                data-dtc-confirm-tone="success"
                                                data-dtc-confirm-form="#confirm-appt-{{ $appointment->id }}">
                                            <i data-lucide="check"></i><span class="rg-appointment-action-label">Confirm</span>
                                        </button>
                                    </form>

                                    {{-- Cancel --}}
                                    <button type="button"
                                            class="dtc-icon-btn danger rg-booking-cancel"
                                            title="Cancel appointment"
                                            data-toggle="modal"
                                            data-target="#cancelModal{{ $appointment->id }}">
                                        <i data-lucide="x"></i><span class="rg-appointment-action-label">Cancel</span>
                                    </button>
                                @else
                                    <span style="font-size:11px;color:var(--dtc-text-muted);">—</span>
                                @endif
                            </div>

                            {{-- Cancel Modal --}}
                            <div class="modal fade" id="cancelModal{{ $appointment->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" id="cancel-appointment-{{ $appointment->id }}"
                                              action="{{ route('registrar.appointments.cancel', $appointment) }}">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title">Cancel Appointment</h5>
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body">
                                                <p style="font-size:13px;color:var(--dtc-text-secondary);margin-bottom:12px;">
                                                    Provide a reason for cancelling
                                                    <strong>{{ $appointment->user->name }}</strong>'s appointment.
                                                </p>
                                                <div class="form-group">
                                                    <label>Reason / Remarks</label>
                                                    <textarea name="remarks" class="form-control" rows="3"
                                                              required
                                                              placeholder="e.g. Slot unavailable, rescheduling required..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary"
                                                        data-dismiss="modal">Back</button>
                                                <button type="button" class="btn btn-danger" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Cancel appointment?" data-dtc-confirm-message="The appointment for {subject} will be cancelled with the entered reason." data-dtc-confirm-subject="{{ $appointment->user->name }}" data-dtc-confirm-ok="Cancel" data-dtc-confirm-form="#cancel-appointment-{{ $appointment->id }}">
                                                    Confirm Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i data-lucide="calendar-check" class="mb-3 u-border-color" style="width:3em;height:3em;"></i>
                            <p style="color:var(--dtc-text-muted);font-size:13px;">
                                No {{ $status !== 'all' ? $status : '' }} appointments found.
                            </p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-footer u-panel-footer">
        <div class="d-flex justify-content-between align-items-center flex-wrap u-gap-15">
            <small class="u-text-secondary">
                Showing
                <strong>{{ $appointments->firstItem() ?? 0 }}</strong>
                to
                <strong>{{ $appointments->lastItem() ?? 0 }}</strong>
                of
                <strong>{{ $appointments->total() }}</strong>
                appointments
            </small>
            @if($appointments->hasPages())
            <div>{{ $appointments->onEachSide(1)->links('pagination::bootstrap-4') }}</div>
            @endif
        </div>
    </div>
</div>


</div>
@endsection

@section('css')
<style>
.dtc-tab-active {
    box-shadow: inset 0 0 0 2px currentColor;
    font-weight: 800 !important;
}
</style>
@endsection
