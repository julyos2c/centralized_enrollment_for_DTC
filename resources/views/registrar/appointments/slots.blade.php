@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Manage Appointment Slots')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Manage Appointment Slots</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">Set available dates and times for student appointments</p>
        </div>
        <a href="{{ route('registrar.appointments.index') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-calendar-check mr-1"></i> View Requests
        </a>
    </div>
@endsection

@section('content')
<div class="row">

    {{-- Add Slot Form --}}
    <div class="col-lg-4 mb-3">
        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                <i class="fas fa-plus-circle mr-2" style="color:#0F4CDB;"></i> Add New Slot
            </div>
            <div class="card-body">

                @if ($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('registrar.appointments.slots.store') }}">
                    @csrf

                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control"
                               min="{{ today()->toDateString() }}"
                               value="{{ old('date') }}" required>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label>Start Time</label>
                                <input type="time" name="start_time" class="form-control"
                                       value="{{ old('start_time') }}" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label>End Time</label>
                                <input type="time" name="end_time" class="form-control"
                                       value="{{ old('end_time') }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Max Bookings</label>
                        <input type="number" name="max_bookings" class="form-control"
                               value="{{ old('max_bookings', 5) }}" min="1" max="50" required>
                        <small style="color:#94A3B8; font-size:11px;">
                            Maximum students per slot
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-plus mr-1"></i> Add Slot
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Slots List --}}
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                <i class="fas fa-clock mr-2" style="color:#0F4CDB;"></i> Available Slots
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Max</th>
                            <th>Booked</th>
                            <th>Available</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($slots as $slot)
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="background:#EEF2FF; color:#0F4CDB; border-radius:10px;
                                                padding:6px 10px; font-size:11px; font-weight:700;
                                                text-align:center; line-height:1.3; min-width:42px;">
                                        {{ \Carbon\Carbon::parse($slot->date)->format('M') }}<br>
                                        <span style="font-size:16px;">{{ \Carbon\Carbon::parse($slot->date)->format('d') }}</span>
                                    </div>
                                    <div style="font-size:12px; color:#64748B;">
                                        {{ \Carbon\Carbon::parse($slot->date)->format('D, Y') }}
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:13px; font-weight:500; color:#1E293B;">
                                {{ \Carbon\Carbon::parse($slot->start_time)->format('h:i A') }}
                                <span style="color:#94A3B8;">—</span>
                                {{ \Carbon\Carbon::parse($slot->end_time)->format('h:i A') }}
                            </td>
                            <td>
                                <span style="font-size:13px; font-weight:600; color:#1E293B;">
                                    {{ $slot->max_bookings }}
                                </span>
                            </td>
                            <td>
                                <span style="font-size:13px; font-weight:600; color:#0F4CDB;">
                                    {{ $slot->appointments_count }}
                                </span>
                            </td>
                            <td>
                                @php $available = $slot->availableSlots(); @endphp
                                <span style="font-size:13px; font-weight:600;
                                             color:{{ $available > 0 ? '#15803D' : '#DC2626' }};">
                                    {{ $available }}
                                </span>
                            </td>
                            <td>
                                @if($slot->availableSlots() > 0)
                                    <span class="badge badge-success">Available</span>
                                @elseif($slot->appointments_count >= $slot->max_bookings)
                                    <span class="badge badge-danger">Full</span>
                                @else
                                    <span class="badge badge-warning">Nearly Full</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST"
                                      action="{{ route('registrar.appointments.slots.delete', $slot) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            style="background:#FEE2E2; color:#DC2626; border:none;
                                                   padding:5px 10px; border-radius:8px; font-size:11px;
                                                   font-weight:600; cursor:pointer;"
                                            onclick="return confirm('Delete this slot?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x mb-3" style="color:#E2E8F0;"></i>
                                <p style="color:#94A3B8; font-size:13px;">No slots added yet.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection