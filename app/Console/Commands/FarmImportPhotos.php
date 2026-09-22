<?php

namespace App\Console\Commands;

use App\Models\FarmColor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Filament photos from the Drive export: one folder per colour, named like the colour's `code`
 * (e.g. "44_MATTE_PLA+_White"). Every colour gets the smallest JPEG/PNG of its folder, resized to 800 px, on the public disk.
 *
 *   php artisan farm:import-photos "C:\Users\roman\Downloads\FOTKY filamenty" "C:\Users\roman\Downloads\filamenty Roman"
 *   php artisan farm:import-photos <dir> --replace      also colours that already have a photo
 */
class FarmImportPhotos extends Command
{
    protected $signature = 'farm:import-photos {dirs* : directories with one sub-folder per colour} {--replace : replace photos that already exist} {--size=800 : longest side in px}';

    protected $description = 'Attach filament photos to the farm colour catalogue from folders named like the colours';

    public function handle(): int
    {
        if (! function_exists('imagecreatefromjpeg')) {
            $this->error('PHP GD is required (extension=gd).');

            return self::FAILURE;
        }
        $colors = FarmColor::whereNotNull('code')->get()->keyBy(fn (FarmColor $c) => $this->key($c->code));
        $done = $skipped = $missing = 0;
        foreach ((array) $this->argument('dirs') as $dir) {
            foreach (glob(rtrim($dir, '\\/').'/*', GLOB_ONLYDIR) ?: [] as $folder) {
                $color = $colors->get($this->key(basename($folder)));
                if (! $color) {
                    $this->line("<comment>no colour for</comment> ".basename($folder));
                    $missing++;

                    continue;
                }
                if ($color->photo_path && ! $this->option('replace')) {
                    $skipped++;

                    continue;
                }
                $photo = $this->pick($folder);
                if (! $photo) {
                    $this->line("<comment>no jpeg in</comment> ".basename($folder));
                    $missing++;

                    continue;
                }
                $rel = 'farm/colors/'.Str::slug($color->code, '-').'-'.substr(md5($photo), 0, 6).'.jpg';
                Storage::disk('public')->put($rel, $this->resized($photo, (int) $this->option('size')));
                if ($color->photo_path && $color->photo_path !== $rel) {
                    Storage::disk('public')->delete($color->photo_path);
                }
                $color->update(['photo_path' => $rel]);
                $done++;
            }
        }
        $this->info("{$done} photos attached, {$skipped} kept, {$missing} folders without a match.");

        return self::SUCCESS;
    }

    /** Folder names differ in case and stray spaces between the Drive export and the catalogue. */
    private function key(string $name): string
    {
        return strtolower(preg_replace('/[\s_]+/', '_', trim($name)));
    }

    /**
     * The product shot: "<folder> (1).jpg" in the studio set, a short named file (plaplusred.jpg) in the e-shop set;
     * camera files (IMG_1234.JPG) only when there is nothing else, the smallest first.
     */
    private function pick(string $folder): ?string
    {
        $files = array_values(array_filter(glob($folder.'/*') ?: [], fn ($f) => is_file($f) && preg_match('/\.(jpe?g|png)$/i', $f)));
        if (! $files) {
            return null;
        }
        $rank = function (string $f): array {
            $b = basename($f);
            if (preg_match('/\((\d+)\)\.jpe?g$/i', $b, $m)) {
                return [0, (int) $m[1], filesize($f)];
            }
            if (! preg_match('/^(IMG_|DSC|STE)/i', $b) && preg_match('/\.jpe?g$/i', $b)) {
                return [1, 0, filesize($f)];
            }

            return [preg_match('/\.png$/i', $b) ? 3 : 2, 0, filesize($f)];
        };
        usort($files, fn ($a, $b) => $rank($a) <=> $rank($b));

        return $files[0];
    }

    private function resized(string $path, int $max): string
    {
        $src = preg_match('/\.png$/i', $path) ? imagecreatefrompng($path) : imagecreatefromjpeg($path);
        // camera JPEGs carry their orientation in EXIF
        $exif = function_exists('exif_read_data') && ! preg_match('/\.png$/i', $path) ? @exif_read_data($path) : false;
        if (! empty($exif['Orientation'])) {
            $src = match ((int) $exif['Orientation']) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $dst = imagecreatetruecolor((int) round($w * $scale), (int) round($h * $scale));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // PNG transparency becomes white
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        ob_start();
        imagejpeg($dst, null, 82);

        return (string) ob_get_clean();
    }
}
