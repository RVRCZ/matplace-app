<?php

namespace App\Console\Commands;

use App\Engines\Contracts\ProjectExporter;
use App\Engines\Project\OrcaProjectExporter;
use Illuminate\Console\Command;

/**
 * Builds the list of printers a 3MF project can be exported for, from the vendor presets shipped with OrcaSlicer.
 * Run after installing or upgrading OrcaSlicer:  php artisan matplace:printer-catalog --verify
 */
class PrinterCatalog extends Command
{
    protected $signature = 'matplace:printer-catalog {--verify : Test-export a small cube for every printer and keep only those that work}';

    protected $description = 'Rebuild the printer catalogue for 3MF project export';

    public function handle(ProjectExporter $exporter): int
    {
        if (! $exporter instanceof OrcaProjectExporter) {
            $this->warn('The active project exporter has a fixed printer list; nothing to build.');

            return self::SUCCESS;
        }
        $verify = (bool) $this->option('verify');
        $n = 0;
        $r = $exporter->rebuildCatalog($verify, function (string $id) use (&$n) {
            if (++$n % 25 === 0) {
                $this->line("  {$n} checked…");
            }
        });
        $this->info("Printers in the catalogue: {$r['printers']}".($verify ? ', dropped: '.count($r['dropped']) : ' (not verified)'));
        if ($r['dropped']) {
            $this->line('Dropped: '.implode(', ', $r['dropped']));
        }

        return self::SUCCESS;
    }
}
