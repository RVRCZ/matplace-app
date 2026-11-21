<?php

namespace App\Domain\Tools;

use App\Engines\Contracts\ImageRestyler;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\DTO\GenerationHandle;
use App\Engines\DTO\GenerationStatus;
use App\Engines\Generator\TripoGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The picture-upload window of the image tools (21 Nov 2026): a logo photographed on a table (skewed, in shadow, on a
 * background) redrawn by the image model as a flat, straight, whole graphic on a plain background, the form the colour
 * layers of the tools (art_tool.py: background, colours, smoothing) work well with. The same mechanism as the bust's
 * photo (GenerateModel: AI_CLEAN_PHOTO, ImageRestyler), but behind its own switch, AI_CLEAN_ARTWORK, off by default:
 * it costs a picture task (5 credits, about 1 CZK) every time. Asynchronous like the bust's: start() answers with an
 * id, poll() is asked until the cleaned picture is stored as a new upload of the visitor ("my pictures"); what the
 * visitor sees stays the original until then. The classic cheap path (size, turn, background, colours) needs none of
 * this and is always there.
 */
final class ArtworkCleaner
{
    public const PROMPT = 'Redraw this picture as a clean, flat, straight-on graphic: exactly the same logo or drawing with the same shapes, the same lettering, '
        .'the same colours and proportions, nothing added and nothing beautified. Straighten it (no perspective, no tilt), remove every shadow, reflection, '
        .'paper or table texture, hand, finger and other object, and complete neatly what is blurred, cut off or covered. '
        .'Solid flat colours with crisp edges and no gradients. The graphic centred on a plain solid white background that fills the whole picture.';

    /** The task and its owner meet in the cache for half an hour: the poll of somebody else's task finds nothing. */
    private const KEEP = 1800;

    /** Whether the window offers the cleaning: the switch is on and an image model is there to do it. */
    public static function enabled(): bool
    {
        return (bool) config('ai.clean_artwork') && self::restyler() !== null;
    }

    private static function restyler(): ?ImageRestyler
    {
        $generator = app(ModelGenerator::class);
        if ($generator instanceof ImageRestyler) {
            return $generator;
        }

        // Meshy has no image model: Tripo's does it, as for the bust's photo
        return config('ai.tripo.api_key') ? new TripoGenerator((array) config('ai.tripo')) : null;
    }

    private static function counter(?string $owner): string
    {
        return 'artclean:'.now()->format('Ymd').':'.($owner ?? 'x');
    }

    private static function globalCounter(): string
    {
        return 'artclean:'.now()->format('Ymd').':all';
    }

    /**
     * Starts the cleaning of an upload of this visitor.
     *
     * @return array{id: string}|array{error: string}  error: off | no_picture | limit
     */
    public static function start(string $ref, ?string $owner, bool $admin = false): array
    {
        $restyler = self::enabled() ? self::restyler() : null;
        if (! $restyler) {
            return ['error' => 'off'];
        }
        // only the visitor's own upload: a library silhouette is clean already, a stored model's copy is not his to redraw
        $path = preg_match('/^[0-9a-f-]{36}$/', $ref) ? Artwork::path($ref) : null;
        if (! $path || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp'], true)) {
            return ['error' => 'no_picture'];
        }
        $mine = (int) Cache::get(self::counter($owner), 0);
        $all = (int) Cache::get(self::globalCounter(), 0);
        if (! $admin && ($mine >= (int) config('ai.artwork_clean.per_visitor', 10) || $all >= (int) config('ai.artwork_clean.global', 200))) {
            return ['error' => 'limit'];
        }
        $handle = $restyler->restyle($path, self::PROMPT);
        Cache::put(self::counter($owner), $mine + 1, 90000);
        Cache::put(self::globalCounter(), $all + 1, 90000);
        $id = (string) Str::uuid();
        Cache::put('artclean:task:'.$id, ['engine' => $handle->engine, 'external' => $handle->externalId, 'owner' => $owner], self::KEEP);

        return ['id' => $id];
    }

    /**
     * Where the cleaning is: running, failed, or done with the reference of the new picture.
     *
     * @return array{state: string, artwork?: string}
     */
    public static function poll(string $id, ?string $owner): array
    {
        $task = Cache::get('artclean:task:'.$id);
        $restyler = self::restyler();
        if (! is_array($task) || ($task['owner'] ?? null) !== $owner || ! $restyler) {
            return ['state' => 'failed'];
        }
        $status = $restyler->pollImage(new GenerationHandle((string) $task['engine'], (string) $task['external']));
        if ($status->state === GenerationStatus::FAILED) {
            Cache::forget('artclean:task:'.$id);

            return ['state' => 'failed'];
        }
        if ($status->state !== GenerationStatus::DONE || ! $status->previewPath || ! is_file($status->previewPath)) {
            return ['state' => 'running'];
        }
        $ref = Artwork::storeFile($status->previewPath, 'cleaned.png', $owner);
        @unlink($status->previewPath);
        Cache::forget('artclean:task:'.$id);

        return ['state' => 'done', 'artwork' => $ref];
    }
}
