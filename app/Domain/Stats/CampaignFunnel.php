<?php

namespace App\Domain\Stats;

use App\Http\Controllers\Api\StepController;
use App\Models\Event;

/**
 * Where the visitors of a paid campaign leave (Roman, 17 Nov 2026): the links of the ads carry utm_campaign and
 * utm_content, every event of a visit keeps them (Track::origin), so the way from the ad to the payment can be read
 * per channel and per ad. A step counts the visitors (browser sessions) who did it; the first column is every visit
 * that was not a robot's, the second the ones whose browser really showed the page, and nothing after it is counted
 * for a visit the browser never confirmed.
 *
 * The steps before the model come from the page's script (`tool_step`, StepController, docs/AJ.md §7), the ones after
 * it from the server (docs/AJ.md §2).
 */
final class CampaignFunnel
{
    /** step => the marks that pass it, in the order a visitor meets them. */
    public const STEPS = [
        'visit' => ['visit'],
        'page' => ['page'],
        'scroll' => ['step:scroll'],
        'pick' => ['step:pick'],
        'photo' => ['step:photo'],
        'create' => ['step:create', 'generate'],
        'generate' => ['generate'],
        'model' => ['step:shown', 'model_ready'],
        'to_print' => ['order_created', 'order_started'],
        'price' => ['price_shown'],
        'checkout' => ['checkout_started'],
        'paid' => ['order_paid'],
        // not a step towards an order: the model taken away for one's own printer
        'download' => ['download'],
    ];

    /** What stops a visitor: shown beside the steps, not as steps. */
    public const STOPS = [
        'no_consent' => ['step:no_consent'],
        'no_photo' => ['step:no_photo'],
        'refused' => ['step:limit', 'step:rejected', 'step:error', 'failed'],
        'wait_left' => ['step:wait_left'],
    ];

    /** Between the photo and "Create" (the page's script, figure.ts): what the visitor with a photo saw and did. */
    public const BEFORE = [
        'thumb' => ['step:thumb'],
        'create_seen' => ['step:create_seen'],
        'consent_seen' => ['step:consent_seen'],
        'consent_on' => ['step:consent_on'],
        'photo_off' => ['step:photo_off'],
    ];

    /** After the model was shown; `model_left` = left the page with the model and without an order. */
    public const AFTER = [
        'model_touch' => ['step:model_touch'],
        'cta_seen' => ['step:cta_seen'],
        'price_seen' => ['step:price_seen'],
        'again' => ['step:again'],
        'dl_click' => ['step:dl_click'],
        'model_left' => ['step:model_left'],
    ];

    /** What a visitor did on the order's page before paying (the page's script, farm.ts); `o_left` = left it unpaid. */
    public const ORDER = [
        'o_color' => ['step:o_color'],
        'o_setting' => ['step:o_setting'],
        'o_slice' => ['step:o_slice'],
        'o_delivery' => ['step:o_delivery'],
        'o_email' => ['step:o_email'],
        'o_terms' => ['step:o_terms'],
        'o_blocked' => ['step:o_blocked'],
        'o_pay' => ['step:o_pay', 'checkout_started'],
        'o_left' => ['step:o_left'],
    ];

