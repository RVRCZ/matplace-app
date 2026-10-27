<?php

namespace App\Domain\Stats;

use App\Models\Event;

/**
 * The one number of the traffic phase (docs/AN.md §12.3): unique visitors who used at least one tool in a week, from
 * our own events that name a tool. Using = a person's action with a result (a calculation, a model generated, a
 * download, "print with us", a share); looking at a tool's page alone is not. A visitor is a browser session (or a
 * signed-in user without one); the staff's browsers are left out. Per week, per tool and per language.
 */
final class ToolUse
{
    public const USES = ['calculation', 'generate', 'download', 'order_started', 'share'];

    public const PERIODS = [4, 12, 26];

    /**
     * Weeks newest first; tools → week start (Y-m-d) → unique visitors, busiest tool first; keys = the week starts, newest first.
     *
     * @return array{weeks: list<array{week: string, from: string, users: int, by_locale: array<string, int>}>, tools: array<string, array<string, int>>, keys: list<string>}
     */
    public function weekly(int $weeks = 12): array
    {
        $weeks = in_array($weeks, self::PERIODS, true) ? $weeks : 12;
        $start = now()->startOfWeek()->subWeeks($weeks - 1);
        $byWeek = [];
        Event::whereIn('type', self::USES)->where('created_at', '>=', $start)->whereNotNull('meta->tool')->whereNull('meta->staff')
            ->orderBy('id')->select(['session_id', 'user_id', 'locale', 'created_at', 'meta'])
            ->chunk(5000, function ($events) use (&$byWeek) {
                foreach ($events as $e) {
                    $tool = is_array($e->meta) ? ($e->meta['tool'] ?? null) : null;
                    $visitor = $e->session_id !== null ? 's'.$e->session_id : ($e->user_id !== null ? 'u'.$e->user_id : null);
                    if (! is_string($tool) || $tool === '' || $visitor === null) {
                        continue;
                    }
                    $week = $e->created_at->copy()->startOfWeek()->toDateString();
                    $w = &$byWeek[$week];
                    $w ??= ['users' => [], 'locales' => [], 'tools' => []];
                    $w['users'][$visitor] = true;
                    $w['locales'][(string) ($e->locale ?: 'cs')][$visitor] = true;
                    $w['tools'][$tool][$visitor] = true;
                    unset($w);
                }
            });

        $out = ['weeks' => [], 'tools' => [], 'keys' => []];
        for ($i = 0; $i < $weeks; $i++) {
            $from = now()->startOfWeek()->subWeeks($i);
            $key = $from->toDateString();
            $w = $byWeek[$key] ?? ['users' => [], 'locales' => [], 'tools' => []];
            $out['keys'][] = $key;
            $out['weeks'][] = ['week' => $from->format('o-\WW'), 'from' => $key, 'users' => count($w['users']), 'by_locale' => array_map('count', $w['locales'])];
            foreach ($w['tools'] as $tool => $set) {
                $out['tools'][$tool][$key] = count($set);
            }
        }
        uasort($out['tools'], fn (array $a, array $b) => array_sum($b) <=> array_sum($a));

        return $out;
    }

    /**
     * Where the embedded calculator was shown (EmbedController): host → how many times the frame loaded in the period.
     *
     * @return array<string, int>
     */
    public static function embeds(int $days = 30): array
    {
        $hosts = [];
        foreach (Event::where('type', 'embed')->where('created_at', '>=', now()->subDays($days))->get(['meta']) as $e) {
            $host = is_array($e->meta) ? ($e->meta['host'] ?? null) : null;
            if (is_string($host) && $host !== '') {
                $hosts[$host] = ($hosts[$host] ?? 0) + 1;
            }
        }
        arsort($hosts);

        return $hosts;
    }
}
