<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route guard: `role:printer`. The user must be logged in and have the role switched on. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('login'));
        }
        if (! $user->hasRole($role)) {
            return redirect()->route('account')->with('error', __('account.role_required.'.$role));
        }

        return $next($request);
    }
}
