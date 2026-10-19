@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold" style="color:#1E293B;">
                @if(auth()->user()->hasRole('new_applicant'))
                    New Applicant / Enrollee Dashboard
                @elseif(auth()->user()->hasRole('alumni'))
                    Alumni Dashboard
                @else
                    Student Dashboard
                @endif
            </h4>
            <p class="mb-0" style="color:#64748B; font-size:13px;">
                Welcome to Danao Technological College! 👋
            </p>
        </div>
    </div>
@endsection

@section('content')

{{-- ══ TOP STAT CARDS ══════════════════════════════════════════════════ --}}
<div class="row mb-3">
    {{-- Application Status --}}
    <div class="col-lg-3 col-6 mb-3">
        <div class="card" style="border-radius:16px; border:none;
             box-shadow:0 2px 12px rgba(0,0,0,0.06); padding:20px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; border-radius:14px;
                            background:#EEF2FF; display:flex; align-items:center;
                            justify-content:center; flex-shrink:0;">
                    <i class="fas fa-file-alt" style="font-size:20px; color:#0F4CDB;"></i>
                </div>
                <div>
                    <div style="font-size:11px; color:#94A3B8; font-weight:600;
                                text-transform:uppercase; letter-spacing:0.5px;">
                        Application Status
                    </div>
                    <div style="font-size:15px; font-weight:700; color:#1E293B; margin-top:2px;">
                        @if(!$latestEnrollment) Not Started
                        @elseif($latestEnrollment->is_paid) Enrolled ✓
                        @elseif($latestEnrollment->status === 'approved') Approved
                        @elseif($latestEnrollment->status === 'pending') In Progress
                        @else Rejected @endif
                    </div>
                </div>
            </div>
            <a href="{{ route('portal.enrollment.index') }}"
               style="font-size:12px; color:#0F4CDB; text-decoration:none;
                      display:block; margin-top:12px; font-weight:600;">
                View Details →
            </a>
        </div>
    </div>

    {{-- Enrollment Steps --}}
    <div class="col-lg-3 col-6 mb-3">
        <div class="card" style="border-radius:16px; border:none;
             box-shadow:0 2px 12px rgba(0,0,0,0.06); padding:20px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; border-radius:14px;
                            background:#FEF9C3; display:flex; align-items:center;
                            justify-content:center; flex-shrink:0;">
                    <i class="fas fa-list-ol" style="font-size:20px; color:#D97706;"></i>
                </div>
                <div>
                    <div style="font-size:11px; color:#94A3B8; font-weight:600;
                                text-transform:uppercase; letter-spacing:0.5px;">
                        Enrollment Steps
                    </div>
                    <div style="font-size:15px; font-weight:700; color:#1E293B; margin-top:2px;">
                        {{ $completedSteps }} of {{ count($timeline) ?: 5 }}
                    </div>
                </div>
            </div>
            <a href="{{ route('portal.enrollment.index') }}"
               style="font-size:12px; color:#D97706; text-decoration:none;
                      display:block; margin-top:12px; font-weight:600;">
                View Steps →
            </a>
        </div>
    </div>

    {{-- Last Updated --}}
    <div class="col-lg-3 col-6 mb-3">
        <div class="card" style="border-radius:16px; border:none;
             box-shadow:0 2px 12px rgba(0,0,0,0.06); padding:20px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; border-radius:14px;
                            background:#DCFCE7; display:flex; align-items:center;
                            justify-content:center; flex-shrink:0;">
                    <i class="fas fa-calendar" style="font-size:20px; color:#15803D;"></i>
                </div>
                <div>
                    <div style="font-size:11px; color:#94A3B8; font-weight:600;
                                text-transform:uppercase; letter-spacing:0.5px;">
                        Last Updated
                    </div>
                    <div style="font-size:14px; font-weight:700; color:#1E293B; margin-top:2px;">
                        {{ $latestEnrollment ? $latestEnrollment->updated_at->format('M d, Y') : 'N/A' }}
                    </div>
                    <div style="font-size:11px; color:#94A3B8;">
                        {{ $latestEnrollment ? $latestEnrollment->updated_at->format('h:i A') : '' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Notifications --}}
    <div class="col-lg-3 col-6 mb-3">
        <div class="card" style="border-radius:16px; border:none;
             box-shadow:0 2px 12px rgba(0,0,0,0.06); padding:20px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div style="width:48px; height:48px; border-radius:14px;
                            background:#EDE9FE; display:flex; align-items:center;
                            justify-content:center; flex-shrink:0; position:relative;">
                    <i class="fas fa-bell" style="font-size:20px; color:#7C3AED;"></i>
                    @if($unreadCount > 0)
                        <span style="position:absolute; top:-4px; right:-4px;
                                     background:#EF4444; color:#fff; font-size:9px;
                                     font-weight:700; border-radius:50%; width:18px;
                                     height:18px; display:flex; align-items:center;
                                     justify-content:center;">
                            {{ $unreadCount }}
                        </span>
                    @endif
                </div>
                <div>
                    <div style="font-size:11px; color:#94A3B8; font-weight:600;
                                text-transform:uppercase; letter-spacing:0.5px;">
                        Notifications
                    </div>
                    <div style="font-size:15px; font-weight:700; color:#1E293B; margin-top:2px;">
                        {{ $unreadCount }} New
                    </div>
                </div>
            </div>
            <a href="{{ route('notifications.index') }}"
               style="font-size:12px; color:#7C3AED; text-decoration:none;
                      display:block; margin-top:12px; font-weight:600;">
                View All →
            </a>
        </div>
    </div>
