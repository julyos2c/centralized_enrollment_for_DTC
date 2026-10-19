@extends('adminlte::page')
@include('partials.navbar')


@section('title', 'Admin Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">Admin Dashboard</h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">Overview of the system</p>
        </div>
        <div>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Add New User
            </a>
        </div>
    </div>
@endsection

@section('content')

{{-- ══ STAT CARDS ══════════════════════════════════════════════════════ --}}
<div class="row mb-2">

    <div class="col-lg-4 col-md-6 col-12 mb-3">
        <div class="card stat-card stat-blue">
            <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="stat-value">{{ number_format($totalStudents) }}</div>
            <div class="stat-label">Students</div>
            <div class="stat-footer">Total Registered</div>
        </div>
    </div>

    <div class="col-lg-4 col-md-6 col-12 mb-3">
        <div class="card stat-card stat-purple">
            <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
            <div class="stat-value">{{ number_format($totalAlumni) }}</div>
            <div class="stat-label">Alumni</div>
            <div class="stat-footer">Total Registered</div>
        </div>
    </div>

    <div class="col-lg-4 col-md-6 col-12 mb-3">
        <div class="card stat-card stat-yellow">
            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
            <div class="stat-value">{{ number_format($pendingEnrollments) }}</div>
            <div class="stat-label">Pending Enrollments</div>
            <div class="stat-footer">Needs Approval</div>
        </div>
    </div>

    <div class="col-lg-6 col-md-6 col-12 mb-3">
        <div class="card stat-card stat-green">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-value">{{ number_format($appointmentsToday) }}</div>
            <div class="stat-label">Appointments Today</div>
            <div class="stat-footer">Scheduled</div>
        </div>
    </div>

    <div class="col-lg-6 col-md-12 col-12 mb-3">
        <div class="card stat-card" style="background: linear-gradient(135deg, #0F4CDB, #1a5feb);">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-value">₱{{ number_format($totalRevenue, 2) }}</div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-footer">Total Collection</div>
        </div>
    </div>

</div>

{{-- ══ CHARTS ROW ═══════════════════════════════════════════════════════ --}}
<div class="row">

    {{-- Enrollment Overview Chart --}}
    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold" style="color:#1E293B;">Enrollment Overview</span>
                <span class="badge badge-primary" style="font-size:11px;">{{ now()->year }}</span>
            </div>
            <div class="card-body">
                <canvas id="enrollmentChart" height="110"></canvas>
            </div>
        </div>
    </div>

    {{-- Payment Statistics Donut --}}
    <div class="col-lg-5 mb-3">
        <div class="card h-100">
            <div class="card-header font-weight-bold" style="color:#1E293B;">Payment Statistics</div>
            <div class="card-body">
                <div class="d-flex justify-content-center mb-3">
                    <div style="position:relative; width:180px; height:180px;">
                        <canvas id="paymentChart"></canvas>
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center;">
                            <div style="font-size:20px; font-weight:700; color:#1E293B;">₱{{ number_format($totalRevenue, 0) }}</div>
                            <div style="font-size:10px; color:#64748B; font-weight:600;">TOTAL</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-column gap-2" style="gap:8px;">
                    <div class="d-flex justify-content-between align-items-center" style="font-size:13px;">
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#0F4CDB;margin-right:6px;"></span>Paid</span>
                        <span class="font-weight-bold">{{ $totalPaid }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center" style="font-size:13px;">
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#FFC72C;margin-right:6px;"></span>Unpaid (Approved)</span>
                        <span class="font-weight-bold">{{ $totalUnpaid }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center" style="font-size:13px;">
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#E2E8F0;margin-right:6px;"></span>Pending</span>
                        <span class="font-weight-bold">{{ $totalPending }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- ══ BOTTOM ROW ═══════════════════════════════════════════════════════ --}}
<div class="row">

    {{-- Recent Activities --}}
    <div class="col-lg-5 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold" style="color:#1E293B;">Recent Activities</span>
                <a href="{{ route('notifications.index') }}" style="font-size:12px; color:#0F4CDB;">View All</a>
            </div>
            <div class="card-body p-3">
                @forelse ($recentActivities as $activity)
                <div class="activity-item">
                    <div class="activity-icon" style="background:
                        @if($activity->type === 'success') #DCFCE7; color:#15803D;
                        @elseif($activity->type === 'danger') #FEE2E2; color:#DC2626;
                        @elseif($activity->type === 'warning') #FEF9C3; color:#A16207;
                        @else #DBEAFE; color:#1D4ED8; @endif">
                        <i class="fas
                            @if($activity->type === 'success') fa-check
                            @elseif($activity->type === 'danger') fa-times
                            @elseif($activity->type === 'warning') fa-exclamation
                            @else fa-info @endif"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="activity-text">{{ $activity->title }}</div>
                        <div class="activity-time">{{ $activity->created_at->diffForHumans() }}</div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4" style="color:#94A3B8;">
                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                    <p style="font-size:13px;">No recent activities.</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Upcoming Appointments --}}
    <div class="col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold" style="color:#1E293B;">Upcoming Appointments</span>
                <a href="{{ route('registrar.appointments.index') }}" style="font-size:12px; color:#0F4CDB;">View All</a>
            </div>
            <div class="card-body p-0">
                @forelse ($upcomingAppointments as $appt)
                <div style="padding:14px 20px; border-bottom:1px solid #F1F5F9; display:flex; align-items:center; gap:12px;">
                    <div style="background:#EEF2FF; color:#0F4CDB; border-radius:10px; padding:8px 10px; font-size:11px; font-weight:700; text-align:center; min-width:50px; line-height:1.2;">
                        {{ \Carbon\Carbon::parse($appt->slot->date)->format('M') }}<br>
                        <span style="font-size:18px;">{{ \Carbon\Carbon::parse($appt->slot->date)->format('d') }}</span>
                    </div>
                    <div>
                        <div style="font-size:13px; font-weight:600; color:#1E293B;">{{ $appt->user->name }}</div>
                        <div style="font-size:11px; color:#64748B;">
                            {{ ucfirst($appt->document_type) }} •
                            {{ \Carbon\Carbon::parse($appt->slot->start_time)->format('h:i A') }}
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4" style="color:#94A3B8;">
                    <i class="fas fa-calendar fa-2x mb-2"></i>
                    <p style="font-size:13px;">No upcoming appointments.</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="col-lg-3 mb-3">
        <div class="card h-100">
            <div class="card-header font-weight-bold" style="color:#1E293B;">Quick Actions</div>
            <div class="card-body p-3">
                <a href="{{ route('admin.users.create') }}" class="quick-action-btn">
                    <i class="fas fa-user-plus"></i> Add New User
                </a>
                <a href="{{ route('registrar.enrollments.index') }}" class="quick-action-btn">
                    <i class="fas fa-file-alt"></i> Manage Enrollments
                </a>
                <a href="{{ route('registrar.appointments.slots') }}" class="quick-action-btn">
                    <i class="fas fa-calendar-plus"></i> Manage Slots
                </a>
                <a href="{{ route('cashier.payments.index') }}" class="quick-action-btn">
                    <i class="fas fa-money-bill-wave"></i> Process Payments
                </a>
                <a href="{{ route('admin.users.index') }}" class="quick-action-btn">
                    <i class="fas fa-users"></i> All Users
                </a>
                <a href="{{ route('admin.reports.enrollment') }}" class="quick-action-btn" target="_blank">
                <i class="fas fa-file-pdf"></i> Download Enrollment Report
                </a>
                <a href="{{ route('admin.reports.payment') }}" class="quick-action-btn" target="_blank">
                    <i class="fas fa-file-pdf"></i> Download Payment Report
                </a>
            </div>
        </div>
    </div>

</div>

@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Enrollment Line Chart ──────────────────────────────────────────
    const enrollmentCtx = document.getElementById('enrollmentChart');
    if (enrollmentCtx) {
        new Chart(enrollmentCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
                datasets: [{
                    label: 'Enrollments',
                    data: @json($enrollmentData),
                    borderColor: '#0F4CDB',
                    backgroundColor: 'rgba(15, 76, 219, 0.08)',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#0F4CDB',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Poppins', size: 11 }, color: '#94A3B8' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#F1F5F9', drawBorder: false },
                        ticks: { font: { family: 'Poppins', size: 11 }, color: '#94A3B8', stepSize: 1 }
                    }
                }
            }
        });
    }

    // ── Payment Donut Chart ────────────────────────────────────────────
    const paymentCtx = document.getElementById('paymentChart');
    if (paymentCtx) {
        new Chart(paymentCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Unpaid', 'Pending'],
                datasets: [{
                    data: [{{ $totalPaid }}, {{ $totalUnpaid }}, {{ $totalPending }}],
                    backgroundColor: ['#0F4CDB', '#FFC72C', '#E2E8F0'],
                    borderWidth: 0,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.raw}`
                        }
                    }
                }
            }
        });
    }

});
</script>
@endsection