<?php

namespace App\Jobs;

use App\Domain\Generation\ModelNormalizer;
use App\Engines\Contracts\ImageRestyler;
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
 *
 * A cartoon pet figurine takes two tasks: the photo is redrawn as a toy-like figure first (ImageRestyler), then the
 * model is made of that picture. `description.stage` says which of the two is running.
 */
class GenerateModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 120;     // × 5 s ≈ 10 minutes

    public int $timeout = 300;

    /**
     * What the photo of a pet becomes before the cartoon model is made of it. The thick legs and tail are the point:
     * they are what breaks off a print.
     */
    public const CARTOON_PROMPT = 'Turn this pet into a cute stylized cartoon character figurine: big head, large friendly eyes, simplified rounded shapes, '
        .'thick sturdy legs and tail, smooth surface, full body, same pose and breed, plain white background, 3D toy look';

    public function __construct(public readonly int $requestId) {}

    public function handle(ModelGenerator $generator, ModelNormalizer $normalizer): void
    {
        $req = GenerationRequest::find($this->requestId);
        if (! $req || in_array($req->status, ['done', 'failed'], true)) {
            return;
        }

        try {
            $kind = $req->description['kind'] ?? null;
            $style = $kind === 'pet' ? ($req->description['style'] ?? 'realistic') : null;
            if (! $req->external_id && $style === 'cartoon' && $generator instanceof ImageRestyler && empty($req->description['stage'])) {
                // first task: the photo redrawn as a cartoon figure
                $handle = $generator->restyle(Storage::disk('local')->path((string) $req->image_path), self::CARTOON_PROMPT);
                $req->update(['external_id' => $handle->externalId, 'engine' => $handle->engine, 'status' => 'running',
                    'cost_cents' => (int) ($handle->meta['credits'] ?? 5), 'description' => ['stage' => 'image'] + $req->description]);
            }
            if (($req->description['stage'] ?? null) === 'image' && $generator instanceof ImageRestyler) {
                $drawn = $generator->pollImage(new GenerationHandle((string) $req->engine, (string) $req->external_id));
                if ($drawn->state === GenerationStatus::FAILED) {
                    $req->update(['status' => 'failed', 'error' => $drawn->error]);
                    $this->forgetPhoto($req);

                    return;
                }
                if ($drawn->state !== GenerationStatus::DONE) {
                    // the picture is the first third of the way
                    $req->update(['progress' => max((int) $req->progress, min(30, (int) round($drawn->progress * 0.3)))]);
                    $this->waitOrGiveUp($req);

                    return;
                }
                // the picture takes the place of the photo (and of the other sides: the model is made of it alone);
                // it is a picture of the customer's pet, so it is deleted with the photos when the run ends
                $rel = 'photos/figures/'.Str::uuid().'.png';
                Storage::disk('local')->put($rel, (string) file_get_contents((string) $drawn->previewPath));
                @unlink((string) $drawn->previewPath);
                Storage::disk('local')->delete(array_values($req->photoPaths()));
                $req->update(['image_path' => $rel, 'views' => null, 'external_id' => null, 'progress' => 30, 'description' => ['stage' => 'model'] + $req->description]);
            }
            if (! $req->external_id) {
                // a figure that is smoothed anyway (the miniature, the cartoon) needs no fine fur: standard geometry is half the price
                $options = new GenerationOptions(targetSizeMm: $req->target_mm ? (float) $req->target_mm : null, geometryQuality: in_array($style, ['miniature', 'cartoon'], true) ? 'standard' : null);
                $views = array_map(fn ($rel) => Storage::disk('local')->path($rel), $req->photoPaths());
                $handle = match (true) {
                    $req->type !== 'image' => $generator->fromText((string) $req->prompt, $options),
                    count($views) > 1 => $generator->fromImages($views, $req->prompt, $options),
                    default => $generator->fromImage($views['front'] ?? Storage::disk('local')->path((string) $req->image_path), $req->prompt, $options),
                };
                // the picture of a cartoon figure was paid already: the model comes on top of it
                $req->update(['external_id' => $handle->externalId, 'engine' => $handle->engine, 'status' => 'running', 'cost_cents' => (int) ($handle->meta['credits'] ?? $generator->estimatedCostCents()) + (($req->description['stage'] ?? null) === 'model' ? (int) $req->cost_cents : 0)]);
            }

            $status = $generator->poll(new GenerationHandle((string) $req->engine, (string) $req->external_id));

            if ($status->state === GenerationStatus::FAILED) {
                $req->update(['status' => 'failed', 'error' => $status->error]);
                $this->forgetPhoto($req);

                return;
            }
            if ($status->state !== GenerationStatus::DONE) {
                // after the picture of a cartoon figure the model is the other two thirds of the way
                $shown = ($req->description['stage'] ?? null) === 'model' ? 30 + (int) round($status->progress * 0.69) : $status->progress;
                $req->update(['progress' => max((int) $req->progress, min(99, $shown))]);
                $this->waitOrGiveUp($req);

                return;
            }

            // done: normalise to mm + Z-up and hand over to the standard file pipeline
            $uuid = (string) Str::uuid();
            $rel = 'files/'.$uuid.'/original.stl';
            $abs = Storage::disk(ModelFile::DISK)->path($rel);
            File::ensureDirectoryExists(dirname($abs));
            $options = in_array($kind, ['bust', 'figure', 'pet'], true) ? ['clean', 'pedestal', 'solid'] : ['clean', 'solid'];
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
                'source_out' => in_array($kind, ['bust', 'figure', 'pet'], true) ? dirname($abs).'/source.stl' : null,
                // a pet stands on four thin legs: its own bases, its thinnest place measured, the miniature grown (pet_kind.py)
                'pet' => $kind === 'pet' ? true : null,
                'style' => $style,
                'roughness' => $style === 'miniature' ? (int) ($req->description['roughness'] ?? 2) : null,
                'name_side' => $kind === 'pet' ? ($req->description['name_side'] ?? null) : null,
            ]));
            // what was measured on a pet figurine stays with the request: the page warns about a thin leg or tail
            $measured = $kind === 'pet' ? array_intersect_key($normalizer->report, array_flip(['thinnest_mm', 'thinnest_before_mm', 'grown_mm', 'base_mm', 'pedestal', 'engraved_lines', 'stand_error', 'pet_error'])) : [];
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
            $req->update(['status' => 'done', 'progress' => 100, 'result_model_file_id' => $file->id] + ($measured ? ['description' => $measured + $req->description] : []));
            $this->forgetPhoto($req);
            ProcessModelFile::dispatch($file->id);
        } catch (\Throwable $e) {
            Log::warning('GenerateModel failed', ['id' => $req->id, 'error' => $e->getMessage()]);
            $req->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            $this->forgetPhoto($req);
        }
    }

    /** Not ready yet: look again in five seconds, for ten minutes at most. */
    private function waitOrGiveUp(GenerationRequest $req): void
    {
        if ($this->attempts() >= $this->tries - 1) {
            $req->update(['status' => 'failed', 'error' => 'timeout']);
            $this->forgetPhoto($req);

            return;
        }
        $this->release(5);
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
