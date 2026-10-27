<?php

namespace App\Jobs;

use App\Domain\Farm\PrintPhotos;
use App\Engines\Exceptions\EngineException;
use App\Models\FarmOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * A photo of the finished print arrived (the admin's upload, farm:photo): the background goes, the piece lands on its
 * backdrop in every size (PrintPhotos), and the videos of the print are built again so they end with it. rembg takes a
 * few seconds a photo and the videos minutes: a worker's job, never the request.
 */
class ComposePrintPhoto implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /** @param  string  $sourceRel  the photo as uploaded, on the farm disk */
    public function __construct(public readonly int $orderId, public readonly string $sourceRel, public readonly ?string $bg = null) {}

    public function handle(PrintPhotos $photos): void
    {
        $order = FarmOrder::find($this->orderId);
        $disk = Storage::disk(config('farm.disk'));
        if (! $order || ! $disk->exists($this->sourceRel)) {
            return;
        }
        try {
            $photos->attach($order, $disk->path($this->sourceRel), $this->bg);
        } catch (EngineException $e) {
            Log::warning('Print photo could not be composed', ['order' => $order->id, 'error' => $e->getMessage()]);

            return;
        }
        // the Short and the 16:9 end with the finished piece: built again with the photo, uploaded copies replaced
        if ($order->timelapse_path) {
            AddFinishPhotoToVideo::dispatch($order->id);
        }
    }
}
