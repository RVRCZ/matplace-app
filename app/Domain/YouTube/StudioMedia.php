<?php

namespace App\Domain\YouTube;

use App\Engines\Repair\ScriptRunner;
use App\Jobs\BuildStudioVideos;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use App\Models\FarmVideoClip;
use App\Models\FarmVideoMedia;
use App\Models\ModelFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The media list of a print's videos (the video studio, /admin/youtube/orders/{order}, docs/AL.md §13.10): what the
 * Short and the 16:9 show, in the admin's order. The app puts in what it has of the print (the customer's photo, the
 * design, the sliced plate, the growing print, the photographed piece) plus the clips recorded for the old Short; the
 * admin adds clips and pictures, drags them, leaves any of them out. Every change rebuilds the videos that are not on
 * YouTube yet (BuildStudioVideos, a few seconds later so a run of changes builds once).
 */
class StudioMedia
{
    public const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v'];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

    /** The clip kinds of the old Short in the order it showed them; the removal kept its own sound. */
    private const OLD_CLIPS = ['removal', 'turn', 'detail', 'with_source', 'in_hand'];

    public function __construct(private readonly TimelapseCutter $cutter, private readonly ScriptRunner $python) {}

    /**
     * The order's media in their order, made on first use: the customer's photo (in only where the published Short may
     * show it), the design, the sliced plate, the growing print, the clips of the old Short, the photographed piece.
     *
     * @return Collection<int, FarmVideoMedia>
     */
    public function items(FarmOrder $order): Collection
    {
        if (! FarmVideoMedia::where('farm_order_id', $order->id)->exists()) {
            $this->seed($order);
        }

        return FarmVideoMedia::where('farm_order_id', $order->id)->orderBy('position')->orderBy('id')->get();
    }

    private function seed(FarmOrder $order): void
    {
        $short = $order->videos()->where('kind', FarmVideo::KIND_SHORT)->latest('id')->first();
        $rows = [];
        if ($this->cutter->sourcePicture($order)) {
            $rows[] = ['kind' => FarmVideoMedia::KIND_SOURCE, 'included' => $this->cutter->showsSource($order, $short)];
        }
        $rows[] = ['kind' => FarmVideoMedia::KIND_DESIGN];
        $rows[] = ['kind' => FarmVideoMedia::KIND_SLICED];
        $rows[] = ['kind' => FarmVideoMedia::KIND_GROWTH];
        $clips = $short ? FarmVideoClip::where('farm_video_id', $short->id)->get()->keyBy('kind') : collect();
        foreach (self::OLD_CLIPS as $kind) {
            if (($clip = $clips->get($kind)) && Storage::disk(config('farm.disk'))->exists($clip->path)) {
                $rows[] = ['kind' => FarmVideoMedia::KIND_CLIP, 'path' => $clip->path, 'mime' => $clip->mime, 'size' => $clip->size, 'sound' => $kind === 'removal',
                    'meta' => ['from_clip' => $kind] + $this->probe(Storage::disk(config('farm.disk'))->path($clip->path))];
            }
        }
        $rows[] = ['kind' => FarmVideoMedia::KIND_PHOTO];
        foreach ($rows as $i => $row) {
            FarmVideoMedia::create(['farm_order_id' => $order->id, 'position' => $i + 1] + $row);
        }
    }

