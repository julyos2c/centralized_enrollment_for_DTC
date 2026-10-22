@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Create Portal Account')
@section('content_header')<h1>{{ $record->academic_status === 'alumni' ? 'Set Up Alumni Access' : 'Create Portal Account' }}</h1>@endsection
@push('css')
<link rel="stylesheet" href="{{ asset('css/registrar-workspace.css') }}">
@endpush
@section('content')
<div class="registrar-workspace">

@php($email = $record->email)
<div class="card"><div class="card-body">
    <p>Review this academic identity before creating its portal account. The recipient must open the invitation, verify the contact email, and choose a password. No password is shown to staff.</p>
    <dl class="row">
        <dt class="col-sm-3">Student number</dt><dd class="col-sm-9">{{ $record->student_number ?: 'Not recorded' }}</dd>
        <dt class="col-sm-3">Name</dt><dd class="col-sm-9">{{ trim(implode(' ', array_filter([$record->first_name, $record->middle_name, $record->last_name, $record->suffix]))) }}</dd>
        <dt class="col-sm-3">Program</dt><dd class="col-sm-9">{{ $record->program->name ?? 'Not recorded' }}</dd>
        <dt class="col-sm-3">Academic status</dt><dd class="col-sm-9">{{ ucfirst($record->academic_status) }}</dd>
        <dt class="col-sm-3">Record type</dt><dd class="col-sm-9">{{ $record->academic_status === 'alumni' ? 'Alumni' : 'Student' }}</dd>
        <dt class="col-sm-3">Contact email</dt><dd class="col-sm-9">{{ $email ?: 'Not recorded' }} <span class="text-muted">(verified when the recipient activates)</span></dd>
        <dt class="col-sm-3">Portal role</dt><dd class="col-sm-9">{{ $record->academic_status === 'alumni' ? 'Alumni' : 'Student' }}</dd>
        <dt class="col-sm-3">Eligibility</dt><dd class="col-sm-9">{{ $email && $record->student_number && $record->first_name && $record->last_name && in_array($record->academic_status, ['active', 'alumni'], true) ? 'Eligible for confirmation' : 'Not eligible: complete the required academic identity and contact email.' }}</dd>
    </dl>
    <form method="POST" action="{{ route('registrar.students.provision.store', $record) }}" onsubmit="this.querySelector('button[type=submit]').disabled = true;">@csrf
        <div class="custom-control custom-checkbox mb-3"><input class="custom-control-input" type="checkbox" id="confirm-provision" name="confirm" value="1" required><label class="custom-control-label" for="confirm-provision">I verified this record and authorize a pending portal account invitation.</label></div>
        @error('confirm')<div class="text-danger mb-2">{{ $message }}</div>@enderror
        @error('account')<div class="text-danger mb-2">{{ $message }}</div>@enderror
        <button class="btn btn-primary" {{ !($email && $record->student_number && $record->first_name && $record->last_name && in_array($record->academic_status, ['active', 'alumni'], true)) ? 'disabled' : '' }}>Create account and send invitation</button>
        <a class="btn btn-light" href="{{ route('registrar.students.show', $record) }}">Cancel</a>
    </form>
</div></div>

</div>
@endsection
