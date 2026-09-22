<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route guard: `feature:marketplace`. A switched-off part of the product answers 404, routes and code stay in place. */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(config('features.'.$feature), 404);

        return $next($request);
    }
}
