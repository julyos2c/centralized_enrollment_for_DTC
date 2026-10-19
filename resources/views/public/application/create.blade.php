@extends('layouts.public')

@section('title', 'Online Admission Application')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/application-wizard.css') }}">
@stop


@section('content')
<div class="app-wizard-page">

    {{-- ── Top bar ──────────────────────────────────────────────── --}}
    <div class="app-wizard-topbar">
        <a href="{{ route('landing') }}" class="app-wizard-back" aria-label="Back to home">
            <i data-lucide="arrow-left"></i>
        </a>
        <div class="app-wizard-brand">
            <span class="app-wizard-brand-copy">
                <span class="app-wizard-brand-label">Admissions</span>
                <span class="app-wizard-brand-title">Application form</span>
            </span>
        </div>
        <div class="app-wizard-progress-label">
            Step <span id="wizCurrentStep">1</span> of <span id="wizTotalSteps">6</span>
        </div>
    </div>

    <header class="app-wizard-intro">
        <h1>Start your application</h1>
        <p>Complete each section, then review your details before sending them to the Registrar.</p>
    </header>

    {{-- ── Step Tracker ─────────────────────────────────────────── --}}
    <div class="app-wizard-tracker" id="wizardTracker" role="list">
        <div class="wiz-step active" data-step="1" role="listitem" tabindex="0">
            <div class="wiz-step-dot"><i data-lucide="graduation-cap"></i></div>
            <span class="wiz-step-label">Admission</span>
        </div>
        <div class="wiz-connector"></div>
        <div class="wiz-step" data-step="2" role="listitem" tabindex="0">
            <div class="wiz-step-dot"><i data-lucide="user"></i></div>
            <span class="wiz-step-label">Personal</span>
        </div>
        <div class="wiz-connector"></div>
        <div class="wiz-step" data-step="3" role="listitem" tabindex="0">
            <div class="wiz-step-dot"><i data-lucide="phone"></i></div>
            <span class="wiz-step-label">Contact</span>
        </div>
        <div class="wiz-connector"></div>
        <div class="wiz-step" data-step="4" role="listitem" tabindex="0">
            <div class="wiz-step-dot"><i data-lucide="school"></i></div>
            <span class="wiz-step-label">Education</span>
        </div>
        <div class="wiz-connector"></div>
        <div class="wiz-step" data-step="5" role="listitem" tabindex="0">
            <div class="wiz-step-dot"><i data-lucide="users"></i></div>
            <span class="wiz-step-label">Family &amp; Docs</span>
        </div>
        <div class="wiz-connector"></div>
        <div class="wiz-step" data-step="6" role="listitem" tabindex="0">
            <div class="wiz-step-dot"><i data-lucide="clipboard-check"></i></div>
            <span class="wiz-step-label">Review</span>
        </div>
    </div>

    {{-- ── Progress Bar ─────────────────────────────────────────── --}}
    <div class="app-wizard-bar-wrap" aria-hidden="true">
        <div class="app-wizard-bar" id="wizardBar" style="width:16.67%"></div>
    </div>

    {{-- ── Server-side Validation Errors ───────────────────────── --}}
    @if ($errors->any())
        <div class="wiz-server-errors" id="serverErrors" role="alert" tabindex="-1">
            <div class="wiz-server-errors-icon"><i data-lucide="alert-triangle"></i></div>
            <div class="wiz-server-errors-body">
                <p class="wiz-server-errors-title">Your application couldn't be submitted</p>
                <p class="wiz-server-errors-sub">Please fix the following before resubmitting:</p>
                <ul class="wiz-server-errors-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- ── Screening Notice ─────────────────────────────────────── --}}
    <div class="wiz-notice">
        <i data-lucide="info"></i>
        <div>
            <strong>Preliminary screening only.</strong>
            Uploaded documents are for initial review. You must still present
            original documents to the Registrar's Office before official enrollment.
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════ --}}
    <form
        action="{{ route('public.application.store') }}"
        method="POST"
        enctype="multipart/form-data"
        id="applicationForm"
        novalidate
        autocomplete="on"
    >
        @csrf
        <input type="hidden" name="submission_token" value="{{ $submissionToken }}">

        {{-- ╔══════════════════════════════════════════════════╗
             ║  STEP 1 — APPLICANT TYPE & PROGRAM              ║
             ╚══════════════════════════════════════════════════╝ --}}
        <div class="wiz-panel active" id="step-1">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="graduation-cap"></i></div>
                <div>
                    <h2 class="wiz-panel-title">Applicant Type &amp; Program</h2>
                    <p class="wiz-panel-sub">Tell us what type of applicant you are and which program you're applying for.</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field" id="field-academic_status">
                        <label for="academic_status">Applicant Type <span class="req">*</span></label>
                        <select name="academic_status" id="academic_status"
                            class="wiz-input @error('academic_status') wiz-invalid @enderror"
                            autocomplete="off" required>
                            <option value="">Select applicant type</option>
                            <option value="new_student"    {{ old('academic_status') === 'new_student'    ? 'selected' : '' }}>New Student / First Year</option>
                            <option value="transferee"     {{ old('academic_status') === 'transferee'     ? 'selected' : '' }}>Transferee</option>
                            <option value="shiftee"        {{ old('academic_status') === 'shiftee'        ? 'selected' : '' }}>Shiftee</option>
                            <option value="returnee"       {{ old('academic_status') === 'returnee'       ? 'selected' : '' }}>Returnee / Readmitted</option>
                            <option value="cross_enrollee" {{ old('academic_status') === 'cross_enrollee' ? 'selected' : '' }}>Cross-Enrollee</option>
                        </select>
                        @error('academic_status')
                            <span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="wiz-field" id="field-program_id">
                        <label for="program_id">Intended Program <span class="req">*</span></label>
                        <select name="program_id" id="program_id"
                            class="wiz-input @error('program_id') wiz-invalid @enderror"
                            autocomplete="off" required>
                            <option value="">Select program</option>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" {{ old('program_id') == $program->id ? 'selected' : '' }}>
                                    {{ $program->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('program_id')
                            <span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div id="crossEnrolleeNotice" class="wiz-inline-notice wiz-inline-notice--info d-none">
                    <i data-lucide="info"></i>
                    <div><strong>Cross-Enrollee:</strong> Requirements are subject to confirmation by the Registrar's Office. Please proceed — the Registrar will provide further instructions.</div>
                </div>

            </div>
        </div>


        {{-- ╔══════════════════════════════════════════════════╗
             ║  STEP 2 — PERSONAL INFORMATION                  ║
             ╚══════════════════════════════════════════════════╝ --}}
        <div class="wiz-panel" id="step-2">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="user"></i></div>
                <div>
                    <h2 class="wiz-panel-title">Personal Information</h2>
                    <p class="wiz-panel-sub">Please provide your personal details accurately.</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                {{-- ── Name row: Last (Title prefix) | First | Middle (Suffix) ── --}}
                <div class="wiz-row--name">

                    {{-- Last Name with Title fused to its left edge --}}
                    <div class="wiz-field" id="field-last_name">
                        <label for="last_name">Last Name <span class="req">*</span></label>
                        <div class="name-group name-group--prefix @error('last_name') wiz-invalid @enderror">
                            <select name="honorific" id="honorific"
                                class="name-addon" autocomplete="honorific-prefix"
                                title="Title / Honorific (optional)">
                                <option value="">—</option>
                                <option value="Mr."  {{ old('honorific') === 'Mr.'  ? 'selected' : '' }}>Mr.</option>
                                <option value="Ms."  {{ old('honorific') === 'Ms.'  ? 'selected' : '' }}>Ms.</option>
                                <option value="Mrs." {{ old('honorific') === 'Mrs.' ? 'selected' : '' }}>Mrs.</option>
                                <option value="Dr."  {{ old('honorific') === 'Dr.'  ? 'selected' : '' }}>Dr.</option>
                                <option value="Prof."{{ old('honorific') === 'Prof.'? 'selected' : '' }}>Prof.</option>
                                <option value="Engr."{{ old('honorific') === 'Engr.'? 'selected' : '' }}>Engr.</option>
                                <option value="Atty."{{ old('honorific') === 'Atty.'? 'selected' : '' }}>Atty.</option>
                            </select>
                            <input type="text" name="last_name" id="last_name" placeholder="Dela Cruz"
                                class="wiz-input" value="{{ old('last_name') }}"
                                autocomplete="family-name" required>
                        </div>
                        @error('last_name')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                    </div>

                    {{-- First Name — plain, no addon --}}
                    <div class="wiz-field" id="field-first_name">
                        <label for="first_name">First Name <span class="req">*</span></label>
                        <input type="text" name="first_name" id="first_name" placeholder="Juan"
                            class="wiz-input @error('first_name') wiz-invalid @enderror"
                            value="{{ old('first_name') }}"
                            autocomplete="given-name" required>
                        @error('first_name')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                    </div>

                    {{-- Middle Name with Suffix fused to its right edge --}}
                    <div class="wiz-field">
                        <label for="middle_name">Middle Name <span class="opt">(Optional)</span></label>
                        <div class="name-group name-group--suffix">
                            <input type="text" name="middle_name" id="middle_name" placeholder="Santos"
                                class="wiz-input @error('middle_name') wiz-invalid @enderror"
                                value="{{ old('middle_name') }}"
                                autocomplete="additional-name">
                            <select name="suffix" id="suffix"
                                class="name-addon" autocomplete="honorific-suffix"
                                title="Suffix (optional)">
                                <option value="">—</option>
                                <option value="Jr."  {{ old('suffix') === 'Jr.'  ? 'selected' : '' }}>Jr.</option>
                                <option value="Sr."  {{ old('suffix') === 'Sr.'  ? 'selected' : '' }}>Sr.</option>
                                <option value="II"   {{ old('suffix') === 'II'   ? 'selected' : '' }}>II</option>
                                <option value="III"  {{ old('suffix') === 'III'  ? 'selected' : '' }}>III</option>
                                <option value="IV"   {{ old('suffix') === 'IV'   ? 'selected' : '' }}>IV</option>
                            </select>
                        </div>
                        @error('middle_name')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                    </div>

                </div>

                <div class="wiz-row wiz-row--3">
                    <div class="wiz-field" id="field-gender">
                        <label for="gender">Gender <span class="req">*</span></label>
                        <select name="gender" id="gender"
                            class="wiz-input @error('gender') wiz-invalid @enderror"
                            autocomplete="sex" required>
                            <option value="">Select gender</option>
                            <option value="male"   {{ old('gender') === 'male'   ? 'selected' : '' }}>Male</option>
                            <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Female</option>
                        </select>
                        @error('gender')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field" id="field-birthdate">
                        <label for="birthdate">Date of Birth <span class="req">*</span></label>
                        <input type="date" name="birthdate" id="birthdate"
                            class="wiz-input @error('birthdate') wiz-invalid @enderror"
                            value="{{ old('birthdate') }}"
                            autocomplete="bday" required>
                        @error('birthdate')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field">
                        <label for="birth_place">Place of Birth <span class="opt">(Optional)</span></label>
                        <input type="text" name="birth_place" id="birth_place"
                            placeholder="e.g. Danao, Bohol"
                            class="wiz-input" value="{{ old('birth_place') }}"
                            autocomplete="off">
                    </div>
                </div>

                <div class="wiz-row wiz-row--3">
                    <div class="wiz-field">
                        <label for="religion">Religion <span class="opt">(Optional)</span></label>
                        {{-- Combo: type-ahead via datalist, or pick from dropdown --}}
                        <input type="text" name="religion" id="religion"
                            list="religion-list"
                            placeholder="e.g. Roman Catholic"
                            class="wiz-input" value="{{ old('religion') }}"
                            autocomplete="off">
                        <datalist id="religion-list">
                            <option value="Roman Catholic">
                            <option value="Islam">
                            <option value="Iglesia ni Cristo">
                            <option value="Philippine Independent Church (Aglipayan)">
                            <option value="Seventh-day Adventist">
                            <option value="United Church of Christ in the Philippines">
                            <option value="Christianity (Other)">
                            <option value="Evangelical">
                            <option value="Baptist">
                            <option value="Born-Again Christian">
                            <option value="Jehovah's Witnesses">
                            <option value="The Church of Jesus Christ of Latter-day Saints">
                            <option value="Buddhism">
                            <option value="Hinduism">
                            <option value="No Religion / Atheist / Agnostic">
                            <option value="Prefer not to say">
                        </datalist>
                    </div>
                    <div class="wiz-field">
                        <label for="nationality">Nationality <span class="opt">(Optional)</span></label>
                        <input type="text" name="nationality" id="nationality" placeholder="Filipino"
                            class="wiz-input" value="{{ old('nationality', 'Filipino') }}"
                            autocomplete="off">
                    </div>
                    <div class="wiz-field">
                        <label for="lrn">LRN <span class="opt">(Optional)</span></label>
                        <input type="text" name="lrn" id="lrn" placeholder="136041123456"
                            class="wiz-input" value="{{ old('lrn') }}" maxlength="12"
                            autocomplete="off">
                        <span class="wiz-hint">Learner Reference Number — for DepEd graduates.</span>
                    </div>
                </div>

                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="marital_status">Marital Status <span class="opt">(Optional)</span></label>
                        <select name="marital_status" id="marital_status" class="wiz-input" autocomplete="off">
                            <option value="">Select status</option>
                            <option value="single"   {{ old('marital_status') === 'single'   ? 'selected' : '' }}>Single</option>
                            <option value="married"  {{ old('marital_status') === 'married'  ? 'selected' : '' }}>Married</option>
                            <option value="divorced" {{ old('marital_status') === 'divorced' ? 'selected' : '' }}>Divorced</option>
                            <option value="widowed"  {{ old('marital_status') === 'widowed'  ? 'selected' : '' }}>Widowed</option>
                        </select>
                    </div>
                    <div class="wiz-field" id="spouseField">
                        <label for="spouse_name">Spouse Name <span class="opt">(Optional)</span></label>
                        <input type="text" name="spouse_name" id="spouse_name"
                            placeholder="Spouse's full name"
                            class="wiz-input" value="{{ old('spouse_name') }}"
                            autocomplete="off">
                    </div>
                </div>

            </div>
        </div>


        {{-- ╔══════════════════════════════════════════════════╗
             ║  STEP 3 — CONTACT INFORMATION                   ║
             ╚══════════════════════════════════════════════════╝ --}}
        <div class="wiz-panel" id="step-3">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="phone"></i></div>
                <div>
                    <h2 class="wiz-panel-title">Contact Information</h2>
                    <p class="wiz-panel-sub">Where can we reach you regarding your application?</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field" id="field-email">
                        <label for="email">Email Address <span class="req">*</span></label>
                        <input type="email" name="email" id="email" placeholder="juandelacruz@email.com"
                            class="wiz-input @error('email') wiz-invalid @enderror"
                            value="{{ old('email') }}"
                            autocomplete="email" required>
                        @error('email')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field">
                        <label for="phone">Contact Number <span class="opt">(Optional)</span></label>
                        <input type="text" name="phone" id="phone" placeholder="09XXXXXXXXX"
                            class="wiz-input" value="{{ old('phone') }}"
                            autocomplete="tel-national">
                    </div>
                </div>

                {{-- ── Street / House address ─────────────────── --}}
                <div class="wiz-field">
                    <label for="current_address">House No., Street <span class="opt">(Optional)</span></label>
                    <input type="text" name="current_address" id="current_address"
                        placeholder="e.g. 12 Rizal Street, Poblacion"
                        class="wiz-input" value="{{ old('current_address') }}"
                        autocomplete="street-address">
                </div>

                {{-- ── Cascading PH location ──────────────────── --}}
                {{--
                    VISIBLE: 4 <select> dropdowns (Region → Province → City → Barangay)
                    HIDDEN:  4 text inputs submitted to the server with human-readable names
                    The JS cascade in application-wizard.js drives everything.
                --}}

                {{-- Hidden text inputs that the server actually receives --}}
                <input type="hidden" name="region"   id="region_text"   value="{{ old('region') }}">
                <input type="hidden" name="province" id="province_text" value="{{ old('province') }}">
                <input type="hidden" name="city"     id="city_text"     value="{{ old('city') }}">
                <input type="hidden" name="barangay" id="barangay_text" value="{{ old('barangay') }}">

                {{-- ── Location drill-down picker (bottom-sheet) ──────── --}}
                {{-- Hidden selects kept for existing JS cascade logic --}}
                <div style="display:none" aria-hidden="true">
                    <select id="addr_region"   name="_addr_region"   class="loc-select" autocomplete="off" disabled><option value="">Loading regions…</option></select>
                    <select id="addr_province" name="_addr_province" class="loc-select" autocomplete="off" disabled><option value="">Select province…</option></select>
                    <select id="addr_city"     name="_addr_city"     class="loc-select" autocomplete="off" disabled><option value="">Select city / municipality…</option></select>
                    <select id="addr_barangay" name="_addr_barangay" class="loc-select" autocomplete="off" disabled><option value="">Select barangay…</option></select>
                </div>

                {{-- Trigger button --}}
                <div class="wiz-field">
                    <label>Location <span class="opt">(Optional)</span></label>
                    <button type="button" id="locPickerTrigger" class="loc-picker-trigger wiz-input">
                        <i data-lucide="map-pin" class="loc-picker-trigger-icon"></i>
                        <span id="locPickerTriggerText">Select region, province, city…</span>
                        <i data-lucide="chevron-right" class="loc-picker-trigger-chevron"></i>
                    </button>
                </div>

                {{-- Barangay trigger (shown after city is selected) --}}
                <div class="wiz-field" id="locBarangayField" style="display:none">
                    <label>Barangay <span class="opt">(Optional)</span></label>
                    <button type="button" id="locBarangayTrigger" class="loc-picker-trigger wiz-input">
                        <i data-lucide="map-pin" class="loc-picker-trigger-icon"></i>
                        <span id="locBarangayTriggerText">Select barangay…</span>
                        <i data-lucide="chevron-right" class="loc-picker-trigger-chevron"></i>
                    </button>
                </div>

                {{-- Bottom-sheet modal --}}
                <div id="locPickerModal" class="loc-picker-modal" role="dialog" aria-modal="true" aria-label="Select location" hidden>
                    <div class="loc-picker-backdrop"></div>
                    <div class="loc-picker-sheet">
                        {{-- Sheet header --}}
                        <div class="loc-picker-header">
                            <button type="button" id="locPickerBack" class="loc-picker-nav-btn" aria-label="Back" hidden>
                                <i data-lucide="chevron-left"></i>
                            </button>
                            <span id="locPickerTitle" class="loc-picker-title">Enter your location</span>
                            <button type="button" id="locPickerClose" class="loc-picker-nav-btn" aria-label="Close">
                                <i data-lucide="x"></i>
                            </button>
                        </div>

                        {{-- Breadcrumb tabs --}}
                        <div class="loc-picker-tabs" id="locPickerTabs">
                            <button type="button" class="loc-tab active" data-level="region">Region</button>
                            <button type="button" class="loc-tab" data-level="province" disabled>Province</button>
                            <button type="button" class="loc-tab" data-level="city" disabled>City</button>
                        </div>

                        {{-- Search --}}
                        <div class="loc-picker-search-wrap">
                            <i data-lucide="search" class="loc-picker-search-icon"></i>
                            <input type="text" id="locPickerSearch" class="loc-picker-search" placeholder="Search…" autocomplete="off">
                        </div>

                        {{-- List --}}
                        <div id="locPickerList" class="loc-picker-list">
                            <div class="loc-picker-loading">
                                <span class="loc-picker-spinner"></span> Loading…
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Postal code (auto-filled when city is picked, also editable) --}}
                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="postal_code">Postal Code <span class="opt">(Optional)</span></label>
                        <input type="text" name="postal_code" id="postal_code" placeholder="e.g. 6004"
                            class="wiz-input" value="{{ old('postal_code') }}"
                            autocomplete="postal-code" inputmode="numeric" maxlength="4">
                        <span class="wiz-hint">Auto-filled when you select a city or barangay — you can edit it.</span>
                    </div>
                    <div class="wiz-field" style="max-width:220px">
                        <label for="country">Country <span class="opt">(Optional)</span></label>
                        <input type="text" name="country" id="country" placeholder="Philippines"
                            class="wiz-input" value="{{ old('country', 'Philippines') }}"
                            autocomplete="country-name">
                    </div>
                </div>

                {{-- Address summary chip — shown by JS once all 4 selects are filled --}}
                <div class="loc-summary" id="locSummary">
                    <i data-lucide="map-pin"></i>
                    <span id="locSummaryText"></span>
                </div>

            </div>
        </div>


        {{-- ╔══════════════════════════════════════════════════╗
             ║  STEP 4 — EDUCATIONAL BACKGROUND            ║
             ╚══════════════════════════════════════════════════╝ --}}
        <div class="wiz-panel" id="step-4">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="school" aria-hidden="true"></i></div>
                <div>
                    <h2 class="wiz-panel-title">Educational Background</h2>
                    <p class="wiz-panel-sub">Share your recent school history. These details are optional and help the Registrar understand your application.</p>
                </div>
            </div>
            <div class="wiz-panel-body">
                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="education_level">Highest or current education level <span class="opt">(Optional)</span></label>
                        <select name="education_level" id="education_level" class="wiz-input">
                            <option value="">Select education level</option>
                            <option value="Junior High School" {{ old('education_level') === 'Junior High School' ? 'selected' : '' }}>Junior High School</option>
                            <option value="Senior High School" {{ old('education_level') === 'Senior High School' ? 'selected' : '' }}>Senior High School</option>
                            <option value="College" {{ old('education_level') === 'College' ? 'selected' : '' }}>College</option>
                            <option value="Other" {{ old('education_level') === 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                    <div class="wiz-field">
                        <label for="education_status">Completion status <span class="opt">(Optional)</span></label>
                        <select name="education_status" id="education_status" class="wiz-input">
                            <option value="">Select status</option>
                            <option value="Graduated" {{ old('education_status') === 'Graduated' ? 'selected' : '' }}>Graduated</option>
                            <option value="Currently attending" {{ old('education_status') === 'Currently attending' ? 'selected' : '' }}>Currently attending</option>
                            <option value="Not completed" {{ old('education_status') === 'Not completed' ? 'selected' : '' }}>Not completed</option>
                        </select>
                    </div>
                </div>
                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="most_recent_school">Most recent school attended <span class="opt">(Optional)</span></label>
                        <input type="text" name="most_recent_school" id="most_recent_school" class="wiz-input" value="{{ old('most_recent_school') }}" maxlength="255" autocomplete="organization">
                    </div>
                    <div class="wiz-field">
                        <label for="school_year_completed">School year completed or last attended <span class="opt">(Optional)</span></label>
                        <input type="text" name="school_year_completed" id="school_year_completed" class="wiz-input" value="{{ old('school_year_completed') }}" maxlength="30" placeholder="e.g. 2024–2025" inputmode="text">
                    </div>
                </div>
                <div class="wiz-field">
                    <label for="school_location">School location <span class="opt">(Optional)</span></label>
                    <input type="text" name="school_location" id="school_location" class="wiz-input" value="{{ old('school_location') }}" maxlength="255" placeholder="City or municipality, province">
                </div>
                <div class="education-section d-none" data-education-for="new_student">
                    <div class="wiz-divider"></div>
                    <div class="wiz-field">
                        <label for="shs_strand">Senior High School strand <span class="opt">(Optional)</span></label>
                        <input type="text" name="shs_strand" id="shs_strand" class="wiz-input" value="{{ old('shs_strand') }}" maxlength="100" placeholder="e.g. HUMSS, STEM, TVL">
                    </div>
                </div>
                <div class="education-section d-none" data-education-for="transferee,shiftee,returnee">
                    <div class="wiz-divider"></div>
                    <p class="wiz-group-label"><i data-lucide="book-open" aria-hidden="true"></i> Previous college information <span class="opt">(Optional)</span></p>
                    <div class="wiz-field">
                        <label for="previous_program">Previous or current program <span class="opt">(Optional)</span></label>
                        <input type="text" name="previous_program" id="previous_program" class="wiz-input" value="{{ old('previous_program') }}" maxlength="255">
                    </div>
                </div>
                <div class="education-section d-none" data-education-for="cross_enrollee">
                    <div class="wiz-divider"></div>
                    <p class="wiz-group-label"><i data-lucide="building-2" aria-hidden="true"></i> Home institution <span class="opt">(Optional)</span></p>
                    <div class="wiz-row wiz-row--2">
                        <div class="wiz-field">
                            <label for="home_institution">School or institution <span class="opt">(Optional)</span></label>
                            <input type="text" name="home_institution" id="home_institution" class="wiz-input" value="{{ old('home_institution') }}" maxlength="255">
                        </div>
                        <div class="wiz-field">
                            <label for="home_program">Current course or program <span class="opt">(Optional)</span></label>
                            <input type="text" name="home_program" id="home_program" class="wiz-input" value="{{ old('home_program') }}" maxlength="255">
                        </div>
                    </div>
                </div>
                <div class="education-section d-none" data-education-for="transferee,shiftee,returnee,cross_enrollee">
                    <div class="wiz-field">
                        <label for="last_year_level">Last year level or semester attended <span class="opt">(Optional)</span></label>
                        <input type="text" name="last_year_level" id="last_year_level" class="wiz-input" value="{{ old('last_year_level') }}" maxlength="100" placeholder="e.g. 2nd year, 1st semester">
                    </div>
                </div>
                <p class="wiz-hint">No educational details are required for submission. The Registrar may contact you if clarification is needed.</p>
            </div>
        </div>




        {{-- ╔══════════════════════════════════════════════════╗
             ║  STEP 5 — FAMILY & SUPPORTING DOCUMENTS           ║
             ╚══════════════════════════════════════════════════╝ --}}
        <div class="wiz-panel" id="step-5">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="file-up"></i></div>
                <div>
                    <h2 class="wiz-panel-title">Family &amp; Supporting Documents</h2>
                    <p class="wiz-panel-sub">Add optional family details and preliminary document uploads.</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                {{-- ── Tab Navigation ─────────────────────────────── --}}
                <section class="wiz-family-section" aria-labelledby="family-section-title">
                    <h3 id="family-section-title" class="wiz-subsection-title">Family information <span class="opt">(Optional)</span></h3>

                <p class="wiz-group-label"><i data-lucide="heart"></i> Parent / Guardian</p>

                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="father_name">Father's Name <span class="opt">(Optional)</span></label>
                        <input type="text" name="father_name" id="father_name"
                            placeholder="Father's full name" class="wiz-input"
                            value="{{ old('father_name') }}" autocomplete="off">
                    </div>
                    <div class="wiz-field">
                        <label for="father_occupation">Father's Occupation <span class="opt">(Optional)</span></label>
                        <input type="text" name="father_occupation" id="father_occupation"
                            placeholder="e.g. Driver, Farmer, OFW" class="wiz-input"
                            value="{{ old('father_occupation') }}" autocomplete="off">
                    </div>
                </div>

                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="mother_name">Mother's Name <span class="opt">(Optional)</span></label>
                        <input type="text" name="mother_name" id="mother_name"
                            placeholder="Mother's full name" class="wiz-input"
                            value="{{ old('mother_name') }}" autocomplete="off">
                    </div>
                    <div class="wiz-field">
                        <label for="mother_occupation">Mother's Occupation <span class="opt">(Optional)</span></label>
                        <input type="text" name="mother_occupation" id="mother_occupation"
                            placeholder="e.g. Housewife, Teacher, OFW" class="wiz-input"
                            value="{{ old('mother_occupation') }}" autocomplete="off">
                    </div>
                </div>

                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="parent_address">Parent's Address <span class="opt">(Optional)</span></label>
                        <input type="text" name="parent_address" id="parent_address"
                            placeholder="Same as current address, if applicable" class="wiz-input"
                            value="{{ old('parent_address') }}" autocomplete="off">
                    </div>
                    <div class="wiz-field">
                        <label for="parent_contact">Parent's Contact <span class="opt">(Optional)</span></label>
                        <input type="text" name="parent_contact" id="parent_contact"
                            placeholder="09XXXXXXXXX" class="wiz-input"
                            value="{{ old('parent_contact') }}" autocomplete="off">
                    </div>
                </div>

                <div class="wiz-divider"></div>
                <p class="wiz-group-label"><i data-lucide="clipboard-list"></i> Other Details</p>

                <div class="wiz-row wiz-row--2">
                    <div class="wiz-field">
                        <label for="occupation">Occupation <span class="opt">(Optional)</span></label>
                        <input type="text" name="occupation" id="occupation"
                            placeholder="e.g. Student, Employed, Self-employed" class="wiz-input"
                            value="{{ old('occupation') }}" autocomplete="organization-title">
                    </div>
                    <div class="wiz-field">
                        <label for="disability">Disability <span class="opt">(Optional)</span></label>
                        <input type="text" name="disability" id="disability"
                            placeholder="If applicable" class="wiz-input"
                            value="{{ old('disability') }}" autocomplete="off">
                    </div>
                </div>

                <div class="wiz-field d-none" id="pwdIdField" style="max-width:360px">
                    <label for="pwd_id">PWD ID Number <span class="opt">(Optional)</span></label>
                    <input type="text" name="pwd_id" id="pwd_id"
                        placeholder="PWD ID number, if applicable" class="wiz-input"
                        value="{{ old('pwd_id') }}" autocomplete="off">
                </div>

                </section>
                <div class="wiz-divider"></div>
                <h3 class="wiz-subsection-title">Supporting documents <span class="opt">(Optional)</span></h3>
<div class="doc-tabs" role="tablist">
                    <button type="button" class="doc-tab-btn active"
                        data-tab="uploads" role="tab" aria-selected="true">
                        <i data-lucide="upload-cloud"></i> Upload Documents
                    </button>
                    <button type="button" class="doc-tab-btn"
                        data-tab="checklist" role="tab" aria-selected="false">
                        <i data-lucide="clipboard-check"></i> Requirements Checklist
                    </button>
                </div>

                {{-- ── Tab Panel: Upload Documents ─────────────────── --}}
                <div class="doc-tab-panel active" data-tab-panel="uploads" role="tabpanel">

                    <div id="noDocumentNotice" class="wiz-inline-notice wiz-inline-notice--neutral">
                        <i data-lucide="info"></i>
                        <span>Select your applicant type in Step 1 to see the applicable document uploads.</span>
                    </div>

                    {{-- New Student --}}
                    <div class="document-section d-none" data-type="new_student">
                        <div class="wiz-inline-notice wiz-inline-notice--info" style="margin-bottom:16px">
                            <i data-lucide="info"></i>
                            <div><strong>New Student / First Year</strong> — documents below are optional for preliminary screening.</div>
                        </div>
                        @include('public.application.partials.document-input', ['name'=>'form_138','label'=>'Form 138 or Equivalent','description'=>'Issued by your originating school.'])
                        @include('public.application.partials.document-input', ['name'=>'good_moral_certificate','label'=>'Certificate of Good Moral Character','description'=>'Issued by your originating school.'])
                        @include('public.application.partials.document-input', ['name'=>'birth_certificate','label'=>'PSA Birth Certificate','description'=>'Digital copy for preliminary screening.'])
                    </div>

                    {{-- Transferee --}}
                    <div class="document-section d-none" data-type="transferee">
                        <div class="wiz-inline-notice wiz-inline-notice--info" style="margin-bottom:16px">
                            <i data-lucide="info"></i>
                            <div><strong>Transferee</strong> — documents below are optional for preliminary screening.</div>
                        </div>
                        @include('public.application.partials.document-input', ['name'=>'transfer_credentials','label'=>'Certificate of Transfer Credentials','description'=>'Issued by your originating school.'])
                        @include('public.application.partials.document-input', ['name'=>'transcript_of_records','label'=>'Transcript of Records','description'=>'For preliminary evaluation.'])
                        @include('public.application.partials.document-input', ['name'=>'good_moral_certificate','label'=>'Certificate of Good Moral Character','description'=>'Issued by your originating school.'])
                        @include('public.application.partials.document-input', ['name'=>'birth_certificate','label'=>'PSA Birth Certificate','description'=>'Digital copy for preliminary screening.'])
                    </div>

                    {{-- Shiftee --}}
                    <div class="document-section d-none" data-type="shiftee">
                        <div class="wiz-inline-notice wiz-inline-notice--neutral">
                            <i data-lucide="info"></i>
                            <span>No document upload required for Shiftees at this stage. The Registrar's Office will provide further instructions.</span>
                        </div>
                    </div>

                    {{-- Returnee --}}
                    <div class="document-section d-none" data-type="returnee">
                        <div class="wiz-inline-notice wiz-inline-notice--neutral">
                            <i data-lucide="info"></i>
                            <span>No additional document upload required for Returnees. The Registrar's Office will provide further instructions.</span>
                        </div>
                    </div>

                    {{-- Cross-Enrollee --}}
                    <div class="document-section d-none" data-type="cross_enrollee">
                        <div class="wiz-inline-notice wiz-inline-notice--info">
                            <i data-lucide="info"></i>
                            <div><strong>Cross-Enrollee:</strong> Requirements are subject to confirmation by the Registrar's Office.</div>
                        </div>
                    </div>

                    {{-- Marriage Certificate --}}
                    <div id="marriageDocument" class="d-none" style="margin-top:20px">
                        <div class="wiz-divider"></div>
                        <p class="wiz-group-label"><i data-lucide="bell-ring"></i> Marriage Certificate</p>
                        <p style="font-size:13px;color:#64748b;margin:-8px 0 14px">Applicable only to married applicants. Upload is optional.</p>
                        @include('public.application.partials.document-input', ['name'=>'marriage_certificate','label'=>'Marriage Certificate (PSA)','description'=>'Upload if applicable.'])
                    </div>

                </div>{{-- end uploads tab panel --}}

                {{-- ── Tab Panel: Requirements Checklist ──────────── --}}
                <div class="doc-tab-panel" data-tab-panel="checklist" role="tabpanel">

                    <div id="physicalRequirementsCard" class="wiz-reqs">
                        <div class="wiz-reqs-header">
                            <i data-lucide="folder-open"></i>
                            Physical Requirements to Bring
                        </div>
                        <p class="wiz-reqs-sub">
                            These must be presented at the Registrar's Office regardless of digital uploads.
                            Tick each item off as you prepare it — this checklist is for your reference only.
                        </p>
                        <ul id="physicalRequirementsList" class="wiz-reqs-checklist">
                            <li>
                                <span class="wiz-reqs-empty">
                                    Select your applicant type in Step 1 to see the requirements list.
                                </span>
                            </li>
                        </ul>
                    </div>

                </div>{{-- end checklist tab panel --}}


            </div>
        </div>

        {{-- ╔══════════════════════════════════════════════════╗
             ║  STEP 6 — REVIEW & SUBMIT                        ║
             ╚══════════════════════════════════════════════════╝ --}}
        <div class="wiz-panel" id="step-6">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="clipboard-check"></i></div>
                <div>
                    <h2 class="wiz-panel-title">Review Your Application</h2>
                    <p class="wiz-panel-sub">Please check all your details carefully before submitting.</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                {{-- ── Review Summary (populated by JS buildReview()) ── --}}
                <div id="reviewSummary" class="rev-summary">
                    <div class="rev-loading">
                        <i data-lucide="loader"></i> Loading your summary…
                    </div>
                </div>

                <div class="wiz-divider"></div>

                {{-- ── Simple Acknowledgement with Clickable Links ── --}}
                <div class="simple-ack-bar">
                    <label class="simple-ack-label" for="ack_enrollment">
                        <input type="checkbox" id="ack_enrollment" name="ack_enrollment"
                            class="consent-chk" value="1" required {{ old('ack_enrollment') ? 'checked' : '' }}>
                        <span class="consent-check-box"></span>
                        <span class="simple-ack-text">
                            I confirm that the information I provided is accurate to the best of my knowledge, agree to the <a href="#" class="simple-ack-link" id="openTermsModal">Terms</a>, and acknowledge that I have read the <a href="#" class="simple-ack-link" id="openPrivacyModal">Privacy Policy</a>. I understand this is an application for review and does not confirm admission.
                        </span>
                    </label>

                    <div class="wiz-err-msg d-none" id="ackError" style="margin-top:10px">
                        <i data-lucide="alert-circle"></i> You must agree to the terms and privacy policy before submitting.
                    </div>
                </div>

            </div>
        </div>

        {{-- ── Wizard Navigation ────────────────────────────────────── --}}
        <div class="wiz-nav">
            <button type="button" class="wiz-btn wiz-btn--ghost" id="wizPrev" style="visibility:hidden">
                <i data-lucide="arrow-left"></i> Previous
            </button>
            <button type="button" class="wiz-btn wiz-btn--primary" id="wizNext">
                Next <i data-lucide="arrow-right"></i>
            </button>
            <button type="button" class="wiz-btn wiz-btn--submit d-none" id="wizSubmit">
                <i data-lucide="send"></i> Submit Application
            </button>
        </div>

    </form>

    {{-- ── Submit Confirmation Modal ────────────────────────────────────── --}}
    <div class="confirm-modal-overlay" id="confirmModal">
        <div class="confirm-modal">
            <div class="confirm-modal-header">
                <div class="confirm-modal-icon">
                    <i data-lucide="alert-circle"></i>
                </div>
                <h3 class="confirm-modal-title">Confirm Submission</h3>
            </div>
            <div class="confirm-modal-body">
                <p>Are you sure you want to submit your pre-enrollment application?</p>
                <p class="confirm-modal-sub">Please review all information carefully before confirming. Once submitted, you cannot make changes.</p>
            </div>
            <div class="confirm-modal-footer">
                <button type="button" class="wiz-btn wiz-btn--ghost" id="confirmCancel">
                    Cancel
                </button>
                <button type="button" class="wiz-btn wiz-btn--primary" id="confirmSubmit">
                    <i data-lucide="send"></i> Yes, Submit Application
                </button>
            </div>
        </div>
    </div>

    {{-- ── Privacy Policy Modal ────────────────────────────────────── --}}
    <div class="privacy-modal-overlay" id="privacyModal">
        <div class="privacy-modal">
            <div class="privacy-modal-header">
                <h3 class="privacy-modal-title">Privacy Policy</h3>
            </div>
            <div class="privacy-modal-body">
                <p>
                    We take your privacy seriously. When you fill out this enrollment form, you're sharing personal information with Danao Technological College (DTC). This notice explains what we collect, why we need it, and how we keep it safe. This follows the Data Privacy Act of 2012 (RA 10173).
                </p>

                <div class="privacy-section">
                    <h4>What we collect</h4>
                    <p>Your name, birthdate, address, phone number, family information, academic records, and the documents you upload here. Only what you choose to share with us.</p>
                </div>

                <div class="privacy-section">
                    <h4>Why we need it</h4>
                    <p>To process your enrollment, verify your credentials, send you updates about your application, and maintain your official student records as required by CHED.</p>
                </div>

                <div class="privacy-section">
                    <h4>How we protect it</h4>
                    <p>Your information stays with DTC. Only our staff who need it for your enrollment can access it. We don't sell your data to anyone, and we won't share it without your permission.</p>
                </div>

                <div class="privacy-section">
                    <h4>Your rights</h4>
                    <p>You can ask to see, correct, or delete your information anytime. Just visit the Registrar's Office. If you have concerns, you can also contact the National Privacy Commission at complaints@privacy.gov.ph.</p>
                </div>
            </div>
            <div class="privacy-modal-footer">
                <button type="button" class="wiz-btn wiz-btn--primary" id="closePrivacyModal">
                    I Understand
                </button>
            </div>
        </div>
    </div>

    {{-- ── Terms Modal ────────────────────────────────────── --}}
    <div class="privacy-modal-overlay" id="termsModal">
        <div class="privacy-modal">
            <div class="privacy-modal-header">
                <h3 class="privacy-modal-title">Terms of Service</h3>
            </div>
            <div class="privacy-modal-body">
                <div class="privacy-section">
                    <h4>About this form</h4>
                    <p>This is a preliminary application - it doesn't guarantee admission. You'll still need to bring your original documents to the Registrar's Office. We review all applications and may reject those with incorrect information.</p>
                </div>

                <div class="privacy-section">
                    <h4>Your responsibilities</h4>
                    <p>By submitting this form, you certify that all information provided is true and accurate to the best of your knowledge. You agree to provide original documents when requested by the Registrar's Office.</p>
                </div>

                <div class="privacy-section">
                    <h4>Data collection</h4>
                    <p>You consent to the collection and processing of your personal information as described in our Privacy Policy. This includes providing accurate and complete information for enrollment purposes.</p>
                </div>

                <div class="privacy-section">
                    <h4>Changes to terms</h4>
                    <p>DTC reserves the right to update these terms as needed. Continued use of our enrollment system constitutes acceptance of any changes.</p>
                </div>
            </div>
            <div class="privacy-modal-footer">
                <button type="button" class="wiz-btn wiz-btn--primary" id="closeTermsModal">
                    I Understand
                </button>
            </div>
        </div>
    </div>
</div>
@stop


@section('scripts')
<script src="{{ asset('js/application-wizard.js') }}" defer></script>
@php
    $firstErrorKey = $errors->keys()[0] ?? '';
    $errorStep = match (true) {
        in_array($firstErrorKey, ['academic_status', 'program_id'], true) => 1,
        in_array($firstErrorKey, ['first_name', 'last_name', 'middle_name', 'honorific', 'suffix', 'gender', 'birthdate', 'birth_place', 'religion', 'nationality', 'lrn', 'marital_status', 'spouse_name'], true) => 2,
        in_array($firstErrorKey, ['email', 'phone', 'current_address', 'region', 'province', 'city', 'barangay', 'postal_code', 'country'], true) => 3,
        in_array($firstErrorKey, ['education_level', 'education_status', 'most_recent_school', 'school_year_completed', 'school_location', 'shs_strand', 'previous_program', 'last_year_level', 'home_institution', 'home_program'], true) => 4,
        $firstErrorKey === 'documents' || str_starts_with($firstErrorKey, 'documents.') || in_array($firstErrorKey, ['father_name', 'father_occupation', 'mother_name', 'mother_occupation', 'parent_address', 'parent_contact', 'occupation', 'disability', 'pwd_id', 'ack_enrollment'], true) => in_array($firstErrorKey, ['ack_enrollment'], true) ? 6 : 5,
        default => 1,
    };
@endphp
<script>
    /* Tell the wizard which step to open first.
       Runs after application-wizard.js because it is defer-loaded
       but this inline script runs after DOMContentLoaded as well. */
    document.addEventListener('DOMContentLoaded', function () {
        if (window._wizGoTo) {
            @if ($errors->any())
                window._wizGoTo({{ $errorStep ?? 1 }}, true);
                setTimeout(function () {
                    var keys = @json($errors->keys());
                    var form = document.getElementById('applicationForm');
                    var firstField = null;
                    keys.some(function (key) {
                        var field = form && form.elements.namedItem(key);
                        if (!field && key.indexOf('documents.') === 0) {
                            field = form && form.elements.namedItem('documents[' + key.substring(10) + ']');
                        }
                        if (field && typeof field.focus === 'function') { firstField = field; return true; }
                        return false;
                    });
                    if (firstField) firstField.focus();
                    else document.getElementById('serverErrors')?.focus();
                }, 0);
            @else
                window._wizGoTo(1, true);
            @endif
        }
    });
</script>
@stop
