@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">Student Dashboard</h4>
            <p class="mb-0 u-text-secondary-sm" >Welcome to Danao Technological College!</p>
        </div>
    </div>
@endsection

@section('content')

@php
    if (!$latestEnrollment) {
        $appStatusLabel = 'Not Started';
    } elseif ($latestEnrollment->status === 'approved' && $latestEnrollment->is_paid) {
        $appStatusLabel = 'Enrolled ✓';
    } elseif ($latestEnrollment->status === 'approved') {
        $appStatusLabel = 'Approved';
    } elseif ($latestEnrollment->status === 'pending') {
        $appStatusLabel = 'In Progress';
    } elseif ($latestEnrollment->status === 'rejected') {
        $appStatusLabel = 'Rejected';
    } else {
        $appStatusLabel = 'Status Unavailable';
    }
    if (!$latestEnrollment || $latestEnrollment->status !== 'approved') {
        $paymentStatusLabel = 'Not Required';
    } elseif ($latestEnrollment->is_paid) {
        $paymentStatusLabel = 'Confirmed';
    } elseif ($latestEnrollment->payment && $latestEnrollment->payment->status === 'pending') {
        $paymentStatusLabel = 'Awaiting Verification';
    } elseif ($latestEnrollment->payment && $latestEnrollment->payment->status === 'rejected') {
        $paymentStatusLabel = 'Proof Rejected';
    } else {
        $paymentStatusLabel = 'Awaiting Payment';
    }
    $nextStepSummary = $nextSteps[0]['label'] ?? 'Review enrollment record';
@endphp

