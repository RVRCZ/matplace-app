<?php

namespace App\Domain\Tools;

use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;
use App\Jobs\ProcessModelFile;
use App\Models\AnonymousSession;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sign / name tag / keychain with text. Pure parametric CAD (CadQuery), exact millimetres, no AI, no cost per piece.
 * The result is a normal ModelFile, so it flows into the calculator, inquiries and quotes like any upload.
 */
final class SignGenerator
{
    public const SHAPES = ['rounded', 'rect', 'oval'];

    public const STYLES = ['emboss', 'engrave'];

    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->hasCad();
    }

    /** @return array<string, string> key → absolute font path */
    public function fonts(): array
    {
        $dir = base_path('vendor/dompdf/dompdf/lib/fonts');

        return array_filter([
            'sans' => $dir.'/DejaVuSans-Bold.ttf',
            'serif' => $dir.'/DejaVuSerif-Bold.ttf',
            'mono' => $dir.'/DejaVuSansMono-Bold.ttf',
        ], 'is_file');
    }

    public function generate(array $p, ?AnonymousSession $session, ?User $user): ModelFile
    {
        if (! $this->available()) {
            throw new EngineException('The sign generator needs Python with CadQuery.');
        }
        $lines = array_values(array_filter(array_map(fn ($l) => trim((string) $l), [(string) ($p['line1'] ?? ''), (string) ($p['line2'] ?? '')]), fn ($l) => $l !== ''));
        if (! $lines) {
            throw new EngineException('No text.');
        }
        $fonts = $this->fonts();
        $params = [
            'lines' => array_map(fn ($l) => mb_substr($l, 0, 40), $lines),
            'font' => $fonts[$p['font'] ?? 'sans'] ?? (reset($fonts) ?: null),
            'text_height' => (float) ($p['text_height'] ?? 12),
            'shape' => in_array($p['shape'] ?? '', self::SHAPES, true) ? $p['shape'] : 'rounded',
            'thickness' => (float) ($p['thickness'] ?? 3),
            'relief' => (float) ($p['relief'] ?? 1.2),
            'style' => in_array($p['style'] ?? '', self::STYLES, true) ? $p['style'] : 'emboss',
            'hole' => (bool) ($p['hole'] ?? false),
            'border' => (bool) ($p['border'] ?? true),
        ];

        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = Storage::disk(ModelFile::DISK)->path($rel);
        File::ensureDirectoryExists(dirname($abs));

        $r = $this->python->runScript('sign_tool.py', [$abs, json_encode($params, JSON_UNESCAPED_UNICODE)]);
        if (empty($r['ok']) || ! is_file($abs)) {
            File::deleteDirectory(dirname($abs));
            throw new EngineException('Sign generation failed: '.($r['error'] ?? 'unknown'));
        }

        $name = Str::slug(Str::limit(implode(' ', $lines), 40, '')) ?: 'sign';
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $user?->id, 'anonymous_session_id' => $session?->id,
            'original_name' => $name.'.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
            'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => 'sign',
            'tool_params' => ['line1' => $lines[0], 'line2' => $lines[1] ?? '', 'font' => (string) ($p['font'] ?? 'sans'), 'text_height' => $params['text_height'], 'shape' => $params['shape'], 'thickness' => $params['thickness'], 'relief' => $params['relief'], 'style' => $params['style'], 'hole' => $params['hole'], 'border' => $params['border']],
            'status' => ModelFile::STATUS_UPLOADED,
        ]);
        ProcessModelFile::dispatch($file->id);

        return $file->refresh();
    }
}
