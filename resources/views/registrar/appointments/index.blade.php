@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Appointment Requests')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Appointment Requests</h4>
            <p class="mb-0 u-text-secondary-sm">Review, confirm, and manage student appointment bookings</p>
        </div>
        <a href="{{ route('registrar.appointments.slots') }}" class="btn btn-primary btn-sm dtc-header-btn">
            <i data-lucide="clock" class="mr-1"></i>
            <span class="dtc-header-btn-label">Manage Slots</span>
        </a>
    </div>
@endsection

@section('content')

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
            <table class="table mb-0">
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
                        <td>
                            <div class="u-flex-center-gap-10">
                                <div style="width:30px;height:30px;border-radius:50%;
                                            background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                            display:flex;align-items:center;justify-content:center;
                                            color:#fff;font-weight:700;font-size:11px;flex-shrink:0;">
                                    {{ strtoupper(substr($payment->appointment->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="u-text-sm-bold-primary">{{ $payment->appointment->user->name }}</div>
                                    <div class="u-text-xs-secondary">{{ $payment->appointment->user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="dtc-status-badge is-info" style="font-weight:600;">
                                {{ ucfirst($payment->appointment->document_type) }}
                            </span>
                        </td>
                        <td style="font-size:12px;color:var(--dtc-text);">
                            {{ \Carbon\Carbon::parse($payment->appointment->slot->date)->format('M d, Y') }}
                        </td>
                        <td>
                            <span style="font-size:12px;font-weight:700;font-family:monospace;
                                         color:var(--dtc-text);letter-spacing:0.5px;">
                                {{ $payment->reference_number }}
                            </span>
                        </td>
                        <td style="font-size:11px;color:var(--dtc-text-muted);">
                            {{ $payment->created_at->diffForHumans() }}
                        </td>
                        <td>
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
                                            data-dtc-confirm-title="Verify GCash Payment?"
                                            data-dtc-confirm-message="Confirm that reference number {{ $payment->reference_number }} has been validated in your GCash records."
                                            data-dtc-confirm-ok="Verify"
                                            data-dtc-confirm-type="success"
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
                                        <form method="POST"
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
                                                <button type="submit" class="btn btn-danger">
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
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap u-gap-8">
        <div class="d-flex" style="gap:6px; flex-wrap:wrap;">
            @foreach([
                ['key' => 'pending',   'label' => 'Pending',   'icon' => 'hourglass',      'badge' => 'is-warning'],
                ['key' => 'confirmed', 'label' => 'Confirmed', 'icon' => 'check-circle',   'badge' => 'is-success'],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'icon' => 'x-circle',       'badge' => 'is-danger'],
                ['key' => 'all',       'label' => 'All',       'icon' => 'list',            'badge' => 'is-info'],
            ] as $tab)
            <a href="{{ route('registrar.appointments.index', ['status' => $tab['key']]) }}"
               class="dtc-status-badge {{ $tab['badge'] }} {{ $status === $tab['key'] ? 'dtc-tab-active' : '' }}"
               style="text-decoration:none; cursor:pointer; padding:6px 14px; font-size:11px;">
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
        </div>
        <span class="u-text-secondary-sm">{{ $appointments->total() }} total</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
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
                        <td>
                            <div class="u-flex-center-gap-10">
                                <div style="width:34px;height:34px;border-radius:50%;
                                            background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                            display:flex;align-items:center;justify-content:center;
                                            color:#fff;font-weight:700;font-size:12px;flex-shrink:0;">
                                    {{ strtoupper(substr($appointment->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="u-text-sm-bold-primary">{{ $appointment->user->name }}</div>
                                    <div class="u-text-xs-secondary">{{ $appointment->user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="dtc-status-badge is-info" style="font-weight:600;">
                                {{ ucfirst($appointment->document_type) }}
                            </span>
                        </td>
                        <td>
                            <div style="font-size:13px;font-weight:600;color:var(--dtc-text);">
                                {{ \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y') }}
                            </div>
                            <div style="font-size:11px;color:var(--dtc-text-muted);">
                                {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') }}
                            </div>
                        </td>
                        <td style="font-size:12px;color:var(--dtc-text-secondary);max-width:140px;">
                            {{ $appointment->purpose ?? '—' }}
                        </td>
                        <td>
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
                        <td>
                            <x-dtc.status-badge :status="ucfirst($appointment->status)" />
                        </td>
                        <td>
                            <div class="u-actions-gap">
                                @if($appointment->status === 'pending')
                                    {{-- Confirm --}}
                                    <form id="confirm-appt-{{ $appointment->id }}"
                                          method="POST"
                                          action="{{ route('registrar.appointments.confirm', $appointment) }}">
                                        @csrf
                                        <button type="button"
                                                class="dtc-icon-btn success"
                                                title="Confirm appointment"
                                                data-dtc-confirm
                                                data-dtc-confirm-title="Confirm Appointment?"
                                                data-dtc-confirm-message="Confirm this appointment for {{ $appointment->user->name }}?"
                                                data-dtc-confirm-ok="Confirm"
                                                data-dtc-confirm-type="success"
                                                data-dtc-confirm-form="#confirm-appt-{{ $appointment->id }}">
                                            <i data-lucide="check"></i>
                                        </button>
                                    </form>

                                    {{-- Cancel --}}
                                    <button type="button"
                                            class="dtc-icon-btn danger"
                                            title="Cancel appointment"
                                            data-toggle="modal"
                                            data-target="#cancelModal{{ $appointment->id }}">
                                        <i data-lucide="x"></i>
                                    </button>
                                @else
                                    <span style="font-size:11px;color:var(--dtc-text-muted);">—</span>
                                @endif
                            </div>

                            {{-- Cancel Modal --}}
                            <div class="modal fade" id="cancelModal{{ $appointment->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST"
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
                                                <button type="submit" class="btn btn-danger">
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

@endsection

@section('css')
<style>
.dtc-tab-active {
    box-shadow: inset 0 0 0 2px currentColor;
    font-weight: 800 !important;
}
</style>
@endsection
