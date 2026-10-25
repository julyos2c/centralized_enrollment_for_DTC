<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnforceAccountSession
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $user = $request->user()->fresh();
            $blocked = ! $user || $user->status !== 'active';
            $revoked = $user && $request->hasSession() && ! hash_equals((string) $user->auth_session_token, (string) $request->session()->get('auth_session_token'));
            if ($blocked || $revoked) {
                if ($request->hasSession()) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
                if ($request->expectsJson()) {
                    return response()->json(['message' => $blocked ? 'This account is not active.' : 'Your session was revoked. Please sign in again.'], $blocked ? 403 : 401);
                }
                return redirect()->route('login')->withErrors(['email' => 'Please sign in again. Your account or session has changed.']);
            }
            Auth::setUser($user);
            if ($request->hasSession()) {
                $lastActivity = $request->session()->get('last_user_activity_at');
                $idleLimit = max(1, (int) config('session.lifetime', 120)) * 60;
                if (is_numeric($lastActivity) && now()->timestamp - (int) $lastActivity >= $idleLimit) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    if ($request->expectsJson()) {
                        return response()->json(['message' => 'Your session expired due to inactivity. Please sign in again.'], 401);
                    }
                    return redirect()->route('login')->withErrors(['email' => 'Your session expired due to inactivity. Please sign in again.']);
                }
                // Genuine authenticated HTTP requests renew inactivity. Background
                // polling never renews this deadline, even if Laravel saves its session.
                // Legacy sessions initialize the marker once on their first request.
                if ($lastActivity === null || !$request->routeIs('notifications.poll', 'cashier.notifications.poll')) {
                    $request->session()->put('last_user_activity_at', now()->timestamp);
                }
            }
            if ($user->must_change_password && ! $request->routeIs('account.password.*', 'logout', 'logout.confirm')) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Change your temporary password before continuing.', 'redirect_url' => route('account.password.edit')], 403);
                }
                return redirect()->route('account.password.edit');
            }
        }
        $response = $next($request);
        if ($request->user()) $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }
}
