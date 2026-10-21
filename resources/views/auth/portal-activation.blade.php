<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Set up portal account — DTC EMS</title><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/auth-pages.css') }}"></head>
<body><main class="auth-card"><header class="auth-card-header"><div class="auth-brand"><img src="{{ asset('images/DTC-LOGO.webp') }}" alt="DTC Logo"><div><p class="auth-brand-name">DTC EMS</p><p class="auth-brand-sub">Danao Technological College</p></div></div><p class="auth-card-tagline">Set up your <span>portal account.</span></p></header>
<section class="auth-card-body"><h1 class="auth-card-title">Choose your password</h1>
@if ($valid)
    <p class="auth-card-subtitle">Completing this form verifies this email address and activates your portal account.</p>
    <form method="POST" action="{{ route('portal.activation.store') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
        <div class="auth-field"><label class="auth-label" for="password">New password</label><input id="password" name="password" type="password" class="auth-input" required minlength="8" autocomplete="new-password"></div>
        <div class="auth-field"><label class="auth-label" for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" class="auth-input" required minlength="8" autocomplete="new-password"></div>
        @error('token')<div class="auth-alert auth-alert--error" role="alert">{{ $message }}</div>@enderror
        @error('password')<div class="auth-alert auth-alert--error" role="alert">{{ $message }}</div>@enderror
        <button class="auth-btn" type="submit">Set password</button>
    </form>
@else
    <div class="auth-alert auth-alert--error" role="alert">This account setup link is invalid, expired, or no longer available. Contact the Registrar for assistance.</div>
@endif
</section></main></body></html>
