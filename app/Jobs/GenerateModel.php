<?php

namespace App\Jobs;

use App\Domain\Generation\ModelNormalizer;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\DTO\GenerationHandle;
use App\Engines\DTO\GenerationOptions;
use App\Engines\DTO\GenerationStatus;
use App\Models\GenerationRequest;
use App\Models\ModelFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Text/photo → mesh through the configured ModelGenerator. Starts the remote task once, then re-queues itself
 * every few seconds until it finishes. The result enters the normal pipeline as a ModelFile (origin "generated").
 */
class GenerateModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 120;     // × 5 s ≈ 10 minutes

    public int $timeout = 300;

    public function __construct(public readonly int $requestId) {}

    public function handle(ModelGenerator $generator, ModelNormalizer $normalizer): void
    {
        $req = GenerationRequest::find($this->requestId);
        if (! $req || in_array($req->status, ['done', 'failed'], true)) {
            return;
        }

        try {
            if (! $req->external_id) {
                $options = new GenerationOptions(targetSizeMm: $req->target_mm ? (float) $req->target_mm : null);
                $views = array_map(fn ($rel) => Storage::disk('local')->path($rel), $req->photoPaths());
                $handle = match (true) {
                    $req->type !== 'image' => $generator->fromText((string) $req->prompt, $options),
                    count($views) > 1 => $generator->fromImages($views, $req->prompt, $options),
                    default => $generator->fromImage($views['front'] ?? Storage::disk('local')->path((string) $req->image_path), $req->prompt, $options),
                };
                $req->update(['external_id' => $handle->externalId, 'engine' => $handle->engine, 'status' => 'running', 'cost_cents' => (int) ($handle->meta['credits'] ?? $generator->estimatedCostCents())]);
            }

            $status = $generator->poll(new GenerationHandle((string) $req->engine, (string) $req->external_id));

            if ($status->state === GenerationStatus::FAILED) {
                $req->update(['status' => 'failed', 'error' => $status->error]);
                $this->forgetPhoto($req);

                return;
            }
            if ($status->state !== GenerationStatus::DONE) {
                $req->update(['progress' => max((int) $req->progress, min(99, $status->progress))]);
                if ($this->attempts() >= $this->tries - 1) {
                    $req->update(['status' => 'failed', 'error' => 'timeout']);

                    return;
                }
                $this->release(5);

                return;
            }

            // done: normalise to mm + Z-up and hand over to the standard file pipeline
            $uuid = (string) Str::uuid();
            $rel = 'files/'.$uuid.'/original.stl';
            $abs = Storage::disk(ModelFile::DISK)->path($rel);
            File::ensureDirectoryExists(dirname($abs));
            $kind = $req->description['kind'] ?? null;
            $options = in_array($kind, ['bust', 'figure'], true) ? ['clean', 'pedestal', 'solid'] : ['clean', 'solid'];
            $normalizer->toPrintableStl((string) $status->meshPath, $abs, (float) ($req->target_mm ?: config('ai.default_target_mm', 80)), str_ends_with(strtolower((string) $status->meshPath), '.glb'), $options, array_filter([
                'pedestal' => $req->description['pedestal'] ?? null,
                'name' => $req->description['pedestal_name'] ?? null,
                'dedication' => $req->description['pedestal_dedication'] ?? null,
                // busts do not always arrive facing the front; the name belongs under the face
                'front' => $kind === 'bust' ? 'auto' : null,
                // cut flat across the chest like a sculpted bust, instead of arms that end at the elbows
                'cut' => $kind === 'bust' ? 'bust' : null,
                'tidy' => in_array($kind, ['bust', 'figure'], true) ? true : null,
                // the closed figure without a base is kept: changing the base later needs no new generation
                'source_out' => in_array($kind, ['bust', 'figure'], true) ? dirname($abs).'/source.stl' : null,
            ]));
            // the paid result stays for a week next to the file: a better normalisation can be run again without new credits
            $raw = dirname($abs).'/raw.'.(strtolower(pathinfo((string) $status->meshPath, PATHINFO_EXTENSION)) ?: 'glb');
            if (! @rename((string) $status->meshPath, $raw)) {
                @unlink((string) $status->meshPath);
            }

            $name = Str::slug(Str::limit((string) ($req->description['name_en'] ?? $req->prompt ?? 'model'), 40, '')) ?: 'model';
            $file = ModelFile::create([
                'uuid' => $uuid, 'owner_user_id' => $req->owner_user_id, 'anonymous_session_id' => $req->anonymous_session_id,
                'original_name' => $name.'.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
                'storage_path' => $rel, 'origin' => 'generated', 'origin_ref' => $req->token, 'status' => ModelFile::STATUS_UPLOADED,
            ]);
            $req->update(['status' => 'done', 'progress' => 100, 'result_model_file_id' => $file->id]);
            $this->forgetPhoto($req);
            ProcessModelFile::dispatch($file->id);
        } catch (\Throwable $e) {
            Log::warning('GenerateModel failed', ['id' => $req->id, 'error' => $e->getMessage()]);
            $req->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            $this->forgetPhoto($req);
        }
    }

    /** Personal photos (figures, busts) are not kept: delete the files and the paths once the run is over. */
    private function forgetPhoto(GenerationRequest $req): void
    {
        if (empty($req->description['delete_photo']) || ! $req->photoPaths()) {
            return;
        }
        $req->forgetPhotos();
    }
}
