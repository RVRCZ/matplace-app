<?php

namespace App\Jobs;

use App\Domain\Farm\FilamentFromShop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads one e-shop product page for the admin's filament card (TODO #71, docs/AL.md §21): fetches the page and its
 * pictures, asks Claude, and leaves the card in the status store FilamentFromShop keeps; the loading page asks for
 * the state every 2 s. On the interactive queue like the tools' jobs: a person is waiting for it.
 */
class ReadFilamentPage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public readonly string $jobId)
    {
        $this->onQueue(config('queue.interactive'));
    }

    public function handle(FilamentFromShop $agent): void
    {
        $agent->run($this->jobId);
    }
}
