<?php

namespace App\Jobs;

use App\Domain\Designer\PortfolioImporter;
use App\Models\DesignerImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * One model of a designer's import: card, pictures, translation. The models of one import go one after another
 * (the three workers would otherwise hit the source site in parallel).
 */
class ImportDesignerModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 20;

    public int $timeout = 180;

    public function __construct(public readonly int $importId, public readonly int $index) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('designer-import-'.$this->importId))->releaseAfter(5)->expireAfter(300)];
    }

    public function handle(PortfolioImporter $importer): void
    {
        if ($import = DesignerImport::find($this->importId)) {
            $importer->importItem($import, $this->index);
        }
    }

    /** The queue gave up on the item (a worker died, a lock never came free): the import must still be able to finish. */
    public function failed(?\Throwable $e): void
    {
        DesignerImport::find($this->importId)?->finishItem($this->index, DesignerImport::ITEM_FAILED, 'unreadable');
    }
}
