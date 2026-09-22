<?php

namespace App\Providers;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Calculation\PriceEngine;
use App\Domain\Calculation\RoughEstimator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MaterialCatalog::class, fn () => new MaterialCatalog(config('materials')));
        $this->app->singleton(PriceEngine::class, fn () => new PriceEngine(config('pricing')));
        $this->app->singleton(RoughEstimator::class, fn ($app) => new RoughEstimator(config('pricing.rough'), $app->make(MaterialCatalog::class)));
        // scoped: the admin's overrides are read once per request / queue job, never kept across them
        $this->app->scoped(\App\Domain\Farm\FarmSettings::class);
    }

    public function boot(): void
    {
        // Anonymous use is unlimited by design; these limits only stop abuse of the heavy endpoints.
        // Staging safety: real people were imported from the legacy site. Until launch every outgoing mail goes to one inbox.
        if ($to = config('mail.always_to')) {
            Mail::alwaysTo($to);
        }

        // product switches live in farm_settings so the admin flips them at /admin/farm/settings; .env is only the default
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('farm_settings')) {
                $s = $this->app->make(\App\Domain\Farm\FarmSettings::class);
                config(['features.marketplace' => (bool) $s->get('marketplace'), 'farm.open' => (bool) $s->get('farm_open')]);
            }
        } catch (\Throwable) {
            // no database yet (first install, artisan key:generate…): the .env defaults stand
        }

        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('calculations', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
    }
}
