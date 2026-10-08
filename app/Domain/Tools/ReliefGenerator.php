<?php

namespace App\Domain\Tools;

use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;
use App\Jobs\ProcessModelFile;
use App\Models\AnonymousSession;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo → lithophane (picture visible against light) or relief plaque. Local height-map maths, no AI, no cost per piece.
 * The photo is only read, never stored. The result is a normal ModelFile.
 *
 * Session 3 (docs/R.md) added the plate's shape (rectangle, circle, oval, heart, arch, tree, a silhouette of the
 * visitor's own picture), an explicit height, a numeric frame, a hole or an eyelet for hanging, the photo's brightness,
 * contrast and midtones, and the lamp: the picture wrapped round a tube with a floor for an E14 / E27 socket.
 */
final class ReliefGenerator
{
    public const MODES = ['lithophane', 'relief'];

    public const SHAPES = ['rect', 'circle', 'oval', 'heart', 'arch', 'tree', 'custom', 'cylinder'];

    public const HANGS = ['none', 'hole', 'eyelet'];

    public const SOCKETS = ['e27', 'e14', 'led', 'none'];

    /** What the engine can refuse with a word the page knows a text for (lang/<locale>/relief.php, 'warn.*'). */
    public const REASONS = ['frame_too_wide', 'socket_too_big', 'silhouette'];

    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    /** The form's rules: the API validates with them, the tests know them. */
    public static function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'mode' => ['nullable', 'in:'.implode(',', self::MODES)],
            'shape' => ['nullable', 'in:'.implode(',', self::SHAPES)],
            'silhouette' => ['nullable', 'string', 'max:120', 'regex:'.Artwork::REF],
            'width' => ['nullable', 'numeric', 'min:40', 'max:400'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'min_thickness' => ['nullable', 'numeric', 'min:0.4', 'max:2'],
            'max_thickness' => ['nullable', 'numeric', 'min:1.6', 'max:10'],
            'frame' => ['nullable', 'numeric', 'min:0', 'max:15'],
            'invert' => ['nullable', 'boolean'],
            'stand' => ['nullable', 'boolean'],
            'hang' => ['nullable', 'in:'.implode(',', self::HANGS)],
            'socket' => ['nullable', 'in:'.implode(',', self::SOCKETS)],
            'brightness' => ['nullable', 'numeric', 'min:-50', 'max:50'],
            'contrast' => ['nullable', 'numeric', 'min:-50', 'max:50'],
            'gamma' => ['nullable', 'numeric', 'min:0.5', 'max:2'],
        ];
    }

    /** The form's values as the engine takes them (and as they are kept with the model so the page can reopen it). */
    public static function clean(array $p): array
    {
        $mode = in_array($p['mode'] ?? '', self::MODES, true) ? $p['mode'] : 'lithophane';
        $shape = in_array($p['shape'] ?? '', self::SHAPES, true) ? $p['shape'] : 'rect';
        $lamp = $shape === 'cylinder';
        $frame = $p['frame'] ?? 2.0;
        if (is_bool($frame)) {
            $frame = $frame ? 2.0 : 0.0;    // older forms sent a tick: a 2 mm frame or none
        }

        return [
            'mode' => $mode,
            'shape' => $shape,
            'silhouette' => $shape === 'custom' ? (string) ($p['silhouette'] ?? '') : '',
            'width' => round(max(40.0, min($lamp ? 400.0 : 300.0, (float) ($p['width'] ?? 100))), 1),
            'height' => round(max(0.0, min(300.0, (float) ($p['height'] ?? 0))), 1),
            'min_thickness' => (float) ($p['min_thickness'] ?? ($mode === 'lithophane' ? 0.8 : 1.2)),
            'max_thickness' => (float) ($p['max_thickness'] ?? ($mode === 'lithophane' ? 3.0 : 4.0)),
            'frame' => $lamp ? 0.0 : round(max(0.0, min(15.0, (float) $frame)), 1),
            'invert' => (bool) ($p['invert'] ?? false),
            'stand' => ! $lamp && (bool) ($p['stand'] ?? false),
            'hang' => ! $lamp && in_array($p['hang'] ?? '', self::HANGS, true) ? $p['hang'] : 'none',
            'socket' => $lamp && in_array($p['socket'] ?? '', self::SOCKETS, true) ? $p['socket'] : 'e27',
            'brightness' => (float) ($p['brightness'] ?? 0),
            'contrast' => (float) ($p['contrast'] ?? 0),
            'gamma' => (float) ($p['gamma'] ?? 1.0),
            'standing' => $mode === 'lithophane',
        ];
    }

    public function generate(UploadedFile $photo, array $p, ?AnonymousSession $session, ?User $user): ModelFile
    {
        if (! $this->available()) {
            throw new EngineException('The relief generator needs Python.');
        }
        $params = self::clean($p);
        $silhouette = null;
        if ($params['shape'] === 'custom') {
            $silhouette = $params['silhouette'] !== '' ? Artwork::path($params['silhouette']) : null;
            if (! $silhouette) {
                throw new EngineException('silhouette');
            }
        }

        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = Storage::disk(ModelFile::DISK)->path($rel);
        File::ensureDirectoryExists(dirname($abs));
        if ($silhouette) {
            // the silhouette stays with the model (an upload is pruned after a month), the saved reference points there
            File::copy($silhouette, dirname($abs).'/artwork.'.pathinfo($silhouette, PATHINFO_EXTENSION));
            $params['silhouette'] = 'file:'.$uuid;
        }

        $r = $this->python->runScript('relief_tool.py', [$photo->getRealPath(), $abs, json_encode($params + ['silhouette' => $silhouette])], 300);
        if (empty($r['ok']) || ! is_file($abs)) {
            File::deleteDirectory(dirname($abs));
            $error = (string) ($r['error'] ?? 'unknown');
            throw new EngineException(in_array($error, self::REASONS, true) ? $error : 'Relief generation failed: '.$error);
        }

        $mode = $params['mode'];
        $base = Str::slug(Str::limit(pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME), 30, '')) ?: 'photo';
        $report = array_intersect_key($r, array_flip(['width', 'height', 'thickness', 'min_thickness', 'shades', 'diameter', 'circumference', 'socket', 'triangles', 'standing', 'stand']));
        unset($params['standing']);
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $user?->id, 'anonymous_session_id' => $session?->id,
            'original_name' => ($params['shape'] === 'cylinder' ? 'lamp' : $mode).'-'.$base.'.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs),
            'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => $mode, 'tool_params' => $params + ['report' => $report],   // the photo itself is not kept
            'status' => ModelFile::STATUS_UPLOADED,
        ]);
        ProcessModelFile::dispatch($file->id);

        return $file->refresh();
    }
}
