<?php

namespace App\Console\Commands;

use App\Domain\Farm\AgentService;
use Illuminate\Console\Command;

/** Every minute: printers whose agent went silent become offline, their running jobs "unknown", an admin is told once. */
class FarmWatch extends Command
{
    protected $signature = 'farm:watch';

    protected $description = 'Mark farm printers without a heartbeat as offline and alert the admin';

    public function handle(AgentService $agents): int
    {
        $n = $agents->watch();
        if ($n > 0) {
            $this->info("{$n} printer(s) reported offline.");
        }

        return self::SUCCESS;
    }
}
