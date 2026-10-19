<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
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

        // ── Verify reCAPTCHA (skip on local) ─────────────────────────
        $recaptcha = $request->input('g-recaptcha-response');

        if (!$recaptcha) {
            return back()
                ->withErrors(['captcha' => 'Please complete the reCAPTCHA verification.'])
                ->onlyInput('email');
        }

        // Only verify with Google when NOT on localhost
        if (!app()->environment('local')) {
            try {
                $verify = \Illuminate\Support\Facades\Http::timeout(5)
                    ->asForm()
                    ->post('https://www.google.com/recaptcha/api/siteverify', [
                        'secret'   => env('RECAPTCHA_SECRET_KEY'),
                        'response' => $recaptcha,
                        'remoteip' => $request->ip(),
                    ]);

                if (!$verify->json('success')) {
                    return back()
                        ->withErrors(['captcha' => 'reCAPTCHA verification failed. Please try again.'])
                        ->onlyInput('email');
                }
            } catch (\Exception $e) {
                // If verification fails due to network, allow login (log the error)
                \Log::warning('reCAPTCHA verification failed: ' . $e->getMessage());
            }
        }

        // ── Authenticate ──────────────────────────────────────────────
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

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