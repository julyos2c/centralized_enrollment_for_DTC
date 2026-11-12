@extends('layouts.public')

@section('title', 'Online Admission Application')

@section('styles')
<link rel="stylesheet" href="{{ asset_v('css/application-wizard.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/physical-requirements.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/application-form.css') }}">
<link rel="stylesheet" href="{{ asset_v('css/legal.css') }}">
@stop


@section('content')
<div class="app-wizard-page app-form">

    {{-- ── Top bar ──────────────────────────────────────────────── --}}
    <div class="app-wizard-topbar">
        <a href="{{ route('landing') }}" class="app-wizard-back" aria-label="Back to home">
            <i data-lucide="arrow-left"></i>
        </a>
    </div>

    <header class="app-wizard-intro">
        <h1>Start your application</h1>
        <p>Complete the sections, then review before submitting.</p>
    </header>

    {{-- ── Step Tracker ─────────────────────────────────────────── --}}
    <nav class="app-wizard-tracker" id="wizardTracker" aria-label="Application steps">
        <button type="button" class="wiz-step active" data-step="1" aria-controls="step-1" aria-current="step">
            <span class="wiz-step-dot"><i data-lucide="graduation-cap" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Admission</span>
        </button>
        <div class="wiz-connector"></div>
        <button type="button" class="wiz-step" data-step="2" aria-controls="step-2">
            <span class="wiz-step-dot"><i data-lucide="user" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Personal</span>
        </button>
        <div class="wiz-connector"></div>
        <button type="button" class="wiz-step" data-step="3" aria-controls="step-3">
            <span class="wiz-step-dot"><i data-lucide="phone" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Contact</span>
        </button>
        <div class="wiz-connector"></div>
        <button type="button" class="wiz-step" data-step="4" aria-controls="step-4">
            <span class="wiz-step-dot"><i data-lucide="school" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Education</span>
        </button>
        <div class="wiz-connector"></div>
        <button type="button" class="wiz-step" data-step="5" aria-controls="step-5">
            <span class="wiz-step-dot"><i data-lucide="users" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Family &amp; Docs</span>
        </button>
        <div class="wiz-connector"></div>
        <button type="button" class="wiz-step" data-step="6" aria-controls="step-6">
            <span class="wiz-step-dot"><i data-lucide="clipboard-check" aria-hidden="true"></i></span>
            <span class="wiz-step-label">Review</span>
        </button>
    </nav>

    {{-- Progress summary and messages for locked sections or saving problems. --}}
    <p class="wiz-progress-text" id="wizardProgressText" aria-live="polite">Step 1 of 6: Admission</p>
    <div class="wiz-step-message" id="wizardStepMessage" role="alert" hidden></div>

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
    <div class="application-screening-notice">
        <button type="button" class="application-screening-toggle" id="screeningNoticeToggle"
            aria-expanded="false" aria-controls="screeningNoticeContent">
            <i data-lucide="info" aria-hidden="true"></i>
            <span>Application notice</span>
            <i class="application-screening-chevron" data-lucide="chevron-down" aria-hidden="true"></i>
        </button>
        <div class="application-screening-content" id="screeningNoticeContent" hidden>
            <p>Fields marked <span class="req">*</span> are required. All other fields are optional and can be left blank.</p>
            <strong>Preliminary screening only.</strong>
            Uploaded documents are for initial review. You must still present
            original documents to the Registrar's Office before official enrollment.
        </div>
    </div>

    {{-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
    <form
        action="{{ route('public.application.store') }}"
        method="POST"
        enctype="multipart/form-data"
        id="applicationForm"
        data-step-url="{{ request()->getBaseUrl() . str_replace('/999999', '/:step', route('public.application.step', ['step' => 999999], false)) }}"
        novalidate
        autocomplete="on"
    >
        @csrf
        <input type="hidden" name="submission_token" value="{{ $submissionToken }}">

        {{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             ║  STEP 1 — APPLICANT TYPE & PROGRAM              ║
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="wiz-panel active" id="step-1">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="graduation-cap"></i></div>
                <div>
                    <h2 class="wiz-panel-title">Applicant Type &amp; Program</h2>
                    <p class="wiz-panel-sub">Choose your applicant type and intended program.</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                <div class="wiz-grid">
                    <div class="wiz-field c5" id="field-academic_status">
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
                    <div class="wiz-field c7" id="field-program_id">
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


        {{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             ║  STEP 2 — PERSONAL INFORMATION                  ║
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        @php
            $optionalPersonalFields = ['birth_place', 'religion', 'nationality', 'lrn', 'marital_status', 'spouse_name'];
            $personalDetailsExpanded = collect($optionalPersonalFields)->contains(function ($field) use ($errors) {
                $value = old($field);
                return $errors->has($field) || (is_string($value) && trim($value) !== ''
                    && ($field !== 'nationality' || strcasecmp(trim($value), 'Filipino') !== 0));
            });
        @endphp
        <div class="wiz-panel" id="step-2">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="user"></i></div>
                <div>
                    <h2 class="wiz-panel-title">Personal Information</h2>
                    <p class="wiz-panel-sub">Use the details shown on your official documents.</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                {{-- Each name control has its own label, input, and validation message. --}}
                <section class="wiz-name-section" aria-labelledby="applicant-name-heading">
                    <div class="wiz-name-section-header">
                        <h3 id="applicant-name-heading">Your name</h3>
                    </div>
                    <div class="wiz-personal-names">
                        <div class="wiz-name-composite wiz-name-composite--first">
                            <div class="wiz-field wiz-name-honorific">
                                <label for="honorific">Honorific</label>
                                <input type="text" name="honorific" id="honorific" placeholder="e.g. Mr."
                                    class="wiz-input @error('honorific') wiz-invalid @enderror"
                                    value="{{ old('honorific') }}" autocomplete="honorific-prefix" maxlength="20">
                                @error('honorific')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field wiz-name-required" id="field-first_name">
                                <label for="first_name">First Name <span class="req">*</span></label>
                                <input type="text" name="first_name" id="first_name" placeholder="Juan"
                                    class="wiz-input @error('first_name') wiz-invalid @enderror"
                                    value="{{ old('first_name') }}" autocomplete="given-name" required>
                                @error('first_name')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="wiz-name-composite wiz-name-composite--last">
                            <div class="wiz-field wiz-name-required" id="field-last_name">
                                <label for="last_name">Last Name <span class="req">*</span></label>
                                <input type="text" name="last_name" id="last_name" placeholder="Dela Cruz"
                                    class="wiz-input @error('last_name') wiz-invalid @enderror" value="{{ old('last_name') }}"
                                    autocomplete="family-name" required>
                                @error('last_name')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field wiz-name-suffix">
                                <label for="suffix">Suffix</label>
                                <input type="text" name="suffix" id="suffix" placeholder="e.g. Jr."
                                    class="wiz-input @error('suffix') wiz-invalid @enderror"
                                    value="{{ old('suffix') }}" autocomplete="honorific-suffix" maxlength="20">
                                @error('suffix')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="wiz-field wiz-name-middle">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" name="middle_name" id="middle_name" placeholder="Santos"
                                class="wiz-input @error('middle_name') wiz-invalid @enderror"
                                value="{{ old('middle_name') }}" autocomplete="additional-name">
                            @error('middle_name')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                        </div>
                    </div>
                </section>
                <section class="wiz-personal-section" aria-labelledby="identity-details-heading">
                    <h3 class="wiz-personal-section-title" id="identity-details-heading">Identity details</h3>
                    <div class="wiz-grid">
                        <div class="wiz-field c3" id="field-gender">
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
                        <div class="wiz-field c3" id="field-birthdate">
                            <label for="birthdate">Date of Birth <span class="req">*</span></label>
                            <input type="date" name="birthdate" id="birthdate"
                                class="wiz-input @error('birthdate') wiz-invalid @enderror"
                                value="{{ old('birthdate') }}"
                                max="{{ today()->toDateString() }}"
                                autocomplete="bday" required>
                            @error('birthdate')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                        </div>
                    </div>

                </section>

                <section class="wiz-personal-disclosure" aria-labelledby="personalDetailsToggle">
                    <button type="button" class="wiz-personal-toggle" id="personalDetailsToggle"
                        aria-expanded="{{ $personalDetailsExpanded ? 'true' : 'false' }}" aria-controls="personalDetailsContent">
                        <span><span id="personal-additional-heading">Additional personal details</span>
                            <span class="wiz-personal-toggle-hint">Birthplace, nationality, LRN and marital details</span>
                        </span>
                        <i data-lucide="chevron-down" aria-hidden="true"></i>
                    </button>
                    <div id="personalDetailsContent" class="wiz-personal-content" data-has-old-input="{{ session()->hasOldInput() ? 'true' : 'false' }}" @if (!$personalDetailsExpanded) hidden @endif>
                        <div class="wiz-grid">
                            <div class="wiz-field c4">
                                <label for="birth_place">Place of Birth</label>
                                <input type="text" name="birth_place" id="birth_place" placeholder="e.g. Remedios, Danao, Bohol"
                                    class="wiz-input @error('birth_place') wiz-invalid @enderror" value="{{ old('birth_place') }}" autocomplete="off">
                                @error('birth_place')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field c4 m-half">
                                <label for="religion">Religion</label>
                                <input type="text" name="religion" id="religion"
                                    placeholder="e.g. Roman Catholic"
                                    class="wiz-input @error('religion') wiz-invalid @enderror" value="{{ old('religion') }}"
                                    autocomplete="off">
                                @error('religion')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field c4 m-half">
                                <label for="nationality">Nationality</label>
                                <input type="text" name="nationality" id="nationality" placeholder="Filipino"
                                    class="wiz-input @error('nationality') wiz-invalid @enderror" value="{{ old('nationality', 'Filipino') }}"
                                    @if (!session()->hasOldInput('nationality')) data-draft-default="Filipino" @endif
                                    autocomplete="off">
                                @error('nationality')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field c4 m-half">
                                <label for="lrn">LRN</label>
                                <input type="text" name="lrn" id="lrn" placeholder="136041123456"
                                    class="wiz-input @error('lrn') wiz-invalid @enderror" value="{{ old('lrn') }}" maxlength="12"
                                    autocomplete="off">
                                @error('lrn')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                                <span class="wiz-hint">Learner Reference Number (DepEd)</span>
                            </div>
                            <div class="wiz-field c4 m-half" id="field-marital_status">
                                <label for="marital_status">Marital Status</label>
                                <select name="marital_status" id="marital_status" class="wiz-input @error('marital_status') wiz-invalid @enderror" autocomplete="off">
                                    <option value="">Select</option>
                                    <option value="single"   {{ old('marital_status') === 'single'   ? 'selected' : '' }}>Single</option>
                                    <option value="married"  {{ old('marital_status') === 'married'  ? 'selected' : '' }}>Married</option>
                                    <option value="divorced" {{ old('marital_status') === 'divorced' ? 'selected' : '' }}>Divorced</option>
                                    <option value="widowed"  {{ old('marital_status') === 'widowed'  ? 'selected' : '' }}>Widowed</option>
                                </select>
                                @error('marital_status')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field c4" id="spouseField">
                                <label for="spouse_name">Spouse Name</label>
                                <input type="text" name="spouse_name" id="spouse_name"
                                    placeholder="Spouse's full name"
                                    class="wiz-input @error('spouse_name') wiz-invalid @enderror" value="{{ old('spouse_name') }}"
                                    autocomplete="off">
                                @error('spouse_name')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </div>


        {{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             ║  STEP 3 — CONTACT INFORMATION                   ║
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="wiz-panel" id="step-3">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="phone"></i></div>
                <div>
                    <h2 class="wiz-panel-title">Contact Information</h2>
                    <p class="wiz-panel-sub">Add your contact and address details.</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                <div class="wiz-grid">
                    <div class="wiz-field c7" id="field-email">
                        <label for="email">Email Address <span class="req">*</span></label>
                        <input type="email" name="email" id="email" placeholder="juandelacruz@email.com"
                            class="wiz-input @error('email') wiz-invalid @enderror"
                            value="{{ old('email') }}"
                            autocomplete="email" required>
                        @error('email')<span class="wiz-err-msg"><i data-lucide="alert-circle"></i>{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field c5">
                        <label for="phone">Contact Number</label>
                        <input type="text" name="phone" id="phone" placeholder="09XXXXXXXXX"
                            class="wiz-input" value="{{ old('phone') }}"
                            autocomplete="tel-national" inputmode="tel">
                    </div>
                </div>

                {{-- Country comes first: it decides whether the Philippine
                     region / province / city fields apply. --}}
                <div class="wiz-address-heading">
                    <h3>Address</h3>
                    <p>Optional. Choose your area from the lists; the postal code fills in when it is known.</p>
                </div>
                {{-- Address picker: public/js/address-cascade.js fills the lists from
                     the PSGC endpoints below (relative paths, versioned by data). --}}
                <div class="wiz-grid" id="addressFields"
                    data-lookup="{{ json_encode($addressLookup) }}"
                    data-old="{{ json_encode(['region_code' => old('region_code'), 'province_code' => old('province_code'), 'city_code' => old('city_code'), 'barangay' => old('barangay')]) }}">
                    <div class="wiz-field c8">
                        <label for="current_address">House No., Street</label>
                        <input type="text" name="current_address" id="current_address"
                            placeholder="e.g. 0161 Mahayahay St."
                            class="wiz-input" value="{{ old('current_address') }}"
                            autocomplete="street-address">
                    </div>
                    <div class="wiz-field c4">
                        <label for="country">Country</label>
                        <input type="text" name="country" id="country" placeholder="Philippines"
                            class="wiz-input" value="{{ old('country', 'Philippines') }}"
                            autocomplete="country-name">
                    </div>
                    <div class="wiz-field c4" data-ph-only>
                        <label for="region_code">Region</label>
                        <select name="region_code" id="region_code" data-address="region" class="wiz-input @error('region_code') wiz-invalid @enderror" disabled>
                            <option value="">Loading regions…</option>
                        </select>
                        @error('region_code')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field c4" data-ph-only>
                        <label for="area_select">Province / Independent City</label>
                        <select id="area_select" data-address="area" class="wiz-input @error('province_code') wiz-invalid @enderror" disabled aria-describedby="areaHint">
                            <option value="">Select a region first</option>
                        </select>
                        <input type="hidden" data-address="province" name="province_code" id="province_code" value="{{ old('province_code') }}">
                        <span class="wiz-hint" id="areaHint" data-address="area-hint" hidden></span>
                        @error('province_code')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field c4" data-ph-only>
                        <label for="city_code">City / Municipality</label>
                        <select name="city_code" id="city_code" data-address="city" class="wiz-input @error('city_code') wiz-invalid @enderror" disabled autocomplete="address-level2">
                            <option value="">Select a province first</option>
                        </select>
                        @error('city_code')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field c8" id="internationalLocationField" hidden>
                        <label for="international_location">City / Province outside the Philippines</label>
                        <input type="text" name="international_location" id="international_location" placeholder="e.g. City, State or Province" class="wiz-input" value="{{ old('international_location') }}" maxlength="255">
                        @error('international_location')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field c5" data-ph-only data-address="barangay-select-field" id="barangaySelectField" hidden>
                        <label for="barangay_select">Barangay</label>
                        <select id="barangay_select" data-address="barangay-select" class="wiz-input" disabled>
                            <option value="">Select a city first</option>
                        </select>
                    </div>
                    {{-- Submitted value. Shown for "not listed" barangays and whenever the list is unavailable. --}}
                    <div class="wiz-field c5" data-address="barangay-manual-field" id="barangayManualField">
                        <label for="barangay_text">Barangay</label>
                        <input type="text" data-address="barangay" name="barangay" id="barangay_text" placeholder="e.g. Remedios" class="wiz-input" value="{{ old('barangay') }}" maxlength="255" autocomplete="address-level3">
                    </div>
                    <div class="wiz-field c3 m-half">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" data-address="postal" name="postal_code" id="postal_code" placeholder="e.g. 6344"
                            class="wiz-input" value="{{ old('postal_code') }}" list="postalCodeOptions"
                            autocomplete="postal-code" inputmode="numeric" maxlength="4" aria-describedby="postalCodeHint">
                        <datalist id="postalCodeOptions" data-address="postal-options"></datalist>
                        <span class="wiz-hint" id="postalCodeHint" data-address="postal-hint" aria-live="polite" hidden></span>
                    </div>
                    <div class="c12 wiz-address-status" id="addressLookupStatus" data-address="status" role="status" aria-live="polite" hidden>
                        <span id="addressLookupMessage" data-address="status-message"></span>
                        <button type="button" class="wiz-address-retry" id="addressLookupRetry" data-address="retry" hidden>Try again</button>
                    </div>
                </div>

            </div>
        </div>


{{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             ║  STEP 4 — EDUCATIONAL BACKGROUND            ║
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="wiz-panel" id="step-4">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="school" aria-hidden="true"></i></div>
                <div>
                    <h2 class="wiz-panel-title" id="educationHeading">Educational Background</h2>
                    <p class="wiz-panel-sub" id="educationDescription" aria-live="polite">Choose your applicant type in Admission to see the relevant school details.</p>
                </div>
            </div>
            <div class="wiz-panel-body">
                @php
                    $yearValue = fn (string $field) => old($field);
                    $isListedYear = fn (?string $value) => $value === null || $value === '' || in_array($value, $academicYears, true);
                    $curricula = \App\Models\ShsCurriculum::active()->with(['classifications' => fn ($q) => $q->active()])->orderBy('sort_order')->get();
                @endphp
                {{-- Structured education details (education-background.js). SHS-only
                     controls sit in new_student sections, which the wizard disables
                     (so they are not submitted) for other applicant types. --}}
                <div class="wiz-grid" id="educationFields"
                    data-lookup="{{ json_encode($educationLookup) }}"
                    data-old="{{ json_encode($educationOld) }}"
                    data-curricula="{{ json_encode($curricula->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'choice_label' => $c->choice_label, 'first' => $c->first_graduation_year, 'last' => $c->last_graduation_year, 'classifications' => $c->classifications->map(fn ($x) => ['id' => $x->id, 'track' => $x->track, 'name' => $x->name, 'offering' => $x->offering_key, 'label' => $x->label()])->values()])->values()) }}">
                    <div class="wiz-field c4">
                        <label for="education_level">Highest or current education level</label>
                        <select name="education_level" id="education_level" class="wiz-input @error('education_level') wiz-invalid @enderror" aria-describedby="educationLevelHint">
                            <option value="">Select education level</option>
                            @foreach(\App\Support\ApplicationEducation::levelsFor(is_string(old('academic_status')) ? old('academic_status') : null) as $level)
                                <option value="{{ $level }}" {{ old('education_level') === $level ? 'selected' : '' }}>{{ $level === 'Other' ? 'Other / equivalent qualification' : $level }}</option>
                            @endforeach
                        </select>
                        <span class="wiz-hint" id="educationLevelHint" aria-live="polite">Education levels depend on your applicant type.</span>
                        @error('education_level')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field c4">
                        <label for="education_status">Completion status</label>
                        <select name="education_status" id="education_status" class="wiz-input @error('education_status') wiz-invalid @enderror">
                            <option value="">Select status</option>
                            @foreach($educationStatuses as $value => $label)
                                <option value="{{ $value }}" {{ old('education_status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('education_status')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field c4" id="educationStatusOtherField" @if(old('education_status') !== 'Other') hidden @endif>
                        <label for="education_status_other">Describe your status</label>
                        <input type="text" name="education_status_other" id="education_status_other" data-required-message="Describe your completion status." class="wiz-input @error('education_status_other') wiz-invalid @enderror" value="{{ old('education_status_other') }}" maxlength="255" placeholder="e.g. Alternative Learning System">
                        @error('education_status_other')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>

                    {{-- New students pick their school from the Bohol list (municipality first). --}}
                    @if($schoolScope)
                    <div class="education-section c12 d-none" data-education-for="new_student">
                        <div class="wiz-grid" id="schoolPicker" data-scope="{{ json_encode($schoolScope) }}">
                            <div class="wiz-field c4">
                                <label for="school_locality">Municipality / city of the school</label>
                                <select id="school_locality" class="wiz-input" data-searchable aria-describedby="schoolLocalityHint">
                                    <option value="">All of {{ $schoolScope['province_name'] }}</option>
                                    @foreach($schoolScope['localities'] as $locality)
                                        <option value="{{ $locality['code'] }}">{{ $locality['name'] }}</option>
                                    @endforeach
                                </select>
                                <span class="wiz-hint" id="schoolLocalityHint">{{ $schoolScope['province_name'] }}, Region VII (Central Visayas)</span>
                            </div>
                            <div class="wiz-field c8">
                                <label for="school_select">Senior High School</label>
                                <select id="school_select" class="wiz-input @error('school_id') wiz-invalid @enderror" data-searchable aria-describedby="schoolSelectHint">
                                    <option value="">Loading schools…</option>
                                </select>
                                <input type="hidden" name="school_choice" id="school_choice" value="{{ old('school_choice') }}">
                                <span class="wiz-hint" id="schoolSelectHint" aria-live="polite">Type to search. Not listed, or studied outside {{ $schoolScope['province_name'] }}? Choose it at the end of the list.</span>
                                @error('school_id')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="wiz-field c8" id="schoolNameField">
                        <label for="most_recent_school">Most recent school attended</label>
                        <input type="text" name="most_recent_school" id="most_recent_school" data-required-message="Type the name of your Senior High School." class="wiz-input" value="{{ old('most_recent_school') }}" maxlength="255" autocomplete="organization">
                    </div>
                    <div class="education-section c4 d-none" data-education-for="new_student" id="schoolSectorSection">
                        <input type="hidden" name="school_id" id="school_id" value="{{ old('school_id') }}">
                        <div class="wiz-field" id="schoolSectorField">
                            <label for="school_sector">Public or private</label>
                            <select name="school_sector" id="school_sector" class="wiz-input">
                                <option value="">Select type of school</option>
                                @foreach($schoolSectors as $value => $label)
                                    <option value="{{ $value }}" {{ old('school_sector') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="education-section c12 d-none" data-education-for="new_student">
                        <div class="wiz-school-card" id="schoolSelectedCard" hidden aria-live="polite">
                            <i data-lucide="school" aria-hidden="true"></i>
                            <div>
                                <strong data-school="name"></strong>
                                <span data-school="meta"></span>
                            </div>
                            <button type="button" class="wiz-school-change" id="schoolChangeButton">Change</button>
                        </div>
                    </div>

                    {{-- School location: lists for SHS (auto-filled from a listed school), text for others. --}}
                    <div class="education-section c12 d-none" data-education-for="transferee,shiftee,returnee,cross_enrollee">
                        <div class="wiz-field">
                            <label for="school_location">School location</label>
                            <input type="text" name="school_location" id="school_location" class="wiz-input" value="{{ old('school_location') }}" maxlength="255" placeholder="City or municipality, province">
                        </div>
                    </div>
                    <div class="education-section c12 d-none" data-education-for="new_student">
                        <p class="wiz-group-label"><i data-lucide="map-pin" aria-hidden="true"></i> School location <span class="wiz-group-note">Filled in when you choose a listed school — complete or correct it if needed.</span></p>
                        <div class="wiz-grid" id="schoolLocationFields">
                            <div class="wiz-field c4">
                                <label for="school_region_code">Region</label>
                                <select name="school_region_code" id="school_region_code" data-address="region" class="wiz-input @error('school_region_code') wiz-invalid @enderror" disabled>
                                    <option value="">Loading regions…</option>
                                </select>
                                @error('school_region_code')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field c4">
                                <label for="school_area_select">Province / Independent City</label>
                                <select id="school_area_select" data-address="area" class="wiz-input @error('school_province_code') wiz-invalid @enderror" disabled aria-describedby="schoolAreaHint">
                                    <option value="">Select a region first</option>
                                </select>
                                <input type="hidden" data-address="province" name="school_province_code" id="school_province_code" value="{{ old('school_province_code') }}">
                                <span class="wiz-hint" id="schoolAreaHint" data-address="area-hint" hidden></span>
                                @error('school_province_code')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field c4">
                                <label for="school_city_code">City / Municipality</label>
                                <select name="school_city_code" id="school_city_code" data-address="city" class="wiz-input @error('school_city_code') wiz-invalid @enderror" disabled>
                                    <option value="">Select a province first</option>
                                </select>
                                @error('school_city_code')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field c5" data-address="barangay-select-field" hidden>
                                <label for="school_barangay_select">Barangay</label>
                                <select id="school_barangay_select" data-address="barangay-select" class="wiz-input" disabled>
                                    <option value="">Select a city first</option>
                                </select>
                            </div>
                            <div class="wiz-field c5" data-address="barangay-manual-field">
                                <label for="school_barangay">Barangay</label>
                                <input type="text" data-address="barangay" name="school_barangay" id="school_barangay" class="wiz-input" value="{{ old('school_barangay') }}" maxlength="255" placeholder="e.g. Poblacion">
                            </div>
                            <div class="wiz-field c7">
                                <label for="school_street">Street / address (optional)</label>
                                <input type="text" name="school_street" id="school_street" class="wiz-input" value="{{ old('school_street') }}" maxlength="255" placeholder="e.g. Rizal St.">
                            </div>
                            <div class="c12 wiz-address-status" data-address="status" role="status" aria-live="polite" hidden>
                                <span data-address="status-message"></span>
                                <button type="button" class="wiz-address-retry" data-address="retry" hidden>Try again</button>
                            </div>
                        </div>
                    </div>

                    {{-- School years: searchable lists with "Other" for unusual records. --}}
                    <div class="wiz-field c3">
                        <label for="school_year_started">School year started</label>
                        <select name="school_year_started" id="school_year_started" class="wiz-input @error('school_year_started') wiz-invalid @enderror" data-searchable>
                            <option value="">Select school year</option>
                            @foreach($academicYears as $year)
                                <option value="{{ $year }}" {{ $yearValue('school_year_started') === $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                            <option value="__other" data-extra="1" {{ !$isListedYear($yearValue('school_year_started')) || old('school_year_started') === '__other' ? 'selected' : '' }}>Other (type it)</option>
                        </select>
                        @error('school_year_started')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field c3" id="schoolYearStartedOtherField" @if($isListedYear($yearValue('school_year_started')) && old('school_year_started') !== '__other') hidden @endif>
                        <label for="school_year_started_other">Type the year started</label>
                        <input type="text" name="school_year_started_other" id="school_year_started_other" data-required-message="Type the school year you started." class="wiz-input @error('school_year_started_other') wiz-invalid @enderror" value="{{ old('school_year_started_other', $isListedYear($yearValue('school_year_started')) ? '' : $yearValue('school_year_started')) }}" maxlength="30" placeholder="e.g. 1998-1999">
                        @error('school_year_started_other')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field c3">
                        <label for="school_year_completed">School year completed or last attended</label>
                        <select name="school_year_completed" id="school_year_completed" class="wiz-input @error('school_year_completed') wiz-invalid @enderror" data-searchable>
                            <option value="">Select school year</option>
                            @foreach($academicYears as $year)
                                <option value="{{ $year }}" {{ $yearValue('school_year_completed') === $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                            <option value="__other" data-extra="1" {{ !$isListedYear($yearValue('school_year_completed')) || old('school_year_completed') === '__other' ? 'selected' : '' }}>Other (type it)</option>
                        </select>
                        @error('school_year_completed')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>
                    <div class="wiz-field c3" id="schoolYearCompletedOtherField" @if($isListedYear($yearValue('school_year_completed')) && old('school_year_completed') !== '__other') hidden @endif>
                        <label for="school_year_completed_other">Type the year</label>
                        <input type="text" name="school_year_completed_other" id="school_year_completed_other" data-required-message="Type the school year you graduated or last attended." class="wiz-input @error('school_year_completed_other') wiz-invalid @enderror" value="{{ old('school_year_completed_other', $isListedYear($yearValue('school_year_completed')) ? '' : $yearValue('school_year_completed')) }}" maxlength="30" placeholder="e.g. 1998-1999">
                        @error('school_year_completed_other')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                    </div>

                    {{-- SHS curriculum and strand / elective cluster (configurable by staff). --}}
                    <div class="education-section c12 d-none" data-education-for="new_student">
                        <div class="wiz-grid">
                            <div class="wiz-field c4">
                                <label for="shs_curriculum_id">SHS curriculum</label>
                                <select name="shs_curriculum_id" id="shs_curriculum_id" class="wiz-input @error('shs_curriculum_id') wiz-invalid @enderror" aria-describedby="shsCurriculumHint">
                                    <option value="">Select curriculum</option>
                                    @foreach($curricula as $curriculum)
                                        <option value="{{ $curriculum->id }}" {{ (string) old('shs_curriculum_id') === (string) $curriculum->id ? 'selected' : '' }}>{{ $curriculum->name }}</option>
                                    @endforeach
                                </select>
                                <span class="wiz-hint" id="shsCurriculumHint" aria-live="polite">Chosen from your school year; change it if your school used another curriculum.</span>
                                @error('shs_curriculum_id')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field c8">
                                <label for="shs_strand_selection">Track and strand</label>
                                <select name="shs_strand_selection" id="shs_strand_selection" class="wiz-input @error('shs_strand_selection') wiz-invalid @enderror" data-searchable>
                                    <option value="">Select a curriculum first</option>
                                </select>
                                @error('shs_strand_selection')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                            <div class="wiz-field c8" id="shsStrandOtherField" @if(old('shs_strand_selection') !== 'other') hidden @endif>
                                <label for="shs_strand">Specify your strand or specialization</label>
                                <input type="text" name="shs_strand" id="shs_strand" data-required-message="Type your strand or specialization." class="wiz-input @error('shs_strand') wiz-invalid @enderror" value="{{ old('shs_strand') }}" maxlength="100" placeholder="e.g. TVL – Cookery, Maritime">
                                @error('shs_strand')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="education-section c5 d-none" data-education-for="transferee,shiftee,returnee">
                        <div class="wiz-field">
                            <label for="previous_program">Previous or current program</label>
                            <input type="text" name="previous_program" id="previous_program" class="wiz-input" value="{{ old('previous_program') }}" maxlength="255">
                            <span class="wiz-hint">Your intended program is already selected in Admission.</span>
                        </div>
                    </div>
                    <div class="education-section c12 d-none" data-education-for="cross_enrollee">
                        <p class="wiz-group-label"><i data-lucide="building-2" aria-hidden="true"></i> Home institution</p>
                        <div class="wiz-grid">
                            <div class="wiz-field c7">
                                <label for="home_institution">School or institution</label>
                                <input type="text" name="home_institution" id="home_institution" class="wiz-input" value="{{ old('home_institution') }}" maxlength="255">
                            </div>
                            <div class="wiz-field c5">
                                <label for="home_program">Current course or program</label>
                                <input type="text" name="home_program" id="home_program" class="wiz-input" value="{{ old('home_program') }}" maxlength="255">
                            </div>
                        </div>
                    </div>
                    <div class="education-section c7 d-none" data-education-for="transferee,shiftee,returnee,cross_enrollee">
                        <div class="wiz-field">
                            <label for="last_year_level">Last year level and semester attended</label>
                            <input type="text" name="last_year_level" id="last_year_level" class="wiz-input" value="{{ old('last_year_level') }}" maxlength="100" placeholder="e.g. 2nd year, 1st semester">
                            <span class="wiz-hint" id="lastYearLevelHint">Include your year level and semester, if known.</span>
                        </div>
                    </div>
                </div>
                <p class="wiz-hint wiz-step-note">No educational details are required for submission. The Registrar may contact you if clarification is needed.</p>
            </div>
        </div>




{{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             ║  STEP 5 — FAMILY & SUPPORTING DOCUMENTS           ║
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="wiz-panel" id="step-5">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="file-up"></i></div>
                <div>
                    <h2 class="wiz-panel-title">Family &amp; Supporting Documents</h2>
                    <p class="wiz-panel-sub">Add family details and supporting documents.</p>
                </div>
            </div>
            <div class="wiz-panel-body">

                {{-- ── Tab Navigation ─────────────────────────────── --}}
                <section class="wiz-family-section" aria-labelledby="family-section-title">
                    <h3 id="family-section-title" class="wiz-subsection-title">Family information</h3>

                <p class="wiz-group-label"><i data-lucide="heart"></i> Parent / Guardian</p>

                <div class="wiz-grid">
                    <div class="wiz-field c7">
                        <label for="father_name">Father's Name</label>
                        <input type="text" name="father_name" id="father_name"
                            placeholder="Father's full name" class="wiz-input"
                            value="{{ old('father_name') }}" autocomplete="off">
                    </div>
                    <div class="wiz-field c5">
                        <label for="father_occupation">Father's Occupation</label>
                        <input type="text" name="father_occupation" id="father_occupation"
                            placeholder="e.g. Driver, Farmer, OFW" class="wiz-input"
                            value="{{ old('father_occupation') }}" autocomplete="off">
                    </div>
                    <div class="wiz-field c7">
                        <label for="mother_name">Mother's Name</label>
                        <input type="text" name="mother_name" id="mother_name"
                            placeholder="Mother's full name" class="wiz-input"
                            value="{{ old('mother_name') }}" autocomplete="off">
                    </div>
                    <div class="wiz-field c5">
                        <label for="mother_occupation">Mother's Occupation</label>
                        <input type="text" name="mother_occupation" id="mother_occupation"
                            placeholder="e.g. Housewife, Teacher, OFW" class="wiz-input"
                            value="{{ old('mother_occupation') }}" autocomplete="off">
                    </div>
                    <div class="wiz-field c7">
                        <label for="parent_address">Parent's Address</label>
                        <input type="text" name="parent_address" id="parent_address"
                            placeholder="Same as current address, if applicable" class="wiz-input"
                            value="{{ old('parent_address') }}" autocomplete="off">
                    </div>
                    <div class="wiz-field c5">
                        <label for="parent_contact">Parent's Contact</label>
                        <input type="text" name="parent_contact" id="parent_contact"
                            placeholder="09XXXXXXXXX" class="wiz-input"
                            value="{{ old('parent_contact') }}" autocomplete="off" inputmode="tel">
                    </div>
                </div>

                <details id="guardianDetails" class="wiz-guardian" {{ old('guardian_name') || old('guardian_contact') || $errors->hasAny(['guardian_name', 'guardian_contact']) ? 'open' : '' }}>
                    <summary class="wiz-guardian-toggle">No parent details? Add a guardian instead. <i data-lucide="chevron-down" aria-hidden="true"></i></summary>
                    <div class="wiz-grid">
                        <div class="wiz-field c7">
                            <label for="guardian_name">Guardian's Name</label>
                            <input type="text" name="guardian_name" id="guardian_name" class="wiz-input @error('guardian_name') wiz-invalid @enderror"
                                value="{{ old('guardian_name') }}" placeholder="Guardian's full name" maxlength="255" autocomplete="off">
                            @error('guardian_name')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                        </div>
                        <div class="wiz-field c5">
                            <label for="guardian_contact">Guardian's Contact Number</label>
                            <input type="tel" name="guardian_contact" id="guardian_contact" class="wiz-input @error('guardian_contact') wiz-invalid @enderror"
                                value="{{ old('guardian_contact') }}" placeholder="e.g. 09171234567" maxlength="50" autocomplete="off">
                            @error('guardian_contact')<span class="wiz-err-msg">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </details>

                <p class="wiz-group-label"><i data-lucide="clipboard-list"></i> Other Details</p>
                <div class="wiz-grid">
                    <div class="wiz-field c4">
                        <label for="occupation">Occupation</label>
                        <input type="text" name="occupation" id="occupation"
                            placeholder="e.g. Student, Employed, Self-employed" class="wiz-input"
                            value="{{ old('occupation') }}" autocomplete="organization-title">
                    </div>
                    <div class="wiz-field c4">
                        <label for="disability">Disability</label>
                        <input type="text" name="disability" id="disability"
                            placeholder="If applicable" class="wiz-input"
                            value="{{ old('disability') }}" autocomplete="off">
                    </div>
                    <div class="wiz-field c4 d-none" id="pwdIdField">
                        <label for="pwd_id">PWD ID Number</label>
                        <input type="text" name="pwd_id" id="pwd_id"
                            placeholder="PWD ID number, if applicable" class="wiz-input"
                            value="{{ old('pwd_id') }}" autocomplete="off">
                    </div>
                </div>

                </section>
                <div class="wiz-divider"></div>
                <h3 class="wiz-subsection-title">Supporting documents</h3>
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

                    {{-- Upload slots come from Admissions → Requirements. --}}
                    @foreach(\App\Models\AdmissionRequirement::APPLICANT_TYPES as $typeKey => $typeLabel)
                        <div class="document-section d-none" data-type="{{ $typeKey }}">
                            @if(($requirementsByType[$typeKey] ?? collect())->isNotEmpty())
                                <div class="wiz-inline-notice wiz-inline-notice--info" style="margin-bottom:16px">
                                    <i data-lucide="info"></i>
                                    <div><strong>{{ $typeLabel }}</strong> — upload what you have now. Anything not uploaded here can be added later in your admission portal.</div>
                                </div>
                                @foreach($requirementsByType[$typeKey] as $requirement)
                                    @include('public.application.partials.document-input', ['name' => $requirement->code, 'label' => $requirement->name, 'description' => $requirement->description, 'requirement' => $requirement])
                                @endforeach
                            @elseif($typeKey === 'cross_enrollee')
                                <div class="wiz-inline-notice wiz-inline-notice--info">
                                    <i data-lucide="info"></i>
                                    <div><strong>Cross-Enrollee:</strong> Requirements are subject to confirmation by the Registrar's Office.</div>
                                </div>
                            @else
                                <div class="wiz-inline-notice wiz-inline-notice--neutral">
                                    <i data-lucide="info"></i>
                                    <span>No document upload required for {{ $typeLabel }} applicants at this stage. The Registrar's Office will provide further instructions.</span>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- Marriage Certificate (and any other "married" requirement) --}}
                    <div id="marriageDocument" class="d-none" style="margin-top:20px">
                        @if($conditionalRequirements->isNotEmpty())
                            <div class="wiz-divider"></div>
                            <p class="wiz-group-label"><i data-lucide="bell-ring"></i> Marriage Certificate</p>
                            @foreach($conditionalRequirements as $requirement)
                                @include('public.application.partials.document-input', ['name' => $requirement->code, 'label' => $requirement->name, 'description' => $requirement->description, 'requirement' => $requirement])
                            @endforeach
                        @endif
                    </div>

                </div>{{-- end uploads tab panel --}}

                {{-- ── Tab Panel: Requirements Checklist ──────────── --}}
                <div class="doc-tab-panel" data-tab-panel="checklist" role="tabpanel">

                    <div id="physicalRequirementsCard" class="wiz-reqs physical-requirements-panel">
                        <div class="wiz-reqs-header">
                            <i data-lucide="folder-open"></i>
                            Physical Requirements to Bring
                        </div>
                        <p class="wiz-reqs-sub">Bring physical copies to the Registrar's Office, even if uploaded. Check items as you prepare them; this does not confirm submission.</p>
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

        {{-- â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
             ║  STEP 6 — REVIEW & SUBMIT                        ║
             â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• --}}
        <div class="wiz-panel" id="step-6">
            <div class="wiz-panel-header">
                <div class="wiz-panel-icon"><i data-lucide="clipboard-check"></i></div>
                <div>
                    <h2 class="wiz-panel-title">Review Your Application</h2>
                    <p class="wiz-panel-sub">Review your details before submitting.</p>
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

                {{-- Agreement, acknowledgment, and consent are separate and never pre-ticked. --}}
                <fieldset class="legal-acks" id="applicationAcknowledgement">
                    <legend class="wiz-subsection-title">Before you submit</legend>
                    <label class="legal-ack">
                        <input type="checkbox" name="ack_enrollment" id="ack_enrollment" value="1" required @checked(old('ack_enrollment'))>
                        <span>I have read and agree to the <a href="{{ route('legal.terms') }}" class="simple-ack-link" id="openTermsModal">Terms and Conditions</a> (version {{ $legal['terms']['version'] }}), and I confirm that the information I gave is true and complete.</span>
                    </label>
                    @error('ack_enrollment')<p class="wiz-err-msg">{{ $message }}</p>@enderror
                    <label class="legal-ack">
                        <input type="checkbox" name="acknowledge_privacy" id="acknowledge_privacy" value="1" required @checked(old('acknowledge_privacy'))>
                        <span>I have read and understood the <a href="{{ route('legal.privacy') }}" class="simple-ack-link" id="openPrivacyModal">Privacy Notice</a> (version {{ $legal['privacy']['version'] }}), which explains how DTC processes my personal information.</span>
                    </label>
                    @error('acknowledge_privacy')<p class="wiz-err-msg">{{ $message }}</p>@enderror
                    <div id="sensitiveConsentField" @unless(old('consent_sensitive') || $errors->has('consent_sensitive')) hidden @endunless>
                        <label class="legal-ack">
                            <input type="checkbox" name="consent_sensitive" id="consent_sensitive" value="1" @checked(old('consent_sensitive'))>
                            <span>I consent to DTC processing the optional sensitive information I chose to give — <span id="sensitiveFieldList">religion, civil status, disability, or PWD ID</span> — for the purposes in section 4 of the Privacy Notice. I may leave these fields blank instead, and I may withdraw this consent later.</span>
                        </label>
                        @error('consent_sensitive')<p class="wiz-err-msg">{{ $message }}</p>@enderror
                    </div>
                    <p class="wiz-err-msg" id="legalAckError" role="alert" hidden></p>
                </fieldset>

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
            <button type="button" class="wiz-btn wiz-btn--submit d-none" id="wizSubmit" aria-describedby="applicationAcknowledgement">
                Continue <i data-lucide="arrow-right"></i>
            </button>
        </div>

    </form>

    {{-- ── Submit Confirmation Modal ────────────────────────────────────── --}}

    {{-- Policy documents: the same text as /privacy and /terms. Closing a modal
         never ticks a box and keeps the application as it is. --}}
    <div class="privacy-modal-overlay" id="privacyModal" role="dialog" aria-modal="true" aria-labelledby="privacy-modal-title" hidden>
        <div class="privacy-modal legal-modal">
            <div class="privacy-modal-body legal-modal-body" tabindex="0">
                @include('legal.privacy', ['titleId' => 'privacy-modal-title'])
            </div>
            <div class="privacy-modal-footer">
                <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="legal-modal-link">Open in a new tab</a>
                <button type="button" class="wiz-btn wiz-btn--primary" id="closePrivacyModal">Close</button>
            </div>
        </div>
    </div>

    <div class="privacy-modal-overlay" id="termsModal" role="dialog" aria-modal="true" aria-labelledby="terms-modal-title" hidden>
        <div class="privacy-modal legal-modal">
            <div class="privacy-modal-body legal-modal-body" tabindex="0">
                @include('legal.terms', ['titleId' => 'terms-modal-title'])
            </div>
            <div class="privacy-modal-footer">
                <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="legal-modal-link">Open in a new tab</a>
                <button type="button" class="wiz-btn wiz-btn--primary" id="closeTermsModal">Close</button>
            </div>
        </div>
    </div>
</div>
@stop


@section('scripts')
<script>window.dtcApplicationTitleCaseFields = @json($titleCaseFields);</script>
<script>window.dtcApplicationEducationLevels = @json(\App\Support\ApplicationEducation::LEVELS);</script>
<script>
    /* Sections completed (and saved on the server) in this session. When the
       browser has no draft of its own, the saved answers are restored from it. */
    window.dtcApplicationProgress = @json($progress);
    window.dtcApplicationStepTitles = @json($stepTitles);
    (function () {
        try {
            var key = 'dtc_application_draft';
            var local = JSON.parse(localStorage.getItem(key) || '{}') || {};
            var saved = window.dtcApplicationProgress.data || {};
            if (Object.keys(local).length === 0 && Object.keys(saved).length) {
                var draft = Object.assign({}, saved);
                draft.address_area = saved.province_code || saved.city_code || '';
                draft.school_area = saved.school_province_code || saved.school_city_code || '';
                localStorage.setItem(key, JSON.stringify(draft));
            }
        } catch (e) {}
    })();
</script>
<script src="{{ asset_v('js/dtc-name-normalizer.js') }}" defer></script>
<script src="{{ asset_v('js/application-wizard.js') }}" defer></script>
<script src="{{ asset_v('js/dtc-combobox.js') }}" defer></script>
<script src="{{ asset_v('js/address-cascade.js') }}" defer></script>
<script src="{{ asset_v('js/education-background.js') }}" defer></script>
<script>
    /* Tell the wizard which step to open first: the section with the first
       server error, otherwise where the applicant left off (never a locked one).
       application-wizard.js calls this hook once it has set up the steps. */
    window._wizOnReady = function () {
        if (window._wizGoTo) {
            @if ($errors->any())
                window._wizGoTo(window._wizInitialStep ? window._wizInitialStep() : 1, true);
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
                window._wizGoTo(window._wizInitialStep ? window._wizInitialStep() : 1, true);
            @endif
        }
    };
</script>
@stop
