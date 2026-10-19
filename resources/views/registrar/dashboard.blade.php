@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Registrar Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Registrar Dashboard</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">Manage enrollments and appointments</p>
        </div>
        <a href="{{ route('registrar.appointments.slots') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-calendar-plus mr-1"></i> Manage Slots
        </a>
    </div>
@endsection

@section('content')

{{-- STAT CARDS --}}
<div class="row mb-2">
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-yellow">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-value">{{ $pendingEnrollments }}</div>
            <div class="stat-label">Pending Enrollments</div>
            <div class="stat-footer">For Approval</div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-blue">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-value">{{ $pendingAppointments }}</div>
            <div class="stat-label">Pending Appointments</div>
            <div class="stat-footer">For Review</div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-green">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-value">{{ $approvedToday }}</div>
            <div class="stat-label">Approved Today</div>
            <div class="stat-footer">Total</div>
        </div>
    </div>
    <div class="col-lg-3 col-6 mb-3">
        <div class="card stat-card stat-red">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-value">{{ $rejectedRequests }}</div>
            <div class="stat-label">Rejected Requests</div>
            <div class="stat-footer">Total</div>
        </div>
    </div>
</div>

{{-- MAIN ROW --}}
<div class="row">

    {{-- Pending Enrollments Table --}}
    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold" style="color:#1E293B;">Pending Enrollments</span>
                <a href="{{ route('registrar.enrollments.index') }}"
                   style="font-size:12px; color:#0F4CDB; text-decoration:none;">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Year Level</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentEnrollments as $enrollment)
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:32px; height:32px; border-radius:50%;
                                                background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                                display:flex; align-items:center; justify-content:center;
                                                color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                        {{ strtoupper(substr($enrollment->user->name, 0, 1)) }}
                                    </div>
                                    <span style="font-size:13px; font-weight:500;">{{ $enrollment->user->name }}</span>
                                </div>
                            </td>
                            <td style="font-size:13px;">{{ $enrollment->program->code }}</td>
                            <td style="font-size:13px;">{{ $enrollment->year_level }}{{ ['st','nd','rd','th'][$enrollment->year_level - 1] ?? 'th' }} Year</td>
                            <td>
                                @if($enrollment->status === 'pending')
                                    <span class="badge badge-warning">Pending</span>
                                @elseif($enrollment->status === 'approved')
                                    <span class="badge badge-success">Approved</span>
                                @else
                                    <span class="badge badge-danger">Rejected</span>
                                @endif
                            </td>
                            <td>
                                @if($enrollment->status === 'pending')
                                    <a href="{{ route('registrar.enrollments.show', $enrollment) }}"
                                       style="background:#EEF2FF; color:#0F4CDB; padding:4px 10px;
                                              border-radius:6px; font-size:11px; font-weight:600;
                                              text-decoration:none;">Review</a>
                                @else
                                    <span style="color:#94A3B8; font-size:12px;">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4" style="color:#94A3B8; font-size:13px;">
                                No pending enrollments.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Right Column --}}
    <div class="col-lg-5 mb-3">

        {{-- Notifications --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold" style="color:#1E293B;">Notifications</span>
                <a href="{{ route('notifications.index') }}" style="font-size:12px; color:#0F4CDB; text-decoration:none;">View All</a>
            </div>
            <div class="card-body p-3">
                @forelse ($recentNotifications as $notif)
                <div class="activity-item">
                    <div class="activity-icon"
                         style="background:{{ $notif->type === 'success' ? '#DCFCE7' : ($notif->type === 'danger' ? '#FEE2E2' : '#DBEAFE') }};
                                color:{{ $notif->type === 'success' ? '#15803D' : ($notif->type === 'danger' ? '#DC2626' : '#1D4ED8') }};">
                        <i class="fas {{ $notif->type === 'success' ? 'fa-check' : ($notif->type === 'danger' ? 'fa-times' : 'fa-info') }}"></i>
                    </div>
                    <div>
                        <div class="activity-text">{{ $notif->title }}</div>
                        <div class="activity-time">{{ $notif->created_at->diffForHumans() }}</div>
                    </div>
                </div>
                @empty
                <p class="text-center text-muted py-2" style="font-size:13px;">No notifications.</p>
                @endforelse
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card">
            <div class="card-header font-weight-bold" style="color:#1E293B;">Quick Actions</div>
            <div class="card-body p-3">
                <a href="{{ route('registrar.enrollments.index') }}" class="quick-action-btn">
                    <i class="fas fa-file-alt"></i> Review Enrollments
                </a>
                <a href="{{ route('registrar.appointments.index') }}" class="quick-action-btn">
                    <i class="fas fa-calendar-check"></i> Appointment Requests
                </a>
                <a href="{{ route('registrar.appointments.slots') }}" class="quick-action-btn">
                    <i class="fas fa-clock"></i> Manage Slots
                </a>
            </div>
        </div>

    </div>
</div>

@endsection