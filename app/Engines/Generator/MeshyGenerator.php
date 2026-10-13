<?php

namespace App\Engines\Generator;

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
 * Meshy API — https://api.meshy.ai, written from its documentation (docs.meshy.ai/en/api, read 14 Oct 2026).
 * NOT YET TRIED AGAINST THE REAL SERVICE: there is no key (docs/Y.md §11, docs/TODO.md #1).
 *
 *   POST /openapi/v1/image-to-3d        {image_url, ai_model, should_texture: false, …}   → {result: "<task id>"}
 *   POST /openapi/v1/multi-image-to-3d  {image_urls: [1–4 pictures], …}                     → {result: "<task id>"}
 *   POST /openapi/v2/text-to-3d         {mode: "preview", prompt (800 chars), ai_model, …}  → {result: "<task id>"}
 *   GET  <the same path>/<task id>      → {status: PENDING | IN_PROGRESS | SUCCEEDED | FAILED | CANCELED, progress 0–100,
 *                                          model_urls: {glb, …}, task_error: {message}, consumed_credits}
 *
 * A picture goes in the request itself as a data URI (jpg, png, webp, 20 MB at most): nothing is uploaded first.
 * Geometry only (`should_texture: false`): 20 credits with meshy-7.1 or meshy-6, 5 with meshy-6-lite; the finer
 * geometry (`geometry_resolution` 2k or 4k, meshy-7.1 only) 5 more. The price of a credit is not in the
 * documentation: config ai.meshy.credit_cents is an assumption until Roman's plan says otherwise.
 *
 * The three kinds of task are polled at three addresses, and a request keeps only the engine and one id
 * (generation_requests.external_id): the id carries the kind in front of it, "image-to-3d:018a210d-…".
 * The result is a GLB (Y up); scaling to millimetres and Z up happens in ModelNormalizer, as for Tripo.
 */
final class MeshyGenerator implements ModelGenerator
{
    private const PATHS = ['image-to-3d' => '/openapi/v1/image-to-3d', 'multi-image-to-3d' => '/openapi/v1/multi-image-to-3d', 'text-to-3d' => '/openapi/v2/text-to-3d'];

    private const VIEWS = ['front', 'left', 'back', 'right'];

    private const MIME = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    private const MAX_IMAGE_BYTES = 20 * 1024 * 1024;

    /** @param  array{api_key?: string, base_url?: string, model?: string, detail_resolution?: string, geometry_quality?: string, credit_cents?: float|int, work_dir?: string}  $config */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'meshy';
    }

    public function estimatedCostCents(): int
    {
        return $this->cents($this->credits(null));
    }

    public function fromImage(string $imagePath, ?string $hint, GenerationOptions $options): GenerationHandle
    {
        return $this->create('image-to-3d', ['image_url' => $this->dataUri($imagePath)] + $this->common($options, 'image-to-3d'), $options);
    }

    /** Front and up to three more sides of the same subject, in the order front, left, back, right; a missing side is left out. */
    public function fromImages(array $views, ?string $hint, GenerationOptions $options): GenerationHandle
    {
        if (empty($views['front'])) {
            throw new GenerationException('The front view is required.');
        }
        $views = array_filter(array_intersect_key($views, array_flip(self::VIEWS)));
        if (count($views) < 2) {
            return $this->fromImage($views['front'], $hint, $options);
        }
        $pictures = [];
        foreach (self::VIEWS as $view) {
            if (isset($views[$view])) {
                $pictures[] = $this->dataUri($views[$view]);
            }
        }

        return $this->create('multi-image-to-3d', ['image_urls' => $pictures] + $this->common($options, 'multi-image-to-3d'), $options);
    }

    /** The "preview" of text to 3D is the mesh without a texture: all a print needs. */
    public function fromText(string $prompt, GenerationOptions $options): GenerationHandle
    {
        $prompt = trim($prompt);
        if (mb_strlen($prompt) < 3) {
            throw new GenerationException('Prompt is too short.');
        }
        $body = array_diff_key($this->common($options, 'text-to-3d'), ['should_texture' => 1]);

        return $this->create('text-to-3d', ['mode' => 'preview', 'prompt' => mb_substr($prompt, 0, 800)] + $body, $options);
    }

    public function poll(GenerationHandle $handle): GenerationStatus
    {
        [$kind, $id] = array_pad(explode(':', $handle->externalId, 2), 2, '');
        if (! isset(self::PATHS[$kind]) || $id === '') {
            return new GenerationStatus(GenerationStatus::FAILED, error: 'meshy_bad_task_id');
        }
        $res = $this->client()->get($this->url(self::PATHS[$kind].'/'.rawurlencode($id)));
        if (! $res->ok()) {
            // too many requests or trouble on their side passes: "running" lets the job ask again. A refused key, an
            // empty account or a task that is not there does not pass.
            return $res->status() === 429 || $res->status() >= 500
                ? new GenerationStatus(GenerationStatus::RUNNING, error: 'HTTP '.$res->status())
                : new GenerationStatus(GenerationStatus::FAILED, error: 'meshy_http_'.$res->status());
        }
        $task = (array) $res->json();
        $status = (string) ($task['status'] ?? '');
        $progress = max(0, min(100, (int) ($task['progress'] ?? 0)));

        if ($status === 'PENDING' || $status === 'IN_PROGRESS') {
            return new GenerationStatus($status === 'PENDING' ? GenerationStatus::QUEUED : GenerationStatus::RUNNING, progress: min(99, $progress));
        }
        if ($status !== 'SUCCEEDED') {
            $why = trim((string) ($task['task_error']['message'] ?? ''));

            return new GenerationStatus(GenerationStatus::FAILED, error: mb_substr('meshy_'.(strtolower($status) ?: 'unknown').($why !== '' ? ': '.$why : ''), 0, 300), progress: $progress);
        }
        $url = $task['model_urls']['glb'] ?? null;
        if (! is_string($url) || $url === '') {
            return new GenerationStatus(GenerationStatus::FAILED, error: 'meshy_no_model_url');
        }
        $dir = rtrim((string) $this->config['work_dir'], '/');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/'.Str::uuid().'.glb';
        $dl = Http::timeout(180)->sink($path)->get($url);
        if (! $dl->ok() || ! is_file($path) || filesize($path) < 100) {
            @unlink($path);

            return new GenerationStatus(GenerationStatus::RUNNING, error: 'download_retry', progress: 99);
        }

        return new GenerationStatus(GenerationStatus::DONE, meshPath: $path, previewPath: null, progress: 100);
    }

    private function create(string $kind, array $body, GenerationOptions $options): GenerationHandle
    {
        $res = $this->client()->asJson()->post($this->url(self::PATHS[$kind]), $body);
        $id = $res->json('result');
        if (! $res->successful() || ! is_string($id) || $id === '') {
            throw new GenerationException('Meshy task failed: '.mb_substr((string) ($res->json('message') ?? ('HTTP '.$res->status())), 0, 300));
        }
        $credits = $this->credits($options);
        // one task = one flat price (config/ai.php prices.models): the finer geometry costs more
        AiUsage::record('generate', ($body['geometry_resolution'] ?? 'standard') === 'standard' ? 'meshy' : 'meshy-fine', []);

        // "credits" is what the request keeps as its cost in cents (the job reads it under that name)
        return new GenerationHandle($this->name(), $kind.':'.$id, ['credits' => $this->cents($credits), 'meshy_credits' => $credits, 'model' => $body['ai_model'] ?? null]);
    }

    /**
     * Geometry only, in one format, not remeshed: we simplify ourselves, exactly and without tearing the mesh.
     * Several pictures take the finer geometry up to 2k only.
     */
    private function common(GenerationOptions $options, string $kind): array
    {
        $resolution = $this->resolution($options);
        if ($kind === 'multi-image-to-3d' && $resolution === '4k') {
            $resolution = '2k';
        }

        return [
            'ai_model' => (string) ($this->config['model'] ?? 'meshy-7.1'),
            'should_texture' => false,
            'should_remesh' => false,
            'geometry_resolution' => $resolution,
            'target_formats' => ['glb'],
        ];
    }

    /** "detailed" (asked for, or configured) is the finer geometry of meshy-7.1; anything else the standard one. */
    private function resolution(?GenerationOptions $options): string
    {
        $asked = $options?->geometryQuality;
        $quality = in_array($asked, ['standard', 'detailed'], true) ? $asked : (string) ($this->config['geometry_quality'] ?? 'standard');
        $fine = in_array($this->config['detail_resolution'] ?? null, ['2k', '4k'], true) ? $this->config['detail_resolution'] : '2k';

        return $quality === 'detailed' && str_starts_with((string) ($this->config['model'] ?? 'meshy-7.1'), 'meshy-7') ? $fine : 'standard';
    }

    private function credits(?GenerationOptions $options): int
    {
        $base = str_contains((string) ($this->config['model'] ?? 'meshy-7.1'), 'lite') ? 5 : 20;

        return $base + ($this->resolution($options) === 'standard' ? 0 : 5);
    }

    private function cents(int $credits): int
    {
        return (int) round($credits * (float) ($this->config['credit_cents'] ?? 2));
    }

    private function dataUri(string $imagePath): string
    {
        if (! is_file($imagePath)) {
            throw new GenerationException('Image not found: '.$imagePath);
        }
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION)) ?: 'jpg';
        if (! isset(self::MIME[$ext])) {
            throw new GenerationException('Unsupported image type for generation: '.$ext);
        }
        if (filesize($imagePath) > self::MAX_IMAGE_BYTES) {
            throw new GenerationException('The picture is larger than 20 MB.');
        }

        return 'data:'.self::MIME[$ext].';base64,'.base64_encode((string) file_get_contents($imagePath));
    }

    private function client(): PendingRequest
    {
        if (empty($this->config['api_key'])) {
            throw new GenerationException('MESHY_API_KEY is not set.');
        }

        return Http::timeout(120)->withToken((string) $this->config['api_key'])->acceptJson();
    }

    private function url(string $path): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://api.meshy.ai'), '/').$path;
    }
}
