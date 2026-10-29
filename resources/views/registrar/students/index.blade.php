@extends('adminlte::page')
@include('partials.navbar')
@section('title', $type === 'alumni' ? 'Alumni Records' : 'Student Records')
@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap"><div><h4 class="mb-1 font-weight-bold">{{ $type === 'alumni' ? 'Alumni Records' : 'Student Records' }}</h4><p class="text-muted mb-0">Academic records are maintained separately from portal accounts.</p></div><div class="rg-record-heading-actions"><a class="btn btn-outline-primary" href="{{ route('registrar.imports.index') }}">Import Center</a> <a class="btn btn-primary" href="{{ route('registrar.students.create', ['type' => $type]) }}">Add Record</a></div></div>
@endsection
@push('css')
    <link rel="stylesheet" href="{{ asset_v('css/dtc-filters.css') }}">
@endpush

@push('js')
    <script src="{{ asset_v('js/dtc-filters.js') }}"></script>
@endpush

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
@endpush
@section('content')
<div class="registrar-workspace rg-student-records">


@php
    $stuFilterCount = collect(['program_id', 'academic_status', 'year', 'school_year'])->filter(fn ($k) => filled(request($k)))->count();
    $stuHasFilters  = $stuFilterCount > 0 || filled(request('q'));
@endphp
<form method="GET" class="dtc-fl" data-dtc-filters aria-label="Filter records">
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="dtc-fl-toolbar">
        <div class="dtc-pay-search dtc-fl-search">
            <i data-lucide="search" class="dtc-pay-search-icon" aria-hidden="true"></i>
            <input type="search" id="q" name="q" class="dtc-pay-search-input" value="{{ request('q') }}"
                   placeholder="Name, student number, or email…" autocomplete="off" aria-label="Search records" data-dtc-filter-search>
        </div>
        <button type="button" class="dtc-fl-toggle {{ $stuFilterCount ? 'has-filters' : '' }}" data-toggle="collapse" data-target="#recordsFilterPanel"
                aria-expanded="{{ $stuFilterCount ? 'true' : 'false' }}" aria-controls="recordsFilterPanel" data-dtc-filter-toggle>
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i>
            <span>Filters</span>
            <span class="dtc-fl-count" data-dtc-filter-count @if(!$stuFilterCount) hidden @endif>{{ $stuFilterCount }}</span>
        </button>
    </div>

    <div class="collapse {{ $stuFilterCount ? 'show' : '' }}" id="recordsFilterPanel">
        <div class="dtc-fl-panel">
            <div class="dtc-fl-field">
                <label for="program_id">Program</label>
                <select class="form-control" id="program_id" name="program_id" data-dtc-filter>
                    <option value="">All programs</option>
                    @foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name }}</option>@endforeach
                </select>
            </div>
            <div class="dtc-fl-field">
                <label for="academic_status">Status</label>
                <select class="form-control" id="academic_status" name="academic_status" data-dtc-filter>
                    <option value="">Any status</option>
                    @foreach(['active'=>'Active','inactive'=>'Inactive','alumni'=>'Alumni'] as $value=>$label)<option value="{{ $value }}" @selected(request('academic_status') === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="dtc-fl-field">
                <label for="year">Graduation year</label>
                <input class="form-control" id="year" name="year" value="{{ request('year') }}" inputmode="numeric" data-dtc-filter>
            </div>
            <div class="dtc-fl-field">
                <label for="school_year">School year</label>
                <input class="form-control" id="school_year" name="school_year" value="{{ request('school_year') }}" placeholder="2025-2026" data-dtc-filter>
            </div>
            @include('partials.table-rows', ['tableRecords' => $records, 'tableId' => 'registrar-table-1', 'fixedRows' => true])
<div class="dtc-fl-actions">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="{{ route('registrar.students.index', ['type' => $type]) }}" class="dtc-fl-clear" data-dtc-clear @if(!$stuHasFilters) hidden @endif>Clear all</a>
            </div>
        </div>
    </div>
</form>
<div class="card rg-student-record-card"><div class="card-header d-flex justify-content-between flex-wrap"><div class="btn-group"><a class="btn btn-sm {{ $type === 'students' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('registrar.students.index') }}">Students</a><a class="btn btn-sm {{ $type === 'alumni' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('registrar.students.index', ['type' => 'alumni']) }}">Alumni</a></div><span class="rg-student-record-count">{{ $records->total() }} {{ \Illuminate\Support\Str::plural('record', $records->total()) }}</span></div><div class="card-body">
<div class="table-responsive">
<table id="registrar-table-1" class="rg-record-table rg-student-directory dtc-mobile-records table table-hover"><thead><tr><th>Student No.</th><th>Name</th><th>Program</th><th>Status</th><th></th></tr></thead><tbody>@forelse($records as $record)<tr><td data-reg-label="Student No." data-mobile-label="Student No.">{{ $record->student_number ?: '—' }}</td><td data-reg-label="Name" data-mobile-label="Name">{{ trim(($record->first_name ?: '').' '.($record->middle_name ?: '').' '.($record->last_name ?: '').' '.($record->suffix ?: '')) ?: ($record->user->name ?? 'Name not recorded') }}</td><td data-reg-label="Program" data-mobile-label="Program">{{ $record->program->name ?? '—' }}</td><td data-reg-label="Status" data-mobile-label="Status"><span class="badge badge-{{ $record->academic_status === 'alumni' ? 'info' : ($record->academic_status === 'active' ? 'success' : 'secondary') }}">{{ ucfirst($record->academic_status) }}</span></td><td data-reg-label="Actions" data-mobile-label="Actions" class="text-right"><a class="btn btn-sm btn-outline-primary" href="{{ route('registrar.students.show', $record) }}"><span>View record</span><i data-lucide="arrow-right" aria-hidden="true"></i></a></td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">No records found. Try changing your search or add an academic record.</td></tr>@endforelse</tbody></table></div>{{ $records->links('pagination::bootstrap-4') }}
</div></div>
</div>
@endsection
