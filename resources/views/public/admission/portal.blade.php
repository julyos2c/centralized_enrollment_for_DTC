@extends('layouts.public')

@section('title', 'Admission Portal — ' . $application->reference_no)

@section('styles')
<link rel="stylesheet" href="{{ asset_v('css/application-wizard.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/admission.css') }}">
@stop

@php
    $summary = $tracker['summary'];
    $booking = $tracker['booking'];
    $latestBooking = $tracker['latestBooking'];
    $markerIcons = ['completed' => 'check', 'current' => 'circle-dot', 'action_required' => 'alert-circle', 'rejected' => 'x', 'skipped' => 'minus', 'pending' => 'circle'];
    $stateChips = [
        'missing' => ['Not uploaded', ''],
        'submitted' => ['Awaiting review', 'is-info'],
        'approved' => ['Approved', 'is-success'],
        'rejected' => ['Needs replacement', 'is-danger'],
    ];
    $statusTone = ['approved' => 'is-success', 'rejected' => 'is-danger', 'cancelled' => 'is-danger', 'revision_required' => 'is-warning', 'under_review' => 'is-info'];
    $canUpload = !in_array($application->status, ['rejected', 'cancelled'], true);
@endphp

@section('content')
<div class="app-wizard-page status-lookup-page adm-portal-page">
    <div class="app-wizard-topbar">
        <a href="{{ route('landing') }}" class="app-wizard-back" aria-label="Back to home">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
        </a>
        @unless($signedInApplicant)
            <form method="POST" action="{{ route('public.admission.leave') }}" class="ms-auto">
                @csrf
                <button type="submit" class="adm-btn adm-btn-sm"><i data-lucide="log-out" aria-hidden="true"></i> Leave portal</button>
            </form>
        @endunless
    </div>

    <header class="app-wizard-intro">
        <div class="status-page-heading">
            <h1>Admission portal</h1>
            <span class="adm-chip {{ $statusTone[$application->status] ?? '' }}" role="status">{{ ucwords(str_replace('_', ' ', $application->status)) }}</span>
        </div>
        <p>Track your application, upload requirements, and manage your entrance examination.</p>
    </header>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Summary + next step --}}
    <section class="wiz-panel" aria-labelledby="adm-summary-title">
        <div class="wiz-panel-header">
            <div class="wiz-panel-icon"><i data-lucide="id-card" aria-hidden="true"></i></div>
            <div>
                <h2 class="wiz-panel-title" id="adm-summary-title">{{ $application->full_name }}</h2>
                <p class="wiz-panel-sub">Reference {{ $application->reference_no }}</p>
            </div>
        </div>
        <div class="wiz-panel-body">
            <dl class="adm-facts mb-4">
                <div><dt>Program</dt><dd>{{ $application->program->name ?? '—' }}</dd></div>
                <div><dt>Applicant type</dt><dd>{{ \App\Models\AdmissionRequirement::APPLICANT_TYPES[$application->academic_status] ?? ucwords(str_replace('_', ' ', $application->academic_status)) }}</dd></div>
                <div><dt>Term</dt><dd>{{ $application->school_year ? $application->school_year . ($application->semester ? ' · ' . $application->semester . ' sem' : '') : '—' }}</dd></div>
                <div><dt>Submitted</dt><dd>{{ $application->created_at->format('M d, Y') }}</dd></div>
            </dl>

            <div class="adm-next" role="status">
                <div class="adm-next-icon"><i data-lucide="footprints" aria-hidden="true"></i></div>
                <div>
                    <h3>Next step: {{ $tracker['nextStep']['title'] }}</h3>
                    <p>{{ $tracker['nextStep']['description'] }}</p>
                    @if($tracker['nextStep']['anchor'])
                        <a class="adm-next-link" href="{{ $tracker['nextStep']['anchor'] }}">Go there <i data-lucide="arrow-down" aria-hidden="true"></i></a>
                    @elseif($application->status === 'approved')
                        <a class="adm-next-link" href="{{ $application->user ? route('login') : route('password.activation.request') }}">{{ $application->user ? 'Sign in to the student portal' : 'Request an activation link' }} <i data-lucide="arrow-right" aria-hidden="true"></i></a>
                    @endif
                </div>
            </div>

            @if(in_array($application->status, ['revision_required', 'rejected'], true) && filled($application->remarks))
                <section class="status-remarks mt-3" aria-labelledby="adm-remarks-title">
                    <h3 id="adm-remarks-title">Registrar remarks</h3>
                    <div class="status-remarks-copy">{{ $application->remarks }}</div>
                </section>
            @endif
        </div>
    </section>

    {{-- Progress tracker --}}
    <section class="wiz-panel" aria-labelledby="adm-progress-title">
        <div class="wiz-panel-header">
            <div class="wiz-panel-icon"><i data-lucide="route" aria-hidden="true"></i></div>
            <div>
                <h2 class="wiz-panel-title" id="adm-progress-title">Admission progress</h2>
                <p class="wiz-panel-sub">{{ $tracker['percent'] }}% complete</p>
            </div>
        </div>
        <div class="wiz-panel-body">
            <div class="adm-progress-bar" role="progressbar" aria-valuenow="{{ $tracker['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Admission progress"><span style="width: {{ $tracker['percent'] }}%"></span></div>
            <ol class="adm-tracker">
                @foreach($tracker['steps'] as $step)
                    <li class="is-{{ $step['status'] }}">
                        <span class="adm-tracker-marker" aria-hidden="true"><i data-lucide="{{ $markerIcons[$step['status']] ?? 'circle' }}"></i></span>
                        <div>
                            <strong>{{ $step['label'] }} <span class="visually-hidden">({{ str_replace('_', ' ', $step['status']) }})</span></strong>
                            <p>{{ $step['description'] }}</p>
                            @if($step['timestamp'])
                                <time datetime="{{ $step['timestamp']->toIso8601String() }}">{{ $step['timestamp']->format('M d, Y g:i A') }}</time>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Requirements --}}
    <section class="wiz-panel" id="requirements" aria-labelledby="adm-req-title">
        <div class="wiz-panel-header">
            <div class="wiz-panel-icon"><i data-lucide="folder-up" aria-hidden="true"></i></div>
            <div>
                <h2 class="wiz-panel-title" id="adm-req-title">Admission requirements</h2>
                <p class="wiz-panel-sub">
                    @if($summary['total'])
                        {{ $summary['uploaded'] }} of {{ $summary['total'] }} uploaded · {{ $summary['approved'] }} approved
                    @else
                        No uploads are needed for your applicant type.
                    @endif
                </p>
            </div>
        </div>
        <div class="wiz-panel-body">
            @if($tracker['checklist']->isEmpty())
                <p class="text-muted mb-0">The Registrar's Office will tell you if any documents are needed.</p>
            @else
                <ul class="adm-req-list">
                    @foreach($tracker['checklist'] as $item)
                        @php
                            $requirement = $item['requirement'];
                            $document = $item['document'];
                            [$chipLabel, $chipTone] = $stateChips[$item['state']] ?? [ucfirst($item['state']), ''];
                        @endphp
                        <li class="adm-req is-{{ $item['state'] }}">
                            <div class="adm-req-head">
                                <h3 class="adm-req-title">
                                    {{ $requirement->name }}
                                    @if($requirement->description)<small>{{ $requirement->description }}</small>@endif
                                </h3>
                                <div class="adm-req-tags">
                                    <span class="adm-chip {{ $requirement->is_required ? 'is-warning' : '' }}">{{ $requirement->is_required ? 'Required' : 'Optional' }}</span>
                                    <span class="adm-chip {{ $chipTone }}">{{ $chipLabel }}</span>
                                </div>
                            </div>

                            @if($document)
                                <div class="adm-req-file">
                                    <i data-lucide="file-text" aria-hidden="true"></i>
                                    <span class="adm-req-file-name">{{ $document->original_name }}</span>
                                    <span class="adm-req-file-meta">{{ $document->created_at->format('M d, Y g:i A') }}@if($document->size_label) · {{ $document->size_label }}@endif</span>
                                    <a class="adm-btn adm-btn-sm" href="{{ route('public.admission.document', $document) }}" target="_blank" rel="noopener">
                                        <i data-lucide="eye" aria-hidden="true"></i> {{ $document->isPreviewable() ? 'Preview' : 'Download' }}
                                    </a>
                                </div>
                            @endif

                            @if($item['state'] === 'rejected' && $document?->review_remarks)
                                <div class="adm-req-reason"><strong>Why it was rejected:</strong> {{ $document->review_remarks }}</div>
                            @endif

                            @if($canUpload && $item['state'] !== 'approved')
                                <form method="POST" action="{{ route('public.admission.upload', $requirement) }}" enctype="multipart/form-data" class="adm-req-upload" data-adm-upload>
                                    @csrf
                                    <label class="visually-hidden" for="upload-{{ $requirement->id }}">Choose a file for {{ $requirement->name }}</label>
                                    <input type="file" name="file" id="upload-{{ $requirement->id }}" class="form-control" accept="{{ $requirement->acceptList() }}" required>
                                    <button type="submit" class="adm-btn adm-btn-primary">
                                        <i data-lucide="upload" aria-hidden="true"></i> {{ $document ? 'Replace file' : 'Upload' }}
                                    </button>
                                </form>
                                <div class="adm-req-hint">{{ $requirement->formatSummary() }}</div>
                            @elseif($item['state'] === 'approved')
                                <div class="adm-req-hint">Approved documents are locked. Contact the Registrar's Office if this one needs correction.</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="adm-req-hint mt-3 mb-0"><i data-lucide="info" aria-hidden="true" style="width:14px;height:14px"></i> Uploaded copies are for screening. Original documents are still checked by the Registrar's Office before official enrollment.</p>
            @endif
        </div>
    </section>

    {{-- Entrance examination --}}
    @if($tracker['examEnabled'] || $latestBooking)
    <section class="wiz-panel" id="exam" aria-labelledby="adm-exam-title">
        <div class="wiz-panel-header">
            <div class="wiz-panel-icon"><i data-lucide="calendar-clock" aria-hidden="true"></i></div>
            <div>
                <h2 class="wiz-panel-title" id="adm-exam-title">Entrance examination</h2>
                <p class="wiz-panel-sub">{{ $settings['exam_mode'] === 'required' ? 'Required for admission' : 'Schedule your examination' }}</p>
            </div>
        </div>
        <div class="wiz-panel-body">
            @if($booking)
                <div class="adm-exam-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <h3>{{ $booking->session->title }}</h3>
                        <span class="adm-chip {{ $booking->status === 'confirmed' ? 'is-success' : ($booking->status === 'pending' ? 'is-warning' : 'is-info') }}">{{ $booking->statusLabel() }}</span>
                    </div>
                    <dl class="adm-facts mt-2">
                        <div><dt>Date &amp; time</dt><dd>{{ $booking->session->scheduleLabel() }}</dd></div>
                        <div><dt>Venue</dt><dd>{{ $booking->session->venue }}</dd></div>
                    </dl>
                    @if($booking->session->notes)
                        <p class="adm-req-hint mb-0 mt-2">{{ $booking->session->notes }}</p>
                    @endif

                    @if($booking->status === 'attended')
                        @if($booking->isResultReleased())
                            <div class="adm-result {{ $booking->result === 'passed' ? 'is-passed' : 'is-failed' }}" role="status">
                                <i data-lucide="{{ $booking->result === 'passed' ? 'badge-check' : 'circle-x' }}" aria-hidden="true"></i>
                                <div>
                                    <strong>{{ $booking->result === 'passed' ? 'Passed' : 'Did not pass' }}</strong>
                                    @if($booking->score !== null)<div>Score: {{ rtrim(rtrim((string) $booking->score, '0'), '.') }}</div>@endif
                                    @if($booking->result_remarks)<div>{{ $booking->result_remarks }}</div>@endif
                                </div>
                            </div>
                        @else
                            <p class="adm-req-hint mt-3 mb-0">You attended this examination. Your result will appear here once the Registrar's Office releases it.</p>
                        @endif
                    @elseif(in_array($booking->status, ['pending', 'confirmed'], true))
                        @if($changeBlocker)
                            <p class="adm-req-hint mt-3 mb-0">{{ $changeBlocker }}</p>
                        @else
                            <div class="adm-req-actions">
                                @if($sessions->where('id', '!=', $booking->entrance_exam_session_id)->isNotEmpty())
                                    <details class="w-100">
                                        <summary class="adm-btn adm-btn-sm d-inline-flex"><i data-lucide="calendar-sync" aria-hidden="true"></i> Change schedule</summary>
                                        <form method="POST" action="{{ route('public.admission.exam.reschedule', $booking) }}" class="mt-3">
                                            @csrf
                                            <fieldset>
                                                <legend class="adm-section-title">Choose a new session</legend>
                                                <div class="adm-session-grid">
                                                    @foreach($sessions->where('id', '!=', $booking->entrance_exam_session_id) as $session)
                                                        @include('public.admission.partials.session-option', ['session' => $session, 'inputName' => 'session_id', 'idPrefix' => 'move'])
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                            <button type="submit" class="adm-btn adm-btn-primary mt-3">Move my booking</button>
                                        </form>
                                    </details>
                                @endif
                                <form method="POST" action="{{ route('public.admission.exam.cancel', $booking) }}" id="adm-cancel-booking">
                                    @csrf
                                    <button type="button" class="adm-btn adm-btn-sm adm-btn-danger"
                                            data-dtc-confirm data-dtc-confirm-tone="danger"
                                            data-dtc-confirm-title="Cancel exam booking?"
                                            data-dtc-confirm-message="Your seat on {subject} will be released. You can book another session afterwards."
                                            data-dtc-confirm-subject="{{ $booking->session->scheduleLabel() }}"
                                            data-dtc-confirm-ok="Cancel booking"
                                            data-dtc-confirm-form="#adm-cancel-booking">
                                        <i data-lucide="x" aria-hidden="true"></i> Cancel booking
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endif
                </div>
            @elseif($tracker['bookingBlocker'])
                <div class="wiz-inline-notice wiz-inline-notice--neutral">
                    <i data-lucide="info" aria-hidden="true"></i>
                    <span>{{ $tracker['bookingBlocker'] }}</span>
                </div>
            @elseif($sessions->isEmpty())
                <div class="wiz-inline-notice wiz-inline-notice--neutral">
                    <i data-lucide="calendar-x" aria-hidden="true"></i>
                    <span>No examination sessions are open right now. Please check again later; new sessions are posted by the Registrar's Office.</span>
                </div>
            @else
                <form method="POST" action="{{ route('public.admission.exam.book') }}">
                    @csrf
                    <fieldset>
                        <legend class="adm-section-title">Available sessions</legend>
                        <div class="adm-session-grid">
                            @foreach($sessions as $session)
                                @include('public.admission.partials.session-option', ['session' => $session, 'inputName' => 'session_id', 'idPrefix' => 'book'])
                            @endforeach
                        </div>
                    </fieldset>
                    <p class="adm-req-hint">
                        @if($settings['booking_requires_approval'])
                            Your request is confirmed by the Registrar's Office before it is final.
                        @else
                            Your seat is confirmed immediately.
                        @endif
                        @if($settings['applicant_changes_allowed'])
                            You can change or cancel up to {{ $settings['change_cutoff_hours'] }} hour(s) before the exam.
                        @endif
                    </p>
                    <button type="submit" class="wiz-btn wiz-btn--primary">Book selected session <i data-lucide="arrow-right" aria-hidden="true"></i></button>
                </form>
            @endif
        </div>
    </section>
    @endif

    <p class="status-apply-prompt">Questions? Contact the Registrar's Office and mention reference <strong>{{ $application->reference_no }}</strong>.</p>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form.matches('[data-adm-upload]')) return;
    var button = form.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; button.textContent = 'Uploading…'; }
});
</script>
@endsection
