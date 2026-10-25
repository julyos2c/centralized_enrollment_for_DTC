<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.pwa-head')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Activation Link — DTC Nexus</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    @include('partials.auth-appearance')
</head>
<body class="auth-activation-page">
<div class="auth-card">
    <div class="auth-card-header">
        <div class="auth-brand">
            <img src="{{ asset('images/DTC-LOGO.webp') }}" alt="DTC Logo">
            <div>
                <p class="auth-brand-name">DTC Nexus</p>
                <p class="auth-brand-sub">Danao Technological College</p>
            </div>
        </div>
        <p class="auth-card-tagline">Activate your <span>account.</span></p>
    </div>

    <div class="auth-card-body">
        @if (session('success'))
            <div class="auth-alert auth-alert--success" role="status">
                <div>{{ session('success') }}<p class="auth-message-help">No email? Check your spam folder or try again later.</p></div>
            </div>
        @endif
        <h1 class="auth-card-title">Activate your account</h1>
        <p class="auth-card-subtitle">
            Enter the email used for your application.
        </p>

        <form method="POST" action="{{ route('password.activation.resend') }}" data-link-request>
            @csrf
            <div class="auth-field">
                <label for="email" class="auth-label">Email address</label>
                <div class="auth-input-wrap">
                    <i data-lucide="mail" class="auth-input-icon" aria-hidden="true"></i>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="auth-input"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                        autofocus
                    >
                </div>
                @error('email')
                    <div class="auth-alert auth-alert--error" style="margin-top:8px; margin-bottom:0;">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="auth-btn">
                Send activation link
            </button>
        </form>

        <div style="text-align:center; margin-bottom:4px;">
            <a href="{{ route('landing') }}" class="auth-back">
            <i data-lucide="arrow-left"></i>
            Back to Sign In</a>
        </div>
    </div>

    <div class="auth-card-footer">
        <p class="auth-card-copyright">&copy; {{ date('Y') }} Danao Technological College</p>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded', function () { if (window.lucide) lucide.createIcons(); });</script>
<script src="{{ asset('js/auth-link-request.js') }}" defer></script>
</body>
</html>
