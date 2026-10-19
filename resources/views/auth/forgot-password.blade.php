<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — DTC Nexus</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/auth-pages.css') }}">
</head>
<body>

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
            <div class="auth-alert auth-alert--success">
                <i data-lucide="check-circle"></i>
                {{ session('status') }}
            </div>
        @endif

        <div class="auth-icon-badge">
            <i data-lucide="key"></i>
        </div>

        <h1 class="auth-card-title">Forgot Password?</h1>
        <p class="auth-card-subtitle">
            Enter the email address linked to your account and
            we'll send you a secure reset link.
        </p>

        <form method="POST" action="{{ route('password.email') }}">
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
                <i data-lucide="send" aria-hidden="true"></i>
                Send Reset Link
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
</body>
</html>
