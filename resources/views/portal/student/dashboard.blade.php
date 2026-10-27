@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">Student Dashboard</h4>
        </div>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/dashboard-summaries.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/student-cor.css') }}">
@endpush
@section('content')

@php
    if (!$latestEnrollment) {
        $appStatusLabel = 'Not Started';
    } elseif ($latestEnrollment->status === 'approved' && $latestEnrollment->is_paid) {
        $appStatusLabel = 'Enrolled';
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
@endphp

<div class="dtc-portal-dashboard">

{{-- ══ WELCOME CARD ════════════════════════════════════════════════════ --}}
@include('portal.partials.dashboard-profile', ['profileRole' => 'student'])

{{-- ══ MAIN CONTENT ROW ════════════════════════════════════════════════ --}}
<div class="row">

    {{-- LEFT: Enrollment Progress + Subjects --}}
    <div class="col-lg-8">

        <section class="card mb-3 dtc-current-term" aria-labelledby="current-term-title">
            <div class="card-body">
                <div class="dtc-current-term-heading">
                    <div class="dtc-term-identity">
                        <span class="dtc-term-icon" aria-hidden="true"><i data-lucide="calendar"></i></span>
                        <div>
                            <span class="dtc-current-term-kicker">Current semester</span>
                            <h2 id="current-term-title">{{ $currentSemester ? ($currentSemester === 'Summer' ? 'Summer term' : $currentSemester.' Semester') : 'Semester not set' }}</h2>
                        </div>
                    </div>
                    <span class="dtc-term-badge is-{{ $termStatus }}"><span aria-hidden="true"></span>
                        @if($termStatus === 'in_progress') In progress
                        @elseif($termStatus === 'upcoming') Upcoming
                        @elseif($termStatus === 'ended') Term ended
                        @else Dates not set
                        @endif
                    </span>
                </div>
                @if($termProgress !== null)
                    <div class="dtc-term-dates">
                        <div><span>Starts</span><time datetime="{{ $termStartDate->toDateString() }}">{{ $termStartDate->format('M j, Y') }}</time></div>
                        <div><span>Ends</span><time datetime="{{ $termEndDate->toDateString() }}">{{ $termEndDate->format('M j, Y') }}</time></div>
                    </div>
                    <div class="dtc-term-progress" role="progressbar" aria-label="Semester elapsed" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $termProgress }}" style="--term-progress: {{ $termProgress }}%">
                        <span></span>
                    </div>
                    <div class="dtc-term-progress-meta">
                        <span><strong>{{ $termProgress }}%</strong> complete</span>
                        <span>
                            @if($termStatus === 'upcoming') <strong>{{ $termDaysUntilStart }} {{ $termDaysUntilStart === 1 ? 'day' : 'days' }}</strong> until start
                            @elseif($termStatus === 'ended') Term ended
                            @elseif($termDaysLeft === 0) <strong>Ends today</strong>
                            @else <strong>{{ $termDaysLeft }} {{ $termDaysLeft === 1 ? 'day' : 'days' }}</strong> left
                            @endif
                        </span>
                    </div>
                @else
                    <p class="dtc-term-empty">Semester dates are unavailable. Please check with the Registrar.</p>
                @endif
                <div class="dtc-term-footer"><a href="{{ route('portal.enrollment.index') }}">Enrollment history <i data-lucide="arrow-right" aria-hidden="true"></i></a></div>
            </div>
        </section>

        {{-- Enrollment Progress Stepper --}}
        @if($timeline)
        @php
            $journeyCompleted = collect($timeline)->where('done', true)->count();
            $journeyComplete = $journeyCompleted === count($timeline);
        @endphp
        <section class="card mb-3 dtc-enrollment-card" aria-labelledby="enrollment-progress-title">
            <div class="card-header dtc-enrollment-card-header">
                <div>
                    <span class="dtc-enrollment-eyebrow">Enrollment journey</span>
                    <h2 id="enrollment-progress-title">Enrollment progress</h2>
                    <p>
                        @if($currentSchoolYear && $currentSemester)
                            {{ $currentSemester }} semester &middot; AY {{ $currentSchoolYear }}
                        @elseif($latestEnrollment)
                            Latest enrollment{{ $latestEnrollment->school_year ? ' · AY '.$latestEnrollment->school_year : '' }}
                        @else
                            Track your enrollment milestones.
                        @endif
                    </p>
                </div>
                <span class="dtc-enrollment-status {{ $journeyComplete ? 'is-complete' : '' }}" aria-label="{{ $journeyCompleted }} of {{ count($timeline) }} milestones complete">
                    <span aria-hidden="true"></span>{{ $journeyCompleted }} of {{ count($timeline) }} complete
                </span>
            </div>
            <div class="card-body dtc-enrollment-card-body">
                <ol class="dtc-enrollment-steps" style="--enrollment-step-count: {{ count($timeline) }};">
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
                                <small class="dtc-enrollment-step-number">Step {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</small>
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
                        <i data-lucide="award" aria-hidden="true"></i>
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
                            {{ $nextAppointment->document_labels->join(', ') }}
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
<link rel="stylesheet" href="{{ asset('css/dashboard-profile.css') }}">
<link rel="stylesheet" href="{{ asset('css/student-dashboard.css') }}">
@endsection

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script src="{{ asset_v('js/dashboard-avatar.js') }}"></script>
@endsection
