<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice(Request $request)
    {
        if (! $request->user()->email_verification_required) {
            return redirect()->route('login');
        }
        return response()->view('auth.verify-changed-email')->header('Cache-Control', 'no-store, private');
    }

    public function send(Request $request)
    {
        if ($request->user()->email_verification_required) {
            $request->user()->sendEmailVerificationNotification();
        }
        return back()->with('status', 'A verification link has been sent to your current email address.');
    }

    public function verify(EmailVerificationRequest $request)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $user = \App\Models\User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            // Recheck after locking in case another request changed the email.
            abort_unless(hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash')), 403);
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                event(new \Illuminate\Auth\Events\Verified($user));
            }
        });
        return redirect()->route('login')->with('success', 'Email verified. You can continue using your account.');
    }
}
