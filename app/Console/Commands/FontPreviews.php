<?php

namespace App\Console\Commands;

use App\Domain\Tools\ParametricGenerator;
use App\Engines\Repair\PythonTool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Draws the pictures of the font picker: the name of every typeface of ParametricGenerator::FONTS set in that typeface
 * (public/img/fonts/<key>.svg, kept in the repository). Run it after a face is added or replaced.
 *
 *   php artisan matplace:font-previews           only pictures that are missing
 *   php artisan matplace:font-previews --force   all of them again
 */
class FontPreviews extends Command
{
    protected $signature = 'matplace:font-previews {--force : draw again what already exists}';

    protected $description = 'Draw the pictures of the typefaces for the font picker of the tools (public/img/fonts)';

    public function handle(PythonTool $python): int
    {
        File::ensureDirectoryExists(public_path('img/fonts'));
        $drawn = $failed = 0;
        foreach (ParametricGenerator::FONTS as $key => [$file, $name]) {
            $target = public_path('img/fonts/'.$key.'.svg');
            if (is_file($target) && ! $this->option('force')) {
                continue;
            }
            $r = $python->runScript('font_preview.py', [base_path($file), $target, $name], 60);
            if (empty($r['ok']) || ! is_file($target)) {
                $this->warn(sprintf('%s: not drawn (%s)', $key, (string) ($r['error'] ?? 'no answer')));
                $failed++;

                continue;
            }
            $this->line(sprintf('%-14s %s  %d kB', $key, $name, (int) ceil(filesize($target) / 1024)));
            $drawn++;
        }
        $this->info("Drawn {$drawn}".($failed ? ", failed {$failed}" : '').'.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
