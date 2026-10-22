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
        @if($application->status === 'cancelled')
            <header class="application-success-hero">
                <div class="application-success-icon is-cancelled" aria-hidden="true"><i data-lucide="x"></i></div>
                <p class="application-success-eyebrow">Admissions application</p>
                <h1>Application cancelled</h1>
                <p>The Registrar approved your cancellation request. Your application record remains on file.</p>
            </header>
        @else
            <header class="application-success-hero is-compact">
                <p class="application-success-eyebrow">Admissions application</p>
                <h1>Your application</h1>
            </header>
        @endif

        <section class="application-reference-card" aria-labelledby="application-reference-title">
            <h2 id="application-reference-title" class="application-reference-label">Application reference number</h2>
            <div class="application-reference-value">{{ $application->reference_no }}</div>
            <div class="application-reference-footer">
                <p>Copy this number, then enter it with your email on the status page.</p>
                <div class="application-reference-actions">
                    <button type="button" class="application-copy-reference" data-reference-copy="{{ $application->reference_no }}">
                        <i data-lucide="copy" aria-hidden="true"></i><span>Copy reference</span>
                    </button>
                    <a href="{{ route('public.application.status.form') }}" class="application-reference-link">Go to status page <i data-lucide="arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
            <span class="visually-hidden" aria-live="polite" id="referenceCopyStatus"></span>
        </section>

        @if($application->pendingCancellationRequest)
            <section class="application-screening-notice" role="status">
                <i data-lucide="hourglass" aria-hidden="true"></i>
                <div>Your cancellation request is awaiting Registrar review. The application remains {{ str_replace('_', ' ', $application->status) }} until a decision is recorded.</div>
            </section>
        @elseif(in_array($application->status, ['submitted', 'under_review', 'revision_required'], true) && !$application->user_id)
            <section class="application-cancel-card" aria-labelledby="cancel-application-title">
                <div class="application-cancel-head">
                    <div class="application-cancel-icon" aria-hidden="true"><i data-lucide="file-x-2"></i></div>
                    <div>
                        <h2 id="cancel-application-title">Request application cancellation</h2>
                        <p>This does not cancel the application immediately. The Registrar will review your request and decide whether it can be cancelled.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('public.application.cancel') }}" class="application-cancel-form" id="application-cancellation-form">
                    @csrf
                    <label for="cancellation-reason">Reason for the request</label>
                    <textarea id="cancellation-reason" name="reason" rows="3" minlength="5" maxlength="500" required placeholder="Tell the Registrar why you want to cancel this application…" data-cancel-reason>{{ old('reason') }}</textarea>
                    <div class="application-cancel-meta">
                        <span class="application-cancel-count" data-cancel-count aria-live="off">0 / 500</span>
                    </div>
                    @error('reason')<p class="application-cancel-error" role="alert">{{ $message }}</p>@enderror
                    @if($errors->has('cancellation'))<p class="application-cancel-error" role="alert">{{ $errors->first('cancellation') }}</p>@endif
                    <div class="application-cancel-actions">
                        <button type="button" class="application-cancel-button" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Request application cancellation?" data-dtc-confirm-message="This sends a cancellation request to the Registrar for review." data-dtc-confirm-ok="Send Request" data-dtc-confirm-form="#application-cancellation-form"><i data-lucide="send" aria-hidden="true"></i> Request cancellation</button>
                    </div>
                </form>
            </section>
        @endif

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
                    <div><dt>Current status</dt><dd><span class="application-submitted-badge">{{ ucwords(str_replace('_', ' ', $application->status)) }}</span></dd></div>
                </dl>
            </section>

            <aside class="application-next-steps" aria-labelledby="application-next-steps-title">
                <div class="application-success-card-icon"><i data-lucide="list-checks" aria-hidden="true"></i></div>
                <h2 id="application-next-steps-title">What happens next</h2>
                @if($application->status === 'cancelled')
                    <p>Your application will not proceed to enrollment. Contact the Registrar if you need guidance about applying again.</p>
                @else
                    <ol>
                        <li>The Registrar reviews your submitted information.</li>
                        <li>Check your status using your reference number and email.</li>
                        <li>Follow any instructions or document requests from the Registrar.</li>
                    </ol>
                @endif
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
        // Copy reference
        var copyButton = document.querySelector('[data-reference-copy]');
        var copyStatus = document.getElementById('referenceCopyStatus');
        if (copyButton && navigator.clipboard) {
            copyButton.addEventListener('click', function () {
                navigator.clipboard.writeText(copyButton.dataset.referenceCopy).then(function () {
                    copyButton.querySelector('span').textContent = 'Copied';
                    copyStatus.textContent = 'Application reference copied to clipboard.';
                });
            });
        }

        // Cancellation reason character counter
        var reason = document.querySelector('[data-cancel-reason]');
        var count = document.querySelector('[data-cancel-count]');
        if (reason && count) {
            var update = function () { count.textContent = reason.value.length + ' / ' + reason.maxLength; };
            reason.addEventListener('input', update);
            update();
        }

        // One-time success toast (not repeated on refresh or revisit in this tab)
        @if($application->status === 'submitted')
        var toastKey = 'dtc-application-toast-{{ $application->reference_no }}';
        try {
            if (!sessionStorage.getItem(toastKey)) {
                sessionStorage.setItem(toastKey, '1');
                window.dtcToast && window.dtcToast.success('Your pre-enrollment application was submitted. Keep your reference number.', { title: 'Application submitted' });
            }
        } catch (e) {
            window.dtcToast && window.dtcToast.success('Your pre-enrollment application was submitted.', { title: 'Application submitted' });
        }
        @endif
    });
</script>
@endsection
