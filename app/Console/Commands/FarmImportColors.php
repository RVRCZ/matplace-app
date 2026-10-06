<?php

namespace App\Console\Commands;

use App\Domain\Farm\ColorCatalog;
use Illuminate\Console\Command;

/**
 * The colour catalogue from a spreadsheet (Google Sheets → File → Download → CSV; `;` between the columns, UTF-8,
 * the first row names the columns):
 *
 *   code;material;name;name_en;hex;in_stock;sort
 *   45_PLA_Matte_Olive;PLA matte;olivová;;;1;450
 *
 * code, material and name are required; an empty name_en or hex is left for `farm:colors-fill`. A row whose code
 * exists updates that colour, a new code creates one (offered, hex to be filled). Photos come separately:
 * `farm:import-photos` with folders named like the codes.
 *
 *   php artisan farm:import-colors spools.csv --dry-run     show the differences, change nothing
 *   php artisan farm:import-colors spools.csv
 */
class FarmImportColors extends Command
{
    protected $signature = 'farm:import-colors {csv : path of the CSV file} {--dry-run : show the differences, change nothing}';

    protected $description = 'Create or update farm colours from a CSV (code;material;name;name_en;hex;in_stock;sort)';

    public function handle(ColorCatalog $catalog): int
    {
        $dry = (bool) $this->option('dry-run');
        $report = $catalog->import((string) $this->argument('csv'), $dry);
        foreach ($report['created'] as $code) {
            $this->line("<info>new</info>      {$code}");
        }
        foreach ($report['updated'] as $u) {
            $this->line('changed  '.$u['code'].'  '.collect($u['changes'])->map(fn ($c, $k) => $k.': '.json_encode($c[0], JSON_UNESCAPED_UNICODE).' → '.json_encode($c[1], JSON_UNESCAPED_UNICODE))->implode(', '));
        }
        foreach ($report['errors'] as $error) {
            $this->line("<error>{$error}</error>");
        }
        $this->info(($dry ? '[dry-run] ' : '').sprintf('new %d, changed %d, unchanged %d, errors %d', count($report['created']), count($report['updated']), $report['same'], count($report['errors'])));

        return $report['errors'] && ! $report['created'] && ! $report['updated'] && ! $report['same'] ? self::FAILURE : self::SUCCESS;
    }
}
