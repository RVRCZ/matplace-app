<?php

namespace App\Domain\Stats;

use App\Models\AiCall;
use App\Models\Event;
use App\Models\FarmOrder;
use Illuminate\Support\Carbon;

/**
 * What the AI costs, from ai_calls (every call books itself through App\Support\AiUsage): by day, by month and by
 * kind of work, and set against what it serves: the cost per visitor and per paid order.
 */
final class AiActivity
{
    /**
     * @return array{days: int, total: array{calls: int, tokens_in: int, tokens_out: int, cost: float}, by_kind: list<array<string, mixed>>, by_day: list<array<string, mixed>>, by_month: list<array<string, mixed>>, visitors: int, orders: int, per_visitor: ?float, per_order: ?float}
     */
    public function summary(int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();
        $calls = AiCall::where('created_at', '>=', $since)->get(['created_at', 'kind', 'engine', 'tokens_in', 'tokens_out', 'cost_czk']);

        $sum = fn ($rows) => ['calls' => count($rows), 'tokens_in' => (int) collect($rows)->sum('tokens_in'), 'tokens_out' => (int) collect($rows)->sum('tokens_out'), 'cost' => round((float) collect($rows)->sum('cost_czk'), 2)];
        $byKind = $calls->groupBy('kind')->map(fn ($rows, $kind) => ['kind' => (string) $kind, 'engines' => $rows->pluck('engine')->unique()->values()->all()] + $sum($rows))
            ->sortByDesc('cost')->values()->all();
        $byDay = $calls->groupBy(fn (AiCall $c) => $c->created_at->toDateString())->map(fn ($rows, $day) => ['day' => (string) $day] + $sum($rows))->sortByDesc('day')->values()->all();

        // months reach further back than the chosen period: the last twelve
        $byMonth = [];
        foreach (AiCall::where('created_at', '>=', now()->subMonths(11)->startOfMonth())->get(['created_at', 'tokens_in', 'tokens_out', 'cost_czk'])
            ->groupBy(fn (AiCall $c) => $c->created_at->format('Y-m')) as $month => $rows) {
            $byMonth[] = ['month' => (string) $month] + $sum($rows);
        }
        usort($byMonth, fn ($a, $b) => strcmp($b['month'], $a['month']));

        $total = $sum($calls);
        $visitors = (int) Event::where('created_at', '>=', $since)->whereNotNull('session_id')->distinct()->count('session_id');
        $orders = (int) FarmOrder::where('kind', FarmOrder::KIND_PRINT)->whereNotNull('paid_at')->where('paid_at', '>=', $since)->count();

        return [
            'days' => $days, 'total' => $total, 'by_kind' => $byKind, 'by_day' => $byDay, 'by_month' => $byMonth,
            'visitors' => $visitors, 'orders' => $orders,
            'per_visitor' => $visitors > 0 ? round($total['cost'] / $visitors, 2) : null,
            'per_order' => $orders > 0 ? round($total['cost'] / $orders, 2) : null,
        ];
    }

    /** "2026-10" → "říjen 2026". */
    public static function monthName(string $month): string
    {
        return Carbon::createFromFormat('Y-m-d', $month.'-01')->translatedFormat('F Y');
    }
}
