<?php

namespace App\Console\Commands;

use App\Domain\Farm\ProfileLibrary;
use App\Models\FarmMaterial;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterMaterial;
use Illuminate\Console\Command;

/**
 * The "use on the other machines of the same type" button of the tuning page from the command line: one row's
 * settings onto every other machine with the same slicer machine profile and nozzle. The same rules as the button
 * (ProfileLibrary::spread): keys are added to what each row holds, a tuned row is left alone, changed rows go back
 * to "testing". Written for the day the farm's tuned rows went to every S1 (Roman, 17 Oct 2026).
 *
 *   php artisan farm:spread U2 PETG                          say what would change (process, filament and temps)
 *   php artisan farm:spread U1 PLA/silk --write              write it
 *   php artisan farm:spread U1 PLA+ --what=process --write   only the process group (the seam)
 *   php artisan farm:spread U3 PLA+ --write --note="Max → K3M"
 */
class FarmSpread extends Command
{
    protected $signature = 'farm:spread {printer : the code (U2) or key (kobra-s1-02) of the machine whose row is the source} {kind : the kind as code or code/finish (PETG, PLA/silk, PLA+)} {--what=process,filament,temps : the groups to carry over, comma separated} {--note= : a word for the history of the rows} {--write : write the changes (default: dry run)}';

    protected $description = 'One tuning row onto the other machines of the same type (the spread button of the tuning page)';

    public function handle(ProfileLibrary $library): int
    {
        $printer = FarmPrinter::where('code', $this->argument('printer'))->orWhere('key', $this->argument('printer'))->first();
        if (! $printer) {
            $this->error('No printer with the code or key "'.$this->argument('printer').'".');

            return self::FAILURE;
        }
        [$code, $finish] = array_pad(explode('/', (string) $this->argument('kind'), 2), 2, 'solid');
        $kind = FarmMaterial::where('code', $code)->where('finish', $finish)->first();
        if (! $kind) {
            $this->error("No kind {$code}/{$finish}.");

            return self::FAILURE;
        }
        $row = FarmPrinterMaterial::where('farm_printer_id', $printer->id)->where('farm_material_id', $kind->id)->whereNull('farm_color_id')->first();
        if (! $row) {
            $this->error("{$printer->name} has no row for {$kind->label()}.");

            return self::FAILURE;
        }
        $what = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('what')))));
        if (! $what || array_diff($what, ['process', 'filament', 'temps'])) {
            $this->error('--what takes process, filament and temps, comma separated.');

            return self::FAILURE;
        }
        $write = (bool) $this->option('write');
        $tag = $write ? '' : '[dry-run] ';
        $this->line(sprintf('source   %s %s v%d (%s): %s', $printer->name, $row->label(), $row->version, $row->status, json_encode($row->overrides, JSON_UNESCAPED_UNICODE)));
        $why = 'podle řádku #'.$row->id.' ('.$printer->name.'), farm:spread'.($this->option('note') ? ', '.$this->option('note') : '');
        $r = $library->spread($row, $what, $why, $write);
        if (! $r['take']) {
            $this->error('The row holds nothing of '.implode(', ', $what).'.');

            return self::FAILURE;
        }
        foreach ($r['done'] as $d) {
            $this->line(sprintf('row      %-14s %-16s v%d → v%d', $d['row']->printer->name, $d['row']->label(), $d['from'], $d['to']));
        }
        foreach ($r['same'] as $t) {
            $this->line(sprintf('same     %-14s %s', $t->printer->name, $t->label()));
        }
        foreach ($r['skipped'] as $t) {
            $this->line(sprintf('<comment>skipped</comment>  %-14s %s (tuned)', $t->printer->name, $t->label()));
        }
        $this->info($tag.sprintf('%d changed, %d already the same, %d tuned rows left alone', count($r['done']), count($r['same']), count($r['skipped'])));

        return self::SUCCESS;
    }
}
