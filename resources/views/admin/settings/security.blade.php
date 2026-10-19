@extends('adminlte::page')
@include('partials.navbar')

@section('title', 'System Security')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
        <div>
            <h4 class="mb-0 font-weight-bold u-text">System Security</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="u-text-secondary">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.settings.index') }}" class="u-text-secondary">Settings</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Security</li>
                </ol>
            </nav>
        </div>

        <button type="submit" form="security-settings-form" class="btn btn-primary">
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

        <form id="security-settings-form" method="POST" action="{{ route('admin.settings.security.update') }}">
            @csrf
            @method('PUT')

            <div class="row">

                {{-- Password Policy --}}
                <div class="col-lg-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="settings-section-title">
                                <div class="settings-section-icon is-primary">
                                    <i data-lucide="key"></i>
                                </div>
                                <h3>Password Policy</h3>
                            </div>

                            <div class="form-group">
                                <label>Minimum Password Length</label>
                                <input type="number" name="min_password_length" class="form-control" min="6" max="32"
                                       value="{{ old('min_password_length', $settings->min_password_length ?? 8) }}">
                                <small class="form-text" style="color:var(--dtc-text-muted);">
                                    Applies to new accounts and password resets. Between 6 and 32 characters.
                                </small>
                            </div>

                            <div class="d-flex align-items-center justify-content-between flex-wrap mt-3" style="gap:16px;">
                                <div style="max-width:320px;">
                                    <h3 style="font-size:14px; font-weight:700; color:var(--dtc-text); margin:0;">
                                        Require Special Characters
                                    </h3>
                                    <p style="font-size:12px; color:var(--dtc-text-secondary); margin:4px 0 0;">
                                        Passwords must include at least one symbol (e.g. !, @, #, $).
                                    </p>
                                </div>

                                <label class="dtc-toggle">
                                    <input type="checkbox" name="require_special_chars" value="1"
                                           {{ old('require_special_chars', $settings->require_special_chars ?? '0') === '1' ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Session & Login --}}
                <div class="col-lg-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="settings-section-title">
                                <div class="settings-section-icon is-warning">
                                    <i data-lucide="user-check"></i>
                                </div>
                                <h3>Session &amp; Login</h3>
                            </div>

                            <div class="form-group">
                                <label>Session Timeout (minutes)</label>
                                <input type="number" name="session_timeout" class="form-control" min="5"
                                       value="{{ old('session_timeout', $settings->session_timeout ?? 30) }}">
                                <small class="form-text" style="color:var(--dtc-text-muted);">
                                    Users are signed out automatically after this many minutes of inactivity.
                                </small>
                            </div>

                            <div class="form-group mb-0">
                                <label>Max Login Attempts</label>
                                <input type="number" name="max_login_attempts" class="form-control" min="3"
                                       value="{{ old('max_login_attempts', $settings->max_login_attempts ?? 5) }}">
                                <small class="form-text" style="color:var(--dtc-text-muted);">
                                    Accounts are temporarily locked after this many failed sign-in attempts.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection
