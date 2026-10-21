@extends('layouts.public')

@section('title', 'Application Submitted')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/application-success.css') }}">
@stop

@section('content')
@php
    $hasEducationDetails = collect([$application->education_level, $application->education_status, $application->most_recent_school, $application->school_year_completed, $application->school_location, $application->shs_strand, $application->previous_program, $application->last_year_level, $application->home_institution, $application->home_program])->filter()->isNotEmpty();
    $applicantType = match ($application->academic_status) {
        'new_student' => 'New Student / First Year',
        'cross_enrollee' => 'Cross-Enrollee',
        default => ucwords(str_replace('_', ' ', $application->academic_status)),
    };
@endphp

<div class="application-success-page">
    <div class="application-success-shell">
        <header class="application-success-hero">
            <div class="application-success-icon" aria-hidden="true"><i data-lucide="check"></i></div>
            <p class="application-success-eyebrow">Admissions application received</p>
            <h1>Application submitted successfully</h1>
            <p>Your pre-enrollment application is now awaiting preliminary screening by the Registrar.</p>
        </header>

        <section class="application-reference-card" aria-labelledby="application-reference-title">
            <div class="application-reference-heading">
                <div>
                    <p class="application-success-eyebrow">Keep this for your records</p>
                    <h2 id="application-reference-title">Application reference number</h2>
                </div>
                <span class="application-submitted-badge"><i data-lucide="clock-3" aria-hidden="true"></i> Submitted</span>
            </div>
            <div class="application-reference-value">{{ $application->reference_no }}</div>
            <div class="application-reference-footer">
                <p>Use this reference number and your email address to check your application status.</p>
                <button type="button" class="application-copy-reference" data-reference-copy="{{ $application->reference_no }}">
                    <i data-lucide="copy" aria-hidden="true"></i><span>Copy reference</span>
                </button>
            </div>
            <span class="visually-hidden" aria-live="polite" id="referenceCopyStatus"></span>
        </section>

        <div class="application-success-grid">
            <section class="application-success-card" aria-labelledby="application-summary-title">
                <div class="application-success-card-heading">
                    <div class="application-success-card-icon"><i data-lucide="file-text" aria-hidden="true"></i></div>
                    <div><h2 id="application-summary-title">Application summary</h2><p>Review the details submitted for this application.</p></div>
                </div>
                <dl class="application-summary-list">
                    <div><dt>Applicant</dt><dd>{{ $application->full_name }}</dd></div>
                    <div><dt>Applicant type</dt><dd>{{ $applicantType }}</dd></div>
                    <div><dt>Email address</dt><dd>{{ $application->email }}</dd></div>
                    <div><dt>Current status</dt><dd><span class="application-submitted-badge">Submitted</span></dd></div>
                </dl>
            </section>

            <aside class="application-next-steps" aria-labelledby="application-next-steps-title">
                <div class="application-success-card-icon"><i data-lucide="list-checks" aria-hidden="true"></i></div>
                <h2 id="application-next-steps-title">What happens next</h2>
                <ol>
                    <li>The Registrar reviews your submitted information.</li>
                    <li>Check your status using your reference number and email.</li>
                    <li>Follow any instructions or document requests from the Registrar.</li>
                </ol>
            </aside>
        </div>

        <section class="application-success-card" aria-labelledby="education-summary-title">
            <div class="application-success-card-heading">
                <div class="application-success-card-icon"><i data-lucide="graduation-cap" aria-hidden="true"></i></div>
                <div><h2 id="education-summary-title">Educational background submitted</h2><p>Optional education details included with this application.</p></div>
            </div>
            @if($hasEducationDetails)
                <dl class="application-education-list">
                    @foreach(['Education level' => $application->education_level, 'Completion status' => $application->education_status, 'Most recent school' => $application->most_recent_school, 'School year' => $application->school_year_completed, 'School location' => $application->school_location, 'SHS strand' => $application->shs_strand, 'Previous or current program' => $application->previous_program, 'Last year level or semester attended' => $application->last_year_level, 'Home institution' => $application->home_institution, 'Home program' => $application->home_program] as $label => $value)
                        @if($value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif
                    @endforeach
                </dl>
            @else
                <p class="application-empty-detail">No optional educational background details were provided with this application.</p>
            @endif
        </section>

        @if(!empty($physicalRequirements))
            <section class="application-success-card" aria-labelledby="physical-requirements-title">
                <div class="application-success-card-heading">
                    <div class="application-success-card-icon"><i data-lucide="clipboard-list" aria-hidden="true"></i></div>
                    <div><h2 id="physical-requirements-title">Prepare these physical requirements</h2><p>Bring the applicable original documents to the Registrar's Office when requested.</p></div>
                </div>
                <ul class="application-requirements-list">
                    @foreach($physicalRequirements as $requirement)
                        <li><i data-lucide="check" aria-hidden="true"></i>{{ $requirement }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="application-screening-notice" aria-labelledby="screening-notice-title">
            <i data-lucide="info" aria-hidden="true"></i>
            <div><h2 id="screening-notice-title">Preliminary screening only</h2><p>Uploaded documents support preliminary screening. Original or physical documents are still required for Registrar verification before official enrollment.</p></div>
        </section>

        <div class="application-success-actions">
            <a href="{{ route('public.application.status.form') }}" class="application-success-button application-success-button--primary"><i data-lucide="search" aria-hidden="true"></i> Check application status</a>
            <a href="{{ route('public.application.create') }}" class="application-success-button application-success-button--secondary"><i data-lucide="plus" aria-hidden="true"></i> Submit another application</a>
            <a href="{{ route('password.activation.request') }}" class="application-success-button application-success-button--secondary">Request account activation link</a>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var copyButton = document.querySelector('[data-reference-copy]');
        var copyStatus = document.getElementById('referenceCopyStatus');
        if (!copyButton || !navigator.clipboard) return;
        copyButton.addEventListener('click', function () {
            navigator.clipboard.writeText(copyButton.dataset.referenceCopy).then(function () {
                copyButton.querySelector('span').textContent = 'Copied';
                copyStatus.textContent = 'Application reference copied to clipboard.';
            });
        });
    });
</script>
@endsection
