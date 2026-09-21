<?php

namespace App\Http\Middleware;

use App\Models\FarmAgent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Agent API guard: `Authorization: Bearer <token>`; the agent is put on the request as the attribute `farm_agent`. */
class AuthenticateFarmAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $agent = FarmAgent::findByToken($request->bearerToken());
        if (! $agent) {
            return response()->json(['error' => 'unauthorized'], 401);
        }
        $request->attributes->set('farm_agent', $agent);

        return $next($request);
    }
}
