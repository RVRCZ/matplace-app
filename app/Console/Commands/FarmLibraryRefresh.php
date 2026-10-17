<?php

namespace App\Console\Commands;

use App\Domain\Farm\ProfileLibrary;
use Illuminate\Console\Command;

/**
 * Brings the tuning rows nobody has touched up to what the profile library says today. ProfileLibrary::sync only
 * creates rows that are missing and never changes one that exists, so a row made while its machine's model said
 * "Printer Cr" (17 Oct 2026: eleven machines, two of them with nothing in their rows at all) stayed empty for good.
 * Rows with status testing or tuned, and rows somebody edited by hand, are never touched: the spread button on the
 * tuning page is for those.
 *
 *   php artisan farm:library-refresh            say what would change, change nothing
 *   php artisan farm:library-refresh --write    write it (missing rows are created first)
 *   php artisan farm:library-refresh --models   also put the slicer profile's machine name into `model` where the
 *                                               free text does not say it ("Printer Cr" → "Anycubic Kobra S1")
 */
class FarmLibraryRefresh extends Command
{
    protected $signature = 'farm:library-refresh {--write : write the changes (default: dry run)} {--models : also correct the free-text model of the printers from their slicer profile}';

    protected $description = 'Bring the untested, unedited tuning rows up to the profile library; optionally correct the printers\' model text';

    public function handle(ProfileLibrary $library): int
    {
        $write = (bool) $this->option('write');
        $tag = $write ? '' : '[dry-run] ';
        if ($this->option('models')) {
            $fixed = $library->fixModels($write);
            foreach ($fixed as $line) {
                $this->line('model    '.$line);
            }
            $this->info($tag.sprintf('models: %d corrected', count($fixed)));
        }
        $created = $write ? $library->sync() : 0;
        $report = $library->refresh($write);
        foreach ($report['changed'] as $line) {
            $this->line('row      '.$line);
        }
        $this->info($tag.sprintf('rows: %d created, %d changed, %d already current, %d left alone (tested, tuned or edited by hand)', $created, count($report['changed']), $report['same'], $report['skipped']));

        return self::SUCCESS;
    }
}
