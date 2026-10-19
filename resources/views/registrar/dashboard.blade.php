@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Registrar Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">Registrar Dashboard</h4>
            <p class="mb-0 u-text-secondary-sm" >Manage enrollments and appointments</p>
        </div>
        <a href="{{ route('registrar.appointments.slots') }}" class="dtc-btn dtc-btn-primary dtc-header-btn">
            <i data-lucide="calendar-plus"></i>
            <span class="dtc-header-btn-label">Manage Slots</span>
        </a>
    </div>
@endsection

@section('content')

{{-- KPI ROW --}}
<div class="row mb-2">
    <div class="col-lg col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="hourglass" color="warning"
            label="Pending Enrollments" value="{{ $pendingEnrollments }}"
            href="{{ route('registrar.enrollments.index') }}" link-text="For Approval" />
    </div>
    <div class="col-lg col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="calendar-check" color="primary"
            label="Pending Appointments" value="{{ $pendingAppointments }}"
            href="{{ route('registrar.appointments.index') }}" link-text="For Review" />
    </div>
    <div class="col-lg col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="check-check" color="success"
            label="Approved Today" value="{{ $approvedToday }}"
            note="Total" />
    </div>
    <div class="col-lg col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="x-circle" color="danger"
            label="Rejected Requests" value="{{ $rejectedRequests }}"
            href="{{ route('notifications.index') }}" link-text="Review notices" />
    </div>
    <div class="col-lg col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="file-text" color="info"
            label="Pending Applications" value="{{ $pendingApplications }}"
            href="{{ route('registrar.applications.index') }}" link-text="For Review" />
    </div>
</div>

{{-- MAIN ROW --}}
<div class="row">

    {{-- Pending Enrollments Table --}}
    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Pending Enrollments</span>
                <a href="{{ route('registrar.enrollments.index') }}"
                   style="font-size:12px; color:var(--dtc-primary); text-decoration:none;">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
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
                                <div  class="u-flex-center-gap-10">
                                    <div style="width:32px; height:32px; border-radius:50%;
                                                background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                                display:flex; align-items:center; justify-content:center;
                                                color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                        {{ strtoupper(substr($enrollment->user->name, 0, 1)) }}
                                    </div>
                                    <span style="font-size:13px; font-weight:500;">{{ $enrollment->user->name }}</span>
                                </div>
                            </td>
                            <td  class="u-text-sm">{{ $enrollment->program->code }}</td>
                            <td  class="u-text-sm">{{ $enrollment->year_level }}{{ ['st','nd','rd','th'][$enrollment->year_level - 1] ?? 'th' }} Year</td>
                            <td>
                                <x-dtc.status-badge :status="$enrollment->status" />
                            </td>
                            <td>
                                @if($enrollment->status === 'pending')
                                    <button type="button" class="dtc-review-btn"
                                            data-url="{{ route('registrar.enrollments.show', $enrollment) }}">
                                        Review
                                    </button>
                                @else
                                    <span style="color:var(--dtc-text-muted); font-size:12px;">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4" style="color:var(--dtc-text-muted); font-size:13px;">
                                No pending enrollments.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            <div class="card-footer u-panel-footer">
                <a href="{{ route('registrar.enrollments.index') }}" class="u-text-sm" style="color:var(--dtc-primary); text-decoration:none;">View All Enrollments →</a>
            </div>
        </div>
    </div>

    {{-- Right Column --}}
    <div class="col-lg-5 mb-3">

        {{-- Notifications --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Notifications</span>
                <a href="{{ route('notifications.index') }}" style="font-size:12px; color:var(--dtc-primary); text-decoration:none;">View All</a>
            </div>
            <div class="card-body p-3">
                @forelse ($recentNotifications as $notif)
                <div class="activity-item">
                    <div class="activity-icon dtc-icon-swatch is-{{ $notif->type === 'success' ? 'success' : ($notif->type === 'danger' ? 'danger' : 'info') }}">
                        <i data-lucide="{{ $notif->type === 'success' ? 'check' : ($notif->type === 'danger' ? 'x' : 'info') }}"></i>
                    </div>
                    <div>
                        <div class="activity-text">{{ $notif->title }}</div>
                        <div class="activity-time">{{ $notif->created_at->diffForHumans() }}</div>
                    </div>
                </div>
                @empty
                <p class="text-center text-muted py-2 u-text-sm" >No notifications.</p>
                @endforelse
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card">
            <div class="card-header font-weight-bold">Quick Actions</div>
            <div class="card-body p-3">
                <a href="{{ route('registrar.enrollments.index') }}" class="quick-action-btn">
                    <i data-lucide="file-text"></i> Review Enrollments
                </a>
                <a href="{{ route('registrar.applications.index') }}" class="quick-action-btn">
                    <i data-lucide="clipboard-list"></i> Review Applications
                </a>
                <a href="{{ route('registrar.appointments.index') }}" class="quick-action-btn">
                    <i data-lucide="calendar-check"></i> Appointment Requests
                </a>
                <a href="{{ route('registrar.appointments.slots') }}" class="quick-action-btn">
                    <i data-lucide="clock"></i> Manage Slots
                </a>
            </div>
        </div>

    </div>
</div>

{{-- RECENT APPLICATIONS ROW --}}
<div class="row">
    <div class="col-12 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Recent Applications</span>
                <a href="{{ route('registrar.applications.index') }}"
                   style="font-size:12px; color:var(--dtc-primary); text-decoration:none;">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Applicant Name</th>
                            <th>Reference No.</th>
                            <th>Course</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentApplications as $application)
                        <tr>
                            <td>
                                <div  class="u-flex-center-gap-10">
                                    <div style="width:32px; height:32px; border-radius:50%;
                                                background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                                                display:flex; align-items:center; justify-content:center;
                                                color:#fff; font-weight:700; font-size:12px; flex-shrink:0;">
                                        {{ strtoupper(substr($application->first_name, 0, 1)) }}
                                    </div>
                                    <span style="font-size:13px; font-weight:500;">{{ $application->first_name }} {{ $application->last_name }}</span>
                                </div>
                            </td>
                            <td  class="u-text-sm">{{ $application->reference_no }}</td>
                            <td  class="u-text-sm">{{ $application->program->code ?? '—' }}</td>
                            <td>
                                <x-dtc.status-badge :status="$application->status" />
                            </td>
                            <td>
                                @if(in_array($application->status, ['submitted', 'under_review']))
                                    <a href="{{ route('registrar.applications.show', $application) }}" class="dtc-review-btn">
                                        Review
                                    </a>
                                @else
                                    <span style="color:var(--dtc-text-muted); font-size:12px;">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4" style="color:var(--dtc-text-muted); font-size:13px;">
                                No applications yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            <div class="card-footer u-panel-footer">
                <a href="{{ route('registrar.applications.index') }}" class="u-text-sm" style="color:var(--dtc-primary); text-decoration:none;">View All Applications →</a>
            </div>
        </div>
    </div>
</div>

@include('registrar.enrollments._review-modal')

@endsection