@extends('adminlte::page')
@include('partials.navbar')

@section('title', $school->exists ? 'Edit School' : 'Add School')

@section('content_header')
    <div>
        <a href="{{ route('registrar.admissions.schools.index') }}" class="small"><i data-lucide="arrow-left" aria-hidden="true" style="width:14px;height:14px"></i> School list</a>
        <h4 class="mb-0 font-weight-bold u-text">{{ $school->exists ? $school->name : 'Add a school' }}</h4>
        <p class="mb-0 u-text-secondary-sm">{{ $school->exists ? 'Changes apply to the application form immediately. Submitted applications keep the details they were sent with.' : 'Add schools missing from the DepEd list, such as public schools.' }}</p>
    </div>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset_v('css/registrar-workspace.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/admission.css') }}">
@endpush

@section('content')
@php
    $offerings = old('offerings', $school->offerings ?? []);
@endphp
<div class="registrar-workspace">
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $school->exists ? route('registrar.admissions.schools.update', $school) : route('registrar.admissions.schools.store') }}" class="card">
        @csrf
        @if($school->exists) @method('PUT') @endif
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="name">School name</label>
                    <input id="name" name="name" class="form-control" maxlength="255" required value="{{ old('name', $school->name) }}">
                </div>
                <div class="form-group col-md-3">
                    <label for="deped_school_id">DepEd School ID</label>
                    <input id="deped_school_id" name="deped_school_id" class="form-control" inputmode="numeric" pattern="\d{6}" maxlength="6" value="{{ old('deped_school_id', $school->deped_school_id) }}" placeholder="6 digits">
                </div>
                <div class="form-group col-md-3">
                    <label for="sector">Type</label>
                    <select id="sector" name="sector" class="form-control">
                        <option value="">Not recorded</option>
                        @foreach(\App\Models\School::SECTORS as $value => $label)
                            <option value="{{ $value }}" @selected(old('sector', $school->sector) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <h3 class="adm-section-title">Location</h3>
            @if($school->source_location)
                <p class="small text-muted">Recorded in the source list as: {{ $school->source_location }}</p>
            @endif
            {{-- PSGC picker (address-cascade.js) --}}
            <div class="form-row" data-address-picker
                data-lookup="{{ json_encode($lookup) }}"
                data-old="{{ json_encode(['region_code' => old('region_code', $school->region_code), 'province_code' => old('province_code', $school->province_code), 'city_code' => old('city_code', $school->city_code), 'barangay' => old('barangay', $school->barangay)]) }}">
                <div class="form-group col-md-4">
                    <label for="region_code">Region</label>
                    <select id="region_code" name="region_code" data-address="region" class="form-control" disabled><option value="">Loading regions…</option></select>
                </div>
                <div class="form-group col-md-4">
                    <label for="area_select">Province / Independent City</label>
                    <select id="area_select" data-address="area" class="form-control" disabled><option value="">Select a region first</option></select>
                    <input type="hidden" name="province_code" data-address="province" value="{{ old('province_code', $school->province_code) }}">
                    <small class="form-text text-muted" data-address="area-hint" hidden></small>
                </div>
                <div class="form-group col-md-4">
                    <label for="city_code">City / Municipality</label>
                    <select id="city_code" name="city_code" data-address="city" class="form-control" disabled><option value="">Select a province first</option></select>
                </div>
                <div class="form-group col-md-4" data-address="barangay-select-field" hidden>
                    <label for="barangay_select">Barangay</label>
                    <select id="barangay_select" data-address="barangay-select" class="form-control" disabled><option value="">Select a city first</option></select>
                </div>
                <div class="form-group col-md-4" data-address="barangay-manual-field">
                    <label for="barangay">Barangay</label>
                    <input id="barangay" name="barangay" data-address="barangay" class="form-control" maxlength="255" value="{{ old('barangay', $school->barangay) }}">
                </div>
                <div class="form-group col-md-8">
                    <label for="street_address">Street / address</label>
                    <input id="street_address" name="street_address" class="form-control" maxlength="255" value="{{ old('street_address', $school->street_address) }}">
                </div>
                <div class="col-12 alert alert-warning py-2" data-address="status" hidden>
                    <span data-address="status-message"></span>
                    <button type="button" class="btn btn-sm btn-link" data-address="retry" hidden>Try again</button>
                </div>
            </div>

            <h3 class="adm-section-title">SHS program offerings (K to 12 curriculum)</h3>
            <p class="small text-muted">Strands checked here are suggested first to applicants from this school.</p>
            <div class="adm-checkbox-grid mb-3">
                @foreach(\App\Services\Schools\SchoolImporter::OFFERINGS as $offering)
                    <label><input type="checkbox" name="offerings[]" value="{{ $offering }}" @checked(in_array($offering, $offerings, true))> {{ $offering }}</label>
                @endforeach
            </div>

            <label class="mb-0"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $school->is_active))> Show this school in the application form's school lookup</label>
        </div>
        <div class="card-footer d-flex justify-content-between flex-wrap" style="gap:8px">
            <button type="submit" class="btn btn-primary">{{ $school->exists ? 'Save school' : 'Add school' }}</button>
            @if($school->exists)<span class="small text-muted align-self-center">Source: {{ $school->source }} · ID #{{ $school->id }}</span>@endif
        </div>
    </form>
</div>
@endsection

@section('js')
<script src="{{ asset_v('js/address-cascade.js') }}"></script>
@endsection
