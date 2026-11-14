<?php

namespace App\Jobs;

use App\Domain\Generation\ModelNormalizer;
use App\Engines\Contracts\ImageRestyler;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\DTO\GenerationHandle;
use App\Engines\DTO\GenerationOptions;
use App\Engines\DTO\GenerationStatus;
use App\Engines\Generator\MeshyGenerator;
use App\Engines\Generator\TripoGenerator;
use App\Mail\GenerationReady;
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

    /**
     * What the photo of a pet becomes before a generator without its own clean-up (Meshy) makes the model: the animal
     * alone. A hand on the tail, a leash or a shoe behind the legs would otherwise be modelled with it (tried 14 Nov 2026).
     */
    public const CLEAN_PROMPT = 'Photorealistic photo of exactly this same animal, unchanged: same breed, same coat pattern and colours, same pose, same proportions. '
        .'Show the whole animal alone, complete from head to paws and tail. Remove every person, hand, leg, shoe, leash, furniture, cushion, blanket and other object; '
        .'where something covered a part of the animal, restore that part naturally. Plain white studio background, soft even light, no shadow on the ground, no text.';

    /**
     * The same for a portrait (Roman, 14 Nov 2026: "budeme muset čistit i bustu"): the person alone, the face untouched.
     * Tried on three portraits: children round a woman, a garden and a studio backdrop went, the faces stayed. Nothing
     * the person wears is named here: an earlier wording that said "the glasses" drew glasses on a woman who had none.
     * Furniture is named because the back of an armchair became part of Roman's own bust; the eyes and the upright
     * pose are for a photo of somebody who has died, which people will send: nothing in the face may be made up.
     */
    public const CLEAN_BUST_PROMPT = 'Photorealistic photo of exactly this same person, unchanged: keep the face, its features and proportions, the expression, the age, '
        .'the hair, the head pose and everything the person wears precisely as they are; add nothing, do not beautify, do not change the identity. '
        .'Keep the eyes exactly as they are, open or closed. '
        .'Show this one person alone from the chest up, the whole head and both shoulders in the picture. '
        .'If the person is lying down or leaning back, show them upright as in a portrait, with the face itself unchanged. '
        .'Remove the background, every other person, all furniture (a chair back, a headrest, a sofa, a pillow, bedding), objects in front of the body, text and logos; '
        .'where something covered the shoulders or chest, restore them naturally with the same clothing. '
        .'Plain white studio background, soft even front light, no hard shadows.';

    public function __construct(public readonly int $requestId) {}

    /** Who redraws a photo: the generator itself, or Tripo's image model beside a generator that draws no pictures (Meshy). */
    private function restyler(ModelGenerator $generator): ?ImageRestyler
    {
        if ($generator instanceof ImageRestyler) {
            return $generator;
        }

        return $generator instanceof MeshyGenerator && config('ai.tripo.api_key') ? new TripoGenerator((array) config('ai.tripo')) : null;
    }

    /** AI_CLEAN_PHOTO: always (the default) | never | auto (= with Meshy only; Tripo's own `enable_image_autofix` left a chair back in a bust). */
    private function cleansPhoto(ModelGenerator $generator): bool
    {
        return match ((string) config('ai.clean_photo', 'always')) {
            'always' => true,
            'never' => false,
            default => $generator instanceof MeshyGenerator,
        };
    }

    public function handle(ModelGenerator $generator, ModelNormalizer $normalizer): void
    {
        $req = GenerationRequest::find($this->requestId);
        if (! $req || in_array($req->status, ['done', 'failed'], true)) {
            return;
        }

        try {
            $kind = $req->description['kind'] ?? null;
            $style = $kind === 'pet' ? ($req->description['style'] ?? 'realistic') : null;
            $restyler = $this->restyler($generator);
            // the cartoon is drawn of whatever was sent; a realistic pet or a bust is cleaned only when one photo is all there is
            // (the redrawn picture replaces the photos, and the other sides say more than a clean front)
            $redraw = match (true) {
                $style === 'cartoon' => self::CARTOON_PROMPT,
                in_array($kind, ['pet', 'bust'], true) && $req->type === 'image' && count($req->photoPaths()) <= 1 && $this->cleansPhoto($generator)
                    => $kind === 'pet' ? self::CLEAN_PROMPT : self::CLEAN_BUST_PROMPT,
                default => null,
            };
            if (! $req->external_id && $redraw && $restyler && empty($req->description['stage'])) {
                // first task: the photo redrawn (as a cartoon figure, or as the animal alone)
                $handle = $restyler->restyle(Storage::disk('local')->path((string) $req->image_path), $redraw);
                $req->update(['external_id' => $handle->externalId, 'engine' => $handle->engine, 'status' => 'running',
                    'cost_cents' => (int) ($handle->meta['credits'] ?? 5), 'description' => ['stage' => 'image'] + $req->description]);
            }
            if (($req->description['stage'] ?? null) === 'image' && $restyler) {
                $drawn = $restyler->pollImage(new GenerationHandle((string) $req->engine, (string) $req->external_id));
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
                // every figure takes the geometry the generator is set up with (detailed). AI_SMOOTH_FIGURES_STANDARD=true
                // brings back the half-price standard geometry for the figures that are smoothed anyway (miniature, cartoon)
                $cheap = config('ai.smooth_figures_standard') && in_array($style, ['miniature', 'cartoon'], true);
                $options = new GenerationOptions(targetSizeMm: $req->target_mm ? (float) $req->target_mm : null, geometryQuality: $cheap ? 'standard' : null);
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
            GenerationReady::sendFor($req);   // "send me the result": the one mail with the link, if the visitor asked for it
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
