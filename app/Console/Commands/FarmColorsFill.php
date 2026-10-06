<?php

namespace App\Console\Commands;

use App\Domain\Farm\ColorCatalog;
use Illuminate\Console\Command;

/**
 * Fills what the colour catalogue lacks, so nobody types 250 hex codes and English names by hand:
 *   hex      read from the photo of the print (the sample without its background, the median of its middle);
 *            rainbow, wood and glowing filaments get their dominant colour and a note "hex z fotky, zkontrolovat"
 *   name_en  the Czech names translated in one call (App\Engines\Translate)
 *
 *   php artisan farm:colors-fill                 both, only where they are missing
 *   php artisan farm:colors-fill --only=hex      one of them (hex | name_en, comma separated)
 *   php artisan farm:colors-fill --dry-run       say what would be filled, change nothing
 */
class FarmColorsFill extends Command
{
    protected $signature = 'farm:colors-fill {--dry-run : show what would be filled, change nothing} {--only= : hex,name_en (default: both)}';

    protected $description = 'Fill missing hex (from the photo) and English names (translated) in the farm colour catalogue';

    public function handle(ColorCatalog $catalog): int
    {
        $only = array_values(array_intersect(array_filter(array_map('trim', explode(',', (string) $this->option('only')))), ['hex', 'name_en']));
        if ($this->option('only') && ! $only) {
            $this->error('--only takes hex, name_en or both separated by a comma.');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $report = $catalog->fill($only, $dry);
        foreach ($report['hex'] as $r) {
            $this->line(sprintf('hex      %-44s %s%s', $r['code'], $r['hex'], $r['note'] ? '   (no single colour: check it)' : ''));
        }
        foreach ($report['name_en'] as $r) {
            $this->line(sprintf('name_en  %-44s %s → %s', $r['code'], $r['name'], $r['name_en']));
        }
        foreach ($report['skipped'] as $r) {
            $this->line(sprintf('<comment>skipped</comment>  %-44s %s', $r['code'], $r['why']));
        }
        $this->info(($dry ? '[dry-run] ' : '').sprintf('hex %d, name_en %d, skipped %d', count($report['hex']), count($report['name_en']), count($report['skipped'])));

        return self::SUCCESS;
    }
}
