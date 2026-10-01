<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account with an unverified e-mail can do everything except what costs money or publishes something:
 * ordering a print, topping up credit, switching the designer profile on. Google and Facebook accounts are
 * verified from the start.
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['error' => 'email_unverified', 'message' => __('user.verify.needed')], 403)
                : redirect()->back(fallback: route('account'))->with('error', __('user.verify.needed'));
        }

        return $next($request);
    }
}
