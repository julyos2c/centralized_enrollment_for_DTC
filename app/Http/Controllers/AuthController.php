<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Failed attempts allowed per email + IP, and per email from any IP.
    const MAX_ATTEMPTS_PER_IP    = 5;
    const DECAY_SECONDS_PER_IP   = 60;
    const MAX_ATTEMPTS_PER_EMAIL = 20;
    const DECAY_SECONDS_PER_EMAIL = 900;

    public function showLanding()
    {
        return view('auth.landing');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // ── Throttle (bot protection is handled at the Cloudflare edge) ──
        $email    = Str::lower($credentials['email']);
        $ipKey    = 'login:' . $email . '|' . $request->ip();
        $emailKey = 'login-email:' . $email;

        foreach ([$ipKey => self::MAX_ATTEMPTS_PER_IP, $emailKey => self::MAX_ATTEMPTS_PER_EMAIL] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
                return back()
                    ->withErrors(['email' => "Too many login attempts. Please try again in {$minutes} minute(s)."])
                    ->onlyInput('email');
            }
        }

        // ── Authenticate ──────────────────────────────────────────────
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($ipKey, self::DECAY_SECONDS_PER_IP);
            RateLimiter::hit($emailKey, self::DECAY_SECONDS_PER_EMAIL);

            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($ipKey);
        RateLimiter::clear($emailKey);
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

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('landing');
    }

    protected function redirectPath($user)
    {
        if ($user->hasRole('admin'))                                    return route('admin.dashboard');
        if ($user->hasRole('registrar'))                                return route('registrar.dashboard');
        if ($user->hasRole('cashier'))                                  return route('cashier.dashboard');
        if ($user->hasAnyRole(['student', 'alumni', 'new_applicant']))  return route('portal.dashboard');
        return route('login');
    }
}