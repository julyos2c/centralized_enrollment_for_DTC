@extends('adminlte::page')
@include('partials.navbar')
@section('title', $record->exists ? 'Edit Academic Record' : 'Add Academic Record')
@section('content_header')
<div class="ar-page-heading">
    <div><h1>{{ $record->exists ? 'Edit academic record' : 'Add academic record' }}</h1><p>Save the academic record first. Portal access can be set up separately.</p></div>
</div>
@endsection
@push('css')
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/academic-record-form.css') }}">
@endpush
@section('content')
@php
    $status = old('academic_status', $record->academic_status ?: ($type === 'alumni' ? 'alumni' : 'active'));
    $recordType = old('record_type', $status === 'alumni' ? 'alumni' : 'students');
    $alumni = $recordType === 'alumni';
@endphp
<div class="registrar-workspace academic-record-form-page">
<form method="POST" action="{{ $record->exists ? route('registrar.students.update', $record) : route('registrar.students.store') }}" data-academic-record-form>
    @csrf
    @if($record->exists) @method('PUT') @endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="card"><div class="card-body">
        <fieldset class="record-type-picker">
            <legend>Record type</legend>
            <div class="record-type-options">
                <label><input type="radio" name="record_type" value="students" @checked(!$alumni) @disabled($record->user_id)><span><strong>Student</strong><small>Currently studying or inactive.</small></span></label>
                <label><input type="radio" name="record_type" value="alumni" @checked($alumni) @disabled($record->user_id)><span><strong>Alumni</strong><small>Completed their studies.</small></span></label>
            </div>
            @if($record->user_id)<p class="record-form-help">Portal-linked records use the student-to-alumni promotion workflow to change status.</p>@endif
        </fieldset>
    </div></section>
    <section class="card"><div class="card-body">
        <h2>Personal details</h2>
        <div class="record-field-grid">
            @foreach([['student_number','Student ID no.',true],['first_name','First name',true],['middle_name','Middle name',false],['last_name','Last name',true],['suffix','Suffix',false],['email','Contact email',false]] as [$field,$label,$required])
                <div class="form-group">
                    <label for="{{ $field }}">{{ $label }} @if($required)<span class="record-required" aria-hidden="true">*</span>@endif</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" class="form-control @error($field) is-invalid @enderror" value="{{ old($field, $record->$field) }}" maxlength="{{ $field === 'student_number' ? 50 : ($field === 'suffix' ? 30 : ($field === 'email' ? 255 : 100)) }}" @if($required)required @endif @if($field === 'student_number' && $record->exists)readonly aria-describedby="number-help" @endif>
                    @if($field === 'student_number' && $record->exists)<small id="number-help" class="record-form-help">Student numbers cannot be changed.</small>@endif
                    @if($field === 'email')<small class="record-form-help">Needed if you send a portal invitation later.</small>@endif
                    @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endforeach
        </div>
    </div></section>
    <section class="card"><div class="card-body">
        <h2>Academic details</h2>
        <div class="record-field-grid">
            <div class="form-group record-field-wide">
                <label for="program_id">Program</label>
                <select id="program_id" name="program_id" class="form-control @error('program_id') is-invalid @enderror"><option value="">Not recorded</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(old('program_id', $record->program_id) == $program->id)>{{ $program->name }}</option>@endforeach</select>
                @error('program_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-group record-field-wide">
                <label for="academic_status">Academic status</label>
                <select id="academic_status" name="academic_status" class="form-control" @if($record->user_id) aria-describedby="linked-status-help" @endif>
                    @foreach(['active'=>'Active student','inactive'=>'Inactive student','alumni'=>'Alumni'] as $value=>$label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach
                </select>
                @if($record->user_id)<small id="linked-status-help" class="record-form-help">Changes to account-linked status require the promotion workflow.</small>@endif
            </div>
            <fieldset class="record-field-wide record-conditional-fields" data-student-fields @if($alumni) hidden disabled @endif>
                <div class="record-field-grid">
                    <div class="form-group"><label for="year_level">Year level</label><input type="number" min="1" max="8" id="year_level" name="year_level" class="form-control" value="{{ old('year_level', $record->year_level) }}">@error('year_level')<div class="record-field-error">{{ $message }}</div>@enderror</div>
                    <div class="form-group"><label for="enrolled_ay">School year</label><input id="enrolled_ay" name="enrolled_ay" class="form-control" placeholder="YYYY-YYYY" pattern="[0-9]{4}-[0-9]{4}" maxlength="9" value="{{ old('enrolled_ay', $record->enrolled_ay) }}">@error('enrolled_ay')<div class="record-field-error">{{ $message }}</div>@enderror</div>
                </div>
            </fieldset>
            <fieldset class="record-field-wide record-conditional-fields" data-alumni-fields @unless($alumni) hidden disabled @endunless>
                <legend>Graduation details <span>(if recorded)</span></legend>
                <div class="record-field-grid">
                    <div class="form-group"><label for="graduation_year">Graduation year</label><input type="number" min="1900" max="{{ date('Y') + 1 }}" id="graduation_year" name="graduation_year" class="form-control" value="{{ old('graduation_year', $record->graduation_year) }}">@error('graduation_year')<div class="record-field-error">{{ $message }}</div>@enderror</div>
                    <div class="form-group"><label for="graduation_date">Graduation date</label><input type="date" id="graduation_date" name="graduation_date" class="form-control" value="{{ old('graduation_date', optional($record->graduation_date)->format('Y-m-d')) }}">@error('graduation_date')<div class="record-field-error">{{ $message }}</div>@enderror</div>
                </div>
            </fieldset>
        </div>
    </div></section>
    <div class="record-form-actions">
        <a class="btn btn-outline-secondary" data-record-cancel href="{{ route('registrar.students.index', ['type' => $recordType]) }}">Cancel</a>
        <button type="submit" class="btn btn-primary" data-loading-text="Saving…">{{ $record->exists ? 'Save changes' : 'Save record' }}</button>
    </div>
</form>
</div>
@endsection
@push('js')<script src="{{ asset_v('js/academic-record-form.js') }}" defer></script>@endpush
