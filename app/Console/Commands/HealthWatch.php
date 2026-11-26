<?php

namespace App\Console\Commands;

use App\Domain\Ops\Health;
use App\Domain\Ops\HealthChecks;
use Illuminate\Console\Command;

/**
 * The server checks (docs/AU.md): run, remember, and mail the owner about what changed. Every ten minutes from the
 * scheduler; `--print` only shows the answers.
 *
 *   php artisan matplace:health [--print]
 */
class HealthWatch extends Command
{
    protected $signature = 'matplace:health {--print : show the checks, remember and mail nothing}';

    protected $description = 'Check the server and tell the owner when something changed';

    public function handle(Health $health, HealthChecks $checks): int
    {
        if ($this->option('print')) {
            $rows = $checks->run();
        } else {
            $r = $health->run();
            $rows = $r['checks'];
            if ($r['changes'] !== []) {
                $this->line(count($r['changes']).' change(s)'.($r['alerted'] ? ', owner mailed' : ', nobody to mail (farm setting alert_email)'));
            }
        }
        foreach ($rows as $c) {
            $this->line(($c['ok'] ? 'OK   ' : 'FAIL ').str_pad(HealthChecks::label($c['key']), 24).$c['message']);
        }

        return in_array(false, array_column($rows, 'ok'), true) ? self::FAILURE : self::SUCCESS;
    }
}
