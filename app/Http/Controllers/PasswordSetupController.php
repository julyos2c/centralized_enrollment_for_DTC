<?php

namespace App\Http\Controllers;

use App\Mail\ApplicationApproved;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class PasswordSetupController extends Controller
{
    /**
     * Show the one-time "set your password" form.
     *
     * Reached via the activation link sent in the ApplicationApproved email.
     * Checks token validity up front (without consuming it) so an applicant
     * with an expired link sees the expired state immediately, rather than
     * only after submitting the form.
     */
    public function showForm(Request $request, string $token)
    {
        $email = $request->query('email', '');

        $linkExpired = ! $this->tokenIsValid($email, $token);

        return view('auth.set-password', [
            'token'       => $token,
            'email'       => $email,
            'linkExpired' => $linkExpired,
        ]);
    }

    public function requestForm()
    {
        return view('auth.request-activation');
    }

    /**
     * Consume the token and set the account's password.
     *
     * Reuses Laravel's existing password broker (password_resets table +
     * config/auth.php 'passwords.users' settings) for all token/expiry
     * validation — no custom token logic is implemented here.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => \App\Support\SecuritySettings::passwordRules(),
        ]);

        $status = Password::broker('users')->reset(
            $validated,
            function ($user, $password) {
                $user->forceFill([
                    'password'          => Hash::make($password),
                    // Marks the account as fully activated. See note in
                    // resend() below for how this is used.
                    'email_verified_at' => now(),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('success', 'Password set. You can sign in now.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'password' => $this->friendlyBrokerMessage($status),
            ]);
    }

    /**
     * Handle a "Request New Activation Link" submission from the expired
     * activation-link state.
     *
     * Always returns the same generic response regardless of whether the
     * submitted email matches an approved, not-yet-activated application —
     * this is deliberate: it prevents using this endpoint to discover
     * whether a given email address belongs to an applicant, has already
     * activated their account, or doesn't exist in the system at all.
     *
     * A fresh token is always minted via Password::broker()->createToken()
     * for the eligible case — never reused or hand-generated. Since
     * password_resets is keyed by email, minting a new token for the same
     * user simply replaces any still-outstanding one; the old token was
     * already unusable (expired) by the time this runs.
     */
    public function resend(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $this->attemptResend($validated['email']);
        } catch (\Throwable $exception) {
            Log::warning('Activation link resend failed.', [
                'email_hash' => hash_hmac('sha256', strtolower(trim($validated['email'])), (string) config('app.key')),
            ]);
        }

        return back()->withInput($request->only('email'))->with(
            'success',
            'If your account is eligible, an activation link will arrive shortly.'
        );
    }

    /**
     * Looks up an approved, user-linked application for the given email and,
     * only if that account hasn't been activated yet, mints a fresh token
     * and resends the ApplicationApproved email. Silently does nothing for
     * every other case (no match, not approved, already activated) — the
     * caller (resend()) always shows the same message either way.
     */
    private function attemptResend(string $email): void
    {
        $application = Application::where('email', $email)
            ->where('status', 'approved')
            ->whereNotNull('user_id')
            ->latest('reviewed_at')
            ->first();

        if (! $application || ! $application->user) {
            return;
        }

        $user = $application->user;

        // Already activated (password.setup.store already ran successfully
        // for this account) — do not send another activation email.
        if ($user->email_verified_at !== null) {
            return;
        }

        $token = Password::broker('users')->createToken($user);

        $setupUrl = route('password.setup.show', [
            'token' => $token,
            'email' => $user->email,
        ]);

        Mail::to($application->email)->send(
            new ApplicationApproved(
                $application,
                $setupUrl,
                config('auth.passwords.users.expire', 60)
            )
        );
    }

    /**
     * Whether the given token is currently valid for the given email,
     * without consuming it.
     *
     * Deliberately collapses two distinct failure cases — "no such user"
     * and "token invalid/expired" — into a single false result, so this
     * check alone can never be used to determine whether a given email
     * address belongs to an applicant.
     */
    private function tokenIsValid(string $email, string $token): bool
    {
        if ($email === '' || $token === '') {
            return false;
        }

        $broker = Password::broker('users');

        $user = $broker->getUser(['email' => $email]);

        if (! $user) {
            return false;
        }

        return $broker->tokenExists($user, $token);
    }

    /**
     * Translate the broker's status constant into a message that doesn't
     * assume the applicant knows Laravel's internal terminology.
     */
    private function friendlyBrokerMessage(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN => 'This activation link is invalid or has already been used. Please contact the Registrar\'s Office for a new one.',
            Password::INVALID_USER => 'We could not find an account for this email address.',
            default => 'This activation link has expired. Please contact the Registrar\'s Office for a new one.',
        };
    }
}
