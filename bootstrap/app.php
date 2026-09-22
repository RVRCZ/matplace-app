<?php

use App\Http\Middleware\AuthenticateFarmAgent;
use App\Http\Middleware\EnsureAnonymousSession;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SetLocale;
use App\Models\AnonymousSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // farm-agent API and payment webhooks: stateless, outside the web group
        then: fn () => Route::middleware(SubstituteBindings::class)->group(__DIR__.'/../routes/agent.php'),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            EnsureAnonymousSession::class,
        ]);
        // mp_sid is a plain random token (never encrypted) so the value survives across app-key rotations and tests
        $middleware->encryptCookies(except: [AnonymousSession::COOKIE]);
        $middleware->alias(['role' => EnsureRole::class, 'farm.agent' => AuthenticateFarmAgent::class, 'feature' => \App\Http\Middleware\EnsureFeature::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        // JSON API used by the calculator page (same-origin, cookie session); CSRF is enforced by SameSite cookies.
        $middleware->validateCsrfTokens(except: ['api/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
