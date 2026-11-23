<?php

namespace App\Domain\Farm;

use App\Engines\Exceptions\EngineException;
use App\Engines\Photo\BackgroundRemover;
use App\Models\FarmOrder;
use App\Models\GenerationRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * The photo of a finished print from the photo box, made presentable (TODO #48, docs/AL.md §13): the background taken
 * off (rembg through BackgroundRemover, a drawn fake in tests), the piece put on a backdrop with a shadow
 * (engines/python/photo_compose.py) in the sizes the places want: the web (3:2), a Short (9:16) and the square Short's
 * end (1:1), plus a WebP of the web one. An order's photo lives on the public disk under prints/<token>/ and the order
 * remembers it (`photo_path` = the web JPEG, `photo_meta` = every file, the backdrop, whether the cut worked). Without
 * rembg the photo is fitted and cropped as it is and `cut` says so, so nothing waits on the server's Python.
 */
final class PrintPhotos
{
    /** studio = a light grey vignette (the default), textures from public/img/backgrounds (photo_backgrounds.py draws them). */
    public const BACKGROUNDS = ['studio', 'wood', 'white', 'gradient', 'paper', 'marble', 'concrete', 'linen'];

    public const DEFAULT = 'studio';

    /**
     * SCENE FORMAT. A scene is a photo of an empty interior (a shelf, a coffee table) the piece is put on:
     * public/img/scenes/<name>.jpg (square, about 2048x2048, the surface in the lower third) and <name>.json beside it:
     *   { "kind": "shelf"|"table", "surface_y": 0.80, "x": 0.45, "piece_h": 0.40, "light": "left"|"right"|"top",
     *     "placeholder": true }
     * surface_y = the row the piece's bottom stands on (share of the image height), x = the centre of the piece (share
     * of the width), piece_h = the piece's height (share of the image height), light = where the light comes from
     * (the cast shadow falls away from it; top = only a pool under the piece), placeholder = a drawn stand-in (never
     * chosen by default, only by hand). The figure is always the real photo's cut-out; the scene is never drawn by AI.
     */
    public const SCENE_KINDS = ['shelf', 'table'];

    /** What a generation request's description kind must be for the default to choose a scene (a bust, a figure, a pet). */
    public const SCENE_MODEL_KINDS = ['bust', 'figure', 'pet'];

    /** The renders of one photo: name => [width, height]. */
    public const SIZES = ['web' => [1200, 800], 'short' => [1080, 1920], 'square' => [1080, 1080]];

    public const DISK = 'public';

    /**
     * The share of the photo a cut must keep to be the piece: under it the remover found nothing (an empty backdrop
     * with a shadow went up on production on 27 Oct 2026, F26-000097, 0 %; real pieces in the photo box take 46–86 %),
     * over it the remover kept the whole photo. Either way the photo is fitted as it is instead.
     */
    public const MIN_COVERAGE = 0.03;

    public const MAX_COVERAGE = 0.98;

    /** Under this mean grey level, or this spread of grey levels, a photo is a black frame (the camera did not capture) or one flat colour: nothing to show. */
    public const DARK_MEAN = 8.0;

    public const DARK_STD = 2.0;

    /** @param  array{bin?: string, timeout?: int}  $python  config engines.python */
    public function __construct(private readonly BackgroundRemover $remover, private readonly array $python) {}

    /** The folder of the scenes (config farm.scenes_dir overrides it, for tests). */
    public static function sceneDir(): string
    {
        return (string) (config('farm.scenes_dir') ?: public_path('img/scenes'));
    }

    /**
     * The scenes there are: name => its JSON (kind, surface_y, x, piece_h, light, placeholder); only those with both
     * the picture and a readable JSON.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function scenes(): array
    {
        $out = [];
        foreach (glob(self::sceneDir().'/*.json') ?: [] as $js) {
            $name = basename($js, '.json');
            $meta = json_decode((string) @file_get_contents($js), true);
            if (is_array($meta) && is_file(self::sceneDir().'/'.$name.'.jpg') && preg_match('/^[a-z0-9_-]+$/i', $name)) {
                $out[$name] = $meta;
            }
        }
        ksort($out);

        return $out;
    }

    /** Every backdrop the admin may choose: the plain ones and the textures, then the scenes. @return list<string> */
    public static function backgrounds(): array
    {
        return [...self::BACKGROUNDS, ...array_keys(self::scenes())];
    }

    /**
     * The backdrop of an order when nobody chose one: a bust, a figure or a pet from the generator stands on a shelf or
     * a coffee table (the scene alternates by the order's id; the drawn stand-ins are skipped), everything else on the
     * studio backdrop as before.
     */
    public static function defaultFor(FarmOrder $order): string
    {
        $file = $order->modelFile;
        if (! $file || $file->origin !== 'generated' || ! $file->origin_ref) {
            return self::DEFAULT;
        }
        $kind = GenerationRequest::where('token', $file->origin_ref)->first()?->description['kind'] ?? null;
        if (! in_array($kind, self::SCENE_MODEL_KINDS, true)) {
            return self::DEFAULT;
        }
        $real = array_filter(self::scenes(), fn (array $m) => empty($m['placeholder']));
        $want = self::SCENE_KINDS[$order->id % 2];   // odd ids a table, even ids a shelf
        $pool = array_keys(array_filter($real, fn (array $m) => ($m['kind'] ?? null) === $want)) ?: array_keys($real);
        if (! $pool) {
            return self::DEFAULT;
        }

        return $pool[intdiv($order->id, 2) % count($pool)];
    }

    /** Whether the background can be taken off here (rembg with its model; the answer is remembered for an hour). */
    public function available(): bool
    {
        return $this->remover->available();
    }

    /**
     * What the remover says about itself: rembg's probe (installed, model present, home) or a line for the fake and
     * for none; `farm:photo --probe` prints it.
     *
     * @return array<string, mixed>
     */
    public function probe(): array
    {
        return method_exists($this->remover, 'probe') ? $this->remover->probe() : ['ok' => $this->remover->available(), 'rembg' => $this->remover::class, 'model_present' => $this->remover->available()];
    }

    /**
     * Renders one photo into `$outDir` as `<stem>-cutout.png` (when the cut worked), `<stem>-web.jpg` + `.webp`,
     * `<stem>-short.jpg`, `<stem>-square.jpg`.
     *
     * @return array{cut: bool, bg: string, coverage: ?float, cut_error: ?string, files: array<string, string>} absolute paths by name
     *
     * @throws EngineException when the composition itself fails (Python missing, the file not a picture)
     */
    public function render(string $source, string $outDir, string $stem, ?string $bg = null): array
    {
        $bg = in_array($bg, self::backgrounds(), true) ? $bg : self::DEFAULT;
        $this->refuseDark($source);
        File::ensureDirectoryExists($outDir);
        $cutout = $outDir.'/'.$stem.'-cutout.png';
        $cut = false;
        $coverage = null;
        $cutError = null;
        if ($this->remover->available()) {
            try {
                $facts = $this->remover->cut($source, $cutout);
                $coverage = (float) $facts['coverage'];
                if (! self::pieceFound($coverage)) {
                    throw new EngineException(($coverage < self::MIN_COVERAGE ? 'empty: the remover found no piece (' : 'whole: the remover kept the whole photo (').round(100 * $coverage, 1).' % of the photo)');
                }
                $cut = true;
            } catch (EngineException $e) {
                // the piece stays in its photo: said in the meta (cut_error), the photo is still usable
                Log::warning('Print photo: the background could not be taken off', ['file' => basename($source), 'error' => $e->getMessage()]);
                @unlink($cutout);
                $cutError = mb_substr($e->getMessage(), 0, 200);
            }
        }
        $files = $cut ? ['cutout' => $cutout] : [];
        foreach (self::SIZES as $name => [$w, $h]) {
            $jpg = $outDir.'/'.$stem.'-'.$name.'.jpg';
            $args = [$cut ? $cutout : $source, $jpg, '--bg', $bg, '--size', $w.'x'.$h, '--cut', $cut ? '1' : '0', '--backgrounds', public_path('img/backgrounds'), '--scenes', self::sceneDir()];
            if ($name === 'web') {
                $args[] = '--webp';
                $args[] = $outDir.'/'.$stem.'-web.webp';
            }
            $r = Process::timeout((int) ($this->python['timeout'] ?? 120))->run([$this->python['bin'] ?? 'python3', base_path('engines/python/photo_compose.py'), ...$args]);
            $json = json_decode(trim($r->output()), true);
            if (! is_array($json) || empty($json['ok'])) {
                throw new EngineException('photo_compose.py ('.$name.'): '.(is_array($json) ? (string) ($json['error'] ?? 'failed') : mb_substr($r->output().$r->errorOutput(), -300)));
            }
            $files[$name] = $jpg;
            if ($name === 'web') {
                $files['webp'] = $outDir.'/'.$stem.'-web.webp';
            }
        }

        return ['cut' => $cut, 'bg' => $bg, 'coverage' => $coverage, 'cut_error' => $cutError, 'files' => $files];
    }

    /** Whether these grey levels (photo_tool.py stats / normalize) are a black frame or one flat colour. */
    public static function isDark(?float $mean, ?float $std): bool
    {
        return $mean !== null && $std !== null && ($mean < self::DARK_MEAN || $std < self::DARK_STD);
    }

    /**
     * F26-000097, 27 Oct 2026: a black 3840×2160 frame (the camera did not capture) went through the cut (empty) and
     * the fit (a black picture) alike. Such a photo is no photo.
     *
     * @throws EngineException
     */
    private function refuseDark(string $source): void
    {
        $light = $this->brightness($source);
        if ($light !== null && self::isDark($light['mean'], $light['std'])) {
            throw new EngineException('dark: the photo is black or one flat colour (brightness '.$light['mean'].' of 255, spread '.$light['std'].'): the camera did not capture, shoot it again');
        }
    }

    /** @return array{mean: float, std: float}|null the photo's brightness by photo_tool.py; null when it cannot say (the render says then) */
    private function brightness(string $source): ?array
    {
        $r = Process::timeout(60)->run([$this->python['bin'] ?? 'python3', base_path('engines/python/photo_tool.py'), 'stats', $source, '-']);
        $json = json_decode(trim($r->output()), true);

        return is_array($json) && ! empty($json['ok']) && isset($json['mean'], $json['std']) ? ['mean' => (float) $json['mean'], 'std' => (float) $json['std']] : null;
    }

    /** Whether a cut that kept this share of the photo is the piece, and not nothing or everything. */
    public static function pieceFound(?float $coverage): bool
    {
        return $coverage !== null && $coverage >= self::MIN_COVERAGE && $coverage <= self::MAX_COVERAGE;
    }

    /**
     * What a stored meta says of the cut: `cut` (the piece on its backdrop), `empty` (cut before the threshold
     * existed, nothing in it: worth a redo), `fitted` (the remover was not there that day: worth a redo), `uncut`
     * (the remover had its go and failed: fitted, no use trying again unasked).
     *
     * @param  array<string, mixed>  $meta
     */
    public static function cutState(array $meta): string
    {
        if (! empty($meta['cut'])) {
            return self::pieceFound(isset($meta['coverage']) ? (float) $meta['coverage'] : null) ? 'cut' : 'empty';
        }

        return empty($meta['cut_error']) ? 'fitted' : 'uncut';
    }

    /**
     * The order's photo: rendered into the public disk under prints/<token>/ (an earlier one goes) and remembered on
     * the order. Returns the meta as stored; `source` is the photo it was made from on the farm disk when the caller
     * knows it (the photo box's photo, the admin's upload), so a later photo or a redo can tell where it came from.
     *
     * @return array{bg: string, cut: bool, coverage: ?float, cut_error: ?string, at: string, source: ?string, files: array<string, string>}
     *
     * @throws EngineException
     */
    public function attach(FarmOrder $order, string $source, ?string $bg = null, ?string $sourceRel = null): array
    {
        $disk = Storage::disk(self::DISK);
        $dir = 'prints/'.$order->token;
        $bg = in_array($bg, self::backgrounds(), true) ? $bg : self::defaultFor($order);
        $this->refuseDark($source);   // before the photo the order has goes: a black frame must not take a good one away
        $disk->deleteDirectory($dir);
        $r = $this->render($source, $disk->path($dir), 'photo', $bg);
        $rel = fn (string $abs): string => $dir.'/'.basename($abs);
        $meta = ['bg' => $r['bg'], 'cut' => $r['cut'], 'coverage' => $r['coverage'], 'cut_error' => $r['cut_error'], 'at' => now()->toIso8601String(), 'source' => $sourceRel, 'files' => array_map($rel, $r['files'])];
        $order->forceFill(['photo_path' => $rel($r['files']['web']), 'photo_meta' => $meta])->save();
        // the photo offered as the picture of every colour of the order, for the admin's yes (docs/AL.md §21.7); never breaks the photo
        try {
            app(ColorPhotoProposals::class)->proposeFor($order);
        } catch (\Throwable $e) {
            Log::warning('Colour photo proposals were not made', ['order' => $order->id, 'error' => $e->getMessage()]);
        }

        return $meta;
    }

    /** The photo and its renders go; the order forgets it. */
    public function remove(FarmOrder $order): void
    {
        Storage::disk(self::DISK)->deleteDirectory('prints/'.$order->token);
        $order->forceFill(['photo_path' => null, 'photo_meta' => null])->save();
    }

    /** The absolute path of one render of the order's photo (web, webp, short, square, cutout), when it is there. */
    public static function file(FarmOrder $order, string $variant = 'web'): ?string
    {
        $rel = $order->photoFile($variant);

        return $rel !== null && Storage::disk(self::DISK)->exists($rel) ? Storage::disk(self::DISK)->path($rel) : null;
    }
}
