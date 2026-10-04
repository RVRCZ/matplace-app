<?php

namespace App\Domain\Stats;

use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Since when a visit must be confirmed by the browser to count as a person's: the moment of the first confirmed
 * visit, i.e. the deploy of the confirming script. Visits before it could not be confirmed and are judged by
 * matplace:events-bots alone, so the numbers of both sides of that day can stand next to each other.
 */
final class Humans
{
    private const KEY = 'stats.humans_since';

    public static function since(): ?Carbon
    {
        $at = Cache::get(self::KEY);
        if (! $at) {
            $at = Event::where('type', Event::VISIT)->where('meta->js', true)->min('created_at');
            if ($at) {
                Cache::forever(self::KEY, (string) $at);   // it never moves once it exists
            }
        }

        return $at ? Carbon::parse($at) : null;
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
