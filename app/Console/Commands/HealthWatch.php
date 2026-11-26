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

        // a failing check is the mail's business, not the scheduler's: an exit code 1 would land in the log as an ERROR,
        // and the "errors in the log" check would then report the check itself (26 Nov 2026, every ten minutes)
        return $this->option('print') && in_array(false, array_column($rows, 'ok'), true) ? self::FAILURE : self::SUCCESS;
    }
}
