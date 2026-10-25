@extends('adminlte::page')
@extends('partials.navbar')
@section('title', 'Review Application')

@section('content_header')
    @php
        $statusBadges = [
            'submitted'         => 'badge-secondary',
            'under_review'      => 'badge-primary',
            'revision_required' => 'badge-warning',
            'approved'          => 'badge-success',
            'rejected'          => 'badge-danger',
            'cancelled'         => 'badge-dark',
        ];

        $applicantTypeLabels = [
            'new_student'    => 'New Student / First Year',
            'transferee'     => 'Transferee',
            'shiftee'        => 'Shiftee',
            'returnee'       => 'Returnee / Readmitted',
            'cross_enrollee' => 'Cross-Enrollee',
        ];

    @endphp

    <div class="app-review-header">
        <a href="{{ route('registrar.applications.index') }}" class="app-review-back">
            <i data-lucide="arrow-left" aria-hidden="true"></i> Back to applications
        </a>
        <div class="app-review-header-main">
            <div class="app-review-title-row">
                <h1>Application Review</h1>
                <span class="badge {{ $statusBadges[$application->status] ?? 'badge-secondary' }}">
                    {{ ucwords(str_replace('_', ' ', $application->status)) }}
                </span>
            </div>
            <p class="app-review-ref">Reference <strong>{{ $application->reference_no }}</strong></p>
            <div class="app-review-context">
                <p class="app-review-program">{{ $application->program->name ?? 'Program not provided' }}</p>
                <p class="app-review-type">{{ $applicantTypeLabels[$application->academic_status] ?? ucwords(str_replace('_', ' ', $application->academic_status)) }}</p>
            </div>
        </div>
    </div>
@endsection


@push('css')
<link rel="stylesheet" href="{{ asset('css/registrar-workspace.css') }}">
<link rel="stylesheet" href="{{ asset('css/application-review.css') }}">
<link rel="stylesheet" href="{{ asset('css/physical-requirements.css') }}">
@endpush
@section('content')
<div class="registrar-workspace application-review-page">