    /**
     * @return array{days: int, campaign: ?string, campaigns: array<string, int>, rows: list<array{source: string, content: string, steps: array<string, int>, stops: array<string, int>, wait_s: ?int}>, total: array{steps: array<string, int>, stops: array<string, int>, wait_s: ?int}}
     */
    public function compute(int $days, ?string $campaign = null): array
    {
        $sessions = array_filter($this->sessions($days), fn (array $s) => ! $s['out']);

        $campaigns = [];
        foreach ($sessions as $s) {
            $campaigns[$s['campaign']] = ($campaigns[$s['campaign']] ?? 0) + 1;
        }
        arsort($campaigns);
        $campaign = $campaign !== null && isset($campaigns[$campaign]) ? $campaign : array_key_first($campaigns);

        $empty = ['steps' => array_fill_keys(array_keys(self::STEPS), 0), 'stops' => array_fill_keys(array_keys(self::STOPS), 0), 'order' => array_fill_keys(array_keys(self::ORDER), 0),
            'before' => array_fill_keys(array_keys(self::BEFORE), 0), 'after' => array_fill_keys(array_keys(self::AFTER), 0), 'waits' => []];
        $rows = [];
        $total = $empty;
        foreach ($sessions as $s) {
            if ($s['campaign'] !== $campaign) {
                continue;
            }
            $key = $s['source'].'|'.$s['content'];
            $rows[$key] ??= ['source' => $s['source'], 'content' => $s['content']] + $empty;
            $rows[$key] = $this->add($rows[$key], $s);
            $total = $this->add($total, $s);
        }
        $rows = array_map($this->close(...), array_values($rows));
        usort($rows, fn (array $a, array $b) => [$b['steps']['visit'], $a['source'], $a['content']] <=> [$a['steps']['visit'], $b['source'], $b['content']]);

        return ['days' => $days, 'campaign' => $campaign, 'campaigns' => $campaigns, 'rows' => $rows, 'total' => $this->close($total)];
    }

    /**
     * Every browser session of the period that came by a link with utm_campaign, with what it did.
     *
     * @return array<int, array{campaign: string, source: string, content: string, marks: array<string, true>, out: bool, human: bool, wait: ?int}>
     */
    private function sessions(int $days): array
    {
        $sessions = [];
        Event::where('created_at', '>=', now()->subDays($days))->whereNotNull('utm')->whereNotNull('session_id')->where('type', '!=', Event::CRAWL)->orderBy('id')
            ->select(['id', 'session_id', 'type', 'utm', 'meta'])
            ->chunk(5000, function ($events) use (&$sessions) {
                foreach ($events as $e) {
                    $utm = (array) $e->utm;
                    $name = (string) ($utm['utm_campaign'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $meta = (array) $e->meta;
                    $s = $sessions[$e->session_id] ?? ['campaign' => $name, 'source' => (string) ($utm['utm_source'] ?? '?'), 'content' => (string) ($utm['utm_content'] ?? '–'), 'marks' => [], 'out' => false, 'human' => false, 'wait' => null];
                    $s['out'] = $s['out'] || ! empty($meta['bot']) || ! empty($meta['staff']);
                    $s['human'] = $s['human'] || $e->type === Event::PAGE || ! empty($meta['js']) || ! empty($meta['act']);
                    $mark = $e->type === StepController::TYPE ? 'step:'.($meta['step'] ?? '') : $e->type;
                    $s['marks'][$mark] = true;
                    if ($mark === 'step:wait_left' && isset($meta['ms'])) {
                        $s['wait'] = (int) $meta['ms'];
                    }
                    $sessions[$e->session_id] = $s;
                }
            });

        return $sessions;
    }

    /** One visitor added to a row: the visit always, the rest only when the browser showed the page. */
    private function add(array $row, array $s): array
    {
        $row['steps']['visit']++;
        if (! $s['human']) {
            return $row;
        }
        $marks = $s['marks'] + ['page' => true];
        foreach (self::STEPS as $step => $passes) {
            if ($step !== 'visit' && array_intersect_key(array_flip($passes), $marks)) {
                $row['steps'][$step]++;
            }
        }
        foreach (self::STOPS as $stop => $passes) {
            if (array_intersect_key(array_flip($passes), $marks)) {
                $row['stops'][$stop]++;
            }
        }
        foreach (['order' => self::ORDER, 'before' => self::BEFORE, 'after' => self::AFTER] as $group => $steps) {
            foreach ($steps as $step => $passes) {
                if (array_intersect_key(array_flip($passes), $marks)) {
                    $row[$group][$step]++;
                }
            }
        }
        if ($s['wait'] !== null) {
            $row['waits'][] = $s['wait'];
        }

        return $row;
    }

    /** The waits of those who left become their median in seconds. */
    private function close(array $row): array
    {
        sort($row['waits']);
        $row['wait_s'] = $row['waits'] ? (int) round($row['waits'][intdiv(count($row['waits']), 2)] / 1000) : null;
        unset($row['waits']);

        return $row;
    }
}
