<!DOCTYPE html>
<html lang="en"><head>
    @include('partials.pwa-head')<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Set up portal account — DTC Nexus</title>@include('partials.auth-appearance')</head>
<body class="auth-activation-page"><main class="auth-card"><header class="auth-card-header"><div class="auth-brand"><img src="{{ asset('images/DTC-LOGO.webp') }}" alt="DTC Logo"><div><p class="auth-brand-name">DTC Nexus</p><p class="auth-brand-sub">Danao Technological College</p></div></div><p class="auth-card-tagline">Set up your <span>portal account.</span></p></header>
<section class="auth-card-body"><h1 class="auth-card-title">Choose your password</h1>
@if ($valid)
    <p class="auth-card-subtitle">Create a password to verify your email and activate your account.</p>
    <form data-password-icon-toggles class="portal-activation-form" method="POST" action="{{ route('portal.activation.store') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
        <div class="auth-field"><label class="auth-label" for="password">New password</label><div class="portal-password-input"><svg class="portal-password-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/></svg><input id="password" name="password" type="password" class="auth-input @error('password') auth-input--error @enderror" placeholder="At least 8 characters" required minlength="8" autocomplete="new-password"></div></div>
        <div class="auth-field"><label class="auth-label" for="password_confirmation">Confirm password</label><div class="portal-password-input"><svg class="portal-password-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/></svg><input id="password_confirmation" name="password_confirmation" type="password" class="auth-input @error('password_confirmation') auth-input--error @enderror" placeholder="Re-enter your password" required minlength="8" autocomplete="new-password"></div></div>
        @error('token')<div class="auth-alert auth-alert--error" role="alert">{{ $message }}</div>@enderror
        @error('password')<div class="auth-alert auth-alert--error" role="alert">{{ $message }}</div>@enderror
        <button class="auth-btn" type="submit">Set password</button>
    </form>
@else
    <div class="auth-alert auth-alert--error" role="alert">This account setup link is invalid, expired, or no longer available. Contact the Registrar for assistance.</div>
@endif
</section></main>@include('components.dtc.password-guidance')
</body></html>
