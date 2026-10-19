@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Academic Settings')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">Academic Settings</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="u-text-secondary">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}" class="u-text-secondary">Settings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Academic</li>
                </ol>
            </nav>
        </div>

        <button type="submit" form="academic-settings-form" class="btn btn-primary">
            <i data-lucide="save" class="mr-1"></i> Save Changes
        </button>
    </div>
@endsection

@section('content')
<div class="row">

    <div class="col-12">


        @if ($errors->any())
            <div class="alert alert-danger">
                <i data-lucide="alert-circle" class="mr-1"></i>
                <ul class="mb-0 pl-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="academic-settings-form" method="POST" action="{{ route('admin.settings.academic.update') }}">
            @csrf
            @method('PUT')

            <div class="row">

                {{-- School Year & Semester --}}
                <div class="col-lg-7 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="settings-section-title">
                                <div class="settings-section-icon is-primary">
                                    <i data-lucide="calendar-days"></i>
                                </div>
                                <h3>Current Academic Year &amp; Semester</h3>
                            </div>

                            <div class="form-group">
                                <label>Academic Year</label>
                                <input type="text" name="current_school_year" class="form-control"
                                       placeholder="e.g. 2026-2027"
                                       value="{{ old('current_school_year', $settings->current_school_year ?? '2026-2027') }}">
                                <small class="form-text" style="color:var(--dtc-text-muted);">
                                    Format: YYYY-YYYY. This is shown across the enrollment portal and reports.
                                </small>
                            </div>

                            <div class="form-group mb-0">
                                <label>Semester</label>
                                <select name="current_semester" class="form-control">
                                    @php $currentSem = old('current_semester', $settings->current_semester ?? '1st'); @endphp
                                    <option value="1st"    {{ $currentSem === '1st'    ? 'selected' : '' }}>1st Semester</option>
                                    <option value="2nd"    {{ $currentSem === '2nd'    ? 'selected' : '' }}>2nd Semester</option>
                                    <option value="Summer" {{ $currentSem === 'Summer' ? 'selected' : '' }}>Summer</option>
                                </select>
                            </div>

                            <div class="form-row mt-3">
                                <div class="form-group col-md-6">
                                    <label for="semester_starts_on">Semester starts</label>
                                    <input type="date" id="semester_starts_on" name="semester_starts_on" class="form-control"
                                           value="{{ old('semester_starts_on', $settings->semester_starts_on ?? '') }}">
                                </div>
                                <div class="form-group col-md-6 mb-0">
                                    <label for="semester_ends_on">Semester ends</label>
                                    <input type="date" id="semester_ends_on" name="semester_ends_on" class="form-control"
                                           value="{{ old('semester_ends_on', $settings->semester_ends_on ?? '') }}">
                                </div>
                            </div>
                            <small class="form-text" style="color:var(--dtc-text-muted);">
                                Set both dates so the portal can show whether this term is upcoming, in progress, or ended. The end date includes the full day.
                            </small>
                        </div>
                    </div>
                </div>

                {{-- Enrollment Status --}}
                <div class="col-lg-5 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="settings-section-title">
                                <div class="settings-section-icon is-success">
                                    <i data-lucide="door-open"></i>
                                </div>
                                <h3>Enrollment Status</h3>
                            </div>

                            <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:16px;">
                                <div style="max-width:320px;">
                                    <h3 style="font-size:15px; font-weight:700; color:var(--dtc-text); margin:0;">
                                        Accept Enrollment Applications
                                    </h3>
                                    <p style="font-size:12px; color:var(--dtc-text-secondary); margin:4px 0 0;">
                                        When off, students and applicants won't be able to submit new enrollment
                                        applications for the current school year.
                                    </p>
                                </div>

                                <label class="dtc-toggle">
                                    <input type="checkbox" name="enrollment_open" value="1"
                                           {{ old('enrollment_open', $settings->enrollment_open ?? '1') === '1' ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Registrar Info --}}
                <div class="col-lg-7 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="settings-section-title">
                                <div class="settings-section-icon is-warning">
                                    <i data-lucide="stamp"></i>
                                </div>
                                <h3>Registrar Info</h3>
                            </div>

                            <div class="form-group mb-0">
                                <label>College Registrar Name</label>
                                <input type="text" name="registrar_name" class="form-control"
                                       placeholder="e.g. Marynol Virgie P. Lofranco, LPT"
                                       value="{{ old('registrar_name', $settings->registrar_name ?? '') }}">
                                <small class="form-text" style="color:var(--dtc-text-muted);">
                                    Printed on the signature line of the Certificate of Registration and other
                                    official documents issued by this office.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Programs & Subjects quick access --}}
                <div class="col-12 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start" style="gap:14px;">
                                <div class="settings-section-icon is-purple">
                                    <i data-lucide="graduation-cap"></i>
                                </div>
                                <div>
                                    <h3 style="font-size:15px; font-weight:700; color:var(--dtc-text); margin:0;">
                                        Programs &amp; Subjects
                                    </h3>
                                    <p style="font-size:12px; color:var(--dtc-text-secondary); margin:2px 0 0;">
                                        {{ $programsCount }} {{ Str::plural('program', $programsCount) }} &middot;
                                        {{ $subjectsCount }} {{ Str::plural('subject', $subjectsCount) }} on record
                                    </p>
                                </div>
                            </div>

                            <a href="{{ route('admin.programs.index') }}" class="btn btn-secondary mt-3">
                                <i data-lucide="arrow-right" class="mr-1"></i> Manage Programs & Subjects
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection
