@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Admission Requirements')

@section('content_header')
    <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:12px">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Admission Requirements</h4>
            <p class="mb-0 u-text-secondary-sm">Documents applicants upload online. Changes apply to the application form and every applicant's portal immediately.</p>
        </div>
        <button type="button" class="adm-btn adm-btn-primary" data-toggle="collapse" data-target="#newRequirement" aria-expanded="{{ $errors->any() && !old('_requirement_id') ? 'true' : 'false' }}" aria-controls="newRequirement">
            <i data-lucide="plus" aria-hidden="true"></i> Add requirement
        </button>
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

    <div class="collapse {{ $errors->any() && !old('_requirement_id') ? 'show' : '' }}" id="newRequirement">
        <div class="card">
            <div class="card-header"><span class="font-weight-bold">New requirement</span></div>
            <form method="POST" action="{{ route('registrar.admissions.requirements.store') }}">
                @csrf
                <div class="card-body">
                    @include('registrar.admissions._requirement-fields', ['requirement' => null, 'prefix' => 'new'])
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-primary">Add requirement</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="font-weight-bold">Configured requirements</span>
            <span class="u-text-secondary-sm">Deactivating hides a requirement from applicants; files already submitted remain on record.</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 adm-table adm-stack-table">
                    <thead>
                        <tr><th>Requirement</th><th>Applies to</th><th>Files</th><th>Rule</th><th>Uploads</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($requirements as $requirement)
                            <tr>
                                <td data-label="Requirement">
                                    <strong>{{ $requirement->name }}</strong>
                                    @if($requirement->description)<div class="small text-muted">{{ $requirement->description }}</div>@endif
                                </td>
                                <td data-label="Applies to" class="small">
                                    {{ $requirement->applicantTypesLabel() }}
                                    @if($requirement->condition)<div class="text-muted">{{ \App\Models\AdmissionRequirement::CONDITIONS[$requirement->condition] ?? $requirement->condition }}</div>@endif
                                </td>
                                <td data-label="Files" class="small">{{ $requirement->formatSummary() }}</td>
                                <td data-label="Rule"><span class="adm-chip {{ $requirement->is_required ? 'is-warning' : '' }}">{{ $requirement->is_required ? 'Required' : 'Optional' }}</span></td>
                                <td data-label="Uploads" class="small">{{ $requirement->current_uploads_count }} on file @if($requirement->awaiting_review_count)<div class="text-muted">{{ $requirement->awaiting_review_count }} awaiting review</div>@endif</td>
                                <td data-label="Status"><span class="adm-chip {{ $requirement->is_active ? 'is-success' : '' }}">{{ $requirement->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td data-label="Actions">
                                    <div class="adm-row-actions justify-content-md-end">
                                        <button type="button" class="adm-btn adm-btn-sm" data-toggle="collapse" data-target="#edit-req-{{ $requirement->id }}" aria-expanded="false" aria-controls="edit-req-{{ $requirement->id }}"><i data-lucide="pencil" aria-hidden="true"></i> Edit</button>
                                        <form method="POST" action="{{ route('registrar.admissions.requirements.toggle', $requirement) }}" id="toggle-req-{{ $requirement->id }}">
                                            @csrf
                                            <button type="button" class="adm-btn adm-btn-sm {{ $requirement->is_active ? 'adm-btn-danger' : '' }}"
                                                data-dtc-confirm data-dtc-confirm-tone="{{ $requirement->is_active ? 'danger' : 'neutral' }}"
                                                data-dtc-confirm-title="{{ $requirement->is_active ? 'Deactivate' : 'Activate' }} requirement?"
                                                data-dtc-confirm-message="{{ $requirement->is_active ? '{subject} will no longer be shown to applicants. Files already submitted stay on record.' : '{subject} will be shown to applicants it applies to.' }}"
                                                data-dtc-confirm-subject="{{ $requirement->name }}"
                                                data-dtc-confirm-ok="{{ $requirement->is_active ? 'Deactivate' : 'Activate' }}"
                                                data-dtc-confirm-form="#toggle-req-{{ $requirement->id }}">
                                                {{ $requirement->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr class="collapse {{ (string) old('_requirement_id') === (string) $requirement->id ? 'show' : '' }}" id="edit-req-{{ $requirement->id }}">
                                <td colspan="7" class="bg-light-subtle">
                                    <form method="POST" action="{{ route('registrar.admissions.requirements.update', $requirement) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="_requirement_id" value="{{ $requirement->id }}">
                                        @include('registrar.admissions._requirement-fields', ['requirement' => $requirement, 'prefix' => 'r' . $requirement->id])
                                        <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="adm-empty">No requirements yet. Add the documents applicants should upload.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
