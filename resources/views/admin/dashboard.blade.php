@extends('adminlte::page')
@include('partials.navbar')


@section('title', 'Admin Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">Admin Dashboard</h4>
            <p class="mb-0 u-text-secondary-sm" >Overview of the system</p>
        </div>
        <div>
            <a href="{{ route('admin.users.index') }}" class="dtc-btn dtc-btn-primary dtc-header-btn">
                <i data-lucide="plus"></i>
                <span class="dtc-header-btn-label">Add New User</span>
            </a>
        </div>
    </div>
@endsection

@section('content')

{{-- KPI ROW --}}
<div class="row mb-2">
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="graduation-cap" color="primary"
            label="Students" value="{{ number_format($totalStudents) }}"
            href="{{ route('admin.users.index') }}" link-text="Registered learners" />
    </div>
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="award" color="info"
            label="Alumni" value="{{ number_format($totalAlumni) }}"
            note="Total Registered" />
    </div>
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="file-text" color="warning"
            label="Pending Enrollments" value="{{ number_format($pendingEnrollments) }}"
            note="Needs Approval" />
    </div>
    <div class="col-lg-3 col-md-6 col-12 mb-3">
        <x-dtc.stat-card
            icon="calendar-check" color="success"
            label="Appointments Today" value="{{ number_format($appointmentsToday) }}"
            note="Scheduled visits" />
    </div>
    <div class="col-12 mb-3">
        <x-dtc.stat-card
            icon="coins" color="neutral"
            label="Total Revenue" value="₱{{ number_format($totalRevenue, 2) }}"
            note="Total Collection from {{ $totalPaid }} transaction{{ $totalPaid !== 1 ? 's' : '' }}" />
    </div>
</div>

{{-- CHARTS ROW --}}
<div class="row">

    {{-- Enrollment Overview Chart --}}
    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Enrollment Overview</span>
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
            <div class="card-header font-weight-bold">Payment Statistics</div>
            <div class="card-body">
                <div class="d-flex justify-content-center mb-3">
                    <div style="position:relative; width:180px; height:180px;">
                        <canvas id="paymentChart"></canvas>
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center;">
                            <div style="font-size:20px; font-weight:700;">₱{{ number_format($totalRevenue, 0) }}</div>
                            <div style="font-size:10px; color:var(--dtc-text-secondary); font-weight:600;">TOTAL</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-column gap-2 u-gap-8" >
                    <div class="d-flex justify-content-between align-items-center u-text-sm" >
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#0F4CDB;margin-right:6px;"></span>Paid</span>
                        <span class="font-weight-bold">{{ $totalPaid }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center u-text-sm" >
                        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#FFC72C;margin-right:6px;"></span>Unpaid (Approved)</span>
                        <span class="font-weight-bold">{{ $totalUnpaid }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center u-text-sm" >
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
                <span class="font-weight-bold">Recent Activities</span>
                <a href="{{ route('notifications.index') }}" style="font-size:12px; color:var(--dtc-primary);">View All</a>
            </div>
            <div class="card-body p-3">
                @forelse ($recentActivities as $activity)
                    @php
                        $__activityTone = match ($activity->type) {
                            'success' => 'success',
                            'danger'  => 'danger',
                            'warning' => 'warning',
                            default   => 'info',
                        };
                        $__activityIcon = match ($activity->type) {
                            'success' => 'check',
                            'danger'  => 'x',
                            'warning' => 'alert-triangle',
                            default   => 'info',
                        };
                    @endphp
                <div class="activity-item">
                    <div class="activity-icon dtc-icon-swatch is-{{ $__activityTone }}">
                        <i data-lucide="{{ $__activityIcon }}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="activity-text">{{ $activity->title }}</div>
                        <div class="activity-time">{{ $activity->created_at->diffForHumans() }}</div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4 u-muted" >
                    <i data-lucide="bell-off" class="mb-2" style="width:2em;height:2em"></i>
                    <p  class="u-text-sm">No recent activities.</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Upcoming Appointments --}}
    <div class="col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Upcoming Appointments</span>
                <a href="{{ route('registrar.appointments.index') }}" style="font-size:12px; color:var(--dtc-primary);">View All</a>
            </div>
            <div class="card-body p-0">
                @forelse ($upcomingAppointments as $appt)
                <div style="padding:14px 20px; border-bottom:1px solid var(--dtc-border-soft); display:flex; align-items:center; gap:12px;">
                    <div class="dtc-date-chip" style="min-width:50px; padding:8px 10px;">
                        {{ \Carbon\Carbon::parse($appt->slot->date)->format('M') }}<br>
                        <span class="dtc-date-chip-day" style="font-size:18px;">{{ \Carbon\Carbon::parse($appt->slot->date)->format('d') }}</span>
                    </div>
                    <div>
                        <div  class="u-text-sm-bold">{{ $appt->user->name }}</div>
                        <div  class="u-text-xs-secondary">
                            {{ ucfirst($appt->document_type) }} •
                            {{ \Carbon\Carbon::parse($appt->slot->start_time)->format('h:i A') }}
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4 u-muted" >
                    <i data-lucide="calendar" class="mb-2" style="width:2em;height:2em"></i>
                    <p  class="u-text-sm">No upcoming appointments.</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="col-lg-3 mb-3">
        <div class="card h-100">
            <div class="card-header font-weight-bold">Quick Actions</div>
            <div class="card-body p-3">
                <a href="{{ route('admin.users.index') }}" class="quick-action-btn">
                    <i data-lucide="user-plus"></i> Add New User
                </a>
                <a href="{{ route('registrar.enrollments.index') }}" class="quick-action-btn">
                    <i data-lucide="file-text"></i> Manage Enrollments
                </a>
                <a href="{{ route('registrar.appointments.slots') }}" class="quick-action-btn">
                    <i data-lucide="calendar-plus"></i> Manage Slots
                </a>
                <a href="{{ route('cashier.payments.index') }}" class="quick-action-btn">
                    <i data-lucide="banknote"></i> Process Payments
                </a>
                <a href="{{ route('admin.users.index') }}" class="quick-action-btn">
                    <i data-lucide="users"></i> All Users
                </a>
                <a href="{{ route('admin.reports.enrollment') }}" class="quick-action-btn" target="_blank">
                <i data-lucide="file-type"></i> Download Enrollment Report
                </a>
                <a href="{{ route('admin.reports.payment') }}" class="quick-action-btn" target="_blank">
                    <i data-lucide="file-type"></i> Download Payment Report
                </a>
            </div>
        </div>
    </div>

</div>

@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const chartTheme = getDtcChartTheme();

    // -- Enrollment Line Chart ------------------------------------------
    const enrollmentCtx = document.getElementById('enrollmentChart');
    if (enrollmentCtx) {
        registerDtcChart(new Chart(enrollmentCtx.getContext('2d'), {
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
                    pointBorderColor: chartTheme.pointBorder,
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
                        ticks: { font: { family: 'Poppins', size: 11 }, color: chartTheme.text }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: chartTheme.grid, drawBorder: false },
                        ticks: { font: { family: 'Poppins', size: 11 }, color: chartTheme.text, stepSize: 1 }
                    }
                }
            }
        }));
    }

    // -- Payment Donut Chart --------------------------------------------
    const paymentCtx = document.getElementById('paymentChart');
    if (paymentCtx) {
        registerDtcChart(new Chart(paymentCtx.getContext('2d'), {
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
                        backgroundColor: chartTheme.tooltipBg,
                        titleColor: chartTheme.tooltipText,
                        bodyColor: chartTheme.tooltipText,
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.raw}`
                        }
                    }
                }
            }
        }));
    }

});
</script>
@endsection