<div class="row">
    <div class="col-lg-8">

        {{-- ===================================================== --}}
        {{-- SECTION 1 — APPLICANT INFORMATION                      --}}
        {{-- ===================================================== --}}
        @include('registrar.applications._applicant-information')

        {{-- ===================================================== --}}
        {{-- SECTION 2 — CONTACT INFORMATION                        --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Contact Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Email</div>
                        <div>{{ $application->email }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Phone</div>
                        <div>{{ $application->phone ?: '—' }}</div>
                    </div>

                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Current Address</div>
                        <div>{{ $application->current_address ?: '—' }}</div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="text-muted small">Barangay</div>
                        <div>{{ $application->barangay ?: '—' }}</div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="text-muted small">City / Municipality</div>
                        <div>{{ $application->city ?: '—' }}</div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="text-muted small">Province</div>
                        <div>{{ $application->province ?: '—' }}</div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="text-muted small">Region</div>
                        <div>{{ $application->region ?: '—' }}</div>
                    </div>

                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Postal Code</div>
                        <div>{{ $application->postal_code ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Country</div>
                        <div>{{ $application->country ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- EDUCATIONAL BACKGROUND                                --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Educational Background</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Education level</div>
                        <div>{{ $application->education_level ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Completion status</div>
                        <div>{{ $application->education_status ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Most recent school attended</div>
                        <div>{{ $application->most_recent_school ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">School year completed or last attended</div>
                        <div>{{ $application->school_year_completed ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">School location</div>
                        <div>{{ $application->school_location ?: '—' }}</div>
                    </div>
                    @if($application->academic_status === 'new_student')
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Senior High School strand</div>
                        <div>{{ $application->shs_strand ?: '—' }}</div>
                    </div>
                    @endif
                    @if(in_array($application->academic_status, ['transferee', 'shiftee', 'returnee'], true))
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Previous or current program</div>
                        <div>{{ $application->previous_program ?: '—' }}</div>
                    </div>
                    @endif
                    @if(in_array($application->academic_status, ['transferee', 'shiftee', 'returnee', 'cross_enrollee'], true))
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Last year level or semester attended</div>
                        <div>{{ $application->last_year_level ?: '—' }}</div>
                    </div>
                    @endif
                    @if($application->academic_status === 'cross_enrollee')
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Home institution</div>
                        <div>{{ $application->home_institution ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Current course or program</div>
                        <div>{{ $application->home_program ?: '—' }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 3 — FAMILY / PARENT INFORMATION                --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Family / Parent / Guardian Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Guardian's Name</div>
                        <div>{{ $application->guardian_name ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Guardian's Contact</div>
                        <div>{{ $application->guardian_contact ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Father's Name</div>
                        <div>{{ $application->father_name ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Father's Occupation</div>
                        <div>{{ $application->father_occupation ?: '—' }}</div>
                    </div>

                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Mother's Name</div>
                        <div>{{ $application->mother_name ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Mother's Occupation</div>
                        <div>{{ $application->mother_occupation ?: '—' }}</div>
                    </div>

                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Parent's Address</div>
                        <div>{{ $application->parent_address ?: '—' }}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="text-muted small">Parent's Contact</div>
                        <div>{{ $application->parent_contact ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 4 — OTHER INFORMATION                          --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Other Information</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Occupation</div>
                        <div>{{ $application->occupation ?: '—' }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">Disability</div>
                        <div>{{ $application->disability ?: '—' }}</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="text-muted small">PWD ID</div>
                        <div>{{ $application->pwd_id ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 5 — SUPPORTING DOCUMENTS                       --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Supporting Documents</span>
            </div>
            <div class="card-body p-0">
                @php
                    $documentLabels = [
                        'form_138'                => 'Form 138 / Equivalent',
                        'good_moral_certificate'   => 'Certificate of Good Moral Character',
                        'birth_certificate'        => 'PSA Birth Certificate',
                        'marriage_certificate'     => 'Marriage Certificate (PSA)',
                        'transfer_credentials'     => 'Certificate of Transfer Credentials',
                        'transcript_of_records'    => 'Transcript of Records',
                    ];
                @endphp

                @forelse ($application->documents as $document)
                    <div class="application-document">
                        <div class="application-document-info">
                            <h3 class="application-document-title">
                                {{ $documentLabels[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type)) }}
                            </h3>
                            <p class="application-document-filename">
                                {{ $document->original_name }}
                            </p>
                            <p class="application-document-meta">
                                <span>Uploaded {{ $document->created_at->format('M d, Y · h:i A') }}</span>
                                @if($document->size_bytes)
                                    @php
                                        $sizeKb = $document->size_bytes / 1024;
                                    @endphp
                                    <span>{{ $sizeKb >= 1024 ? number_format($sizeKb / 1024, 2) . ' MB' : number_format($sizeKb, 1) . ' KB' }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="application-document-actions">
                        <a href="{{ route('registrar.applications.documents.view', [$application, $document]) }}"
                           class="application-document-download" target="_blank" rel="noopener noreferrer"
                           aria-label="View {{ $documentLabels[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type)) }} (opens in a new tab)">
                            <i data-lucide="eye" aria-hidden="true"></i> View
                        </a>
                        <a href="{{ route('registrar.applications.documents.show', [$application, $document]) }}"
                           class="application-document-download"
                           aria-label="Download {{ $documentLabels[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type)) }}">
                            <i data-lucide="download" aria-hidden="true"></i> Download file
                        </a>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4 u-text-sm" >
                        No documents were uploaded with this application.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ===================================================== --}}
        {{-- SECTION 6 — PHYSICAL REQUIREMENTS                      --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Physical Requirements</span>
            </div>
            <div class="card-body">

                @if(in_array($application->academic_status, ['new_student', 'transferee']))

                    <p class="physical-requirements-note">Physical copies are still required, even when digital copies are uploaded.</p>

                    @if($application->academic_status === 'new_student')
                        <ul class="physical-requirements-list">
                            <li>Form 138 or equivalent</li>
                            <li>Certificate of Good Moral Character</li>
                            <li>PSA Birth Certificate</li>
                            @if($application->marital_status === 'married')
                                <li>Marriage Certificate — if applicable</li>
                            @endif
                            <li>2 copies recent 2×2 picture</li>
                            <li>2 copies recent 1×1 picture</li>
                            <li>2 pcs Long Brown Envelope</li>
                        </ul>
                    @elseif($application->academic_status === 'transferee')
                        <ul class="physical-requirements-list">
                            <li>Certificate of Transfer Credentials</li>
                            <li>Transcript of Records</li>
                            <li>Certificate of Good Moral Character</li>
                            <li>PSA Birth Certificate</li>
                            @if($application->marital_status === 'married')
                                <li>Marriage Certificate — if applicable</li>
                            @endif
                            <li>2 copies recent 2×2 picture</li>
                            <li>2 copies recent 1×1 picture</li>
                            <li>2 pcs Long Brown Envelope</li>
                        </ul>
                    @endif

                @elseif($application->academic_status === 'cross_enrollee')

                    <div class="physical-requirements-note">
                        Requirements for Cross-Enrollees are subject to confirmation
                        by the Registrar's Office.
                    </div>

                @else

                    <div class="text-muted u-text-sm" >
                        No additional physical documents are required at this
                        pre-enrollment stage for this applicant type.
                    </div>

                @endif

            </div>
        </div>

    </div>

    <div class="col-lg-4">

        {{-- ===================================================== --}}
        {{-- SECTION 7 — REVIEW INFORMATION                         --}}
        {{-- ===================================================== --}}
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Review Information</span>
            </div>
            <div class="card-body">
                @if($application->reviewed_at)
                    <div class="mb-2">
                        <div class="text-muted small">Reviewer</div>
                        <div>{{ $application->reviewer->name ?? '—' }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="text-muted small">Review Date</div>
                        <div>{{ $application->reviewed_at->format('M d, Y h:i A') }}</div>
                    </div>
                    <div class="mb-0">
                        <div class="text-muted small">Registrar Remarks</div>
                        <div>{{ $application->remarks ?: '—' }}</div>
                    </div>
                @else
                    <div class="text-muted u-text-sm" >
                        Not yet reviewed
                    </div>
                @endif
            </div>
        </div>

        @foreach($application->cancellationRequests as $cancellationRequest)
            <div class="card {{ $cancellationRequest->status === 'pending' ? 'border-warning' : '' }}">
                <div class="card-header"><span class="font-weight-bold">Cancellation Request — {{ ucfirst($cancellationRequest->status) }}</span></div>
                <div class="card-body">
                    <p class="mb-2"><strong>Applicant reason:</strong> {{ $cancellationRequest->reason }}</p>
                    <p class="text-muted small mb-2">Requested {{ $cancellationRequest->created_at->format('M d, Y h:i A') }} from status {{ str_replace('_', ' ', $cancellationRequest->previous_status) }}.</p>
                    @if($cancellationRequest->review_remarks)
                        <p class="mb-2"><strong>Review remarks:</strong> {{ $cancellationRequest->review_remarks }}</p>
                    @endif
                    @if($cancellationRequest->reviewer)
                        <p class="text-muted small">Reviewed by {{ $cancellationRequest->reviewer->name ?? 'Unavailable reviewer' }} {{ optional($cancellationRequest->reviewed_at)->format('M d, Y h:i A') }}</p>
                    @endif
                    @if($cancellationRequest->status === 'pending')
                        <form method="POST" action="{{ route('registrar.applications.cancellation.approve', $cancellationRequest) }}" class="mb-2" id="approve-cancellation-{{ $cancellationRequest->id }}">
                            @csrf
                            <button class="btn btn-danger btn-block" type="button" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Approve cancellation?" data-dtc-confirm-message="The application for {subject} will be cancelled and its record will be retained." data-dtc-confirm-subject="{{ $application->full_name }}" data-dtc-confirm-ok="Approve" data-dtc-confirm-form="#approve-cancellation-{{ $cancellationRequest->id }}">Approve Cancellation</button>
                        </form>
                        <form method="POST" action="{{ route('registrar.applications.cancellation.reject', $cancellationRequest) }}" id="decline-cancellation-{{ $cancellationRequest->id }}">
                            @csrf
                            <label for="cancellation-remarks-{{ $cancellationRequest->id }}">Reason for declining</label>
                            <textarea id="cancellation-remarks-{{ $cancellationRequest->id }}" name="remarks" class="form-control mb-2" rows="2" maxlength="500" required></textarea>
                            <button class="btn btn-outline-secondary btn-block" type="button" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Decline cancellation request?" data-dtc-confirm-message="The application for {subject} will remain active and the entered reason will be sent." data-dtc-confirm-subject="{{ $application->full_name }}" data-dtc-confirm-ok="Decline" data-dtc-confirm-form="#decline-cancellation-{{ $cancellationRequest->id }}">Decline Request</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- Approved account info --}}
        @if($application->status === 'approved')
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Linked Account</span>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <div class="text-muted small">Name</div>
                    <div>{{ $application->user->name ?? '—' }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted small">Email</div>
                    <div>{{ $application->user->email ?? '—' }}</div>
                </div>
                <div class="mb-0">
                    <div class="text-muted small">Status</div>
                    <div>
                        <span class="badge badge-success">
                            {{ $application->user?->status ? ucfirst($application->user->status) : '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ===================================================== --}}
        {{-- SECTION 8 — ACTIONS                                    --}}
        {{-- ===================================================== --}}
        @if($application->status === 'submitted' && !$application->pendingCancellationRequest)
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Registrar Actions</span>
            </div>
            <div class="card-body">
                <form id="start-review-{{ $application->id }}"
                      method="POST"
                      action="{{ route('registrar.applications.start-review', $application) }}">
                    @csrf
                    <button type="button"
                            class="application-decision application-decision--start"
                            data-dtc-confirm
                            data-dtc-confirm-title="Start review?"
                            data-dtc-confirm-message="The application for {subject} will be marked as under review and a notification will be sent."
                            data-dtc-confirm-subject="{{ $application->full_name }}"
                            data-dtc-confirm-ok="Start"
                            data-dtc-confirm-tone="neutral"
                            data-dtc-confirm-form="#start-review-{{ $application->id }}">
                        Start Review
                    </button>
                </form>
            </div>
        </div>
        @elseif($application->status === 'under_review' && !$application->pendingCancellationRequest)
        <div class="card">
            <div class="card-header">
                <span class="font-weight-bold">Registrar Actions</span>
            </div>
            <div class="card-body application-review-actions">

                <form id="approve-application-{{ $application->id }}"
                      method="POST"
                      action="{{ route('registrar.applications.approve', $application) }}"
                      class="application-review-approve-form">
                    @csrf
                    <button type="button"
                            class="application-decision application-decision--approve"
                            data-dtc-confirm
                            data-dtc-confirm-title="Approve application?"
                            data-dtc-confirm-message="The application for {subject} will be approved and an account will be created."
                            data-dtc-confirm-subject="{{ $application->full_name }}"
                            data-dtc-confirm-ok="Approve"
                            data-dtc-confirm-tone="success"
                            data-dtc-confirm-form="#approve-application-{{ $application->id }}">
                        Approve
                    </button>
                </form>

                <button type="button" class="application-decision application-decision--revise"
                        data-toggle="modal" data-target="#revisionModal">
                    Request Revision
                </button>

                <button type="button" class="application-decision application-decision--reject"
                        data-toggle="modal" data-target="#rejectModal">
                    Reject
                </button>

            </div>
        </div>
        @elseif($application->status === 'revision_required')
        <div class="card">
            <div class="card-body">
                <div class="text-muted u-text-sm">
                    This application requires revision. Review the Registrar remarks above before further action.
                </div>
            </div>
        </div>
        @elseif($application->status === 'approved')
        <div class="card">
            <div class="card-body">
                <div class="text-muted u-text-sm">
                    This application has been approved. No further action is available.
                </div>
            </div>
        </div>
        @elseif($application->status === 'rejected')
        <div class="card">
            <div class="card-body">
                <div class="text-muted u-text-sm" >
                    This application has been rejected. Review the Registrar remarks above for the reason.
                </div>
            </div>
        </div>
        @elseif($application->status === 'cancelled')
        <div class="card">
            <div class="card-body"><div class="text-muted u-text-sm">This application was cancelled after Registrar review. The application and cancellation history remain on file.</div></div>
        </div>
        @elseif($application->pendingCancellationRequest)
        <div class="card">
            <div class="card-body"><div class="text-muted u-text-sm">Resolve the pending cancellation request above before continuing the application review.</div></div>
        </div>
        @else
        <div class="card">
            <div class="card-body">
                <div class="text-muted u-text-sm">
                    This application has an unrecognized status. No Registrar action is available.
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

@if($application->status === 'under_review')
{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('registrar.applications.reject', $application) }}" id="reject-application-{{ $application->id }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject Application</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <label>Reason / Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3" maxlength="500" required></textarea>
                </div>
                <div class="modal-footer dtc-balanced-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Reject application?" data-dtc-confirm-message="The application for {subject} will be rejected with the entered remarks." data-dtc-confirm-subject="{{ $application->full_name }}" data-dtc-confirm-ok="Reject" data-dtc-confirm-form="#reject-application-{{ $application->id }}">Reject Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Revision Modal --}}
<div class="modal fade" id="revisionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('registrar.applications.revision', $application) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Request Revision</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <label>Revision Instructions / Required Corrections</label>
                    <textarea name="remarks" class="form-control" rows="3" maxlength="500" required></textarea>
                </div>
                <div class="modal-footer dtc-balanced-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Request Revision</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif


</div>
@endsection
