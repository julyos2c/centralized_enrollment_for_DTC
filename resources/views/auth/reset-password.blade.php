<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — DTC Nexus</title>
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
            Create a new <span>password.</span>
        </p>
    </div>

    {{-- ── Body ─────────────────────────────────────────── --}}
    <div class="auth-card-body">

        @if ($errors->any())
            <div class="auth-alert auth-alert--error">
                <i data-lucide="alert-circle"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <div class="auth-icon-badge">
            <i data-lucide="lock"></i>
        </div>

        <h1 class="auth-card-title">Set New Password</h1>
        <p class="auth-card-subtitle">
            Resetting password for
            <strong style="color:#1E293B;">{{ $email }}</strong>
        </p>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            {{-- New password --}}
            <div class="auth-field">
                <label for="password" class="auth-label">New Password</label>
                <div class="auth-input-wrap">
                    <i data-lucide="lock" class="auth-input-icon" aria-hidden="true"></i>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="auth-input"
                        placeholder="At least 8 characters"
                        autocomplete="new-password"
                        minlength="8"
                        required
                        autofocus
                    >
                    <button type="button" class="auth-eye" onclick="togglePw('password', this)" aria-label="Show password">
                        <i data-lucide="eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            {{-- Confirm password --}}
            <div class="auth-field">
                <label for="password_confirmation" class="auth-label">Confirm Password</label>
                <div class="auth-input-wrap">
                    <i data-lucide="lock" class="auth-input-icon" aria-hidden="true"></i>
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        class="auth-input"
                        placeholder="Re-enter your password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                    <button type="button" class="auth-eye" onclick="togglePw('password_confirmation', this)" aria-label="Show password">
                        <i data-lucide="eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="auth-btn" data-loading-text="Resetting…">
                <i data-lucide="check" aria-hidden="true"></i>
                Reset Password
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
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    const hidden = input.type === 'password';
    input.type = hidden ? 'text' : 'password';
    icon.classList.toggle('fa-eye', !hidden);
    icon.classList.toggle('fa-eye-slash', hidden);
    btn.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
}
</script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (window.lucide) lucide.createIcons();
        });
    </script>
</body>
</html>
