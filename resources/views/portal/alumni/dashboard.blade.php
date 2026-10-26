@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 font-weight-bold">Alumni Dashboard</h4>
            <p class="mb-0 u-text-secondary-sm" >Welcome to Danao Technological College!</p>
        </div>
    </div>
@endsection

@section('content')

@php
    $statusTones = [
        'submitted'  => 'warning',
        'processing' => 'info',
        'ready'      => 'purple',
        'released'   => 'success',
    ];
@endphp

@php
    if ($readyRequests > 0) {
        $dashboardNextStep = 'Pick up a ready document';
        $dashboardNextStepNote = $readyRequests . ' request' . ($readyRequests === 1 ? ' is' : 's are') . ' ready for pickup.';
        $dashboardNextStepUrl = route('portal.documents.index');
    } elseif ($latestRequest && $latestRequest->payment_status === 'rejected') {
        $dashboardNextStep = 'Resubmit payment proof';
        $dashboardNextStepNote = 'The Cashier could not verify payment for ' . $latestRequest->document_label . '.';
        $dashboardNextStepUrl = route('portal.documents.index');
    } elseif ($latestRequest && $latestRequest->payment_status === 'pending_verification') {
        $dashboardNextStep = 'Payment under review';
        $dashboardNextStepNote = 'The Cashier is checking payment for ' . $latestRequest->document_label . '.';
        $dashboardNextStepUrl = route('portal.documents.index');
    } elseif ($latestRequest && $latestRequest->payment_status === 'unpaid') {
        $dashboardNextStep = 'Pay at the Cashier’s Office';
        $dashboardNextStepNote = 'Pay the fee for ' . $latestRequest->document_label . ' to continue processing.';
        $dashboardNextStepUrl = route('portal.documents.index');
    } elseif ($latestRequest) {
        $dashboardNextStep = 'Check your latest request';
        $dashboardNextStepNote = ucfirst(str_replace('_', ' ', $latestRequest->status)) . ' · ' . $latestRequest->document_label;
        $dashboardNextStepUrl = route('portal.documents.index');
    } else {
        $dashboardNextStep = 'Request a document';
        $dashboardNextStepNote = 'Start a transcript, diploma, or other document request.';
        $dashboardNextStepUrl = route('portal.documents.create');
    }
@endphp

<div class="dtc-portal-dashboard">
<div class="dtc-dashboard-overview mb-3" aria-label="Document request overview">
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Document requests</span>
        <strong>{{ $totalRequests }} total · {{ $releasedDocs }} released</strong>
        <a href="{{ route('portal.documents.index') }}">View request history</a>
    </div>
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Ready for pickup</span>
        <strong>{{ $readyRequests }}</strong>
        @if($readyRequests > 0)
            <a href="{{ route('portal.documents.index') }}">View ready requests</a>
        @else
            <span class="dtc-overview-note">No documents awaiting pickup</span>
        @endif
    </div>
    <div class="dtc-overview-item">
        <span class="dtc-overview-label">Next step</span>
        <strong>{{ $dashboardNextStep }}</strong>
        <a href="{{ $dashboardNextStepUrl }}">{{ $readyRequests > 0 ? 'View request details' : ($latestRequest ? 'View latest request' : 'Start request') }}</a>
        <span class="d-block dtc-overview-note">{{ $dashboardNextStepNote }}</span>
    </div>
</div>

{{-- ══ WELCOME CARD ════════════════════════════════════════════════════ --}}
<div class="card mb-3 dtc-welcome-card">
    <div class="card-body">
        <div class="dtc-welcome-layout">
            @include('portal.partials.dashboard-avatar')
            <div class="dtc-welcome-copy">
                <h4 class="dtc-welcome-title">
                    <span class="dtc-welcome-greeting">Welcome back,</span>{{ auth()->user()->name }}
                </h4>
                @if($studentProfile && ($studentProfile->graduation_year || $studentProfile->program))
                <p class="dtc-welcome-meta">
                    @if($studentProfile->program)<span>{{ $studentProfile->program->name }}</span>@endif
                    @if($studentProfile->graduation_year)<span>Batch {{ $studentProfile->graduation_year }}</span>@endif
                </p>
                @endif
                <p class="dtc-welcome-text">
                    Request transcripts, diplomas, and other documents.
                </p>
            </div>
        </div>
    </div>
</div>

