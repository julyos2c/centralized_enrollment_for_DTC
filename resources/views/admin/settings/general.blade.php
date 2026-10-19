@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'Settings')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div>
            <h4 class="mb-0 font-weight-bold u-text" >Settings</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"  class="u-text-secondary">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Settings</li>
                </ol>
            </nav>
        </div>

        <button type="submit" form="general-settings-form" class="btn btn-primary">
            <i data-lucide="save" class="mr-1"></i> Save Changes
        </button>
    </div>
@endsection

@section('content')
<div class="row">

    {{-- ══ General Settings panels ═════════════════════════════ --}}
    <div class="col-12">


        <form id="general-settings-form" method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row">
                {{-- Institution Information --}}
                <div class="col-lg-7 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="settings-section-title">
                                <div class="settings-section-icon is-primary">
                                    <i data-lucide="landmark"></i>
                                </div>
                                <h3>Institution Information</h3>
                            </div>

                            <div class="form-group">
                                <label>Institution Name</label>
                                <input type="text" name="institution_name" class="form-control"
                                       value="{{ old('institution_name', $settings->institution_name ?? 'Danao Technological College') }}">
                            </div>

                            <div class="form-group">
                                <label>System Name</label>
                                <input type="text" name="system_name" class="form-control"
                                       value="{{ old('system_name', $settings->system_name ?? 'DTC Nexus — Admissions & Enrollment System') }}">
                            </div>

                            <div class="form-group">
                                <label>Tagline</label>
                                <input type="text" name="tagline" class="form-control"
                                       value="{{ old('tagline', $settings->tagline ?? 'Excellence in Education, Service to the Community.') }}">
                            </div>

                            <div class="form-group mb-0">
                                <label>Address</label>
                                <input type="text" name="address" class="form-control"
                                       value="{{ old('address', $settings->address ?? 'Sta. Rosa St., Danao City, Cebu, Philippines') }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- System Logo & Favicon --}}
                <div class="col-lg-5 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="settings-section-title">
                                <div class="settings-section-icon is-warning">
                                    <i data-lucide="image"></i>
                                </div>
                                <h3>System Logo &amp; Favicon</h3>
                            </div>

                            <label class="mb-2">System Logo</label>
                            <div class="d-flex align-items-center" style="gap:16px; margin-bottom:20px;">
                                <div class="settings-upload-box">
                                    <img src="{{ $settings->logo_url ?? asset('images/DTC-LOGO.webp') }}" alt="System Logo">
                                </div>
                                <div>
                                    <div style="font-size:11px; color:var(--dtc-text-muted); margin-bottom:8px;">
                                        Recommended size:<br>512 x 512px (PNG)
                                    </div>
                                    <label class="btn btn-sm btn-secondary mb-1" style="cursor:pointer;">
                                        <i data-lucide="arrow-up" class="mr-1"></i> Change Logo
                                        <input type="file" name="logo" accept="image/png" hidden>
                                    </label>
                                    <br>
                                    <button type="button" class="btn btn-link btn-sm p-0 dtc-remove-logo" style="color:#DC2626; font-size:12px;">
                                        <i data-lucide="trash-2" class="mr-1"></i> Remove
                                    </button>
                                </div>
                            </div>

                            <label class="mb-2">Favicon</label>
                            <div class="d-flex align-items-center" style="gap:16px;">
                                <div class="settings-upload-box" style="max-width:64px;">
                                    <img src="{{ $settings->favicon_url ?? asset('images/DTC-LOGO.webp') }}" alt="Favicon">
                                </div>
                                <div>
                                    <div style="font-size:11px; color:var(--dtc-text-muted); margin-bottom:8px;">
                                        Recommended size:<br>32 x 32px (PNG)
                                    </div>
                                    <label class="btn btn-sm btn-secondary mb-1" style="cursor:pointer;">
                                        <i data-lucide="arrow-up" class="mr-1"></i> Change Favicon
                                        <input type="file" name="favicon" accept="image/png" hidden>
                                    </label>
                                    <br>
                                    <button type="button" class="btn btn-link btn-sm p-0 dtc-remove-favicon" style="color:#DC2626; font-size:12px;">
                                        <i data-lucide="trash-2" class="mr-1"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- System Preferences --}}
                <div class="col-lg-7 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="settings-section-title">
                                <div class="settings-section-icon is-success">
                                    <i data-lucide="sliders-horizontal"></i>
                                </div>
                                <h3>System Preferences</h3>
                            </div>

                            <div class="form-group">
                                <label>Default Language</label>
                                <div  class="u-text-xs-hint">Select the default system language.</div>
                                <select name="default_language" class="form-control">
                                    <option value="en" {{ ($settings->default_language ?? 'en') === 'en' ? 'selected' : '' }}>English</option>
                                    <option value="fil" {{ ($settings->default_language ?? '') === 'fil' ? 'selected' : '' }}>Filipino</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Default Timezone</label>
                                <div  class="u-text-xs-hint">Select the default timezone.</div>
                                <select name="default_timezone" class="form-control">
                                    <option value="Asia/Manila" {{ ($settings->default_timezone ?? 'Asia/Manila') === 'Asia/Manila' ? 'selected' : '' }}>(GMT+08:00) Asia/Manila</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Date Format</label>
                                <div  class="u-text-xs-hint">Choose the default date format.</div>
                                <select name="date_format" class="form-control">
                                    <option value="m/d/Y"  {{ ($settings->date_format ?? 'm/d/Y')  === 'm/d/Y'  ? 'selected' : '' }}>MM/DD/YYYY</option>
                                    <option value="d/m/Y"  {{ ($settings->date_format ?? '')        === 'd/m/Y'  ? 'selected' : '' }}>DD/MM/YYYY</option>
                                    <option value="Y-m-d"  {{ ($settings->date_format ?? '')        === 'Y-m-d'  ? 'selected' : '' }}>YYYY-MM-DD</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Time Format</label>
                                <div  class="u-text-xs-hint">Choose the default time format.</div>
                                <select name="time_format" class="form-control">
                                    <option value="12" {{ ($settings->time_format ?? '12') === '12' ? 'selected' : '' }}>12-hour (AM/PM)</option>
                                    <option value="24" {{ ($settings->time_format ?? '')   === '24' ? 'selected' : '' }}>24-hour</option>
                                </select>
                            </div>

                            <div class="form-group mb-0">
                                <label>Items Per Page</label>
                                <div  class="u-text-xs-hint">Set default number of items in tables.</div>
                                <select name="items_per_page" class="form-control">
                                    @foreach (['10', '25', '50', '100'] as $n)
                                        <option value="{{ $n }}" {{ ($settings->items_per_page ?? '10') === $n ? 'selected' : '' }}>{{ $n }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- System Information (read-only) --}}
                <div class="col-lg-5 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="settings-section-title">
                                <div class="settings-section-icon is-purple">
                                    <i data-lucide="info"></i>
                                </div>
                                <h3>System Information</h3>
                            </div>

                            <div class="settings-info-row">
                                <span class="label">Current Version</span>
                                <span class="value">{{ $systemInfo['version'] ?? 'v1.0.0' }}</span>
                            </div>
                            <div class="settings-info-row">
                                <span class="label">Environment</span>
                                <span class="badge badge-success">{{ ucfirst(app()->environment()) }}</span>
                            </div>
                            <div class="settings-info-row">
                                <span class="label">Last Updated</span>
                                <span class="value">{{ $systemInfo['last_updated'] ?? now()->format('M d, Y h:i A') }}</span>
                            </div>
                            <div class="settings-info-row">
                                <span class="label">Database</span>
                                <span class="value">{{ $systemInfo['database'] ?? 'MySQL' }}</span>
                            </div>
                            <div class="settings-info-row">
                                <span class="label">PHP Version</span>
                                <span class="value">{{ PHP_VERSION }}</span>
                            </div>
                            <div class="settings-info-row">
                                <span class="label">Server</span>
                                <span class="value">{{ $_SERVER['SERVER_SOFTWARE'] ?? 'Apache' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Maintenance Mode --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-body d-flex align-items-center justify-content-between flex-wrap" style="gap:16px;">
                            <div class="d-flex align-items-center" style="gap:14px;">
                                <div class="settings-section-icon is-warning">
                                    <i data-lucide="wrench"></i>
                                </div>
                                <div>
                                    <h3 style="font-size:15px; font-weight:700; color:var(--dtc-text); margin:0;">Maintenance Mode</h3>
                                    <p style="font-size:12px; color:var(--dtc-text-secondary); margin:2px 0 0; max-width:520px;">
                                        Enable maintenance mode to restrict access to the system while performing updates.
                                        Only administrators will be able to access the system.
                                    </p>
                                </div>
                            </div>

                            <label class="dtc-toggle">
                                <input type="checkbox" name="maintenance_mode" value="1"
                                       {{ ($settings->maintenance_mode ?? '0') === '1' ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

            </div>
        </form>

        {{-- Standalone removal forms (kept outside #general-settings-form to avoid nested <form> tags) --}}
        <form id="remove-logo-form" method="POST" action="{{ route('admin.settings.logo.remove') }}" style="display:none;">
            @csrf
            @method('DELETE')
        </form>
        <form id="remove-favicon-form" method="POST" action="{{ route('admin.settings.favicon.remove') }}" style="display:none;">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>

@push('css')
<style>
/* Disabled (coming soon) tab items */
.settings-tab-disabled {
    opacity: 0.45;
    cursor: not-allowed;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 12px;
    margin-bottom: 4px;
}
</style>
@endpush

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    /* Upgrade native confirm() buttons to use DTC confirm modal.
       We set data-dtc-confirm attributes at runtime so the logo/favicon
       buttons are already in the DOM when dtc-feedback.js processes clicks. */
    var logoBtn = document.querySelector('.dtc-remove-logo');
    if (logoBtn) {
        logoBtn.setAttribute('data-dtc-confirm', '');
        logoBtn.setAttribute('data-dtc-confirm-title', 'Remove Logo?');
        logoBtn.setAttribute('data-dtc-confirm-message', 'The system logo will be removed and reverted to the default. Are you sure?');
        logoBtn.setAttribute('data-dtc-confirm-ok', 'Remove Logo');
        logoBtn.setAttribute('data-dtc-confirm-type', 'warning');
        logoBtn.setAttribute('data-dtc-confirm-form', '#remove-logo-form');
    }

    var faviconBtn = document.querySelector('.dtc-remove-favicon');
    if (faviconBtn) {
        faviconBtn.setAttribute('data-dtc-confirm', '');
        faviconBtn.setAttribute('data-dtc-confirm-title', 'Remove Favicon?');
        faviconBtn.setAttribute('data-dtc-confirm-message', 'The favicon will be removed and reverted to the default. Are you sure?');
        faviconBtn.setAttribute('data-dtc-confirm-ok', 'Remove Favicon');
        faviconBtn.setAttribute('data-dtc-confirm-type', 'warning');
        faviconBtn.setAttribute('data-dtc-confirm-form', '#remove-favicon-form');
    }
});
</script>
@endpush
@endsection