<?php

namespace App\Support;

use App\Domain\Farm\FarmGate;
use App\Domain\Tools\ToolVisibility;
use App\Models\Post;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;

/**
 * The blocks of a landing page under its tool (docs/AH.md §11, config/landing.php, lang/<locale>/landing.php):
 * "Before you order" (questions; the ones about printing with us only while the farm is offered to this visitor),
 * "Who it is for", "Where next" (similar tools that are public, articles that are published in this language).
 */
final class LandingPage
{
    public static function has(?string $tool): bool
    {
        return $tool !== null && isset(config('landing.pages')[$tool]) && Lang::has('landing.'.$tool.'.order');
    }

    /** @return list<array{q: string, a: string}> the questions this visitor can act on, with today's numbers */
    public static function order(string $tool): array
    {
        if (! self::has($tool)) {
            return [];
        }
        $farm = FarmGate::offered(auth()->user());
        $numbers = self::numbers();

        return array_values(array_map(
            fn (array $i) => ['q' => (string) $i['q'], 'a' => strtr((string) $i['a'], $numbers)],
            array_filter((array) __('landing.'.$tool.'.order'), fn ($i) => is_array($i) && ($farm || empty($i['farm']))),
        ));
    }

    /** @return list<array{t: string, text: string}> */
    public static function who(string $tool): array
    {
        return self::has($tool) ? array_values(array_filter((array) __('landing.'.$tool.'.who'), 'is_array')) : [];
    }

    /** @return list<array{key: string, url: string}> similar tools a visitor can open */
    public static function related(string $tool): array
    {
        $out = [];
        foreach ((array) (config('landing.pages')[$tool]['related'] ?? []) as $key) {
            $route = config('tools.'.$key.'.route');
            if ($route && Route::has($route) && ToolVisibility::isPublic($key)) {
                $out[] = ['key' => $key, 'url' => route($route)];
            }
        }

        return $out;
    }

    /** @return list<array{title: string, url: string}> articles published in the language of the page */
    public static function posts(string $tool): array
    {
        $slugs = (array) (config('landing.pages')[$tool]['blog'] ?? []);
        if (! $slugs) {
            return [];
        }
        $locale = app()->getLocale();

        return Post::published()->whereIn('slug', $slugs)->get()
            ->filter(fn (Post $p) => in_array($locale, $p->locales(), true))
            ->sortBy(fn (Post $p) => array_search($p->slug, $slugs, true))
            ->map(fn (Post $p) => ['title' => $p->titleIn($locale), 'url' => route('blog.show', $p)])
            ->values()->all();
    }

    /** The numbers the answers name: delivery (config/farm.php, in the visitor's currency) and the daily limit of models from a photo. */
    public static function numbers(): array
    {
        $currency = Currency::current();
        $zones = (array) config('farm.shipping.zones', []);
        $price = fn (?string $zone, string $kind) => isset($zones[$zone][$kind][$currency]) ? Money::of((float) $zones[$zone][$kind][$currency], $currency)->format() : '—';
        $eu = collect(['EU1', 'EU2', 'EU3'])->map(fn ($z) => $zones[$z]['point'][$currency] ?? null)->filter()->min();

        return [
            ':cz_point' => $price('CZ', 'point'), ':cz_home' => $price('CZ', 'home'), ':sk_point' => $price('SK', 'point'),
            ':eu_point' => $eu !== null ? Money::of((float) $eu, $currency)->format() : '—',
            ':guest' => (string) (int) config('ai.daily_limits.generate_guest'), ':account' => (string) (int) config('ai.daily_limits.generate_user'),
        ];
    }
}
