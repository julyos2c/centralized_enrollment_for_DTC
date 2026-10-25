<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Show the "Forgot Password" request form.
     * GET /forgot-password
     */
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send the password reset link email.
     * POST /forgot-password
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We always show the same success message regardless of whether
        // the email exists — this prevents user enumeration.
        Password::sendResetLink($request->only('email'));

        return back()->withInput($request->only('email'))->with(
            'status',
            'If your email is registered, a reset link will arrive shortly.'
        );
    }
}