<div class="dtc-portal-dashboard">
@if (session('success'))
    <div class="alert alert-success" role="status"><i data-lucide="circle-check" class="mr-2"></i>{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert" class="mr-2"></i>{{ session('error') }}</div>
@endif
<div class="dtc-dashboard-overview mb-3" aria-label="Enrollment overview">
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Enrollment</span>
        <strong>{{ $appStatusLabel }}</strong>
        <a href="{{ route('portal.enrollment.index') }}">View application</a>
    </div>
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Payment</span>
        <strong>{{ $paymentStatusLabel }}</strong>
        @if($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid && (!$latestEnrollment->payment || $latestEnrollment->payment->status !== 'pending'))
            <a href="{{ route('portal.enrollment.payment-info', $latestEnrollment) }}">Payment details</a>
        @else
            <span class="dtc-overview-note">{{ $latestEnrollment && $latestEnrollment->is_paid ? 'Payment recorded' : 'No payment action due' }}</span>
        @endif
    </div>
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Next step</span>
        <strong>{{ $nextStepSummary }}</strong>
        @if(!empty($nextSteps[0]['url']))
            <a href="{{ $nextSteps[0]['url'] }}">Continue</a>
        @else
            <span class="dtc-overview-note">See the Registrar for guidance</span>
        @endif
    </div>
</div>

{{-- ══ WELCOME CARD ════════════════════════════════════════════════════ --}}
<div class="card mb-3 dtc-welcome-card">
    <div class="card-body">
        <div class="dtc-welcome-layout">
            @include('portal.partials.dashboard-avatar')
            <div class="dtc-welcome-copy">
                <h4 class="dtc-welcome-title">
                    Hello, {{ auth()->user()->name }}
                </h4>
                @if($studentProfile && $studentProfile->enrolled_ay)
                <p class="dtc-welcome-meta">
                    🎓
                    @if($studentProfile->program){{ $studentProfile->program->name }} · @endif
                    Enrolled AY {{ $studentProfile->enrolled_ay }}
                </p>
                @endif
                <p class="dtc-welcome-text">
                    @if(!$latestEnrollment)
                        Submit your application to begin enrollment.
                    @elseif($latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                        Your payment is confirmed and your enrollment is complete.
                    @elseif($latestEnrollment->status === 'approved' && $latestEnrollment->payment && $latestEnrollment->payment->status === 'pending')
                        Your payment proof is awaiting Cashier verification.
                    @elseif($latestEnrollment->status === 'approved' && $latestEnrollment->payment && $latestEnrollment->payment->status === 'rejected')
                        Your payment proof was rejected. Review the payment details and submit a corrected proof.
                    @elseif($latestEnrollment->status === 'approved')
                        Your enrollment is approved! Please proceed to the Cashier to pay ₱{{ number_format($fee, 2) }}.
                    @elseif($latestEnrollment->status === 'pending')
                        Your enrollment is under review. Please wait for the Registrar's approval.
                    @elseif($latestEnrollment->status === 'rejected')
                        Your enrollment was rejected. Review the Registrar's feedback and follow the resubmission step below.
                    @else
                        Your enrollment was not approved. Please contact the Registrar.
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>

{{-- ══ MAIN CONTENT ROW ════════════════════════════════════════════════ --}}
<div class="row">

    {{-- LEFT: Enrollment Progress + Subjects --}}
    <div class="col-lg-8">

        <section class="card mb-3 dtc-current-term" aria-labelledby="current-term-title">
            <div class="card-body">
                <div class="dtc-current-term-heading">
                    <div>
                        <span class="dtc-current-term-kicker">Academic calendar</span>
                        <h2 id="current-term-title">Current semester</h2>
                        <p>{{ $currentSemester ?: 'Semester not set' }}{{ $currentSchoolYear ? ' · AY '.$currentSchoolYear : '' }}</p>
                    </div>
                    <span class="dtc-term-badge is-{{ $termStatus }}">
                        @if($termStatus === 'in_progress') In progress
                        @elseif($termStatus === 'upcoming') Upcoming
                        @elseif($termStatus === 'ended') Term ended
                        @else Dates not set
                        @endif
                    </span>
                </div>

                @if($semesterStartsOn && $semesterEndsOn)
                    <p class="dtc-current-term-dates">
                        <i data-lucide="calendar-days" aria-hidden="true"></i>
                        {{ \Carbon\Carbon::parse($semesterStartsOn)->format('M j, Y') }} – {{ \Carbon\Carbon::parse($semesterEndsOn)->format('M j, Y') }}
                    </p>
                @else
                    <p class="dtc-current-term-dates">The Registrar has not published the semester dates yet.</p>
                @endif

                <div class="dtc-current-term-enrollment">
                    <span>Your enrollment</span>
                    @if(!$currentTermEnrollment)
                        <strong>No enrollment record for this term</strong>
                    @elseif($currentTermEnrollment->status === 'rejected')
                        <strong class="is-rejected">Returned for correction</strong>
                    @elseif($currentTermEnrollment->status === 'pending')
                        <strong class="is-pending">Awaiting Registrar review</strong>
                    @elseif($currentTermEnrollment->status === 'approved' && $currentTermEnrollment->is_paid)
                        <strong class="is-complete">Enrollment confirmed</strong>
                    @elseif($currentTermEnrollment->status === 'approved' && $currentTermEnrollment->payment && $currentTermEnrollment->payment->status === 'pending')
                        <strong class="is-pending">Payment awaiting verification</strong>
                    @elseif($currentTermEnrollment->status === 'approved' && $currentTermEnrollment->payment && $currentTermEnrollment->payment->status === 'rejected')
                        <strong class="is-rejected">Payment proof rejected</strong>
                    @else
                        <strong class="is-pending">Awaiting payment</strong>
                    @endif
                    <a href="{{ route('portal.enrollment.index') }}">Enrollment history</a>
                </div>
            </div>
        </section>

        {{-- Enrollment Progress Stepper --}}
        @if($timeline)
        @php
            $timelineCount = max(count($timeline), 2);
            $timelineProgress = $completedSteps > 0
                ? (($completedSteps - 1) / ($timelineCount - 1)) * 80
                : 0;
        @endphp
        <section class="card mb-3 dtc-enrollment-card" aria-labelledby="enrollment-progress-title">
            <div class="card-header dtc-enrollment-card-header">
                <div>
                    <h2 id="enrollment-progress-title">First enrollment progress</h2>
                    <p>Your admission and first enrollment milestones.</p>
                </div>
                <span class="dtc-enrollment-status">{{ $appStatusLabel }}</span>
            </div>
            <div class="card-body dtc-enrollment-card-body">
                <ol class="dtc-enrollment-steps" style="--enrollment-progress: {{ $timelineProgress }}%; --enrollment-step-count: {{ count($timeline) }};">
                    <li class="dtc-enrollment-track" aria-hidden="true"><span></span></li>
                    @foreach ($timeline as $i => $step)
                        @php
                            $stepState = ($step['rejected'] ?? false) ? 'rejected' : ($step['done'] ? 'done' : ($step['active'] ? 'active' : 'pending'));
                        @endphp
                        <li class="dtc-enrollment-step" data-step-state="{{ $stepState }}" aria-current="{{ $step['active'] ? 'step' : 'false' }}">
                            <span class="dtc-enrollment-marker" aria-hidden="true">
                                @if($stepState === 'rejected')
                                    <i data-lucide="x"></i>
                                @elseif($step['done'])
                                    <i data-lucide="check"></i>
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </span>
                            <span class="dtc-enrollment-step-copy">
                                <strong>{{ $step['label'] }}</strong>
                                <span>{{ $step['sublabel'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>

                {{-- Current step info --}}
                @php $activeStep = collect($timeline)->firstWhere('active', true); @endphp
                @if($activeStep)
                <div class="dtc-enrollment-notice is-active" role="status">
                    <i data-lucide="arrow-right" aria-hidden="true"></i>
                    <div><strong>Current Step: {{ $activeStep['label'] }}</strong><span>{{ $activeStep['sublabel'] }}</span></div>
                </div>
                @elseif($latestEnrollment && $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                <div class="dtc-enrollment-notice is-complete" role="status">
                    <i data-lucide="circle-check" aria-hidden="true"></i>
                    <div><strong>Enrollment complete</strong><span>All steps completed! You are officially enrolled.</span></div>
                </div>
                @elseif($latestEnrollment && $latestEnrollment->status === 'rejected')
                <div class="dtc-enrollment-notice is-rejected" role="alert">
                    <i data-lucide="circle-alert" aria-hidden="true"></i>
                    <div><strong>Enrollment needs attention</strong><span>{{ $latestEnrollment->remarks ?: 'No additional feedback was provided. Review the available next steps or contact the Registrar for guidance.' }}</span></div>
                </div>
                @endif
            </div>
        </section>
        @endif

        {{-- Enrolled Subjects — COR-style table --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Enrolled Subjects</span>
                <div class="d-flex align-items-center" style="gap:10px;">
                    @if($subjects->isNotEmpty())
                        <span style="font-size:12px; color:var(--dtc-primary); font-weight:600;">
                            {{ $subjects->count() }} Subject{{ $subjects->count() > 1 ? 's' : '' }}
                        </span>
                    @endif
                    @if($latestEnrollment && $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                        <a href="{{ route('portal.cor.show') }}"
                           class="btn btn-outline-primary btn-sm"
                           style="font-size:11px; padding:3px 10px;">
                            <i data-lucide="scroll-text" style="width:12px;height:12px;margin-right:4px;"></i>
                            View Full COR
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                @if($subjects->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-size:12.5px;">
                        <thead>
                            <tr style="background:var(--dtc-surface-soft);">
                                <th style="padding:10px 16px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--dtc-text-secondary); border-bottom:1px solid var(--dtc-border);">Course Code</th>
                                <th style="padding:10px 16px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--dtc-text-secondary); border-bottom:1px solid var(--dtc-border);">Description</th>
                                <th style="padding:10px 16px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--dtc-text-secondary); border-bottom:1px solid var(--dtc-border); text-align:center;">Units</th>
                                <th style="padding:10px 16px; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--dtc-text-secondary); border-bottom:1px solid var(--dtc-border); text-align:center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($subjects as $subject)
                            <tr style="border-bottom:1px solid var(--dtc-border-soft);">
                                <td style="padding:10px 16px; vertical-align:middle;">
                                    <span class="dtc-status-badge is-neutral" style="font-weight:700; font-size:11px;">
                                        {{ $subject->subject_code }}
                                    </span>
                                </td>
                                <td style="padding:10px 16px; vertical-align:middle; color:var(--dtc-text); font-weight:500;">
                                    {{ $subject->subject_name }}
                                </td>
                                <td style="padding:10px 16px; vertical-align:middle; text-align:center; color:var(--dtc-text); font-weight:600;">
                                    {{ rtrim(rtrim(number_format($subject->units ?? 3, 1), '0'), '.') }}
                                </td>
                                <td style="padding:10px 16px; vertical-align:middle; text-align:center;">
                                    @if($latestEnrollment && $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                                        <span class="dtc-status-badge is-success" style="font-size:10.5px;">
                                            <i data-lucide="check" style="width:10px;height:10px;"></i> Enrolled
                                        </span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'pending')
                                        <span class="dtc-status-badge is-warning" style="font-size:10.5px;">
                                            <i data-lucide="clock" style="width:10px;height:10px;"></i> Pending
                                        </span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid && $latestEnrollment->payment && $latestEnrollment->payment->status === 'pending')
                                        <span class="dtc-status-badge is-warning" style="font-size:10.5px;">Payment Verification</span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid && $latestEnrollment->payment && $latestEnrollment->payment->status === 'rejected')
                                        <span class="dtc-status-badge is-danger" style="font-size:10.5px;">Payment Rejected</span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid)
                                        <span class="dtc-status-badge is-warning" style="font-size:10.5px;">Awaiting Payment</span>
                                    @elseif($latestEnrollment && $latestEnrollment->status === 'rejected')
                                        <span class="dtc-status-badge is-danger" style="font-size:10.5px;">Rejected</span>
                                    @else
                                        <span class="dtc-status-badge is-neutral" style="font-size:10.5px;">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        @php $totalUnits = $subjects->sum(fn($s) => $s->units ?? 3); @endphp
                        <tfoot>
                            <tr style="background:var(--dtc-surface-soft);">
                                <td colspan="2" style="padding:10px 16px; font-weight:700; font-size:12px; color:var(--dtc-text); text-align:right;">
                                    Total Units
                                </td>
                                <td style="padding:10px 16px; font-weight:800; font-size:13px; color:var(--dtc-primary); text-align:center;">
                                    {{ rtrim(rtrim(number_format($totalUnits, 1), '0'), '.') }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="text-center py-4 u-muted">
                    <i data-lucide="book-open" class="mb-2" style="width:2em;height:2em"></i>
                    <p class="u-text-sm">No subjects enrolled yet.</p>
                    @if(!$latestEnrollment)
                        <a href="{{ route('portal.enrollment.create') }}"
                           class="btn btn-primary btn-sm">Enroll Now</a>
                    @endif
                </div>
                @endif
            </div>
        </div>

        {{-- Next Steps --}}
        @if($nextSteps)
        <div class="card mb-3">
            <div class="card-header font-weight-bold">Next Steps</div>
            <div class="card-body p-0">
                @foreach ($nextSteps as $step)
                @if($step['isPayment'] ?? false)
                    <button type="button"
                       class="dtc-payment-btn dtc-next-step-row {{ $step['active'] ? 'is-active' : '' }}"
                       data-url="{{ $step['url'] }}" style="width:100%; border:0; text-align:left;">
                        <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0;
                                    background:{{ $step['active'] ? 'var(--dtc-primary)' : 'var(--dtc-surface-soft)' }};
                                    display:flex; align-items:center; justify-content:center;">
                            <i data-lucide="{{ $step['icon'] }}" style="font-size:18px;
                                      color:{{ $step['active'] ? '#fff' : 'var(--dtc-text-muted)' }};"></i>
                        </div>
                        <div  class="u-flex-1">
                            <div style="font-size:13px; font-weight:600;
                                        color:{{ $step['active'] ? 'var(--dtc-on-primary-soft)' : 'var(--dtc-text)' }};">
                                {{ $step['label'] }}
                            </div>
                            <div  class="u-text-xxs-secondary">
                                {{ $step['desc'] }}
                            </div>
                        </div>
                        <i data-lucide="chevron-right" style="color:{{ $step['active'] ? 'var(--dtc-on-primary-soft)' : 'var(--dtc-text-muted)' }};
                                  font-size:12px;"></i>
                    </button>
                @else
                    <a href="{{ $step['url'] }}" class="dtc-next-step-row {{ $step['active'] ? 'is-active' : '' }}">
                        <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0;
                                    background:{{ $step['active'] ? 'var(--dtc-primary)' : 'var(--dtc-surface-soft)' }};
                                    display:flex; align-items:center; justify-content:center;">
                            <i data-lucide="{{ $step['icon'] }}" style="font-size:18px;
                                      color:{{ $step['active'] ? '#fff' : 'var(--dtc-text-muted)' }};"></i>
                        </div>
                        <div  class="u-flex-1">
                            <div style="font-size:13px; font-weight:600;
                                        color:{{ $step['active'] ? 'var(--dtc-on-primary-soft)' : 'var(--dtc-text)' }};">
                                {{ $step['label'] }}
                            </div>
                            <div  class="u-text-xxs-secondary">
                                {{ $step['desc'] }}
                            </div>
                        </div>
                        <i data-lucide="chevron-right" style="color:{{ $step['active'] ? 'var(--dtc-on-primary-soft)' : 'var(--dtc-text-muted)' }};
                                  font-size:12px;"></i>
                    </a>
                @endif
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- RIGHT: Announcements + Quick Actions --}}
    <div class="col-lg-4">

        {{-- Important Reminder --}}
        @if($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid && (!$latestEnrollment->payment || $latestEnrollment->payment->status !== 'pending'))
        <div class="card mb-3 alert alert-warning"
             style="border-radius:16px;">
            <div class="card-body p-3">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <div class="dtc-icon-swatch is-warning" style="width:32px; height:32px;">
                        <i data-lucide="bell" style="font-size:14px;"></i>
                    </div>
                    <span style="font-size:13px; font-weight:700;">
                        Important Reminder
                    </span>
                </div>
                <p style="font-size:12px; opacity:0.85; margin:0 0 12px; line-height:1.6;">
                    @if($latestEnrollment->payment && $latestEnrollment->payment->status === 'rejected')
                        Your payment proof was rejected. Review the cashier's feedback and submit a corrected proof.
                    @else
                        Please complete your enrollment process by paying the ₱{{ number_format($fee, 2) }} fee at the Cashier's Office.
                    @endif
                </p>
                <button type="button" class="btn btn-warning btn-sm btn-block dtc-payment-btn u-text-xxs"
                        data-url="{{ route('portal.enrollment.payment-info', $latestEnrollment) }}"
                        >
                    Proceed to Payment →
                </button>
            </div>
        </div>
        @endif

        {{-- Recent notifications use the real messages stored for this student. --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Recent Notifications</span>
                <a href="{{ route('notifications.index') }}"
                   style="font-size:12px; color:var(--dtc-primary); text-decoration:none; font-weight:600;">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                @forelse ($announcements as $notif)
                <div style="padding:14px 20px; border-bottom:1px solid var(--dtc-border-soft);">
                    <div style="display:flex; gap:10px; align-items:flex-start;">
                        <div class="dtc-icon-swatch is-{{ $notif->type === 'success' ? 'success' : ($notif->type === 'danger' ? 'danger' : 'info') }}" style="width:32px; height:32px; flex-shrink:0;">
                            <i data-lucide="megaphone" style="font-size:12px;"></i>
                        </div>
                        <div class="dtc-notification-copy">
                            <div  class="u-text-sm-bold">
                                {{ $notif->title }}
                            </div>
                            <div class="u-text-xxs-secondary">{{ $notif->message }}</div>
                            <div style="font-size:11px; color:var(--dtc-text-muted); margin-top:2px;">
                                {{ $notif->created_at->format('M d, Y') }}
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4" style="color:var(--dtc-text-muted); font-size:13px;">
                    No announcements yet.
                </div>
                @endforelse
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card mb-3">
            <div class="card-header font-weight-bold">Quick Actions</div>
            <div class="card-body p-3">
                <a href="{{ route('portal.enrollment.index') }}" class="quick-action-btn">
                    <i data-lucide="file-text"></i> My Application
                </a>
                <a href="{{ route('portal.appointments.create') }}" class="quick-action-btn">
                    <i data-lucide="calendar-check"></i> Book Appointment
                </a>
                @if($latestEnrollment && $latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                <a href="{{ route('portal.cor.show') }}" class="quick-action-btn">
                    <i data-lucide="scroll-text"></i> Certificate of Registration
                </a>
                @endif
                <a href="{{ route('notifications.index') }}" class="quick-action-btn">
                    <i data-lucide="bell"></i> Notifications
                    @if($unreadCount > 0)
                        <span class="badge badge-danger ml-auto" style="font-size:10px;">{{ $unreadCount }}</span>
                    @endif
                </a>
            </div>
        </div>

        {{-- Next Appointment --}}
        @if($nextAppointment)
        <div class="card dtc-upcoming-appointment-card"
             >
            <div class="card-body p-3">
                <div style="font-size:11px; font-weight:600; opacity:0.75; margin-bottom:8px;
                            text-transform:uppercase; letter-spacing:0.5px;">
                    Upcoming Appointment
                </div>
                <div style="display:flex; align-items:center; gap:14px;">
                    <div style="background:rgba(255,255,255,0.15); border-radius:12px;
                                padding:10px 14px; text-align:center; min-width:52px;">
                        <div style="font-size:22px; font-weight:800; line-height:1; color:#FFC72C;">
                            {{ \Carbon\Carbon::parse($nextAppointment->slot->date)->format('d') }}
                        </div>
                        <div style="font-size:10px; font-weight:600; opacity:0.8;">
                            {{ \Carbon\Carbon::parse($nextAppointment->slot->date)->format('M') }}
                        </div>
                    </div>
                    <div>
                        <div  class="u-text-sm-bold">
                            {{ ucfirst($nextAppointment->document_type) }}
                        </div>
                        <div style="font-size:11px; opacity:0.75; margin-top:2px;">
                            <i data-lucide="clock" class="mr-1"></i>
                            {{ \Carbon\Carbon::parse($nextAppointment->slot->start_time)->format('h:i A') }}
                        </div>
                        <div style="font-size:11px; opacity:0.75; margin-top:2px;">
                            <i data-lucide="map-pin" class="mr-1"></i> Registrar Office
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

</div>

@include('student.enrollment._payment-modal')

@include('portal.partials.dashboard-avatar-modals')

@endsection

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
<link rel="stylesheet" href="{{ asset('css/student-dashboard.css') }}">
@endsection

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script src="{{ asset('js/dashboard-avatar.js') }}"></script>
@endsection
