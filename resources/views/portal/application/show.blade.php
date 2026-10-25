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
                Track your application and next steps.
            </p>
        </div>

        <a href="{{ route('portal.dashboard') }}" class="btn btn-secondary btn-sm">
            ← Back to Dashboard
        </a>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('css/portal-application.css') }}">
@endpush
@section('content')

<div class="row portal-application-page">
    <div class="col-lg-8">

        {{-- ===================================================== --}}
        {{-- SECTION 1 — APPLICATION STATUS & PROGRESS               --}}
        {{-- ===================================================== --}}
        <div class="card mb-3">
            <div class="card-header">
                <span class="font-weight-bold">Application Status</span>
            </div>
            <div class="card-body">
                <div class="row application-status-facts">
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

                <section class="application-next-step" aria-label="Next step">
                    <h2>Next step</h2>
                    <p>{{ $nextStep }}</p>
                    @if($nextActionUrl)
                        <a class="btn btn-primary" href="{{ $nextActionUrl }}">{{ $nextActionLabel }}</a>
                    @endif
                </section>
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

                <ol class="application-timeline" aria-label="Application progress">
                    @foreach ($timeline as $step)
                        @continue($step['key'] === 'corrections' && $step['status'] === 'skipped')
                        <li class="timeline-step timeline-step-{{ $step['status'] }}" @if(in_array($step['status'], ['current', 'action_required'])) aria-current="step" @endif>
                            <span class="timeline-marker" aria-hidden="true">
                                @if($step['status'] === 'completed')<i data-lucide="check"></i>
                                @elseif($step['status'] === 'rejected')<i data-lucide="x"></i>
                                @else<span></span>@endif
                            </span>
                            <div class="timeline-content">
                                <div class="timeline-label">{{ $step['label'] }}<span class="sr-only"> — {{ str_replace('_', ' ', $step['status']) }}</span></div>
                                <p class="timeline-description">{{ $step['description'] }}</p>
                                @if($step['timestamp'])<time class="timeline-timestamp" datetime="{{ $step['timestamp']->toIso8601String() }}">{{ $step['timestamp']->format('M d, Y · h:i A') }}</time>@endif
                            </div>
                        </li>
                    @endforeach
                </ol>
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
                            {{ $application->full_name }}
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
                            <div style="font-size: 12px; padding: 8px; background: var(--dtc-surface); border-radius: 6px; border-left: 3px solid var(--dtc-primary);">
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
