@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">New Applicant Dashboard</h4>
            <p class="mb-0 u-text-secondary-sm" >Welcome to Danao Technological College!</p>
        </div>
    </div>
@endsection

@section('content')

@php
    $applicationBadges = [
        'submitted'         => ['tone' => 'neutral', 'label' => 'Submitted'],
        'under_review'      => ['tone' => 'info',    'label' => 'Under Review'],
        'revision_required' => ['tone' => 'warning', 'label' => 'Revision Required'],
        'approved'          => ['tone' => 'success', 'label' => 'Approved'],
        'rejected'          => ['tone' => 'danger',  'label' => 'Rejected'],
    ];

    $enrollmentLabel = 'Not Started';
    if ($enrollment) {
        if ($enrollment->is_paid) {
            $enrollmentLabel = 'Paid';
        } elseif ($enrollment->status === 'approved') {
            $enrollmentLabel = 'Approved';
        } elseif ($enrollment->status === 'pending') {
            $enrollmentLabel = 'Pending';
        } else {
            $enrollmentLabel = ucfirst($enrollment->status);
        }
    }

    $appStatusLabel = $application
        ? ($applicationBadges[$application->status]['label'] ?? ucwords(str_replace('_',' ',$application->status)))
        : 'Not Found';

    if (!$enrollment || $enrollment->status !== 'approved') {
        $paymentStatusLabel = 'Not Required';
    } elseif ($enrollment->is_paid) {
        $paymentStatusLabel = 'Confirmed';
    } elseif ($enrollment->payment && $enrollment->payment->status === 'pending') {
        $paymentStatusLabel = 'Awaiting Verification';
    } elseif ($enrollment->payment && $enrollment->payment->status === 'rejected') {
        $paymentStatusLabel = 'Proof Rejected';
    } else {
        $paymentStatusLabel = 'Awaiting Payment';
    }
@endphp

<div class="dtc-portal-dashboard">
<div class="dtc-dashboard-overview mb-3" aria-label="Application and enrollment overview">
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Pre-enrollment application</span>
        <strong>{{ $appStatusLabel }}</strong>
        @if($application)
            <a href="{{ route('portal.application.show') }}">View application</a>
        @else
            <span class="dtc-overview-note">No application is linked to this account</span>
        @endif
    </div>
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Official enrollment</span>
        <strong>{{ $enrollmentLabel }}</strong>
        @if($enrollment)
            <a href="{{ route('portal.enrollment.index') }}">View enrollment</a>
        @else
            <span class="dtc-overview-note">Available after application approval</span>
        @endif
    </div>
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">{{ $enrollment && $enrollment->status === 'approved' ? 'Payment' : 'Next step' }}</span>
        <strong>{{ $enrollment && $enrollment->status === 'approved' ? $paymentStatusLabel : $nextStep['label'] }}</strong>
        @if($enrollment && $enrollment->status === 'approved' && !$enrollment->is_paid)
            <a href="{{ route('portal.enrollment.payment-info', $enrollment) }}">Payment details</a>
        @elseif(!empty($nextStep['url']))
            <a href="{{ $nextStep['url'] }}">Continue</a>
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
                @if($application && $application->program)
                <p class="dtc-welcome-meta">{{ $application->program->name }}</p>
                @endif
                <p class="dtc-welcome-text">
                    @if($application && $application->status === 'approved')
                        Your application is approved.
                        @if(!$enrollment)
                            Complete your official enrollment to continue.
                        @endif
                    @else
                        Welcome to Danao Technological College!
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>

