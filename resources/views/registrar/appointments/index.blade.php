@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Appointment Requests')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Appointment Requests</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">Review and confirm student appointment bookings</p>
        </div>
        <a href="{{ route('registrar.appointments.slots') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-clock mr-1"></i> Manage Slots
        </a>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Document Type</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($appointments as $appointment)
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:34px; height:34px; border-radius:50%;
                                        background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                        display:flex; align-items:center; justify-content:center;
                                        color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                {{ strtoupper(substr($appointment->user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                    {{ $appointment->user->name }}
                                </div>
                                <div style="font-size:11px; color:#64748B;">
                                    {{ $appointment->user->email }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span style="background:#EEF2FF; color:#0F4CDB; padding:4px 10px;
                                     border-radius:20px; font-size:12px; font-weight:600;">
                            {{ ucfirst($appointment->document_type) }}
                        </span>
                    </td>
                    <td style="font-size:13px; font-weight:500; color:#1E293B;">
                        {{ \Carbon\Carbon::parse($appointment->slot->date)->format('M d, Y') }}
                    </td>
                    <td style="font-size:13px; color:#64748B;">
                        {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') }}
                    </td>
                    <td style="font-size:12px; color:#64748B; max-width:150px;">
                        {{ $appointment->purpose ?? '—' }}
                    </td>
                    <td>
                        <span class="badge badge-warning">Pending</span>
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            {{-- Confirm --}}
                            <form method="POST"
                                  action="{{ route('registrar.appointments.confirm', $appointment) }}">
                                @csrf
                                <button type="submit"
                                        style="background:#DCFCE7; color:#15803D; border:none;
                                               padding:5px 12px; border-radius:8px; font-size:11px;
                                               font-weight:600; cursor:pointer;"
                                        onclick="return confirm('Confirm this appointment?')">
                                    <i class="fas fa-check mr-1"></i> Confirm
                                </button>
                            </form>

                            {{-- Cancel --}}
                            <button type="button"
                                    style="background:#FEE2E2; color:#DC2626; border:none;
                                           padding:5px 12px; border-radius:8px; font-size:11px;
                                           font-weight:600; cursor:pointer;"
                                    data-toggle="modal"
                                    data-target="#cancelModal{{ $appointment->id }}">
                                <i class="fas fa-times mr-1"></i> Cancel
                            </button>
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
                                            <button type="button" class="close" data-dismiss="modal">
                                                &times;
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <p style="font-size:13px; color:#64748B; margin-bottom:12px;">
                                                Please provide a reason for cancelling
                                                <strong>{{ $appointment->user->name }}</strong>'s appointment.
                                            </p>
                                            <div class="form-group">
                                                <label>Reason / Remarks</label>
                                                <textarea name="remarks" class="form-control"
                                                          rows="3" required
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
                        <i class="fas fa-calendar-check fa-3x mb-3" style="color:#E2E8F0;"></i>
                        <p style="color:#94A3B8; font-size:13px;">No pending appointment requests.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection