<?php

namespace App\Engines\Generator;

use App\Engines\Contracts\ImageRestyler;
use App\Engines\Contracts\ModelGenerator;
use App\Engines\DTO\GenerationHandle;
use App\Engines\DTO\GenerationOptions;
use App\Engines\DTO\GenerationStatus;
use App\Engines\Exceptions\GenerationException;
use App\Support\AiUsage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Tripo (VAST) API V3 — https://openapi.tripo3d.ai/v3. V2 is retired on 2026-11-01, so only V3 is used.
 *
 *   POST /v3/files                        multipart "file"            → data.file_token
 *   POST /v3/generation/image-to-model    {file:{type,file_token}, model, texture:false, pbr:false, face_limit}
 *   POST /v3/generation/text-to-model     {prompt, model, texture:false, pbr:false, face_limit}
 *   POST /v3/generation/multiview-to-model {files:[front,left,back,right], …}  front required, min. 2 views; missing view = {}
 *   GET  /v3/tasks/{id}                   → data.status queued|running|success|failed|cancelled|banned, progress, output.model_url (GLB, link valid ~5 min)
 *   POST /v3/generation/image-to-image    {input: file_token, prompt, model: seedream_v5} → output.generated_image_url (PNG); 5 credits
 *
 * Geometry only (no textures): 20 credits per image, 10 per text (1 credit = $0.01); detailed geometry 20 more.
 * There is no `style` for a model in V3 (sent anyway it costs 5 credits and changes nothing, tried 10 Oct 2026):
 * another look comes from redrawing the photo first (restyle()) and making the model of that picture.
 * The result is a unit-sized, Y-up GLB: scaling to millimetres and Z-up happens in ModelNormalizer.
 */
