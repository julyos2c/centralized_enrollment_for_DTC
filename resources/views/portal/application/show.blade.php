@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'My Application')

@section('content_header')
    @php
        $statusBadges = [
            'submitted'         => 'badge-secondary',
            'under_review'      => 'badge-primary',
            'revision_required' => 'badge-warning',
            'approved'          => 'badge-success',
            'rejected'          => 'badge-danger',
        ];

        $applicantTypeLabels = [
            'new_student'    => 'New Student / First Year',
            'transferee'     => 'Transferee',
            'shiftee'        => 'Shiftee',
            'returnee'       => 'Returnee / Readmitted',
            'cross_enrollee' => 'Cross-Enrollee',
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >
                My Application
            </h4>
            <p class="mb-0 u-text-secondary-sm" >
                View your submitted pre-enrollment application and its current status.
            </p>
        </div>

        <a href="{{ route('portal.dashboard') }}" class="btn btn-secondary btn-sm">
            ← Back to Dashboard
        </a>
    </div>
@endsection

@section('content')

<div class="row">
    <div class="col-lg-8">

        {{-- ===================================================== --}}
        {{-- SECTION 1 — APPLICATION STATUS & PROGRESS               --}}
        {{-- ===================================================== --}}
        <div class="card mb-3">
            <div class="card-header">
                <span class="font-weight-bold">Application Status</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Reference Number</div>
                        <div class="font-weight-bold">{{ $application->reference_no }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Current Status</div>
                        <div>
                            <span class="badge {{ $statusBadges[$application->status] ?? 'badge-secondary' }}">
                                {{ ucwords(str_replace('_', ' ', $application->status)) }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Applicant Type</div>
                        <div>{{ $applicantTypeLabels[$application->academic_status] ?? ucwords(str_replace('_', ' ', $application->academic_status)) }}</div>
                    </div>
                </div>

                {{-- Status Description --}}
                <div class="alert {{ $application->status === 'revision_required' ? 'alert-warning' : ($application->status === 'rejected' ? 'alert-danger' : 'alert-info') }}" style="border-radius: 12px; margin-top: 16px;">
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="flex-shrink: 0;">
                            @if($application->status === 'revision_required')
                                <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                            @elseif($application->status === 'rejected')
                                <i data-lucide="x-circle" style="width: 20px; height: 20px;"></i>
                            @elseif($application->status === 'approved')
                                <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                            @else
                                <i data-lucide="info" style="width: 20px; height: 20px;"></i>
                            @endif
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;">
                                @if($application->status === 'revision_required')
                                    Action Required
                                @elseif($application->status === 'rejected')
                                    Application Not Approved
                                @elseif($application->status === 'approved')
                                    Application Approved
                                @else
                                    Current Status
                                @endif
                            </div>
                            <div style="font-size: 12px; opacity: 0.9;">
                                {{ $application->getStatusDescription() }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-light border mb-0" role="status">
                    <strong>Next step:</strong> {{ $nextStep }}
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 2 — PROGRESS TIMELINE                          --}}
        {{-- ===================================================== --}}
        <div class="card mb-3">
            <div class="card-header">
                <span class="font-weight-bold">Progress Timeline</span>
            </div>
            <div class="card-body">
                @php
                    $timeline = $progressTimeline;
                @endphp

                <div class="application-timeline">
                    @foreach ($timeline as $index => $step)
                        <div class="timeline-step timeline-step-{{ $step['status'] }}">
                            <div class="timeline-marker">
                                @if($step['status'] === 'completed')
                                    <i data-lucide="check" style="width: 16px; height: 16px;"></i>
                                @elseif($step['status'] === 'current')
                                    <div class="timeline-marker-pulse"></div>
                                    <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                                @elseif($step['status'] === 'action_required')
                                    <i data-lucide="alert-triangle" style="width: 16px; height: 16px;"></i>
                                @elseif($step['status'] === 'rejected')
                                    <i data-lucide="x" style="width: 16px; height: 16px;"></i>
                                @else
                                    <div style="width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--dtc-border);"></div>
                                @endif
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-label">{{ $step['label'] }}</div>
                                <div class="timeline-description">{{ $step['description'] }}</div>
                                @if($step['timestamp'])
                                    <div class="timeline-timestamp">
                                        <i data-lucide="calendar" style="width: 12px; height: 12px; margin-right: 4px;"></i>
                                        {{ $step['timestamp']->format('M d, Y · h:i A') }}
                                    </div>
                                @elseif($step['status'] === 'completed')
                                    <div class="timeline-timestamp">
                                        <i data-lucide="check" style="width: 12px; height: 12px; margin-right: 4px;"></i>
                                        Completed
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if($loop->last === false)
                            <div class="timeline-connector {{ $step['status'] === 'completed' ? 'completed' : '' }}"></div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 3 — APPLICATION SUMMARY                        --}}
        {{-- ===================================================== --}}
        <div class="card mb-3">
            <div class="card-header">
                <span class="font-weight-bold">Application Details</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Program Applied For</div>
                        <div>{{ $application->program->name ?? 'Not provided' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Date Submitted</div>
                        <div>{{ $application->created_at ? $application->created_at->format('M d, Y h:i A') : 'Not provided' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Date Reviewed</div>
                        <div>{{ $application->reviewed_at ? $application->reviewed_at->format('M d, Y h:i A') : 'Not yet reviewed' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 4 — PERSONAL INFORMATION                       --}}
        {{-- ===================================================== --}}
        <div class="card mb-3">
            <div class="card-header">
                <span class="font-weight-bold">Personal Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Full Name</div>
                        <div>
                            {{ $application->first_name }}
                            {{ $application->middle_name ? $application->middle_name . ' ' : '' }}
                            {{ $application->last_name }}
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Gender</div>
                        <div>{{ $application->gender ? ucfirst($application->gender) : 'Not provided' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Age</div>
                        <div>{{ $application->age ?? 'Not provided' }}</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Birthdate</div>
                        <div>{{ $application->birthdate ? $application->birthdate->format('M d, Y') : 'Not provided' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Place of Birth</div>
                        <div>{{ $application->birth_place ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Religion</div>
                        <div>{{ $application->religion ?: 'Not provided' }}</div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Nationality</div>
                        <div>{{ $application->nationality ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">LRN</div>
                        <div>{{ $application->lrn ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Marital Status</div>
                        <div>{{ $application->marital_status ? ucfirst($application->marital_status) : 'Not provided' }}</div>
                    </div>
                    @if($application->marital_status === 'married')
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Spouse Name</div>
                        <div>{{ $application->spouse_name ?: 'Not provided' }}</div>
                    </div>
                    @endif

                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Disability</div>
                        <div>{{ $application->disability ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">PWD ID</div>
                        <div>{{ $application->pwd_id ?: 'Not provided' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 5 — CONTACT INFORMATION                        --}}
        {{-- ===================================================== --}}
        <div class="card mb-3">
            <div class="card-header">
                <span class="font-weight-bold">Contact Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Email</div>
                        <div>{{ $application->email }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Contact Number</div>
                        <div>{{ $application->phone ?: 'Not provided' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Current Address</div>
                        <div>{{ $application->current_address ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">City / Municipality</div>
                        <div>{{ $application->city ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="text-muted small">Province</div>
                        <div>{{ $application->province ?: 'Not provided' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Postal Code</div>
                        <div>{{ $application->postal_code ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Country</div>
                        <div>{{ $application->country ?: 'Not provided' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 6 — FAMILY / PARENT INFORMATION                --}}
        {{-- ===================================================== --}}
        <div class="card mb-3">
            <div class="card-header">
                <span class="font-weight-bold">Family / Parent Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Father's Name</div>
                        <div>{{ $application->father_name ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Father's Occupation</div>
                        <div>{{ $application->father_occupation ?: 'Not provided' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Mother's Name</div>
                        <div>{{ $application->mother_name ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Mother's Occupation</div>
                        <div>{{ $application->mother_occupation ?: 'Not provided' }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="text-muted small">Parent's Address</div>
                        <div>{{ $application->parent_address ?: 'Not provided' }}</div>
                    </div>
                    <div class="col-md-6 mb-0">
                        <div class="text-muted small">Parent's Contact</div>
                        <div>{{ $application->parent_contact ?: 'Not provided' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 7 — SUPPORTING DOCUMENTS                       --}}
        {{-- ===================================================== --}}
        <div class="card mb-3">
            <div class="card-header">
                <span class="font-weight-bold">Supporting Documents</span>
            </div>
            <div class="card-body p-0">
                @php
                    $documentLabels = [
                        'form_138'              => 'Form 138 / Equivalent',
                        'good_moral_certificate' => 'Certificate of Good Moral Character',
                        'birth_certificate'      => 'PSA Birth Certificate',
                        'marriage_certificate'   => 'Marriage Certificate (PSA)',
                        'transfer_credentials'   => 'Certificate of Transfer Credentials',
                        'transcript_of_records'  => 'Transcript of Records',
                    ];
                @endphp

                @forelse ($application->documents as $document)
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div>
                            <div class="font-weight-bold u-text-sm" >
                                {{ $documentLabels[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type)) }}
                            </div>
                            <div class="text-muted u-text-xxs" >
                                {{ $document->original_name }}
                                @if($document->size_bytes)
                                    &nbsp;·&nbsp;
                                    @php
                                        $sizeKb = $document->size_bytes / 1024;
                                    @endphp
                                    {{ $sizeKb >= 1024 ? number_format($sizeKb / 1024, 2) . ' MB' : number_format($sizeKb, 1) . ' KB' }}
                                @endif
                            </div>
                        </div>

                        <span class="badge badge-success">
                            <i data-lucide="check"></i> Submitted
                        </span>
                    </div>
                @empty
                    <div class="text-center text-muted py-4 u-text-sm" >
                        No supporting documents found.
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    <div class="col-lg-4">

        {{-- ===================================================== --}}
        {{-- SECTION 8 — REGISTRAR REVIEW & UPDATES                  --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Recent Updates</span>
            </div>
            <div class="card-body">
                @if($application->reviewed_at)
                    <div style="padding: 12px; border-radius: 8px; background: var(--dtc-surface-soft); margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <div class="dtc-icon-swatch is-info" style="width: 28px; height: 28px;">
                                <i data-lucide="user-check" style="font-size: 12px;"></i>
                            </div>
                            <div style="font-size: 13px; font-weight: 600;">
                                Registrar reviewed your application
                            </div>
                        </div>
                        <div style="font-size: 11px; color: var(--dtc-text-muted); margin-bottom: 8px;">
                            <i data-lucide="clock" style="width: 11px; height: 11px; margin-right: 3px;"></i>
                            {{ $application->reviewed_at->format('M d, Y · h:i A') }}
                        </div>
                        @if($application->remarks)
                            <div style="font-size: 12px; padding: 8px; background: #fff; border-radius: 6px; border-left: 3px solid var(--dtc-primary);">
                                {{ $application->remarks }}
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-muted u-text-sm" >
                        No updates yet. Your application is still awaiting review.
                    </div>
                @endif

                @if($application->status === 'revision_required' && $application->remarks)
                    <div style="margin-top: 16px; padding: 12px; border-radius: 8px; background: #fff3cd; border: 1px solid #ffc107;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <div style="width: 28px; height: 28px; border-radius: 6px; background: #ffc107; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="alert-triangle" style="width: 14px; height: 14px; color: #000;"></i>
                            </div>
                            <div style="font-size: 13px; font-weight: 600; color: #856404;">
                                Action Required
                            </div>
                        </div>
                        <div style="font-size: 12px; color: #856404; margin-bottom: 8px;">
                            Please contact the Registrar's Office to address the required corrections.
                        </div>
                        <div style="font-size: 11px; color: #856404; font-style: italic;">
                            "{{ $application->remarks }}"
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

@endsection

@section('css')
<style>
/* Application Timeline Styles */
.application-timeline {
    position: relative;
    padding: 8px 0;
}

.timeline-step {
    display: flex;
    gap: 16px;
    position: relative;
    padding-bottom: 24px;
}

.timeline-step:last-child {
    padding-bottom: 0;
}

.timeline-marker {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}

.timeline-step-completed .timeline-marker {
    background: var(--dtc-success);
    color: #fff;
}

.timeline-step-current .timeline-marker {
    background: var(--dtc-primary);
    color: #fff;
}

.timeline-step-action_required .timeline-marker {
    background: var(--dtc-accent);
    color: #fff;
}

.timeline-step-rejected .timeline-marker {
    background: var(--dtc-danger);
    color: #fff;
}

.timeline-step-pending .timeline-marker {
    background: var(--dtc-surface-soft);
    color: var(--dtc-text-muted);
}

.timeline-step-skipped .timeline-marker {
    background: var(--dtc-surface-soft);
    color: var(--dtc-text-muted);
    opacity: 0.5;
}

.timeline-marker-pulse {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: var(--dtc-primary);
    animation: pulse 2s infinite;
    opacity: 0.3;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 0.3;
    }
    50% {
        transform: scale(1.3);
        opacity: 0.1;
    }
    100% {
        transform: scale(1);
        opacity: 0.3;
    }
}

.timeline-content {
    flex: 1;
    padding-top: 4px;
}

.timeline-label {
    font-weight: 600;
    font-size: 13px;
    color: var(--dtc-text);
    margin-bottom: 4px;
}

.timeline-step-completed .timeline-label {
    color: var(--dtc-success);
}

.timeline-step-current .timeline-label {
    color: var(--dtc-primary);
}

.timeline-step-action_required .timeline-label {
    color: var(--dtc-accent);
}

.timeline-step-rejected .timeline-label {
    color: var(--dtc-danger);
}

.timeline-step-pending .timeline-label {
    color: var(--dtc-text-muted);
}

.timeline-step-skipped .timeline-label {
    color: var(--dtc-text-muted);
    text-decoration: line-through;
}

.timeline-description {
    font-size: 12px;
    color: var(--dtc-text-secondary);
    margin-bottom: 4px;
    line-height: 1.4;
}

.timeline-step-action_required .timeline-description {
    color: var(--dtc-accent);
    font-weight: 500;
}

.timeline-timestamp {
    font-size: 11px;
    color: var(--dtc-text-muted);
    display: flex;
    align-items: center;
}

.timeline-connector {
    position: absolute;
    left: 16px;
    top: 32px;
    bottom: 0;
    width: 2px;
    background: var(--dtc-border);
    z-index: 1;
}

.timeline-connector.completed {
    background: var(--dtc-success);
}

/* Dark mode support */
body.dtc-dark .timeline-marker-pulse {
    background: var(--dtc-primary);
}

body.dtc-dark .timeline-step-pending .timeline-marker,
body.dtc-dark .timeline-step-skipped .timeline-marker {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.5);
}

body.dtc-dark .timeline-connector {
    background: rgba(255, 255, 255, 0.1);
}

body.dtc-dark .timeline-connector.completed {
    background: var(--dtc-success);
}
</style>
@endsection
