@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'School List')

@section('content_header')
    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">School List</h4>
            <p class="mb-0 u-text-secondary-sm">Schools applicants can pick as their previous school. Imported from DepEd's list of senior high schools; add or correct schools here.</p>
        </div>
        <a href="{{ route('registrar.admissions.schools.create') }}" class="adm-btn adm-btn-primary"><i data-lucide="plus" aria-hidden="true"></i> Add school</a>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/admission.css') }}">
@endpush

@section('content')
<div class="registrar-workspace">
    <div class="adm-rate-grid mb-3">
        <div class="adm-rate"><span>Schools on the list</span><strong>{{ number_format($totals['all']) }}</strong><small>{{ number_format($totals['active']) }} shown to applicants</small></div>
        <div class="adm-rate"><span>Location not set</span><strong>{{ number_format($totals['no_location']) }}</strong><small>applicants complete it themselves</small></div>
    </div>

    <form method="GET" class="card" aria-label="Search schools">
        <div class="card-body">
            <div class="adm-filter-grid">
                <div style="grid-column: span 2">
                    <label for="school-search">Name or DepEd School ID</label>
                    <input id="school-search" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="e.g. Danao or 404297">
                </div>
                <div>
                    <label for="school-status">Show</label>
                    <select id="school-status" name="status" class="form-control">
                        <option value="">All schools</option>
                        <option value="active" @selected(request('status') === 'active')>Shown to applicants</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Hidden</option>
                        <option value="no_location" @selected(request('status') === 'no_location')>Location not set</option>
                    </select>
                </div>
                <div class="adm-toolbar">
                    <button class="btn btn-primary">Search</button>
                    @if(request()->hasAny(['search', 'status']))<a href="{{ route('registrar.admissions.schools.index') }}" class="dtc-fl-clear">Clear</a>@endif
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header"><span class="font-weight-bold">Schools</span><span class="u-text-secondary-sm">{{ number_format($schools->total()) }} found</span></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 adm-table adm-stack-table">
                    <thead><tr><th>School</th><th>DepEd ID</th><th>Location</th><th>SHS offerings</th><th>Applicants</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($schools as $school)
                            <tr>
                                <td data-label="School"><strong>{{ $school->name }}</strong>@if($school->sectorLabel())<div class="small text-muted">{{ $school->sectorLabel() }}</div>@endif</td>
                                <td data-label="DepEd ID">{{ $school->deped_school_id ?: '—' }}</td>
                                <td data-label="Location" class="small">{{ $school->locationLabel() ?: '—' }}@unless($school->city_code)<div class="text-warning">Not matched to a city</div>@endunless</td>
                                <td data-label="SHS offerings" class="small">{{ $school->offerings ? implode(', ', $school->offerings) : '—' }}</td>
                                <td data-label="Applicants">{{ $school->applications_count }}</td>
                                <td data-label="Status"><span class="adm-chip {{ $school->is_active ? 'is-success' : '' }}">{{ $school->is_active ? 'Shown' : 'Hidden' }}</span></td>
                                <td data-label="Actions">
                                    <div class="adm-row-actions justify-content-md-end">
                                        <a href="{{ route('registrar.admissions.schools.edit', $school) }}" class="adm-btn adm-btn-sm"><i data-lucide="pencil" aria-hidden="true"></i> Edit</a>
                                        <form method="POST" action="{{ route('registrar.admissions.schools.toggle', $school) }}">
                                            @csrf
                                            <button class="adm-btn adm-btn-sm {{ $school->is_active ? 'adm-btn-danger' : '' }}">{{ $school->is_active ? 'Hide' : 'Show' }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="adm-empty">No schools match. @if($totals['all'] === 0) Run <code>php artisan schools:import</code> to load DepEd's list, or add schools one by one. @endif</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($schools->hasPages())<div class="card-footer">{{ $schools->links() }}</div>@endif
    </div>
</div>
@endsection
