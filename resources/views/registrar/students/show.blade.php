@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Academic Record')

@section('content_header')
    <div class="ar-page-heading academic-record-heading">
        <div>
            <h1>Academic Record</h1>
            <p>Record {{ $record->student_number }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('registrar.students.edit', $record) }}">Edit Record</a>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/academic-record-show.css') }}">
@endpush
@section('content')
<div class="registrar-workspace academic-record-page">

    @php
        $fullName = trim(implode(' ', array_filter([
            $record->first_name,
            $record->middle_name,
            $record->last_name,
            $record->suffix,
        ])));
        $yearLevel = $portalEnrollment?->year_level ?? $record->year_level;
        $yearLevelLabel = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year'][$yearLevel] ?? 'Not recorded';
        $details = [
            'Student ID no.' => $record->student_number,
            'Email' => $record->email ?: 'Not recorded',
            'Program' => $record->program->name ?? 'Not recorded',
            'Year Level' => $yearLevelLabel,
            'School Year' => $record->enrolled_ay ?: 'Not recorded',
            'Academic Status' => ucfirst($record->academic_status),
            'Graduation Year' => $record->graduation_year ?: 'Not recorded',
            'Graduation Date' => optional($record->graduation_date)->format('Y-m-d') ?: 'Not recorded',
        ];
    @endphp

    <section class="card ar-record-card" aria-label="Student academic details">
        <div class="card-body">
            @if (session('success'))<div class="record-notice record-notice-success" role="status">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="record-notice record-notice-error" role="alert">{{ session('error') }}</div>@endif
            @if ($errors->has('account'))<div class="record-notice record-notice-error" role="alert">{{ $errors->first('account') }}</div>@endif
            @php
                $linkedUser = $record->user;
                $invite = $record->portalInvitation;
                $portalState = ! $linkedUser ? 'No portal account' : ($linkedUser->status === 'locked' ? 'Disabled' : ($linkedUser->email_verified_at ? ucfirst($linkedUser->status) : ($invite ? ucfirst(str_replace('_', ' ', $invite->status)) : 'Account pending')));
            @endphp
            <section class="record-portal" aria-labelledby="portal-account-heading">
                <div class="record-account-heading">
                    <span class="record-account-icon" aria-hidden="true"><i data-lucide="shield-check"></i></span>
                    <h2 class="h5" id="portal-account-heading">Portal account</h2>
                </div>
                <span class="record-account-state is-{{ \Illuminate\Support\Str::slug(strtolower($portalState)) }}">{{ $portalState }}</span>
                @if ($linkedUser)
                    <p class="record-account-email">{{ $linkedUser->email }}</p>
                    <p class="record-account-help">{{ $linkedUser->email_verified_at ? 'Email verified.' : 'The recipient must verify their email before accessing the portal.' }}</p>
                @else
                    <p class="record-account-help">Set up access separately. The recipient verifies their email and chooses a password.</p>
                @endif
                <div class="record-account-actions">
                @if (! $linkedUser)
                    <a class="btn btn-primary btn-sm" href="{{ route('registrar.students.provision', $record) }}">{{ $record->academic_status === 'alumni' ? 'Set Up Alumni Access' : 'Create Portal Account' }}</a>
                @elseif ($invite && in_array($invite->status, ['pending', 'sending', 'delivery_failed', 'expired', 'revoked'], true) && $linkedUser->status === 'pending')
                    @php $sendingLeaseExpired = $invite->status === 'sending' && (! $invite->updated_at || $invite->updated_at->lte(now()->subMinutes(max(1, (int) config('portal_accounts.sending_lease_minutes', 15))))); @endphp
                    @if ($invite->status !== 'sending' || $sendingLeaseExpired)
                        <form class="d-inline" id="resend-invitation-{{ $record->id }}" method="POST" action="{{ route('registrar.students.invitation.resend', $record) }}">@csrf<button type="button" class="btn btn-outline-primary btn-sm" data-dtc-confirm data-dtc-confirm-tone="neutral" data-dtc-confirm-title="Send portal invitation?" data-dtc-confirm-message="A portal setup invitation will be sent to the academic record email for {subject}." data-dtc-confirm-subject="{{ $fullName ?: ($record->user->name ?? 'this record') }}" data-dtc-confirm-ok="Send" data-dtc-confirm-form="#resend-invitation-{{ $record->id }}">{{ $sendingLeaseExpired ? 'Retry Invitation' : ($invite->status === 'pending' ? 'Resend Invitation' : 'Send New Invitation') }}</button></form>
                    @else
                        <span class="text-muted small">Invitation delivery in progress</span>
                    @endif
                    @if (in_array($invite->status, ['pending', 'sending', 'delivery_failed', 'expired'], true))
                        <form class="d-inline" id="revoke-invitation-{{ $record->id }}" method="POST" action="{{ route('registrar.students.invitation.revoke', $record) }}">@csrf<input type="hidden" name="confirm" value="1"><button type="button" class="btn btn-outline-danger btn-sm" data-dtc-confirm data-dtc-confirm-tone="danger" data-dtc-confirm-title="Revoke invitation?" data-dtc-confirm-message="The unused portal link for {subject} will stop working." data-dtc-confirm-subject="{{ $fullName ?: ($record->user->name ?? 'this record') }}" data-dtc-confirm-ok="Revoke" data-dtc-confirm-form="#revoke-invitation-{{ $record->id }}">Revoke Invitation</button></form>
                    @endif
                @endif
                </div>
            </section>
            <section class="record-information" aria-labelledby="record-information-heading">
                <div class="record-person">
                    <div>
                        <h2 id="record-information-heading">{{ $fullName ?: ($record->user->name ?? 'Not recorded') }}</h2>
                        <p>Academic details</p>
                    </div>
                    <span class="record-academic-status is-{{ \Illuminate\Support\Str::slug($record->academic_status) }}">{{ ucfirst($record->academic_status) }}</span>
                </div>
            <dl class="ar-record-grid">
                @foreach ($details as $label => $value)
                    <div class="ar-record-field ar-record-field--{{ \Illuminate\Support\Str::slug($label) }}">
                        <dt>{{ $label }}</dt>
                        <dd>{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            </section>

            @if ($enrollments->isNotEmpty())
                <section class="ar-history-section">
                    <h2>Enrollment History</h2>
                    <div class="table-responsive">
<table id="registrar-table-1" class="rg-record-table dtc-mobile-records table table-sm ar-history-table">
                            <thead><tr><th>School Year</th><th>Semester</th><th>Program</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($enrollments as $enrollment)
                                    <tr>
                                        <td data-reg-label="School Year" data-mobile-label="School Year">{{ $enrollment->school_year }}</td>
                                        <td data-reg-label="Semester" data-mobile-label="Semester">{{ ucfirst($enrollment->semester) }}</td>
                                        <td data-reg-label="Program" data-mobile-label="Program">{{ $enrollment->program->name ?? '—' }}</td>
                                        <td data-reg-label="Status" data-mobile-label="Status">{{ ucfirst($enrollment->status) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($documentRequests->isNotEmpty())
                <section class="ar-history-section">
                    <h2>Certificate and Document Requests</h2>
                    <div class="table-responsive">
<table id="registrar-table-2" class="rg-record-table dtc-mobile-records table table-sm ar-history-table">
                            <thead><tr><th>Document</th><th>Requested</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($documentRequests as $documentRequest)
                                    <tr>
                                        <td data-reg-label="Document" data-mobile-label="Document">{{ $documentRequest->document_label }}</td>
                                        <td data-reg-label="Requested" data-mobile-label="Requested">{{ $documentRequest->created_at->format('M j, Y') }}</td>
                                        <td data-reg-label="Status" data-mobile-label="Status">{{ ucfirst($documentRequest->status) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <a class="btn btn-outline-secondary record-back" href="{{ route('registrar.students.index', ['type' => $record->academic_status === 'alumni' ? 'alumni' : 'students']) }}">Back to records</a>
        </div>
    </section>

</div>
@endsection
