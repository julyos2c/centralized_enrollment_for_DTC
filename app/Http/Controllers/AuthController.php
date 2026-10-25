<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function showLanding()
    {
        return view('auth.welcome');
    }

    public function showLogin()
    {
        return view('auth.welcome');
    }

    public function login(Request $request)
    {
        $email = mb_strtolower(trim((string) $request->input('email', '')));
        $ip = (string) ($request->ip() ?: 'unknown');
        $ipLimiterKey = 'login:ip:' . hash('sha256', $ip);
        $identityLimiterKey = 'login:identity:' . hash('sha256', $email . '|' . $ip);
        // IP-independent bucket: the client IP can be spoofed on direct-to-origin
        // requests, so an account still gets locked down regardless of source IP.
        $emailLimiterKey = 'login:email:' . hash('sha256', $email);
        $ipLimit = (int) config('services.login_rate_limit.per_ip_attempts', 300);
        $identityLimit = \App\Support\SecuritySettings::loginAttempts();
        $decaySeconds = (int) config('services.login_rate_limit.decay_seconds', 300);
        $emailDecaySeconds = (int) config('services.login_rate_limit.per_email_decay_seconds', 900);

        if (RateLimiter::tooManyAttempts($ipLimiterKey, $ipLimit)
            || RateLimiter::tooManyAttempts($identityLimiterKey, $identityLimit)
            || RateLimiter::tooManyAttempts($emailLimiterKey, $emailLimit)) {
            return back()
                ->withErrors(['email' => 'Too many login attempts. Please wait a few minutes and try again.'])
                ->onlyInput('email');
        }

        // Count attempts before CAPTCHA so provider failures cannot disable the limit.
        RateLimiter::hit($ipLimiterKey, $decaySeconds);
        RateLimiter::hit($identityLimiterKey, $decaySeconds);
        RateLimiter::hit($emailLimiterKey, $emailDecaySeconds);

        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);
        $credentials['email'] = $email;

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

        // Keep the IP-wide bucket, but clear this normalized identity's failures.
        RateLimiter::clear($identityLimiterKey);
        RateLimiter::clear($emailLimiterKey);

        // Prevent session fixation.
        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->status === 'locked' || $user->status === 'pending') {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Your account is ' . $user->status . '. Please contact the Administrator.',
            ]);
        }

        return redirect()->intended($this->redirectPath($user));
    }

    private function captchaRetryResponse()
    {
        return back()
            ->withErrors(['captcha' => 'CAPTCHA verification is temporarily unavailable. Please try again shortly.'])
            ->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }

    protected function redirectPath($user)
    {
        if ($user->hasRole('admin')) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('registrar')) {
            return route('registrar.dashboard');
        }

        if ($user->hasRole('cashier')) {
            return route('cashier.dashboard');
        }

        if ($user->hasAnyRole(['student', 'alumni', 'new_applicant'])) {
            return route('portal.dashboard');
        }

        return route('login');
    }
}
