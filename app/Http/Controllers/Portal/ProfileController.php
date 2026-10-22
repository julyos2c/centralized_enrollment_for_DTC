<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Show the profile settings page.
     */
    public function show()
    {
        $user           = Auth::user();
        $studentProfile = $user->studentProfile;

        return view('portal.profile.show', compact('user', 'studentProfile'))
            ->with('profileRoutePrefix', $this->routePrefix($user));
    }

    /**
     * Update personal information (name, email) and optional avatar.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user     = Auth::user();
        $fillable = $request->only(['name', 'email']);

        if ($request->hasFile('avatar')) {
            // Delete the old avatar before writing the new one.
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $fillable['avatar'] = $request->file('avatar')
                ->store('avatars', 'public');
        }

        $user->update($fillable);

        $redirectRoute = $request->boolean('return_to_dashboard')
            && $request->hasFile('avatar')
            && $user->hasAnyRole(['student', 'alumni', 'new_applicant'])
                ? 'portal.dashboard'
                : $this->routePrefix($user) . '.show';

        return redirect()->route($redirectRoute)
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password'      => ['required'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'The current password is incorrect.'])
                ->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        $user->update(['password' => Hash::make($request->password)]);
        $request->session()->regenerate();
        $request->session()->put('auth_session_token', $user->auth_session_token);

        return redirect()->route($this->routePrefix($user) . '.show')
            ->with('success', 'Password changed successfully.');
    }

    /**
     * Remove the user's avatar and revert to the initials fallback.
     */
    public function removeAvatar()
    {
        $user = Auth::user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return redirect()->route($this->routePrefix($user) . '.show')
            ->with('success', 'Profile photo removed.');
    }

    /** Return the matching route namespace for portal and staff profiles. */
    private function routePrefix($user): string
    {
        return $user->hasAnyRole(['student', 'alumni', 'new_applicant'])
            ? 'portal.profile'
            : 'profile';
    }
}