{{-- ══ MAIN CONTENT ROW ════════════════════════════════════════════════ --}}
<div class="row">

    {{-- LEFT: Recent Requests --}}
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header dtc-recent-head">
                <span class="font-weight-bold">Recent Requests</span>
                <a href="{{ route('portal.documents.index') }}" class="dtc-recent-all">View all</a>
            </div>
            <div class="card-body p-0">
                @forelse ($recentRequests as $req)
                    @php
                        $statusText  = ['submitted' => 'Submitted', 'processing' => 'Preparing', 'ready' => 'Ready', 'released' => 'Released'][$req->status] ?? ucfirst($req->status);
                        $paymentTone = ['unpaid' => 'warning', 'pending_verification' => 'info', 'verified' => 'success', 'rejected' => 'danger'][$req->payment_status] ?? 'neutral';
                        $paymentText = ['unpaid' => 'Unpaid', 'pending_verification' => 'Under review', 'verified' => 'Paid', 'rejected' => 'Rejected'][$req->payment_status] ?? ucwords(str_replace('_', ' ', $req->payment_status));
                    @endphp
                    <a href="{{ route('portal.documents.index') }}" class="dtc-recent-row">
                        <span class="dtc-recent-main">
                            <span class="dtc-recent-name">{{ $req->document_label }}</span>
                            <span class="dtc-recent-meta">{{ $req->copies }} {{ $req->copies > 1 ? 'copies' : 'copy' }} · {{ $req->created_at->format('M j, Y') }}</span>
                        </span>
                        <span class="dtc-recent-status">
                            <span class="dtc-status-badge is-{{ $statusTones[$req->status] ?? 'neutral' }}">{{ $statusText }}</span>
                            @if ($req->payment_status === 'verified')
                                <span class="dtc-recent-paid">{{ $paymentText }}</span>
                            @else
                                <span class="dtc-status-badge is-{{ $paymentTone }}">{{ $paymentText }}</span>
                            @endif
                        </span>
                    </a>
                @empty
                    <div class="text-center py-4 u-muted">
                        <p class="u-text-sm mb-2">No document requests yet.</p>
                        <a href="{{ route('portal.documents.create') }}" class="btn btn-primary btn-sm">Request a document</a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- RIGHT: Quick Actions + Next Appointment --}}
    <div class="col-lg-4">

        <div class="card mb-3">
            <div class="card-header font-weight-bold">Quick Actions</div>
            <div class="card-body p-3">
                <a href="{{ route('portal.documents.create') }}" class="quick-action-btn">
                    <i data-lucide="plus-circle"></i> Request Document
                </a>
                <a href="{{ route('portal.documents.index') }}" class="quick-action-btn">
                    <i data-lucide="list"></i> My Requests
                </a>
                <a href="{{ route('portal.appointments.create') }}" class="quick-action-btn">
                    <i data-lucide="calendar-check"></i> Book Appointment
                </a>
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
<link rel="stylesheet" href="{{ asset_v('css/profile.css') }}">
<style>
    .dtc-recent-head { display: flex !important; align-items: center; justify-content: space-between !important; gap: 14px; padding-left: 20px !important; padding-right: 20px !important; }
    .dtc-recent-all { margin-left: auto; text-align: right; }
    .dtc-recent-all { font-size: 13px; font-weight: 600; color: var(--dtc-primary); text-decoration: none; }
    .dtc-recent-all:hover { text-decoration: underline; }
    body.dtc-dark .dtc-recent-all { color: #93C5FD; }
    .dtc-recent-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 64px; padding: 12px 20px; border-bottom: 1px solid var(--dtc-border-soft); color: inherit; text-decoration: none; }
    .dtc-recent-row:last-child { border-bottom: 0; }
    .dtc-recent-row:hover { background: var(--dtc-surface-soft); color: inherit; text-decoration: none; }
    .dtc-recent-row:focus-visible { outline: 2px solid var(--dtc-primary); outline-offset: -2px; }
    .dtc-recent-main { display: grid; gap: 2px; min-width: 0; }
    .dtc-recent-name { font-size: 14px; font-weight: 600; color: var(--dtc-text); overflow-wrap: anywhere; }
    .dtc-recent-meta { font-size: 12.5px; color: var(--dtc-text-secondary); }
    .dtc-recent-status { display: flex; flex: 0 0 auto; flex-direction: column; align-items: flex-end; gap: 4px; }
    .dtc-recent-paid { font-size: 12px; font-weight: 600; color: var(--dtc-text-secondary); padding-right: 2px; }
    @media (max-width: 575.98px) { .dtc-recent-row { padding: 12px 16px; } .dtc-recent-head { padding-left: 16px !important; padding-right: 16px !important; } }
</style>
@endsection

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script src="{{ asset_v('js/dashboard-avatar.js') }}"></script>
@endsection
