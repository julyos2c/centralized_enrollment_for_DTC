@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'School List')

@section('content_header')
    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">School List</h4>
            <p class="mb-0 u-text-secondary-sm">Schools applicants can pick as their previous school{{ $scopeName ? ' — only schools in ' . $scopeName . ' are offered' : '' }}. Verified rows come from DepEd's official list; add, correct, or hide schools here.</p>
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
        <div class="adm-rate"><span>Offered to applicants</span><strong>{{ number_format($totals['selectable']) }}</strong><small>active{{ $scopeName ? ', in ' . $scopeName : '' }}</small></div>
        <div class="adm-rate"><span>Not yet verified / needs review</span><strong>{{ number_format($totals['review']) }}</strong><small>check against DepEd records</small></div>
        <div class="adm-rate"><span>Location not set</span><strong>{{ number_format($totals['no_location']) }}</strong><small>listed under “Municipality not recorded”</small></div>
    </div>

    <form method="GET" class="card" aria-label="Search schools">
        <div class="card-body">
            <div class="adm-filter-grid">
                <div style="grid-column: span 2">
                    <label for="school-search">Name or DepEd School ID</label>
                    <input id="school-search" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="e.g. Danao or 404297">
                </div>
                @if($localities->isNotEmpty())
                <div>
                    <label for="school-city">Municipality / city</label>
                    <select id="school-city" name="city" class="form-control">
                        <option value="">All of {{ $scopeName }}</option>
                        @foreach($localities as $locality)
                            <option value="{{ $locality['code'] }}" @selected(request('city') === $locality['code'])>{{ $locality['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="school-scope">Records</label>
                    <select id="school-scope" name="scope" class="form-control">
                        <option value="province">{{ $scopeName }} only</option>
                        <option value="all" @selected(request('scope') === 'all')>All records (incl. outside {{ $scopeName }})</option>
                    </select>
                </div>
                @endif
                <div>
                    <label for="school-status">Show</label>
                    <select id="school-status" name="status" class="form-control">
                        <option value="">All schools</option>
                        <option value="active" @selected(request('status') === 'active')>Shown to applicants</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Hidden</option>
                        <option value="no_location" @selected(request('status') === 'no_location')>Location not set</option>
                        <option value="needs_review" @selected(request('status') === 'needs_review')>Needs review</option>
                        <option value="unverified" @selected(request('status') === 'unverified')>Not yet verified</option>
                    </select>
                </div>
                <div class="adm-toolbar">
                    <button class="btn btn-primary">Search</button>
                    @if(request()->hasAny(['search', 'status', 'city', 'scope']))<a href="{{ route('registrar.admissions.schools.index') }}" class="dtc-fl-clear">Clear</a>@endif
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header"><span class="font-weight-bold">Schools</span><span class="u-text-secondary-sm">{{ number_format($schools->total()) }} found</span></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 adm-table adm-stack-table">
                    <thead><tr><th>School</th><th>DepEd ID</th><th>Location</th><th>SHS offerings</th><th>Applicants</th><th>Verification</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($schools as $school)
                            <tr>
                                <td data-label="School"><strong>{{ $school->name }}</strong>@if($school->sectorLabel())<div class="small text-muted">{{ $school->sectorLabel() }}</div>@endif</td>
                                <td data-label="DepEd ID">{{ $school->deped_school_id ?: '—' }}</td>
                                <td data-label="Location" class="small">{{ $school->locationLabel() ?: '—' }}@unless($school->city_code)<div class="text-warning">Not matched to a city</div>@endunless</td>
                                <td data-label="SHS offerings" class="small">{{ $school->offerings ? implode(', ', $school->offerings) : '—' }}</td>
                                <td data-label="Applicants">{{ $school->applications_count }}</td>
                                <td data-label="Verification"><span class="adm-chip {{ ['verified' => 'is-success', 'needs_review' => 'is-warning'][$school->verification_status] ?? '' }}" @if($school->verification_note) title="{{ $school->verification_note }}" @endif>{{ \App\Models\School::VERIFICATION[$school->verification_status] ?? $school->verification_status }}</span></td>
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
                            <tr><td colspan="8"><div class="adm-empty">No schools match. @if($totals['all'] === 0) Run <code>php artisan schools:import</code> to load DepEd's list, or add schools one by one. @endif</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($schools->hasPages())<div class="card-footer">{{ $schools->links() }}</div>@endif
    </div>
</div>
@endsection
