@extends('layouts.public')

@section('title', 'Application Submitted')

@section('content')

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">

                {{-- Success Header --}}
                <div class="text-center px-4 py-5">

                    <div class="mb-4">
                        <div
                            class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10"
                            style="width: 80px; height: 80px;">
                            <i data-lucide="check" class="text-success" style="font-size: 36px;"></i>
                        </div>
                    </div>

                    <h2 class="fw-bold mb-2">
                        Application Submitted Successfully
                    </h2>

                    <p class="text-muted mb-0">
                        Your pre-enrollment application has been successfully submitted
                        for preliminary screening.
                    </p>

                </div>

                {{-- Reference Number --}}
                <div class="px-4 pb-4">
                    <div class="bg-light rounded-4 p-4 text-center">

                        <p class="text-muted small text-uppercase fw-semibold mb-2">
                            Your Application Reference Number
                        </p>

                        <div class="fs-3 fw-bold text-primary mb-2">
                            {{ $application->reference_no }}
                        </div>

                        <p class="text-muted small mb-0">
                            Please save this reference number. You will need it together
                            with your email address to check your application status.
                        </p>

                    </div>
                </div>

                <div class="px-4 pb-4">
                    <div class="border rounded-3 p-3">
                        <h3 class="h6 fw-bold mb-3">Educational background submitted</h3>
                        <div class="row g-3 small">
                            <div class="col-sm-6">
                                <div class="text-muted">Education level</div>
                                <div>{{ $application->education_level ?: 'Not provided' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted">Most recent school</div>
                                <div>{{ $application->most_recent_school ?: 'Not provided' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted">Completion status</div>
                                <div>{{ $application->education_status ?: 'Not provided' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted">School year</div>
                                <div>{{ $application->school_year_completed ?: 'Not provided' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted">School location</div>
                                <div>{{ $application->school_location ?: 'Not provided' }}</div>
                            </div>
                            @if($application->academic_status === 'new_student' && $application->shs_strand)
                                <div class="col-sm-6"><div class="text-muted">SHS strand</div><div>{{ $application->shs_strand }}</div></div>
                            @endif
                            @if($application->previous_program)
                                <div class="col-sm-6"><div class="text-muted">Previous or current program</div><div>{{ $application->previous_program }}</div></div>
                            @endif
                            @if($application->last_year_level)
                                <div class="col-sm-6"><div class="text-muted">Last year level or semester attended</div><div>{{ $application->last_year_level }}</div></div>
                            @endif
                            @if($application->home_institution)
                                <div class="col-sm-6"><div class="text-muted">Home institution</div><div>{{ $application->home_institution }}</div></div>
                            @endif
                            @if($application->home_program)
                                <div class="col-sm-6"><div class="text-muted">Home program</div><div>{{ $application->home_program }}</div></div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Important Notice --}}
                <div class="px-4 pb-4">

                    <div class="alert alert-warning border-0 rounded-4 mb-0">
                        <div class="d-flex">

                            <div class="me-3">
                                <i data-lucide="alert-triangle"></i>
                            </div>

                            <div>
                                <h6 class="fw-bold mb-2">
                                    Important
                                </h6>

                                <p class="mb-0 small">
                                    Uploaded documents are for preliminary screening only.
                                    You must still present the required original/physical
                                    documents to the Registrar's Office for verification
                                    before official enrollment.
                                </p>
                            </div>

                        </div>
                    </div>

                </div>

                {{-- Application Information --}}
                <div class="px-4 pb-4">

                    <div class="border rounded-4 p-4">

                        <h5 class="fw-bold mb-3">
                            Application Information
                        </h5>

                        <div class="row g-3">

                            <div class="col-md-6">
                                <div class="text-muted small">
                                    Applicant
                                </div>

                                <div class="fw-semibold">
                                    {{ $application->first_name }}
                                    {{ $application->middle_name ? $application->middle_name . ' ' : '' }}
                                    {{ $application->last_name }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">
                                    Applicant Type
                                </div>

                                <div class="fw-semibold">
                                    {{ ucwords(str_replace('_', ' ', $application->academic_status)) }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">
                                    Email
                                </div>

                                <div class="fw-semibold">
                                    {{ $application->email }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">
                                    Status
                                </div>

                                <span class="badge bg-warning text-dark">
                                    Submitted
                                </span>
                            </div>

                        </div>

                    </div>

                </div>

                {{-- Physical Requirements --}}
                @if(in_array($application->academic_status, ['new_student', 'transferee']))

                <div class="px-4 pb-4">

                    <div class="border rounded-4 p-4">

                        <h5 class="fw-bold mb-2">
                            Physical Documents & Requirements
                        </h5>

                        <p class="text-muted small mb-3">
                            Please prepare the following requirements for presentation
                            to the Registrar's Office.
                        </p>

                        @if($application->academic_status === 'new_student')

                        <ul class="small mb-0">
                            <li class="mb-2">
                                Form 138 or equivalent — original/required physical copy
                            </li>
                            <li class="mb-2">
                                Certificate of Good Moral Character — original/required physical copy
                            </li>
                            <li class="mb-2">
                                PSA Birth Certificate — required physical copy
                            </li>

                            @if($application->marital_status === 'married')
                            <li class="mb-2">
                                Marriage Certificate — if applicable
                            </li>
                            @endif

                            <li class="mb-2">
                                2 copies recent 2×2 picture
                            </li>
                            <li class="mb-2">
                                2 copies recent 1×1 picture
                            </li>
                            <li>
                                2 pcs Long Brown Envelope
                            </li>
                        </ul>

                        @elseif($application->academic_status === 'transferee')

                        <ul class="small mb-0">
                            <li class="mb-2">
                                Certificate of Transfer Credentials
                            </li>
                            <li class="mb-2">
                                Transcript of Records
                            </li>
                            <li class="mb-2">
                                Certificate of Good Moral Character
                            </li>
                            <li class="mb-2">
                                PSA Birth Certificate
                            </li>

                            @if($application->marital_status === 'married')
                            <li class="mb-2">
                                Marriage Certificate — if applicable
                            </li>
                            @endif

                            <li class="mb-2">
                                2 copies recent 2×2 picture
                            </li>
                            <li class="mb-2">
                                2 copies recent 1×1 picture
                            </li>
                            <li>
                                2 pcs Long Brown Envelope
                            </li>
                        </ul>

                        @endif

                    </div>

                </div>

                @endif

                {{-- Actions --}}
                <div class="border-top px-4 py-4">

                    <div class="d-flex flex-column flex-md-row justify-content-center gap-2">
                        <a
                        href="{{ route('public.application.status.form') }}"
                        class="btn btn-primary px-4">
                        <i data-lucide="search" class="me-2"></i>
                        Check Application Status
                        </a>
                        <a
                        href="{{ route('public.application.create') }}"
                        class="btn btn-outline-secondary px-4">
                        <i data-lucide="plus" class="me-2"></i>
                        Submit Another Application
                        </a>
                        <a
                        href="{{ route('password.activation.request') }}"
                        class="btn btn-outline-secondary px-4">
                        Request Account Activation Link
                        </a>

                    </div>

                </div>

            </div>

        </div>
    </div>
</div>

@endsection
