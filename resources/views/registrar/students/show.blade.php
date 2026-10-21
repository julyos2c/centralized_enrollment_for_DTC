@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Academic Record')

@section('content_header')
    <div class="ar-page-heading">
        <div>
            <h1>Academic Record</h1>
            <p>Record {{ $record->student_number }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('registrar.students.edit', $record) }}">Edit Record</a>
    </div>
@endsection

@section('content')
    @php
        $fullName = trim(implode(' ', array_filter([
            $record->first_name,
            $record->middle_name,
            $record->last_name,
            $record->suffix,
        ])));
        $details = [
            'Student Number' => $record->student_number,
            'Name' => $fullName ?: ($record->user->name ?? 'Not recorded'),
            'Email' => $record->email ?: 'Not recorded',
            'Program' => $record->program->name ?? 'Not recorded',
            'Year Level' => $record->year_level ?: 'Not recorded',
            'School Year' => $record->enrolled_ay ?: 'Not recorded',
            'Academic Status' => ucfirst($record->academic_status),
            'Graduation Year' => $record->graduation_year ?: 'Not recorded',
            'Graduation Date' => optional($record->graduation_date)->format('Y-m-d') ?: 'Not recorded',
            'Portal Account' => $record->user ? 'Linked' : 'No portal account',
        ];
    @endphp

    <section class="card ar-record-card" aria-label="Student academic details">
        <div class="card-body">
            @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
            @if ($errors->has('account'))<div class="alert alert-danger" role="alert">{{ $errors->first('account') }}</div>@endif
            @php
                $linkedUser = $record->user;
                $invite = $record->portalInvitation;
                $portalState = ! $linkedUser ? 'No portal account' : ($linkedUser->status === 'locked' ? 'Disabled' : ($linkedUser->email_verified_at ? ucfirst($linkedUser->status) : ($invite ? ucfirst(str_replace('_', ' ', $invite->status)) : 'Account pending')));
            @endphp
            <section class="mb-4" aria-labelledby="portal-account-heading">
                <h2 class="h5" id="portal-account-heading">Portal account</h2>
                <p class="mb-2"><strong>{{ $portalState }}</strong>
                    @if ($linkedUser) <span class="text-muted">{{ $linkedUser->email }}{{ $linkedUser->email_verified_at ? ' · email verified' : ' · email not verified' }}</span>
                    @else <span class="text-muted">Account setup requires eligibility checks and recipient email verification.</span> @endif
                </p>
                @if (! $linkedUser)
                    <a class="btn btn-primary btn-sm" href="{{ route('registrar.students.provision', $record) }}">{{ $record->academic_status === 'alumni' ? 'Set Up Alumni Access' : 'Create Portal Account' }}</a>
                @elseif ($invite && in_array($invite->status, ['pending', 'sending', 'delivery_failed', 'expired', 'revoked'], true) && $linkedUser->status === 'pending')
                    @php $sendingLeaseExpired = $invite->status === 'sending' && (! $invite->updated_at || $invite->updated_at->lte(now()->subMinutes(max(1, (int) config('portal_accounts.sending_lease_minutes', 15))))); @endphp
                    @if ($invite->status !== 'sending' || $sendingLeaseExpired)
                        <form class="d-inline" method="POST" action="{{ route('registrar.students.invitation.resend', $record) }}" onsubmit="if (!confirm('Send a portal setup invitation to this record’s email address?')) return false; this.querySelector('button[type=submit]').disabled = true;">@csrf<button type="submit" class="btn btn-outline-primary btn-sm">{{ $sendingLeaseExpired ? 'Retry Invitation' : ($invite->status === 'pending' ? 'Resend Invitation' : 'Send New Invitation') }}</button></form>
                    @else
                        <span class="text-muted small">Invitation delivery in progress</span>
                    @endif
                    @if (in_array($invite->status, ['pending', 'sending', 'delivery_failed', 'expired'], true))
                        <form class="d-inline" method="POST" action="{{ route('registrar.students.invitation.revoke', $record) }}" onsubmit="if (!confirm('Revoke this unused invitation? The pending account will remain unable to sign in.')) return false; this.querySelector('button[type=submit]').disabled = true;">@csrf<input type="hidden" name="confirm" value="1"><button type="submit" class="btn btn-outline-danger btn-sm">Revoke Invitation</button></form>
                    @endif
                @endif
            </section>
            <dl class="ar-record-grid">
                @foreach ($details as $label => $value)
                    <div class="ar-record-field">
                        <dt>{{ $label }}</dt>
                        <dd>{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($enrollments->isNotEmpty())
                <section class="ar-history-section">
                    <h2>Enrollment History</h2>
                    <div class="table-responsive">
                        <table class="table table-sm ar-history-table">
                            <thead><tr><th>School Year</th><th>Semester</th><th>Program</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($enrollments as $enrollment)
                                    <tr>
                                        <td>{{ $enrollment->school_year }}</td>
                                        <td>{{ ucfirst($enrollment->semester) }}</td>
                                        <td>{{ $enrollment->program->name ?? '—' }}</td>
                                        <td>{{ ucfirst($enrollment->status) }}</td>
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
                        <table class="table table-sm ar-history-table">
                            <thead><tr><th>Document</th><th>Requested</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($documentRequests as $documentRequest)
                                    <tr>
                                        <td>{{ $documentRequest->document_label }}</td>
                                        <td>{{ $documentRequest->created_at->format('M j, Y') }}</td>
                                        <td>{{ ucfirst($documentRequest->status) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <a class="btn btn-light mt-3" href="{{ route('registrar.students.index', ['type' => $record->academic_status === 'alumni' ? 'alumni' : 'students']) }}">Back to records</a>
        </div>
    </section>
@endsection
