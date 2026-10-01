<?php

namespace App\Providers;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Calculation\PriceEngine;
use App\Domain\Calculation\RoughEstimator;
use App\Domain\Designer\DesignerProfiles;
use App\Domain\Farm\FarmSettings;
use App\Events\AccountErasing;
use App\Models\Event as Visit;
use App\Routing\LocalizedUrlGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MaterialCatalog::class, fn () => new MaterialCatalog(config('materials')));
        $this->app->singleton(PriceEngine::class, fn () => new PriceEngine(config('pricing')));
        $this->app->singleton(RoughEstimator::class, fn ($app) => new RoughEstimator(config('pricing.rough'), $app->make(MaterialCatalog::class)));
        // scoped: the admin's overrides are read once per request / queue job, never kept across them
        $this->app->scoped(FarmSettings::class);

        // route() follows the language of the page (App\Support\Locales): the framework's generator is swapped for ours
        $this->app->extend('url', function (UrlGenerator $url, $app) {
            $localized = new LocalizedUrlGenerator($app['router']->getRoutes(), $url->getRequest(), $app['config']['app.asset_url']);
            $localized->setSessionResolver(fn () => $app['session'] ?? null);
            $localized->setKeyResolver(fn () => [$app['config']->get('app.key'), ...($app['config']->get('app.previous_keys') ?? [])]);

            return $localized;
        });
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
            if (Schema::hasTable('farm_settings')) {
                $s = $this->app->make(FarmSettings::class);
                config(['features.marketplace' => (bool) $s->get('marketplace'), 'farm.open' => (bool) $s->get('farm_open'), 'farm.public' => (bool) $s->get('farm_public')]);
            }
        } catch (\Throwable) {
            // no database yet (first install, artisan key:generate…): the .env defaults stand
        }

        // an account being deleted takes its designer profile down with it and leaves the statistics without a person
        Event::listen(function (AccountErasing $erasing) {
            $this->app->make(DesignerProfiles::class)->eraseFor($erasing->user);
            Visit::where('user_id', $erasing->user->id)->update(['user_id' => null]);
        });

        // @money($order->total()) prints a Money as it is; @money(149) a price defined in crowns, in the visitor's
        // currency; @money(12.5, 'EUR') an amount in a named currency. No template writes "Kč" by itself.
        Blade::directive('money', fn (string $expression) => "<?php echo e(\\App\\Support\\Money::show({$expression})); ?>");

        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('calculations', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
    }
}