    /**
     * A clip or a picture the admin adds (recorded in the browser or uploaded): goes in before the photographed piece
     * when that closes the list, else at the end. A phone's HEIC picture is turned into a JPEG first.
     */
    public function add(FarmOrder $order, UploadedFile $file): FarmVideoMedia
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension()));
        $video = in_array($extension, self::VIDEO_EXTENSIONS, true);
        if (! $video && ! in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['media' => 'Tenhle soubor neumím použít. Nahrajte video (MP4, MOV, WEBM) nebo obrázek (JPG, PNG, WEBP, HEIC).']);
        }
        $items = $this->items($order);
        $disk = Storage::disk(config('farm.disk'));
        $stem = $order->dir().'/media/'.now()->format('YmdHisv').'-'.Str::lower(Str::random(5));
        $disk->makeDirectory(dirname($stem));
        if ($video) {
            $path = $stem.'.'.$extension;
            $disk->putFileAs(dirname($path), $file, basename($path));
            $meta = $this->probe($disk->path($path));
            $mime = (string) ($file->getMimeType() ?: 'video/'.$extension);
        } else {
            $path = $stem.'.jpg';
            $r = $this->python->runScript('photo_tool.py', ['normalize', $file->getRealPath(), $disk->path($path)], 60);
            if (empty($r['ok'])) {
                throw ValidationException::withMessages(['media' => 'Obrázek se nepodařilo přečíst.']);
            }
            $meta = [];
            $mime = 'image/jpeg';
        }
        $last = $items->last();
        $position = $last && $last->kind === FarmVideoMedia::KIND_PHOTO ? $last->position : (int) $items->max('position') + 1;
        if ($last && $last->kind === FarmVideoMedia::KIND_PHOTO) {
            $last->update(['position' => $last->position + 1]);
        }
        $item = FarmVideoMedia::create([
            'farm_order_id' => $order->id, 'kind' => $video ? FarmVideoMedia::KIND_CLIP : FarmVideoMedia::KIND_IMAGE, 'path' => $path, 'mime' => $mime,
            'size' => (int) $disk->size($path), 'position' => $position, 'sound' => false,
            'meta' => ['name' => mb_substr((string) $file->getClientOriginalName(), 0, 120)] + $meta,
        ]);
        $this->changed($order);

        return $item;
    }

    /** The admin's order: the ids as dragged; any id left out keeps its place after them. */
    public function reorder(FarmOrder $order, array $ids): void
    {
        $ids = array_values(array_map('intval', $ids));
        $items = $this->items($order)->sortBy(fn (FarmVideoMedia $m) => ($i = array_search($m->id, $ids, true)) === false ? 10000 + $m->position : $i)->values();
        foreach ($items as $i => $item) {
            if ($item->position !== $i + 1) {
                $item->update(['position' => $i + 1]);
            }
        }
        $this->changed($order);
    }

    /** In or out of the videos, how long it is shown (null = its kind's default), a clip's own sound. */
    public function update(FarmVideoMedia $item, array $changes): void
    {
        $fill = array_intersect_key($changes, array_flip(['included', 'seconds', 'sound']));
        if (array_key_exists('seconds', $fill)) {
            $fill['seconds'] = $fill['seconds'] === null || $fill['seconds'] === '' ? null : max(0.5, min(30.0, (float) $fill['seconds']));
        }
        if ($item->kind !== FarmVideoMedia::KIND_CLIP) {
            unset($fill['sound']);
        }
        $item->fill($fill);
        if ($item->isDirty()) {
            $item->save();
            $this->changed($item->order);
        }
    }

    /** An added clip or picture goes with its file; the app's own items can only be left out. */
    public function delete(FarmVideoMedia $item): bool
    {
        if ($item->isAuto()) {
            return false;
        }
        if ($item->path && ! FarmVideoMedia::where('path', $item->path)->where('id', '!=', $item->id)->exists()
            && ! FarmVideoClip::where('path', $item->path)->exists()) {
            Storage::disk(config('farm.disk'))->delete($item->path);
        }
        $order = $item->order;
        $item->delete();
        $this->changed($order);

        return true;
    }

    /**
     * The picture or clip an item shows in a video of this kind (absolute path), null when there is none: the design
     * and the sliced plate are drawn on first use (ModelPicture; $make false = only when drawn already). The growing
     * print is not a file here (StudioCutter takes the frames or growth.mp4).
     */
    public function file(FarmOrder $order, FarmVideoMedia $item, string $kind = FarmVideo::KIND_SHORT, bool $make = true): ?string
    {
        [$w, $h] = $kind === FarmVideo::KIND_WIDE ? [1920, 1080] : [1080, 1920];
        $pictures = app(ModelPicture::class);

        return match ($item->kind) {
            FarmVideoMedia::KIND_SOURCE => $this->cutter->sourcePicture($order),
            FarmVideoMedia::KIND_DESIGN => $this->designPicture($order, $w, $h, $make),
            FarmVideoMedia::KIND_SLICED => $pictures->plate($order, $w, $h, $make),
            FarmVideoMedia::KIND_PHOTO => $this->cutter->finishPhoto($order, $kind === FarmVideo::KIND_WIDE ? 'web' : 'short'),
            FarmVideoMedia::KIND_GROWTH => null,
            default => $item->path && Storage::disk(config('farm.disk'))->exists($item->path) ? Storage::disk(config('farm.disk'))->path($item->path) : null,
        };
    }

    /** The model as designed: the browser's coloured preview stored with the file, else the server's drawing. */
    private function designPicture(FarmOrder $order, int $w, int $h, bool $make): ?string
    {
        $file = $order->modelFile;
        $disk = Storage::disk(ModelFile::DISK);
        if ($file?->preview_path && $disk->exists($file->preview_path)) {
            return $disk->path($file->preview_path);
        }

        return $file ? app(ModelPicture::class)->get($order, $w, $h, $make) : null;
    }

    /** The growing print can be built: the camera frames are still there, or the growth clip made from them. */
    public function hasGrowth(FarmOrder $order): bool
    {
        return $this->cutter->frames($order)->count() >= 4 || Storage::disk(config('farm.disk'))->exists($order->dir().'/growth.mp4');
    }

    /** Length and sound of a clip, by ffmpeg; empty when it cannot tell. */
    public function probe(string $file): array
    {
        try {
            $r = Process::timeout(20)->run([(string) config('farm.ffmpeg', 'ffmpeg'), '-hide_banner', '-i', $file]);
        } catch (\Throwable) {
            return [];
        }
        $text = $r->errorOutput().$r->output();
        $out = ['has_audio' => (bool) preg_match('/Stream #\S+.*: Audio:/', $text)];
        if (preg_match('/Duration: (\d+):(\d+):(\d+(?:\.\d+)?)/', $text, $m)) {
            $out['duration'] = round($m[1] * 3600 + $m[2] * 60 + (float) $m[3], 2);
        }

        return $out;
    }

    /** Something changed: the videos not on YouTube yet are built again (BuildStudioVideos waits a moment for more). */
    public function changed(?FarmOrder $order): void
    {
        if ($order) {
            BuildStudioVideos::later($order);
        }
    }
}