{{-- ══ MAIN CONTENT ROW ════════════════════════════════════════════════ --}}
<div class="row">

    {{-- LEFT: Application Summary + Next Step --}}
    <div class="col-lg-8">

        {{-- Application Summary --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Pre-Enrollment Application</span>
                @if($application)
                    <span class="dtc-status-badge is-{{ $applicationBadges[$application->status]['tone'] ?? 'neutral' }}" style="font-weight:700;">
                        {{ $applicationBadges[$application->status]['label'] ?? ucwords(str_replace('_',' ',$application->status)) }}
                    </span>
                @endif
            </div>
            <div class="card-body">
                @if($application)
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Reference Number
                            </div>
                            <div style="font-size:14px; font-weight:700;">
                                {{ $application->reference_no }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Program
                            </div>
                            <div style="font-size:14px; font-weight:700;">
                                {{ $application->program->name ?? 'Not provided' }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Submitted
                            </div>
                            <div  class="u-text-sm-secondary-primary">
                                {{ $application->created_at ? $application->created_at->format('M d, Y') : 'Not provided' }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Approved
                            </div>
                            <div  class="u-text-sm-secondary-primary">
                                {{ $application->reviewed_at ? $application->reviewed_at->format('M d, Y') : 'Not yet reviewed' }}
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Documents Submitted
                            </div>
                            <div  class="u-text-sm-secondary-primary">
                                {{ $application->documents_count }} document{{ $application->documents_count == 1 ? '' : 's' }}
                            </div>
                        </div>
                        @if($application->remarks)
                        <div class="col-md-6 mb-3">
                            <div  class="u-eyebrow">
                                Registrar Remarks
                            </div>
                            <div  class="u-text-sm-secondary-primary">
                                {{ $application->remarks }}
                            </div>
                        </div>
                        @endif
                    </div>

                    <a href="{{ route('portal.application.show') }}"
                       class="btn btn-outline-primary btn-sm mt-2">
                        View Full Application →
                    </a>
                @else
                    <div class="text-center py-4 u-muted" >
                        <i data-lucide="alert-triangle" class="mb-2" style="width:2em;height:2em"></i>
                        <p  class="u-text-sm">
                            No approved application found for your account.
                            Please contact the Registrar's Office.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Next Step --}}
        @php
            $nextStepIsEnrollment = $nextStep['label'] === 'Proceed to Official Enrollment';
            $nextStepActionLabel = match ($nextStep['label']) {
                'Proceed to Official Enrollment' => 'Start Enrollment',
                'Submit Corrected Payment Proof' => 'Review Payment',
                'Proceed to Payment' => 'Continue to Payment',
                'Review Required Corrections' => 'Review Corrections',
                'Await Registrar Review', 'Review Application Decision' => 'View Application',
                'Wait for Registrar Approval', 'Wait for Payment Verification',
                'Enrollment Completed', 'View Enrollment Status' => 'View Enrollment',
                default => 'View Details',
            };
            $nextStepDescription = $nextStepIsEnrollment
                ? 'Select your subjects and submit your official enrollment.'
                : $nextStep['desc'];
        @endphp
        <section class="dtc-next-action mb-3 {{ $nextStep['url'] ? 'has-action' : 'is-static' }}"
                 aria-labelledby="next-step-title">
            <div class="dtc-next-action-main">
                <div class="dtc-next-action-status" aria-hidden="true">
                    <i data-lucide="{{ $nextStepIsEnrollment ? 'circle-check' : $nextStep['icon'] }}"></i>
                </div>
                <div class="dtc-next-action-copy">
                    <div class="dtc-next-action-eyebrow">
                        {{ $nextStepIsEnrollment ? 'Application approved' : 'Next step' }}
                    </div>
                    <h2 id="next-step-title">{{ $nextStep['label'] }}</h2>
                    <p>{{ $nextStepDescription }}</p>
                </div>
            </div>

            @if($nextStep['url'])
                @if($nextStep['isPayment'] ?? false)
                    <a
                            class=" dtc-next-action-button"
                            href="{{ $nextStep['url'] }}">
                        <span>{{ $nextStepActionLabel }}</span>
                        <i data-lucide="arrow-right" aria-hidden="true"></i>
                    </a>
                @else
                    <a href="{{ $nextStep['url'] }}" class="dtc-next-action-button">
                        <span>{{ $nextStepActionLabel }}</span>
                        <i data-lucide="arrow-right" aria-hidden="true"></i>
                    </a>
                @endif
            @endif
        </section>

    </div>

    {{-- RIGHT: Announcements + Quick Actions --}}
    <div class="col-lg-4">

        {{-- Announcements --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="font-weight-bold">Announcements</span>
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
                        <div>
                            <div  class="u-text-sm-bold">
                                {{ $notif->title }}
                            </div>
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
                <a href="{{ route('portal.application.show') }}" class="quick-action-btn">
                    <i data-lucide="file-text"></i> My Application
                </a>
                <a href="{{ route('portal.enrollment.create') }}" class="quick-action-btn">
                    <i data-lucide="plus-circle"></i> Enroll Now
                </a>
                <a href="{{ route('portal.enrollment.index') }}" class="quick-action-btn">
                    <i data-lucide="list"></i> My Enrollment Status
                </a>
                <a href="{{ route('notifications.index') }}" class="quick-action-btn">
                    <i data-lucide="bell"></i> Notifications
                    @if($unreadCount > 0)
                        <span class="badge badge-danger ml-auto" style="font-size:10px;">{{ $unreadCount }}</span>
                    @endif
                </a>
            </div>
        </div>

    </div>
</div>

</div>


@include('portal.partials.dashboard-avatar-modals')

@endsection

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endsection

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script src="{{ asset('js/dashboard-avatar.js') }}"></script>
@endsection
