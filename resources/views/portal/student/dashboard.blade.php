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

@push('css')
<link rel="stylesheet" href="{{ asset('css/dashboard-summaries.css') }}">
<link rel="stylesheet" href="{{ asset('css/student-cor.css') }}">
@endpush
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
                    <span class="dtc-welcome-greeting">Hello,</span>{{ auth()->user()->name }}
                </h4>
                @if($studentProfile && $studentProfile->enrolled_ay)
                <p class="dtc-welcome-meta">
                    @if($studentProfile->program)<span>{{ $studentProfile->program->name }}</span>@endif
                    <span>Enrolled AY {{ $studentProfile->enrolled_ay }}</span>
                </p>
                @endif
                <p class="dtc-welcome-text">
                    @if(!$latestEnrollment)
                        Submit your application to begin enrollment.
                    @elseif($latestEnrollment->status === 'approved' && $latestEnrollment->is_paid)
                        Enrollment complete. Your payment is confirmed.
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
            // The track spans first-to-last marker, so fill it 0–100% up to the last finished step.
            $timelineCount = max(count($timeline), 2);
            $lastDone = -1;
            foreach (array_values($timeline) as $idx => $st) { if (! empty($st['done'])) { $lastDone = $idx; } }
            $timelineProgress = $lastDone > 0 ? ($lastDone / ($timelineCount - 1)) * 100 : 0;
        @endphp
        <section class="card mb-3 dtc-enrollment-card" aria-labelledby="enrollment-progress-title">
            <div class="card-header dtc-enrollment-card-header">
                <div>
                    <h2 id="enrollment-progress-title">First enrollment progress</h2>
                    <p>Your admission and first enrollment milestones.</p>
                </div>
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
                        <div><strong>Enrollment needs attention</strong><span>{{ filled($latestEnrollment->remarks) ? $latestEnrollment->remarks : 'No additional feedback was provided. Review the available next steps or contact the Registrar for guidance.' }}</span></div>
                    </div>
                @endif

            </div>
        </section>
        @endif

        {{-- Enrolled Subjects — COR-style table --}}
        @include('portal.student._dashboard-subjects')

        {{-- Next Steps --}}
        @if($nextSteps)
        <div class="student-next-actions mb-3">
            @foreach ($nextSteps as $step)
                <section class="dtc-next-action {{ $step['active'] ? 'has-action' : 'is-secondary' }}" aria-labelledby="student-next-title-{{ $loop->index }}">
                    <div class="dtc-next-action-main">
                        <div class="dtc-next-action-status" aria-hidden="true"><i data-lucide="{{ $step['icon'] }}"></i></div>
                        <div class="dtc-next-action-copy">
                            <div class="dtc-next-action-eyebrow">{{ $step['active'] ? 'Next step' : 'Also available' }}</div>
                            <h2 id="student-next-title-{{ $loop->index }}">{{ $step['label'] }}</h2>
                            <p>{{ $step['desc'] }}</p>
                        </div>
                    </div>
                    <a href="{{ $step['url'] }}" class="dtc-next-action-button">
                        <span>{{ $step['actionLabel'] ?? (($step['isPayment'] ?? false) ? 'View payment details' : 'Continue') }}</span>
                        <i data-lucide="arrow-right" aria-hidden="true"></i>
                    </a>
                </section>
            @endforeach
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
                <a class="btn btn-warning btn-sm btn-block  u-text-xxs"
                        href="{{ route('portal.enrollment.payment-info', $latestEnrollment) }}"
                        >
                    Proceed to Payment →
                </a>
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
