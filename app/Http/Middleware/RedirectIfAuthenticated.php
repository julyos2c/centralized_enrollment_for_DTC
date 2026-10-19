<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::user();

                if ($user->hasRole('admin')) {
                    return redirect()->route('admin.dashboard');
                }
                if ($user->hasRole('registrar')) {
                    return redirect()->route('registrar.dashboard');
                }
                if ($user->hasRole('cashier')) {
                    return redirect()->route('cashier.dashboard');
                }
                if ($user->hasRole(['student', 'alumni', 'new_applicant'])) {
                    return redirect()->route('portal.dashboard');
                }

                return redirect('/');
            }
        }

        return $next($request);
    }
}