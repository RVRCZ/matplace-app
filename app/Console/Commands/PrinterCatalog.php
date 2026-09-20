<?php

namespace App\Console\Commands;

use App\Engines\Contracts\ProjectExporter;
use App\Engines\Project\CompositeProjectExporter;
use App\Engines\Project\OrcaProjectExporter;
use App\Engines\Project\PrusaProjectExporter;
use Illuminate\Console\Command;

/**
 * Builds the list of printers a 3MF project can be exported for, from the vendor presets shipped with OrcaSlicer.
 * Run after installing or upgrading OrcaSlicer:  php artisan matplace:printer-catalog --verify
 */
class PrinterCatalog extends Command
{
    protected $signature = 'matplace:printer-catalog {--verify : Test-export a small cube for every printer and keep only those that work} {--no-download : Keep the Prusa vendor bundle that is already on disk} {--only-prusa : Refresh only the PrusaSlicer list, leave the verified OrcaSlicer catalogue as it is}';

    protected $description = 'Rebuild the printer catalogue for 3MF project export';

    public function handle(ProjectExporter $exporter): int
    {
        if ($exporter instanceof CompositeProjectExporter) {
            foreach ($exporter->exporters() as $e) {
                if ($e instanceof PrusaProjectExporter) {
                    $r = $e->rebuildCatalog(! $this->option('no-download'));
                    $this->info("PrusaSlicer printers: {$r['printers']}".($r['version'] ? " (bundle {$r['version']})" : ' (bundle on disk)'));
                }
                if ($e instanceof OrcaProjectExporter) {
                    $exporter = $e;
                }
            }
        }
        if ($this->option('only-prusa')) {
            return self::SUCCESS;
        }
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
