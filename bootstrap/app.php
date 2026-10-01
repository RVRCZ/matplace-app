<?php

use App\Http\Middleware\AuthenticateFarmAgent;
use App\Http\Middleware\EnsureAnonymousSession;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SetLocale;
use App\Models\AnonymousSession;
use App\Support\Locales;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        // the language must be known before "auth" sends a guest to the login page of that language
        $middleware->prependToPriorityList(AuthenticatesRequests::class, SetLocale::class);
        // lang_seen only says "this browser has been here": a plain value, readable without the app key
        $middleware->encryptCookies(except: [Locales::SEEN_COOKIE]);
        $middleware->alias(['role' => EnsureRole::class, 'farm.agent' => AuthenticateFarmAgent::class, 'feature' => EnsureFeature::class, 'verified.email' => EnsureEmailVerified::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        // JSON API used by the calculator page (same-origin, cookie session); CSRF is enforced by SameSite cookies.
        $middleware->validateCsrfTokens(except: ['api/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // an address nothing answers to never reached SetLocale: the "not found" page still speaks the language of its prefix
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->route()) {
                app()->setLocale(Locales::fromPath($request->path()));
            }

            return null;
        });
    })->create();
