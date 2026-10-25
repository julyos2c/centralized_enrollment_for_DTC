<?php

namespace App\Http\Controllers;

use App\Services\PortalAccountProvisioningService;
use Illuminate\Http\Request;

class PortalActivationController extends Controller
{
    public function show(string $token)
    {
        $hash = hash('sha256', $token);
        $invitation = \App\Models\PortalAccountInvitation::where('token_hash', $hash)->first();
        $valid = $invitation && hash_equals((string) $invitation->token_hash, $hash)
            && $invitation->status === 'pending' && $invitation->delivered_at
            && $invitation->expires_at && $invitation->expires_at->isFuture()
            && ! $invitation->consumed_at && ! $invitation->revoked_at;

        return response()->view('auth.portal-activation', ['valid' => (bool) $valid, 'token' => $valid ? $token : ''])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, PortalAccountProvisioningService $provisioning)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'password' => \App\Support\SecuritySettings::passwordRules(),
        ]);

        if (! $provisioning->activate($data['token'], $data['password'])) {
            return back()->withErrors(['token' => 'This account setup link is invalid, expired, or no longer available.']);
        }

        return redirect()->route('login')->with('success', 'Your portal account is active. You may now sign in.');
    }
}
