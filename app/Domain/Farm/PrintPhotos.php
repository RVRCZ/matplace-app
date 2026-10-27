<?php

namespace App\Domain\Farm;

use App\Engines\Exceptions\EngineException;
use App\Engines\Photo\BackgroundRemover;
use App\Models\FarmOrder;
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

    /** The renders of one photo: name => [width, height]. */
    public const SIZES = ['web' => [1200, 800], 'short' => [1080, 1920], 'square' => [1080, 1080]];

    public const DISK = 'public';

    /** @param  array{bin?: string, timeout?: int}  $python  config engines.python */
    public function __construct(private readonly BackgroundRemover $remover, private readonly array $python) {}

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
     * @return array{cut: bool, bg: string, coverage: ?float, files: array<string, string>} absolute paths by name
     *
     * @throws EngineException when the composition itself fails (Python missing, the file not a picture)
     */
    public function render(string $source, string $outDir, string $stem, ?string $bg = null): array
    {
        $bg = in_array($bg, self::BACKGROUNDS, true) ? $bg : self::DEFAULT;
        File::ensureDirectoryExists($outDir);
        $cutout = $outDir.'/'.$stem.'-cutout.png';
        $cut = false;
        $coverage = null;
        if ($this->remover->available()) {
            try {
                $facts = $this->remover->cut($source, $cutout);
                $cut = true;
                $coverage = (float) $facts['coverage'];
            } catch (EngineException $e) {
                // the piece stays in its photo: said in the meta, the photo is still usable
                Log::warning('Print photo: the background could not be taken off', ['file' => basename($source), 'error' => $e->getMessage()]);
                @unlink($cutout);
            }
        }
        $files = $cut ? ['cutout' => $cutout] : [];
        foreach (self::SIZES as $name => [$w, $h]) {
            $jpg = $outDir.'/'.$stem.'-'.$name.'.jpg';
            $args = [$cut ? $cutout : $source, $jpg, '--bg', $bg, '--size', $w.'x'.$h, '--cut', $cut ? '1' : '0', '--backgrounds', public_path('img/backgrounds')];
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

        return ['cut' => $cut, 'bg' => $bg, 'coverage' => $coverage, 'files' => $files];
    }

    /**
     * The order's photo: rendered into the public disk under prints/<token>/ (an earlier one goes) and remembered on
     * the order. Returns the meta as stored; `source` is the photo it was made from on the farm disk when the caller
     * knows it (the photo box's photo, the admin's upload), so a later photo or a redo can tell where it came from.
     *
     * @return array{bg: string, cut: bool, coverage: ?float, at: string, source: ?string, files: array<string, string>}
     *
     * @throws EngineException
     */
    public function attach(FarmOrder $order, string $source, ?string $bg = null, ?string $sourceRel = null): array
    {
        $disk = Storage::disk(self::DISK);
        $dir = 'prints/'.$order->token;
        $disk->deleteDirectory($dir);
        $r = $this->render($source, $disk->path($dir), 'photo', $bg);
        $rel = fn (string $abs): string => $dir.'/'.basename($abs);
        $meta = ['bg' => $r['bg'], 'cut' => $r['cut'], 'coverage' => $r['coverage'], 'at' => now()->toIso8601String(), 'source' => $sourceRel, 'files' => array_map($rel, $r['files'])];
        $order->forceFill(['photo_path' => $rel($r['files']['web']), 'photo_meta' => $meta])->save();

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
