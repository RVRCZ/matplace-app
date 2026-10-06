<?php

namespace App\Providers;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Calculation\PriceEngine;
use App\Domain\Calculation\RoughEstimator;
use App\Domain\Designer\DesignerProfiles;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\Palette;
use App\Domain\Mail\Outbox;
use App\Events\AccountErasing;
use App\Models\Event as Visit;
use App\Routing\LocalizedUrlGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
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
        // the colour catalogue is read once per request: a page with twenty swatch fields asks it twenty times
        $this->app->scoped(Palette::class);

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

        // every mail that leaves is listed in /admin/emails as sent; one an admin approved there is in the list already
        Event::listen(function (MessageSent $sent) {
            if ($sent->message->getHeaders()->has('X-Matplace-Outgoing')) {
                return;
            }
            $to = implode(', ', array_map(fn ($a) => $a->getAddress(), $sent->message->getTo()));
            Outbox::logSent($to, (string) $sent->message->getSubject(), (string) ($sent->message->getTextBody() ?? strip_tags((string) $sent->message->getHtmlBody())), app()->getLocale());
        });

        // the admin tries the tools in bursts and is not counted
        RateLimiter::for('uploads', fn (Request $r) => $r->user()?->isAdmin() ? Limit::none() : Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('calculations', fn (Request $r) => $r->user()?->isAdmin() ? Limit::none() : Limit::perMinute(60)->by($r->ip()));
    }
}
