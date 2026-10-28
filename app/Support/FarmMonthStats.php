<?php

namespace App\Support;

use App\Models\FarmOrder;
use App\Models\FarmVideo;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The farm's figures for one calendar month, for a blog article ([farm: 2026-11], App\Support\BlogEmbeds): how many
 * prints were finished, how fast paid orders reached their customers, how many time-lapses went public on YouTube
 * and which tools made the printed models. Counted the way App\Domain\Farm\PublicStats counts the whole farm, read
 * only, remembered for an hour; an article scheduled for the month after thus carries the month's real numbers
 * without anyone typing them (docs/AP.md §13.1).
 */
final class FarmMonthStats
{
    public const CACHE_KEY = 'farm:month_stats:';

    public const CACHE_SECONDS = 3600;

    /** How many of the most used tools the card names. */
    public const TOP_TOOLS = 3;

    /**
     * @return ?array{from: CarbonImmutable, to: CarbonImmutable, printed: int, handed_over_days_median: ?float, videos_published: int, tools: list<array{kind: string, n: int}>, uploads: int}
     *                                                                                                                                                                                          null for anything but YYYY-MM
     */
    public static function month(string $ym): ?array
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $ym, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            return null;
        }
        $from = CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, config('app.timezone'));

        return Cache::remember(self::CACHE_KEY.$ym, self::CACHE_SECONDS, fn () => self::compute($from, $from->addMonth()));
    }

    /** The same figures counted now, past the cache: orders finished, handed over or videos published in [from, to). */
    public static function compute(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $printed = FarmOrder::query()->whereNotNull('paid_at')
            ->whereIn('status', [FarmOrder::STATUS_DONE, FarmOrder::STATUS_HANDED_OVER])
            ->where('finished_at', '>=', $from)->where('finished_at', '<', $to);

        $handed = DB::table('farm_order_events as e')
            ->join('farm_orders as o', 'o.id', '=', 'e.farm_order_id')
            ->where('e.to', FarmOrder::STATUS_HANDED_OVER)
            ->whereNotNull('o.paid_at')
            ->where('e.created_at', '>=', $from)->where('e.created_at', '<', $to)
            ->get(['e.created_at as handed_at', 'o.paid_at']);
        $days = $handed->map(fn ($r) => max(0.0, (Carbon::parse($r->handed_at)->getTimestamp() - Carbon::parse($r->paid_at)->getTimestamp()) / 86400))
            ->sort()->values()->all();

        // which tool made each printed model: a tool's kind, 'figure' for a generated one (as PublicStats::latestPhotos names it), a customer's own file otherwise
        $byKind = [];
        $uploads = 0;
        foreach ((clone $printed)->with('modelFile')->get() as $order) {
            $file = $order->modelFile;
            if ($file && $file->origin === 'tool') {
                $byKind[$file->kind()] = ($byKind[$file->kind()] ?? 0) + 1;
            } elseif ($file && $file->origin === 'generated') {
                $byKind['figure'] = ($byKind['figure'] ?? 0) + 1;
            } else {
                $uploads++;
            }
        }
        arsort($byKind);
        $tools = [];
        foreach (array_slice($byKind, 0, self::TOP_TOOLS, true) as $kind => $n) {
            $tools[] = ['kind' => (string) $kind, 'n' => $n];
        }

        return [
            'from' => $from,
            'to' => $to,
            'printed' => (clone $printed)->count(),
            'handed_over_days_median' => self::median($days),
            'videos_published' => FarmVideo::query()->where('status', FarmVideo::STATUS_PUBLISHED)->whereNotNull('youtube_id')
                ->where('published_at', '>=', $from)->where('published_at', '<', $to)->count(),
            'tools' => $tools,
            'uploads' => $uploads,
        ];
    }

    /** Forgotten, so the next month() counts again (after a correction by hand). */
    public static function forget(string $ym): void
    {
        Cache::forget(self::CACHE_KEY.$ym);
    }

    /** @param  list<float>  $sorted  ascending */
    private static function median(array $sorted): ?float
    {
        $n = count($sorted);
        if ($n === 0) {
            return null;
        }

        return $n % 2 ? $sorted[intdiv($n, 2)] : ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2;
    }
}
