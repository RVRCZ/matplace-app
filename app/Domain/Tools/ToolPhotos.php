<?php

namespace App\Domain\Tools;

use App\Models\FarmOrder;
use Illuminate\Support\Facades\Cache;

/**
 * "Takhle to vytiskli" on a tool's page (docs/AN.md §15): the latest photographed prints the farm made from this tool.
 * Paid, finished orders with a photo (PrintPhotos), whose model came from the tool (model_files.origin_ref; the bust
 * from a photo is origin `generated`). The photo shows the print, never the customer, and still only orders whose
 * customer agreed to publishing (video_consent) are shown – except for tools that take sizes alone (a box, a vase, an
 * organizer): such a print carries nothing of its owner. The material and the date go with it, the price never.
 * Remembered for an hour per tool, like the farm's public figures.
 */
final class ToolPhotos
{
    public const MAX = 4;

    public const CACHE_SECONDS = 3600;

    /** @return list<array{url: string, webp: ?string, material: ?string, at: ?string, number: ?string}> newest first */
    public static function for(string $tool, int $limit = self::MAX): array
    {
        $limit = max(1, min(self::MAX, $limit));

        return array_slice(Cache::remember(self::key($tool), self::CACHE_SECONDS, fn () => self::load($tool)), 0, $limit);
    }

    /** The newest photographed print of the tool, for the example card that has no hand-picked photo (calculator/_examples). */
    public static function latest(string $tool): ?array
    {
        return self::for($tool, 1)[0] ?? null;
    }

    public static function forget(string $tool): void
    {
        Cache::forget(self::key($tool));
    }

    /** Prints of this tool carry nothing of their owner: the tool takes sizes alone, no text, picture, photo or place. */
    public static function impersonal(string $tool): bool
    {
        return ToolInput::of($tool, (array) config('tools.'.$tool, [])) === 'sizes';
    }

    private static function key(string $tool): string
    {
        return 'tool_photos:'.$tool;
    }

    /** @return list<array{url: string, webp: ?string, material: ?string, at: ?string, number: ?string}> */
    private static function load(string $tool): array
    {
        $orders = FarmOrder::query()->with(['modelFile', 'material'])
            ->whereNotNull('paid_at')->whereNotNull('photo_path')
            ->whereIn('status', [FarmOrder::STATUS_DONE, FarmOrder::STATUS_HANDED_OVER])
            ->whereHas('modelFile', fn ($q) => $tool === 'figure'
                ? $q->where(fn ($w) => $w->where('origin', 'generated')->orWhere(fn ($t) => $t->where('origin', 'tool')->where('origin_ref', 'figure')))
                : $q->where('origin', 'tool')->where('origin_ref', $tool))
            ->orderByDesc('finished_at')->orderByDesc('id')->limit(self::MAX * 4)->get();
        $impersonal = self::impersonal($tool);
        $out = [];
        foreach ($orders as $order) {
            if (! $order->video_consent && ! $impersonal) {
                continue;
            }
            if (! ($url = $order->photoUrl())) {
                continue;
            }
            $out[] = ['url' => $url, 'webp' => $order->photoUrl('webp'), 'material' => $order->material?->label(), 'at' => $order->finished_at?->toDateString(), 'number' => $order->number];
            if (count($out) >= self::MAX) {
                break;
            }
        }

        return $out;
    }
}
