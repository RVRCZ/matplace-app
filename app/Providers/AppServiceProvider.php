<?php

namespace App\Providers;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Calculation\PriceEngine;
use App\Domain\Calculation\RoughEstimator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MaterialCatalog::class, fn () => new MaterialCatalog(config('materials')));
        $this->app->singleton(PriceEngine::class, fn () => new PriceEngine(config('pricing')));
        $this->app->singleton(RoughEstimator::class, fn ($app) => new RoughEstimator(config('pricing.rough'), $app->make(MaterialCatalog::class)));
    }

    public function boot(): void
    {
        // Anonymous use is unlimited by design; these limits only stop abuse of the heavy endpoints.
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('calculations', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
    }
}
