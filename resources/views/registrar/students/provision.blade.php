@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Create Portal Account')
@section('content_header')<h1>{{ $record->academic_status === 'alumni' ? 'Set Up Alumni Access' : 'Create Portal Account' }}</h1>@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('css/registrar-workspace.css') }}">
<link rel="stylesheet" href="{{ asset('css/portal-invitation.css') }}">
@endpush
@section('content')
<div class="registrar-workspace portal-invitation-page">

@php($email = $record->email)
@php($eligible = $email && $record->student_number && $record->first_name && $record->last_name && in_array($record->academic_status, ['active', 'alumni'], true))
<div class="card"><div class="card-body">
    <header class="invitation-record-heading">
        <p>Portal role: {{ $record->academic_status === 'alumni' ? 'Alumni' : 'Student' }}</p>
        <h2>{{ trim(implode(' ', array_filter([$record->first_name, $record->middle_name, $record->last_name, $record->suffix]))) }}</h2>
    </header>
    <dl class="invitation-record-summary">
        <div><dt>Student number</dt><dd>{{ $record->student_number ?: 'Not recorded' }}</dd></div>
        <div><dt>Academic status</dt><dd>{{ ucfirst($record->academic_status) }}</dd></div>
        <div class="invitation-program"><dt>Program</dt><dd>{{ $record->program->name ?? 'Not recorded' }}</dd></div>
    </dl>
    <section class="invitation-delivery" aria-labelledby="invitation-email-label">
        <h3 id="invitation-email-label">Send invitation to</h3>
        <p class="invitation-email">{{ $email ?: 'No email recorded' }}</p>
        <p>The recipient verifies this email and sets their own password. Access stays pending until activation.</p>
    </section>
    @unless($eligible)<p class="invitation-unavailable" role="status">Complete the required academic identity and contact email before sending an invitation.</p>@endunless
    <form method="POST" action="{{ route('registrar.students.provision.store', $record) }}">@csrf
        <label class="invitation-confirm" for="confirm-provision">
            <input type="checkbox" id="confirm-provision" name="confirm" value="1" required @checked(old('confirm')) @error('confirm') aria-invalid="true" aria-describedby="confirm-provision-error" @enderror>
            <span>I verified the record and email, and authorize this invitation.</span>
        </label>
        @error('confirm')<p class="invitation-field-error" role="alert" id="confirm-provision-error">{{ $message }}</p>@enderror
        @error('account')
            <section class="invitation-error" role="alert" aria-labelledby="invitation-error-heading">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 3v1"/></svg>
                <div>
                    <h3 id="invitation-error-heading">Invitation could not be sent</h3>
                    <p>{{ $message }}</p>
                    <a href="{{ route('registrar.students.edit', $record) }}">Review academic record <span aria-hidden="true">&rarr;</span></a>
                </div>
            </section>
        @enderror
        <div class="invitation-actions">
            <button type="submit" class="btn btn-primary" data-loading-text="Sending…" @disabled(!$eligible)>Send invitation</button>
            <a class="btn btn-outline-secondary" href="{{ route('registrar.students.show', $record) }}">Cancel</a>
        </div>
    </form>
</div></div>

</div>
@endsection
