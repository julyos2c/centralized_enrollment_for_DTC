<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Laravel passes 'role:registrar|admin' as a single string argument.
        // Split each argument on '|' so hasAnyRole() receives individual role names.
        $roles = collect($roles)
            ->flatMap(fn($r) => explode('|', $r))
            ->all();

        if (! $request->user() || ! $request->user()->hasAnyRole($roles)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}