final class TripoGenerator implements ImageRestyler, ModelGenerator
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'tripo';
    }

    public function estimatedCostCents(): int
    {
        return 20;
    }

    public function fromImage(string $imagePath, ?string $hint, GenerationOptions $options): GenerationHandle
    {
        return $this->create('/v3/generation/image-to-model', [
            'file' => $this->upload($imagePath),
        ] + $this->common($options), 20 + $this->detailCredits($options));
    }

    /** Front + up to three more sides of the same subject: the "files" array is always [front, left, back, right]. */
    public function fromImages(array $views, ?string $hint, GenerationOptions $options): GenerationHandle
    {
        if (empty($views['front'])) {
            throw new GenerationException('The front view is required.');
        }
        $views = array_filter(array_intersect_key($views, array_flip(self::VIEWS)));
        if (count($views) < 2) {
            return $this->fromImage($views['front'], $hint, $options);
        }
        $files = [];
        foreach (self::VIEWS as $view) {
            // a missing side is an empty descriptor; Tripo fills it in from the others
            $files[] = isset($views[$view]) ? $this->upload($views[$view]) : (object) [];
        }

        return $this->create('/v3/generation/multiview-to-model', ['files' => $files] + $this->common($options), 20 + $this->detailCredits($options));
    }

    private const VIEWS = ['front', 'left', 'back', 'right'];

    /** @return array{type: string, file_token: string} */
    private function upload(string $imagePath): array
    {
        if (! is_file($imagePath)) {
            throw new GenerationException('Image not found: '.$imagePath);
        }
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION)) ?: 'jpg';
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        if (! in_array($ext, ['jpg', 'png', 'webp'], true)) {
            throw new GenerationException('Unsupported image type for generation: '.$ext);
        }

        $up = $this->client()->attach('file', (string) file_get_contents($imagePath), 'photo.'.$ext)->post($this->url('/v3/files'));
        $token = $up->json('data.file_token');
        if (! $up->ok() || ! $token) {
            throw new GenerationException('Tripo upload failed: '.$this->err($up->json(), $up->status()));
        }

        return ['type' => $ext, 'file_token' => $token];
    }

    public function fromText(string $prompt, GenerationOptions $options): GenerationHandle
    {
        $prompt = trim($prompt);
        if (mb_strlen($prompt) < 3) {
            throw new GenerationException('Prompt is too short.');
        }

        return $this->create('/v3/generation/text-to-model', ['prompt' => mb_substr($prompt, 0, 1000)] + $this->common($options), 10 + $this->detailCredits($options));
    }

    /** The photo redrawn by the image model of Tripo itself (the photo goes to no other provider). */
    public function restyle(string $imagePath, string $prompt): GenerationHandle
    {
        $file = $this->upload($imagePath);

        return $this->create('/v3/generation/image-to-image', ['input' => $file['file_token'], 'prompt' => mb_substr($prompt, 0, 1800), 'model' => $this->config['image_model'] ?? 'seedream_v5'], 5, 'tripo-image');
    }

    public function pollImage(GenerationHandle $handle): GenerationStatus
    {
        $res = $this->client()->get($this->url('/v3/tasks/'.$handle->externalId));
        if (! $res->ok()) {
            return new GenerationStatus(GenerationStatus::RUNNING, error: 'HTTP '.$res->status());
        }
        $d = $res->json('data') ?? [];
        $status = (string) ($d['status'] ?? '');
        if (in_array($status, ['queued', 'running'], true)) {
            return new GenerationStatus($status === 'queued' ? GenerationStatus::QUEUED : GenerationStatus::RUNNING, progress: (int) ($d['progress'] ?? 0));
        }
        $url = $d['output']['generated_image_url'] ?? null;
        if ($status !== 'success' || ! $url) {
            return new GenerationStatus(GenerationStatus::FAILED, error: 'tripo_image_'.($status === 'success' ? 'no_url' : ($status ?: 'unknown')));
        }
        $dir = rtrim($this->config['work_dir'], '/');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/'.Str::uuid().'.png';
        $dl = Http::timeout(120)->sink($path)->get($url);
        if (! $dl->ok() || ! is_file($path) || filesize($path) < 100) {
            @unlink($path);

            return new GenerationStatus(GenerationStatus::RUNNING, error: 'download_retry', progress: 99);
        }

        return new GenerationStatus(GenerationStatus::DONE, previewPath: $path, progress: 100);
    }

    public function poll(GenerationHandle $handle): GenerationStatus
    {
        $res = $this->client()->get($this->url('/v3/tasks/'.$handle->externalId));
        if (! $res->ok()) {
            // transient API trouble: report "running" so the job retries instead of failing the request
            return new GenerationStatus(GenerationStatus::RUNNING, error: 'HTTP '.$res->status());
        }
        $d = $res->json('data') ?? [];
        $status = (string) ($d['status'] ?? '');
        $progress = (int) ($d['progress'] ?? 0);

        if (in_array($status, ['queued', 'running'], true)) {
            return new GenerationStatus($status === 'queued' ? GenerationStatus::QUEUED : GenerationStatus::RUNNING, progress: $progress);
        }
        if ($status !== 'success') {
            return new GenerationStatus(GenerationStatus::FAILED, error: 'tripo_'.($status ?: 'unknown'), progress: $progress);
        }

        $url = $d['output']['model_url'] ?? $d['output']['base_model_url'] ?? null;
        if (! $url) {
            return new GenerationStatus(GenerationStatus::FAILED, error: 'tripo_no_model_url');
        }
        // the signed link expires within minutes: download right away
        $dir = rtrim($this->config['work_dir'], '/');
        File::ensureDirectoryExists($dir);
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'glb';
        $path = $dir.'/'.Str::uuid().'.'.$ext;
        $dl = Http::timeout(180)->sink($path)->get($url);
        if (! $dl->ok() || ! is_file($path) || filesize($path) < 100) {
            @unlink($path);

            return new GenerationStatus(GenerationStatus::RUNNING, error: 'download_retry', progress: 99);
        }

        return new GenerationStatus(GenerationStatus::DONE, meshPath: $path, previewPath: null, progress: 100);
    }

    private function create(string $path, array $body, int $credits, ?string $priced = null): GenerationHandle
    {
        $res = $this->client()->asJson()->post($this->url($path), $body);
        $id = $res->json('data.task_id');
        if (! $res->ok() || ! $id) {
            throw new GenerationException('Tripo task failed: '.$this->err($res->json(), $res->status()));
        }
        // one task = one flat price (config/ai.php prices.models): a picture, a model in standard geometry, or the detailed one
        AiUsage::record('generate', $priced ?? (($body['geometry_quality'] ?? null) === 'standard' ? 'tripo-standard' : 'tripo'), []);

        return new GenerationHandle($this->name(), (string) $id, ['credits' => $credits, 'model' => $body['model'] ?? null]);
    }

    /** Geometry only, bounded triangle count (the adaptive default can exceed a million faces). */
    private function common(?GenerationOptions $options = null): array
    {
        return array_filter([
            'model' => $this->config['model'],
            'texture' => false,
            'pbr' => false,
            // UV unwrapping cuts the surface into islands; for printing that means hundreds of open patches
            'export_uv' => false,
            'geometry_quality' => $this->quality($options),
            'enable_image_autofix' => (bool) ($this->config['image_autofix'] ?? false),
            // 0 = let the generator decide; we simplify ourselves, exactly and without tearing the mesh
            'face_limit' => (int) $this->config['face_limit'] ?: null,
        ], fn ($v) => $v !== null);
    }

    /** Detailed geometry costs 20 credits on top. */
    private function detailCredits(?GenerationOptions $options = null): int
    {
        return $this->quality($options) === 'detailed' ? 20 : 0;
    }

    /** What the request asks for (a figure that is smoothed anyway needs no fine fur), else what is configured. */
    private function quality(?GenerationOptions $options): string
    {
        $asked = $options?->geometryQuality;

        return in_array($asked, ['standard', 'detailed'], true) ? $asked : (string) ($this->config['geometry_quality'] ?? 'standard');
    }

    private function client(): PendingRequest
    {
        if (empty($this->config['api_key'])) {
            throw new GenerationException('TRIPO_API_KEY is not set.');
        }

        return Http::timeout(90)->withToken($this->config['api_key'])->acceptJson();
    }

    private function url(string $path): string
    {
        return rtrim($this->config['base_url'], '/').$path;
    }

    private function err(?array $json, int $status): string
    {
        return mb_substr((string) ($json['message'] ?? ('HTTP '.$status)), 0, 300);
    }
}