</div>

{{-- ══ WELCOME CARD ════════════════════════════════════════════════════ --}}
<div class="card mb-3"
     style="background:linear-gradient(135deg,#EEF2FF,#E0E7FF);
            border:1.5px solid #C7D2FE; border-radius:16px;">
    <div class="card-body" style="padding:24px;">
        <div style="display:flex; align-items:center; gap:20px;">
            <div style="width:64px; height:64px; border-radius:50%;
                        background:linear-gradient(135deg,#0F4CDB,#1a5feb);
                        display:flex; align-items:center; justify-content:center;
                        color:#fff; font-weight:800; font-size:26px; flex-shrink:0;">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div>
                <h4 style="font-weight:800; color:#1E293B; margin:0 0 4px;">
                    Hello, {{ explode(' ', auth()->user()->name)[0] }}! 👋
                </h4>
                <p style="font-size:13px; color:#4338CA; margin:0;">
                    Welcome to Danao Technological College!
                    @if(!$latestEnrollment)
                        Please complete your application and follow the enrollment steps below.
                    @elseif($latestEnrollment->is_paid)
                        Congratulations! You are officially enrolled.
                    @elseif($latestEnrollment->status === 'approved')
                        Your enrollment is approved! Please proceed to the Cashier to pay ₱500.00.
                    @elseif($latestEnrollment->status === 'pending')
                        Your enrollment is under review. Please wait for the Registrar's approval.
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

        {{-- Enrollment Progress Stepper --}}
        @if($timeline)
        <div class="card mb-3">
            <div class="card-header font-weight-bold" style="color:#1E293B;">
                Enrollment Progress
            </div>
            <div class="card-body">
                {{-- Stepper --}}
                <div style="display:flex; align-items:flex-start; justify-content:space-between;
                            position:relative; padding:0 10px;">

                    {{-- Progress line --}}
                    <div style="position:absolute; top:18px; left:10%; right:10%;
                                height:3px; background:#E2E8F0; z-index:0;"></div>
                    <div style="position:absolute; top:18px; left:10%;
                                height:3px; background:#0F4CDB; z-index:1;
                                width:{{ $completedSteps > 0 ? (($completedSteps - 1) / (count($timeline) - 1)) * 80 : 0 }}%;"></div>

                    @foreach ($timeline as $i => $step)
                    <div style="display:flex; flex-direction:column; align-items:center;
                                z-index:2; flex:1; text-align:center;">
                        <div style="width:36px; height:36px; border-radius:50%;
                                    border:3px solid {{ $step['done'] ? '#0F4CDB' : ($step['active'] ? '#FFC72C' : '#E2E8F0') }};
                                    background:{{ $step['done'] ? '#0F4CDB' : ($step['active'] ? '#FFF9E6' : '#ffffff') }};
                                    display:flex; align-items:center; justify-content:center;
                                    font-size:12px; font-weight:700;
                                    color:{{ $step['done'] ? '#fff' : ($step['active'] ? '#D97706' : '#94A3B8') }};
                                    margin-bottom:8px;">
                            @if($step['done'])
                                <i class="fas fa-check" style="font-size:12px;"></i>
                            @else
                                {{ $i + 1 }}
                            @endif
                        </div>
                        <div style="font-size:11px; font-weight:{{ $step['done'] || $step['active'] ? '700' : '500' }};
                                    color:{{ $step['done'] ? '#0F4CDB' : ($step['active'] ? '#D97706' : '#94A3B8') }};">
                            {{ $step['label'] }}
                        </div>
                        <div style="font-size:10px; color:#94A3B8; margin-top:2px;">
                            {{ $step['sublabel'] }}
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Current step info --}}
                @php $activeStep = collect($timeline)->firstWhere('active', true); @endphp
                @if($activeStep)
                <div style="margin-top:20px; background:#FEF9C3; border-radius:12px;
                            padding:12px 16px; display:flex; align-items:center; gap:12px;
                            border:1.5px solid #FDE68A;">
                    <i class="fas fa-arrow-right" style="color:#D97706;"></i>
                    <div>
                        <div style="font-size:13px; font-weight:600; color:#92400E;">
                            Current Step: {{ $activeStep['label'] }}
                        </div>
                        <div style="font-size:12px; color:#B45309;">
                            {{ $activeStep['sublabel'] }}
                        </div>
                    </div>
                </div>
                @elseif($latestEnrollment && $latestEnrollment->is_paid)
                <div style="margin-top:20px; background:#DCFCE7; border-radius:12px;
                            padding:12px 16px; display:flex; align-items:center; gap:12px;
                            border:1.5px solid #BBF7D0;">
                    <i class="fas fa-check-circle" style="color:#15803D;"></i>
                    <div style="font-size:13px; font-weight:600; color:#15803D;">
                        All steps completed! You are officially enrolled.
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Subject List (as Requirements Checklist) --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold" style="color:#1E293B;">Enrolled Subjects</span>
                @if($subjects->isNotEmpty())
                    <span style="font-size:12px; color:#0F4CDB; font-weight:600;">
                        {{ $subjects->count() }} Subject{{ $subjects->count() > 1 ? 's' : '' }}
                    </span>
                @endif
            </div>
            <div class="card-body p-0">
                @forelse ($subjects as $subject)
                <div style="padding:12px 20px; border-bottom:1px solid #F1F5F9;
                            display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:36px; height:36px; border-radius:10px;
                                    background:#EEF2FF; display:flex; align-items:center;
                                    justify-content:center; flex-shrink:0;">
                            <i class="fas fa-book" style="font-size:14px; color:#0F4CDB;"></i>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                {{ $subject->subject_code }}
                            </div>
                            <div style="font-size:11px; color:#64748B;">
                                {{ $subject->subject_name }}
                            </div>
                        </div>
                    </div>
                    @if($latestEnrollment && $latestEnrollment->is_paid)
                        <span style="background:#DCFCE7; color:#15803D; font-size:11px;
                                     font-weight:600; padding:3px 10px; border-radius:20px;">
                            <i class="fas fa-check mr-1"></i> Enrolled
                        </span>
                    @elseif($latestEnrollment && $latestEnrollment->status === 'pending')
                        <span style="background:#FEF9C3; color:#A16207; font-size:11px;
                                     font-weight:600; padding:3px 10px; border-radius:20px;">
                            <i class="fas fa-clock mr-1"></i> Pending
                        </span>
                    @endif
                </div>
                @empty
                <div class="text-center py-4" style="color:#94A3B8;">
                    <i class="fas fa-book fa-2x mb-2"></i>
                    <p style="font-size:13px;">No subjects enrolled yet.</p>
                    @if(!$latestEnrollment)
                        <a href="{{ route('portal.enrollment.create') }}"
                           class="btn btn-primary btn-sm">Enroll Now</a>
                    @endif
                </div>
                @endforelse
            </div>
        </div>

        {{-- Next Steps --}}
        @if($nextSteps)
        <div class="card mb-3">
            <div class="card-header font-weight-bold" style="color:#1E293B;">Next Steps</div>
            <div class="card-body p-0">
                @foreach ($nextSteps as $step)
                <a href="{{ $step['url'] }}"
                   style="display:flex; align-items:center; gap:16px; padding:16px 20px;
                          border-bottom:1px solid #F1F5F9; text-decoration:none;
                          background:{{ $step['active'] ? '#F8FAFF' : '#ffffff' }};
                          transition:background 0.2s;">
                    <div style="width:44px; height:44px; border-radius:12px; flex-shrink:0;
                                background:{{ $step['active'] ? '#0F4CDB' : '#F1F5F9' }};
                                display:flex; align-items:center; justify-content:center;">
                        <i class="fas {{ $step['icon'] }}"
                           style="font-size:18px;
                                  color:{{ $step['active'] ? '#fff' : '#94A3B8' }};"></i>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:13px; font-weight:600;
                                    color:{{ $step['active'] ? '#0F4CDB' : '#1E293B' }};">
                            {{ $step['label'] }}
                        </div>
                        <div style="font-size:12px; color:#64748B; margin-top:2px;">
                            {{ $step['desc'] }}
                        </div>
                    </div>
                    <i class="fas fa-chevron-right"
                       style="color:{{ $step['active'] ? '#0F4CDB' : '#CBD5E1' }};
                              font-size:12px;"></i>
                </a>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- RIGHT: Announcements + Quick Actions --}}
    <div class="col-lg-4">

        {{-- Important Reminder --}}
        @if($latestEnrollment && $latestEnrollment->status === 'approved' && !$latestEnrollment->is_paid)
        <div class="card mb-3"
             style="background:#FFFBEB; border:1.5px solid #FDE68A; border-radius:16px;">
            <div class="card-body p-3">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <div style="width:32px; height:32px; border-radius:10px; background:#FEF3C7;
                                display:flex; align-items:center; justify-content:center;">
                        <i class="fas fa-bell" style="color:#D97706; font-size:14px;"></i>
                    </div>
                    <span style="font-size:13px; font-weight:700; color:#92400E;">
                        Important Reminder
                    </span>
                </div>
                <p style="font-size:12px; color:#B45309; margin:0 0 12px; line-height:1.6;">
                    Please complete your enrollment process by paying the ₱500.00 fee at the Cashier's Office.
                </p>
                <a href="{{ route('portal.enrollment.payment-info', $latestEnrollment) }}"
                   class="btn btn-warning btn-sm btn-block"
                   style="font-size:12px;">
                    Proceed to Payment →
                </a>
            </div>
        </div>
        @endif

        {{-- Announcements (Recent Notifications) --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold" style="color:#1E293B;">Announcements</span>
                <a href="{{ route('notifications.index') }}"
                   style="font-size:12px; color:#0F4CDB; text-decoration:none; font-weight:600;">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                @forelse ($announcements as $notif)
                <div style="padding:14px 20px; border-bottom:1px solid #F1F5F9;">
                    <div style="display:flex; gap:10px; align-items:flex-start;">
                        <div style="width:32px; height:32px; border-radius:10px; flex-shrink:0;
                                    background:{{ $notif->type === 'success' ? '#DCFCE7' : ($notif->type === 'danger' ? '#FEE2E2' : '#DBEAFE') }};
                                    display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-bullhorn"
                               style="font-size:12px;
                                      color:{{ $notif->type === 'success' ? '#15803D' : ($notif->type === 'danger' ? '#DC2626' : '#1D4ED8') }};"></i>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                {{ $notif->title }}
                            </div>
                            <div style="font-size:11px; color:#94A3B8; margin-top:2px;">
                                {{ $notif->created_at->format('M d, Y') }}
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4" style="color:#94A3B8; font-size:13px;">
                    No announcements yet.
                </div>
                @endforelse
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="card mb-3">
            <div class="card-header font-weight-bold" style="color:#1E293B;">Quick Actions</div>
            <div class="card-body p-0">
                @php
                    $actions = [
                        ['icon' => 'fa-file-alt',      'label' => 'My Application',     'sub' => 'View enrollment status',    'url' => route('portal.enrollment.index')],
                        ['icon' => 'fa-calendar-check', 'label' => 'Book Appointment',   'sub' => 'Schedule document pickup',  'url' => route('portal.appointments.create')],
                        ['icon' => 'fa-bell',           'label' => 'Notifications',      'sub' => $unreadCount . ' unread',    'url' => route('notifications.index')],
                    ];
                    if(auth()->user()->hasRole('alumni')) {
                        $actions[] = ['icon' => 'fa-folder-open', 'label' => 'Document Requests', 'sub' => 'Request TOR, Diploma etc.', 'url' => route('portal.documents.index')];
                    }
                @endphp
                @foreach($actions as $action)
                <a href="{{ $action['url'] }}"
                   style="display:flex; align-items:center; justify-content:space-between;
                          padding:14px 20px; border-bottom:1px solid #F1F5F9;
                          text-decoration:none; transition:background 0.2s;"
                   onmouseover="this.style.background='#F8FAFC'"
                   onmouseout="this.style.background='transparent'">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:36px; height:36px; border-radius:10px;
                                    background:#EEF2FF; display:flex; align-items:center;
                                    justify-content:center;">
                            <i class="fas {{ $action['icon'] }}"
                               style="font-size:14px; color:#0F4CDB;"></i>
                        </div>
                        <div>
                            <div style="font-size:13px; font-weight:600; color:#1E293B;">
                                {{ $action['label'] }}
                            </div>
                            <div style="font-size:11px; color:#64748B;">
                                {{ $action['sub'] }}
                            </div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right" style="color:#CBD5E1; font-size:12px;"></i>
                </a>
                @endforeach
            </div>
        </div>

        {{-- Next Appointment --}}
        @if($nextAppointment)
        <div class="card"
             style="background:linear-gradient(135deg,#0F4CDB,#1a5feb); border:none; color:#fff;">
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
                        <div style="font-size:13px; font-weight:600;">
                            {{ ucfirst($nextAppointment->document_type) }}
                        </div>
                        <div style="font-size:11px; opacity:0.75; margin-top:2px;">
                            <i class="fas fa-clock mr-1"></i>
                            {{ \Carbon\Carbon::parse($nextAppointment->slot->start_time)->format('h:i A') }}
                        </div>
                        <div style="font-size:11px; opacity:0.75; margin-top:2px;">
                            <i class="fas fa-map-marker-alt mr-1"></i> Registrar Office
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection