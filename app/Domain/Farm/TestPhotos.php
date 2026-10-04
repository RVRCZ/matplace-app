<?php

namespace App\Domain\Farm;

use App\Engines\Repair\PythonTool;
use App\Models\FarmOrder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos of a printed test object, kept with the test order (farm disk, orders/<token>/photos). They come from the
 * photo box (three fixed cameras: top, left, right) or from a phone; every photo is stored upright as a JPEG, HEIC
 * included, so the judge and the page never deal with formats.
 */
final class TestPhotos
{
    public const VIEWS = ['top', 'left', 'right', 'phone'];

    public const MAX = 12;

    public function __construct(private readonly PythonTool $python) {}

    /** @return array<int, array{file: string, thumb: string, view: string, w: int, h: int, at: string}> */
    public function all(FarmOrder $order): array
    {
        return array_values((array) ($order->test_params['photos'] ?? []));
    }

    /** @throws FarmRefusal when the photo cannot be read or the test already holds enough photos */
    public function add(FarmOrder $order, string $sourcePath, string $view): array
    {
        $photos = $this->all($order);
        if (count($photos) >= self::MAX) {
            throw new FarmRefusal('photos_full', ['max' => self::MAX]);
        }
        $view = in_array($view, self::VIEWS, true) ? $view : 'phone';
        $file = $order->dir().'/photos/'.now()->format('His').'-'.$view.'-'.Str::lower(Str::random(6)).'.jpg';
        $disk = Storage::disk(config('farm.disk'));
        $disk->makeDirectory(dirname($file));
        $r = $this->python->runScript('photo_tool.py', ['normalize', $sourcePath, $disk->path($file)], 60);
        if (empty($r['ok'])) {
            throw new FarmRefusal(($r['error'] ?? '') === 'unreadable' && empty($r['heif']) ? 'photo_heic' : 'photo_unreadable');
        }
        $thumb = substr($file, 0, -4).'-thumb.jpg';
        $this->python->runScript('photo_tool.py', ['fit', $disk->path($file), $disk->path($thumb), '480'], 60);
        $photo = ['file' => $file, 'thumb' => $thumb, 'view' => $view, 'w' => (int) $r['w'], 'h' => (int) $r['h'], 'at' => now()->toIso8601String()];
        $photos[] = $photo;
        $order->forceFill(['test_params' => ['photos' => $photos] + (array) $order->test_params])->save();

        return $photo;
    }

    public function remove(FarmOrder $order, int $index): void
    {
        $photos = $this->all($order);
        if (! isset($photos[$index])) {
            return;
        }
        Storage::disk(config('farm.disk'))->delete(array_filter([$photos[$index]['file'], $photos[$index]['thumb'] ?? null]));
        array_splice($photos, $index, 1);
        $order->forceFill(['test_params' => ['photos' => $photos] + (array) $order->test_params])->save();
    }

    public function path(array $photo): string
    {
        return Storage::disk(config('farm.disk'))->path($photo['file']);
    }

    /** Index of the photo the print's video ends with: a side view first (they look best), the latest of its kind. */
    public function finishIndex(FarmOrder $order): ?int
    {
        $photos = $this->all($order);
        foreach (['left', 'right', 'phone', 'top'] as $view) {
            $match = array_keys(array_filter($photos, fn ($p) => ($p['view'] ?? '') === $view));
            if ($match) {
                return end($match);
            }
        }

        return null;
    }
}
