<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RequiredPasswordController extends Controller
{
    public function edit()
    {
        return view('auth.required-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => array_merge(\App\Support\SecuritySettings::passwordRules(), ['different:current_password']),
        ]);
        $user = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $user = \App\Models\User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($request->current_password, $user->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }
            $user->forceFill(['password' => Hash::make($request->password), 'must_change_password' => false])->save();
            return $user;
        });
        $request->session()->regenerate();
        $request->session()->put('auth_session_token', $user->auth_session_token);
        return redirect()->route('login')->with('success', 'Password changed.');
    }
}
