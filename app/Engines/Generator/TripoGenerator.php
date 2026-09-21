<?php

namespace App\Engines\Generator;

use App\Engines\Contracts\ModelGenerator;
use App\Engines\DTO\GenerationHandle;
use App\Engines\DTO\GenerationOptions;
use App\Engines\DTO\GenerationStatus;
use App\Engines\Exceptions\GenerationException;
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
 *   GET  /v3/tasks/{id}                   → data.status queued|running|success|failed|cancelled|banned, progress, output.model_url (GLB, link valid ~5 min)
 *
 * Geometry only (no textures): 20 credits per image, 10 per text (1 credit = $0.01).
 * The result is a unit-sized, Y-up GLB: scaling to millimetres and Z-up happens in ModelNormalizer.
 */
final class TripoGenerator implements ModelGenerator
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

        return $this->create('/v3/generation/image-to-model', [
            'file' => ['type' => $ext, 'file_token' => $token],
        ] + $this->common(), 20 + $this->detailCredits());
    }

    public function fromText(string $prompt, GenerationOptions $options): GenerationHandle
    {
        $prompt = trim($prompt);
        if (mb_strlen($prompt) < 3) {
            throw new GenerationException('Prompt is too short.');
        }

        return $this->create('/v3/generation/text-to-model', ['prompt' => mb_substr($prompt, 0, 1000)] + $this->common(), 10 + $this->detailCredits());
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

    private function create(string $path, array $body, int $credits): GenerationHandle
    {
        $res = $this->client()->asJson()->post($this->url($path), $body);
        $id = $res->json('data.task_id');
        if (! $res->ok() || ! $id) {
            throw new GenerationException('Tripo task failed: '.$this->err($res->json(), $res->status()));
        }

        return new GenerationHandle($this->name(), (string) $id, ['credits' => $credits, 'model' => $body['model'] ?? null]);
    }

    /** Geometry only, bounded triangle count (the adaptive default can exceed a million faces). */
    private function common(): array
    {
        return array_filter([
            'model' => $this->config['model'],
            'texture' => false,
            'pbr' => false,
            // UV unwrapping cuts the surface into islands; for printing that means hundreds of open patches
            'export_uv' => false,
            'geometry_quality' => $this->config['geometry_quality'] ?? 'standard',
            'enable_image_autofix' => (bool) ($this->config['image_autofix'] ?? false),
            // 0 = let the generator decide; we simplify ourselves, exactly and without tearing the mesh
            'face_limit' => (int) $this->config['face_limit'] ?: null,
        ], fn ($v) => $v !== null);
    }

    /** Detailed geometry costs 20 credits on top. */
    private function detailCredits(): int
    {
        return ($this->config['geometry_quality'] ?? 'standard') === 'detailed' ? 20 : 0;
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
