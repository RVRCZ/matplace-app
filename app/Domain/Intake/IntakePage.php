<?php

namespace App\Domain\Intake;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\FarmState;
use App\Domain\Farm\PriceCalculator;
use App\Domain\Tools\ToolPhotos;
use App\Domain\Tools\ToolVisibility;
use App\Models\User;
use App\Support\Currency;
use App\Support\Money;
use App\Support\ToolSeo;
use Carbon\Carbon;

/**
 * What the home page's router shows besides the form (TODO #78, docs/AN.md §22): the gift cards, the Christmas
 * deadline and the reply time from the farm's settings, the farm's photographed prints. The dates and the hours are
 * the operator's to change at /admin/farm/settings, not the code's.
 */
final class IntakePage
{
    /** The gifts made from a photo, a drawing or a name, in the order of the page: keys of config/tools.php. */
    public const GIFTS = ['figure', 'pet', 'charm', 'medallion', 'map', 'topper', 'coaster', 'earrings'];

    /** The tools that need the generator of shapes from a photo (no generator, no card). */
    private const GENERATED = ['figure', 'pet'];

    /** Hours within which a person answers a request of the router (the farm's setting). */
    public static function hours(): int
    {
        return max(1, (int) app(FarmSettings::class)->get('intake_reply_hours'));
    }

    /**
     * The gift cards: the tools a visitor may open, with their picture, name and the price they start from when a real
     * calculation of the farm gave one (config/site.php example_items, in crowns only); no number is made up.
     *
     * @return list<array{key: string, url: string, title: string, from: ?string}>
     */
    public static function gifts(?User $user, bool $generator): array
    {
        $out = [];
        foreach (self::GIFTS as $key) {
            if (! config('tools.'.$key) || ! ToolVisibility::canOpen($user, $key) || (in_array($key, self::GENERATED, true) && ! $generator)) {
                continue;
            }
            $out[] = ['key' => $key, 'url' => ToolSeo::url($key), 'title' => __('router.gift.'.$key), 'from' => self::fromPrice($key)];
        }

        return $out;
    }

    /**
     * "from 130 Kč": the cheapest priced example of the tool (a real calculation), in crowns; null elsewhere, without
     * one, or when an example of the tool has no price (a smaller piece may cost less than the priced ones).
     */
    public static function fromPrice(string $tool): ?string
    {
        if (Currency::current() !== Money::CZK) {
            return null;
        }
        $prices = collect((array) config('site.example_items'))->where('tool', $tool)->map(fn ($i) => $i['price'][Money::CZK] ?? null);

        return $prices->isEmpty() || $prices->contains(null) ? null : Money::of((float) $prices->min(), Money::CZK)->format();
    }

    /**
     * "A print by us from …": the lowest price of an order as the calculator charges it, from the farm's settings
     * (/admin/farm/settings: the minimum with VAT, rounded up to the farm's step, as FarmPricingProfile does). No
     * number is typed into a text. In crowns only; null elsewhere or with no minimum set.
     */
    public static function minOrderPrice(): ?string
    {
        if (Currency::current() !== Money::CZK) {
            return null;
        }
        $settings = app(FarmSettings::class);
        $min = PriceCalculator::roundUp((float) $settings->get('min_price') * (1 + (float) $settings->get('vat_percent') / 100), (float) $settings->get('rounding'));

        return $min > 0 ? Money::of($min, Money::CZK)->format() : null;
    }

    /** The last day an order still arrives before Christmas, as the page says it (the farm's setting; null when none or gone by). */
    public static function christmas(): ?string
    {
        $raw = (string) app(FarmSettings::class)->get('christmas_deadline');
        if ($raw === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }
        $date = Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        if ($date->isPast() && ! $date->isToday()) {
            return null;
        }
        // a paused farm promises Christmas only when its pause ends by that day, a closed one never (docs/AL.md §23.5)
        if (! FarmState::allowsPromiseBy($date)) {
            return null;
        }
        $locale = app()->getLocale();

        return $date->locale($locale)->isoFormat(match ($locale) {
            'cs' => 'D. M.',
            'es' => 'D [de] MMMM',
            default => 'D MMMM',
        });
    }

    /**
     * Photographed prints of the gift tools, newest first (App\Domain\Tools\ToolPhotos: paid, finished, with the customer's
     * consent). Empty until the farm has such photos; the page then leaves the section out.
     *
     * @return list<array{url: string, webp: ?string, material: ?string, at: ?string, tool: string}>
     */
    public static function prints(int $limit = 3): array
    {
        $out = [];
        foreach (self::GIFTS as $key) {
            if (! config('tools.'.$key)) {
                continue;
            }
            foreach (ToolPhotos::for($key) as $photo) {
                $out[] = $photo + ['tool' => $key];
            }
        }
        usort($out, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        return array_slice($out, 0, max(1, $limit));
    }
}
