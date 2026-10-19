@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Appointments')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">My Appointments</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">Track your document requests and appointment bookings</p>
        </div>
        <a href="{{ route('portal.appointments.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i> Book Appointment
        </a>
    </div>
@endsection

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($appointments->isEmpty())
    {{-- Empty State --}}
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-calendar-times fa-4x mb-3" style="color:#E2E8F0;"></i>
            <h5 style="color:#1E293B; font-weight:700;">No Appointments Yet</h5>
            <p style="color:#64748B; font-size:13px; max-width:360px; margin:0 auto 20px;">
                You haven't booked any appointments yet. Book one now to request your documents from the Registrar's Office.
            </p>
            <a href="{{ route('portal.appointments.create') }}" class="btn btn-primary">
                <i class="fas fa-calendar-plus mr-1"></i> Book Appointment
            </a>
        </div>
    </div>
@else
    <div class="row">
        @foreach ($appointments as $appointment)
        <div class="col-lg-6 mb-3">
            <div class="card"
                 style="border-left:4px solid
                    {{ $appointment->status === 'confirmed' ? '#22C55E' :
                       ($appointment->status === 'pending' ? '#FFC72C' :
                       ($appointment->status === 'cancelled' ? '#EF4444' : '#3B82F6')) }};">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="background:#EEF2FF; color:#0F4CDB; border-radius:12px;
                                        padding:10px 14px; font-size:11px; font-weight:700;
                                        text-align:center; line-height:1.3; min-width:52px;">
                                {{ \Carbon\Carbon::parse($appointment->slot->date)->format('M') }}<br>
                                <span style="font-size:20px; display:block; line-height:1;">
                                    {{ \Carbon\Carbon::parse($appointment->slot->date)->format('d') }}
                                </span>
                            </div>
                            <div>
                                <div style="font-size:14px; font-weight:700; color:#1E293B;">
                                    {{ ucfirst($appointment->document_type) }}
                                </div>
                                <div style="font-size:12px; color:#64748B; margin-top:2px;">
                                    <i class="fas fa-clock mr-1"></i>
                                    {{ \Carbon\Carbon::parse($appointment->slot->start_time)->format('h:i A') }}
                                    —
                                    {{ \Carbon\Carbon::parse($appointment->slot->end_time)->format('h:i A') }}
                                </div>
                                <div style="font-size:12px; color:#64748B; margin-top:2px;">
                                    <i class="fas fa-calendar mr-1"></i>
                                    {{ \Carbon\Carbon::parse($appointment->slot->date)->format('l, F d, Y') }}
                                </div>
                            </div>
                        </div>
                        <div>
                            @if($appointment->status === 'pending')
                                <span class="badge badge-warning">Pending</span>
                            @elseif($appointment->status === 'confirmed')
                                <span class="badge badge-success">Confirmed</span>
                            @elseif($appointment->status === 'cancelled')
                                <span class="badge badge-danger">Cancelled</span>
                            @elseif($appointment->status === 'completed')
                                <span class="badge badge-info">Completed</span>
                            @endif
                        </div>
                    </div>

                    @if($appointment->purpose)
                    <div style="background:#F8FAFC; border-radius:8px; padding:10px 12px;
                                font-size:12px; color:#64748B; margin-bottom:12px;">
                        <i class="fas fa-info-circle mr-1" style="color:#0F4CDB;"></i>
                        {{ $appointment->purpose }}
                    </div>
                    @endif

                    @if($appointment->remarks)
                    <div style="background:#FEF2F2; border-radius:8px; padding:10px 12px;
                                font-size:12px; color:#DC2626; margin-bottom:12px;">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        {{ $appointment->remarks }}
                    </div>
                    @endif

                    @if($appointment->status === 'confirmed')
                    <div style="background:#F0FDF4; border-radius:8px; padding:10px 12px;
                                font-size:12px; color:#15803D; margin-bottom:12px;">
                        <i class="fas fa-check-circle mr-1"></i>
                        Your appointment is confirmed. Please be at the Registrar's Office on time.
                        Bring a valid ID.
                    </div>
                    @endif

                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:11px; color:#94A3B8;">
                            Booked {{ $appointment->created_at->diffForHumans() }}
                        </span>
                        @if($appointment->status === 'pending')
                            <form method="POST"
                                  action="{{ route('portal.appointments.cancel', $appointment) }}">
                                @csrf
                                <button type="submit"
                                        style="background:#FEE2E2; color:#DC2626; border:none;
                                               padding:6px 14px; border-radius:8px; font-size:11px;
                                               font-weight:600; cursor:pointer;"
                                        onclick="return confirm('Cancel this appointment?')">
                                    <i class="fas fa-times mr-1"></i> Cancel
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
@endif

@endsection