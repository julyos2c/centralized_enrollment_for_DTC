<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.pwa-head')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — DTC Nexus</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/auth-pages.css') }}">
</head>
<body class="auth-activation-page">

<div class="auth-card">

    {{-- ── Header ────────────────────────────────────────── --}}
    <div class="auth-card-header">
        <div class="auth-brand">
            <img src="{{ asset('images/DTC-LOGO.webp') }}" alt="DTC Logo">
            <div>
                <p class="auth-brand-name">DTC Nexus</p>
                <p class="auth-brand-sub">Danao Technological College</p>
            </div>
        </div>
        <p class="auth-card-tagline">
            Reset your <span>password.</span>
        </p>
    </div>

    {{-- ── Body ─────────────────────────────────────────── --}}
    <div class="auth-card-body">

        @if (session('status'))
            <div class="auth-alert auth-alert--success" role="status">
                <div>{{ session('status') }}<p class="auth-message-help">No email? Check your spam folder or try again later.</p></div>
            </div>
        @endif

        <h1 class="auth-card-title">Forgot password?</h1>
        <p class="auth-card-subtitle">
            Enter your account email.
        </p>

        <form method="POST" action="{{ route('password.email') }}" data-link-request>
            @csrf

            <div class="auth-field">
                <label for="email" class="auth-label">Email address</label>
                <div class="auth-input-wrap">
                    <i data-lucide="mail" class="auth-input-icon" aria-hidden="true"></i>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="auth-input @error('email') auth-input--error @enderror"
                        placeholder="Enter your registered email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                        autofocus
                    >
                </div>
                @error('email')
                    <div class="auth-alert auth-alert--error" style="margin-top:8px; margin-bottom:0;">
                        <i data-lucide="alert-circle"></i>
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <button type="submit" class="auth-btn" data-loading-text="Sending link…">
                Send reset link
            </button>
        </form>

        <div style="text-align:center; margin-bottom: 4px;">
            <a href="{{ route('landing') }}" class="auth-back">
                <i data-lucide="arrow-left"></i>
                Back to Sign In
            </a>
        </div>

    </div>

    {{-- ── Footer ───────────────────────────────────────── --}}
    <div class="auth-card-footer">
        <p class="auth-card-copyright">© {{ date('Y') }} Danao Technological College</p>
    </div>

</div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (window.lucide) lucide.createIcons();
        });
    </script>
<script src="{{ asset('js/auth-link-request.js') }}" defer></script>
</body>
</html>
