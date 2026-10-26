<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify your email | DTC Nexus</title>
    @include('partials.pwa-head')
    @include('partials.auth-appearance')
</head>
<body class="auth-activation-page auth-verification-page">
<main class="auth-card" aria-labelledby="verification-title">
    <header class="auth-card-header">
        <div class="auth-brand">
            <img src="{{ asset('images/DTC-LOGO.webp') }}" alt="DTC Logo">
            <div><p class="auth-brand-name">DTC Nexus</p><p class="auth-brand-sub">Danao Technological College</p></div>
        </div>
        <p class="auth-card-tagline">Keep your <span>account secure.</span></p>
    </header>
    <section class="auth-card-body">
        <h1 class="auth-card-title" id="verification-title">Verify your new email</h1>
        <p class="auth-card-subtitle">We need to confirm your new email before you continue to your account.</p>
        <p class="auth-account-email">{{ auth()->user()->email }}</p>
        @if(session('status'))
            <div class="auth-alert auth-alert--success" role="status">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('verification.send') }}" data-link-request>
            @csrf
            <button type="submit" class="auth-btn">Send verification link</button>
        </form>
        <p class="auth-message-help auth-verification-help">Open the link while signed in to this account. Check your spam folder if the email hasn't arrived.</p>
        <div class="auth-secondary-actions">
            <a class="auth-secondary-btn" href="{{ route(auth()->user()->hasAnyRole(['student', 'alumni', 'new_applicant']) ? 'portal.profile.show' : 'profile.show') }}">Review email address</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="auth-secondary-btn">Log out</button>
            </form>
        </div>
    </section>
    <footer class="auth-card-footer"><p class="auth-card-copyright">&copy; {{ date('Y') }} Danao Technological College</p></footer>
</main>
<script src="{{ asset_v('js/auth-link-request.js') }}" defer></script>
</body>
</html>
