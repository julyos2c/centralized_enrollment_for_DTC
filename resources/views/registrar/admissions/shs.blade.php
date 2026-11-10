@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'SHS Tracks & Strands')

@section('content_header')
    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">SHS Tracks &amp; Strands</h4>
            <p class="mb-0 u-text-secondary-sm">Curricula and the tracks, strands, or elective clusters applicants choose from. Applicants can always type an unlisted strand or say they don't remember.</p>
        </div>
        <button type="button" class="adm-btn" data-toggle="collapse" data-target="#newCurriculum" aria-expanded="false" aria-controls="newCurriculum"><i data-lucide="plus" aria-hidden="true"></i> Add curriculum</button>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/admission.css') }}">
@endpush

@section('content')
<div class="registrar-workspace">
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="collapse" id="newCurriculum">
        <form method="POST" action="{{ route('registrar.admissions.shs.curricula.store') }}" class="card">
            @csrf
            <div class="card-header"><span class="font-weight-bold">New curriculum</span></div>
            <div class="card-body">@include('registrar.admissions._shs-curriculum-fields', ['curriculum' => null, 'prefix' => 'new'])</div>
            <div class="card-footer"><button class="btn btn-primary">Add curriculum</button></div>
        </form>
    </div>

    @foreach($curricula as $curriculum)
        <div class="card">
            <div class="card-header">
                <div>
                    <span class="font-weight-bold">{{ $curriculum->name }}</span>
                    <span class="adm-chip {{ $curriculum->is_active ? 'is-success' : '' }}">{{ $curriculum->is_active ? 'Offered' : 'Hidden' }}</span>
                    <div class="u-text-secondary-sm">{{ $curriculum->yearsLabel() }} · applicants choose a {{ strtolower($curriculum->choice_label) }} · {{ $curriculum->applications_count }} application(s)</div>
                </div>
                <div class="adm-toolbar">
                    <button type="button" class="adm-btn adm-btn-sm" data-toggle="collapse" data-target="#edit-curriculum-{{ $curriculum->id }}" aria-expanded="false" aria-controls="edit-curriculum-{{ $curriculum->id }}"><i data-lucide="pencil" aria-hidden="true"></i> Edit curriculum</button>
                    <button type="button" class="adm-btn adm-btn-sm adm-btn-primary" data-toggle="collapse" data-target="#add-option-{{ $curriculum->id }}" aria-expanded="false" aria-controls="add-option-{{ $curriculum->id }}"><i data-lucide="plus" aria-hidden="true"></i> Add {{ strtolower($curriculum->choice_label) }}</button>
                </div>
            </div>
            <div class="collapse" id="edit-curriculum-{{ $curriculum->id }}">
                <form method="POST" action="{{ route('registrar.admissions.shs.curricula.update', $curriculum) }}" class="card-body border-bottom">
                    @csrf @method('PUT')
                    @include('registrar.admissions._shs-curriculum-fields', ['curriculum' => $curriculum, 'prefix' => 'c' . $curriculum->id])
                    <button class="btn btn-primary btn-sm">Save curriculum</button>
                </form>
            </div>
            <div class="collapse" id="add-option-{{ $curriculum->id }}">
                <form method="POST" action="{{ route('registrar.admissions.shs.classifications.store', $curriculum) }}" class="card-body border-bottom">
                    @csrf
                    @include('registrar.admissions._shs-option-fields', ['option' => null, 'prefix' => 'add' . $curriculum->id])
                    <button class="btn btn-primary btn-sm">Add</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 adm-table adm-stack-table">
                        <thead><tr><th>Track</th><th>{{ $curriculum->choice_label }}</th><th>Matches school offering</th><th>Applications</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                            @forelse($curriculum->classifications as $option)
                                <tr>
                                    <td data-label="Track" class="small">{{ $option->track }}</td>
                                    <td data-label="{{ $curriculum->choice_label }}"><strong>{{ $option->name }}</strong></td>
                                    <td data-label="School offering" class="small">{{ $option->offering_key ?: '—' }}</td>
                                    <td data-label="Applications">{{ $option->applications_count }}</td>
                                    <td data-label="Status"><span class="adm-chip {{ $option->is_active ? 'is-success' : '' }}">{{ $option->is_active ? 'Offered' : 'Hidden' }}</span></td>
                                    <td data-label="Actions" class="text-md-right">
                                        <button type="button" class="adm-btn adm-btn-sm" data-toggle="collapse" data-target="#edit-option-{{ $option->id }}" aria-expanded="false" aria-controls="edit-option-{{ $option->id }}"><i data-lucide="pencil" aria-hidden="true"></i> Edit</button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="edit-option-{{ $option->id }}">
                                    <td colspan="6">
                                        <form method="POST" action="{{ route('registrar.admissions.shs.classifications.update', $option) }}">
                                            @csrf @method('PUT')
                                            @include('registrar.admissions._shs-option-fields', ['option' => $option, 'prefix' => 'o' . $option->id])
                                            <button class="btn btn-primary btn-sm">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="adm-empty">No options yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
