<?php

namespace App\Jobs;

use App\Domain\Calculation\MaterialCatalog;
use App\Domain\Tools\ModelCheck;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\SliceParams;
use App\Models\DesignerModel;
use App\Models\ModelFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * A file uploaded to a portfolio card: wait until the upload pipeline has made an STL of it, run the model check
 * (the same one as the "check my model" tool) and, when nothing is wrong, slice it once with the farm's default
 * material for the card's size, weight and print time. Only then does the card get the file.
 * A file that fails the check is not used; the card says why.
 */
class PrepareDesignerFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 40;

    public int $timeout = 600;

    public int $backoff = 5;

    public function __construct(public readonly int $designerModelId, public readonly int $modelFileId) {}

    public function handle(Slicer $slicer): void
    {
        $card = DesignerModel::find($this->designerModelId);
        $file = ModelFile::find($this->modelFileId);
        if (! $card || ! $file) {
            return;
        }
        if ($file->status === ModelFile::STATUS_FAILED) {
            $this->refuse($card, [['level' => 'error', 'code' => 'unreadable', 'params' => []]]);

            return;
        }
        if (! $file->isReady()) {
            $this->release($this->backoff);

            return;
        }

        $report = ModelCheck::report($file);
        $errors = array_values(array_filter($report['items'], fn (array $i) => $i['level'] === 'error'));
        if ($errors) {
            $this->refuse($card, $report['items']);

            return;
        }

        $material = app(MaterialCatalog::class)->defaultCode();
        $summary = ['material' => $material, 'quality' => 'standard', 'dims' => $file->bbox, 'grams' => null, 'minutes' => null];
        try {
            $slice = $slicer->slice($file->absoluteStlPath(), SliceParams::fromArray(['material' => $material, 'quality' => 'standard', 'infill' => 15]));
            $summary = ['dims' => $slice->dims->toArray(), 'grams' => round($slice->grams, 1), 'minutes' => $slice->minutes, 'supports' => $slice->supportsUsed] + $summary;
        } catch (\Throwable $e) {
            // the check passed, so the card may be printed; the numbers come later (admin can slice it again)
            Log::warning('Designer file could not be sliced for its summary', ['card' => $card->id, 'error' => $e->getMessage()]);
        }
        $card->forceFill(['model_file_id' => $file->id, 'file_status' => DesignerModel::FILE_READY, 'file_check' => ['items' => $report['items']], 'slice_summary' => $summary,
            'max_mm' => (int) ceil(max(array_map('floatval', array_values((array) $summary['dims'])) ?: [0])),
        ])->save();
    }

    public function failed(?\Throwable $e): void
    {
        if ($card = DesignerModel::find($this->designerModelId)) {
            $this->refuse($card, [['level' => 'error', 'code' => 'unreadable', 'params' => []]]);
        }
    }

    /** @param  list<array{level: string, code: string, params: array<string, string>}>  $items */
    private function refuse(DesignerModel $card, array $items): void
    {
        // a card that already had a good file keeps it (and says the new one was turned down); a card without one is marked as failed
        if ($card->model_file_id) {
            $card->forceFill(['file_status' => DesignerModel::FILE_READY, 'file_check' => ['rejected' => $items] + (array) $card->file_check])->save();

            return;
        }
        $card->forceFill(['file_status' => DesignerModel::FILE_FAILED, 'file_check' => ['items' => $items], 'author_confirmed_at' => null])->save();
    }
}
