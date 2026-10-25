<?php

namespace App\Domain\Farm;

use App\Models\FarmOrder;
use App\Models\FarmVideo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The farm's figures for the trust block of the site (the sales path, 25 Oct 2026): how many prints have been made,
 * how fast a paid order reaches the customer, how many were printed in the last month, how many time-lapses are on
 * YouTube. Read only, counted from the orders and their history, remembered for an hour: the pages that show it are
 * public and the numbers change slowly.
 */
final class PublicStats
{
    public const CACHE_KEY = 'farm:public_stats';

    public const CACHE_SECONDS = 3600;

    /** The median of the handover takes the last this many handed-over orders. */
    public const MEDIAN_OF = 50;

    /**
     * `printed` = paid orders that were printed (done or handed over); `handed_over_days_median` = days from the
     * payment to the handover (the event that moved the order to handed_over), the median of the last MEDIAN_OF, null
     * without any; `last_30_days_printed` = of the printed ones those finished in the last 30 days; `videos_published`
     * = time-lapses public on YouTube.
     *
     * @return array{printed: int, handed_over_days_median: ?float, last_30_days_printed: int, videos_published: int}
     */
    public static function summary(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => self::compute());
    }

    /** The same figures counted now, past the cache. */
    public static function compute(): array
    {
        $printed = FarmOrder::query()->whereNotNull('paid_at')->whereIn('status', [FarmOrder::STATUS_DONE, FarmOrder::STATUS_HANDED_OVER]);

        $recent = DB::table('farm_order_events as e')
            ->join('farm_orders as o', 'o.id', '=', 'e.farm_order_id')
            ->where('e.to', FarmOrder::STATUS_HANDED_OVER)
            ->whereNotNull('o.paid_at')
            ->orderByDesc('e.created_at')->orderByDesc('e.id')
            ->limit(self::MEDIAN_OF)
            ->get(['e.created_at as handed_at', 'o.paid_at']);
        $days = $recent->map(fn ($r) => max(0.0, (Carbon::parse($r->handed_at)->getTimestamp() - Carbon::parse($r->paid_at)->getTimestamp()) / 86400))->sort()->values();

        return [
            'printed' => (clone $printed)->count(),
            'handed_over_days_median' => self::median($days->all()),
            'last_30_days_printed' => (clone $printed)->where('finished_at', '>=', now()->subDays(30))->count(),
            'videos_published' => FarmVideo::query()->where('status', FarmVideo::STATUS_PUBLISHED)->count(),
        ];
    }

    /** Forgotten, so the next summary() counts again (after an import or a correction by hand). */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @param  list<float>  $sorted  ascending */
    private static function median(array $sorted): ?float
    {
        $n = count($sorted);
        if ($n === 0) {
            return null;
        }
        $mid = intdiv($n, 2);
        $value = $n % 2 ? $sorted[$mid] : ($sorted[$mid - 1] + $sorted[$mid]) / 2;

        return round($value, 1);
    }
}
