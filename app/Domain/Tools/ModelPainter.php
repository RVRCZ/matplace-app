<?php

namespace App\Domain\Tools;

use App\Domain\Farm\ColorLimit;
use App\Domain\Farm\Palette;
use App\Domain\Files\PaintedColors;
use App\Engines\Exceptions\EngineException;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;
use App\Jobs\PaintModel;
use App\Models\AnonymousSession;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Colouring a model in the browser (the tool "Vybarvit model", docs/AS.md): a fill of a patch or a body, colours by
 * height, a brush. What the visitor painted comes here as one record over the model's STL and leaves as a 3MF painted
 * the way the slicers save it (engines/python/paint_tool.py). From there on it is an uploaded painted 3MF like any
 * other (App\Domain\Files\PaintedColors, docs/AL.md §14): its colours read, two prices in the calculator, an order by
 * colours, the file itself sliced on one plate. Nothing new for the farm to learn.
 *
 * The record (the "colours" part of a project, docs/AS.md §3), kept with the file as `tool_params.paint`:
 *   v         1
 *   source    the model it was painted over (uuid) and its triangles
 *   palette   ["#rrggbb", …]: a triangle's state is an index into it; 0 = not painted, the model's own colour
 *   heights   [{z: mm above the lowest point, color: state}]: what is not painted takes its band's colour
 *   states    files/<uuid>/paint/states.bin: a byte per source triangle (255 = refined by the brush)
 *   added     files/<uuid>/paint/added.bin (+ added-states.bin): the triangles the brush refined, nine float32 each
 *   report    what the engine made of it: colours with their shares, specks merged, triangles
 *
 * The file's origin is `paint` (not `tool`): everything that asks "is this a painted upload" answers yes for it.
 */
final class ModelPainter
{
    public const KIND = 'paint';

    public const VERSION = 1;

    public const MAX_HEIGHTS = 8;

    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    /** How many colours one model may be painted in: what the farm's biggest changer holds (docs/AL.md §19). */
    public static function maxColors(): int
    {
        return max(2, ColorLimit::farm());
    }

    /**
     * What the page works within.
     *
     * @return array{max_triangles: int, max_added: int, brush_triangles: int, brush_triangles_phone: int, max_colors: int, max_heights: int, edge_mm: float, speck_mm2: float}
     */
    public static function limits(): array
    {
        $c = (array) config('engines.paint');

        return [
            'max_triangles' => (int) $c['max_triangles'], 'max_added' => (int) $c['max_added'],
            'brush_triangles' => (int) $c['brush_triangles'], 'brush_triangles_phone' => (int) $c['brush_triangles_phone'],
            'max_colors' => self::maxColors(), 'max_heights' => self::MAX_HEIGHTS,
            'edge_mm' => (float) $c['edge_mm'], 'speck_mm2' => (float) $c['speck_mm2'],
        ];
    }

    /**
     * The colours offered: the farm's filaments, one tile per colour and finish (docs/AL.md §20), those sitting in a
     * machine first; the sixteen basic colours where there is no farm catalogue.
     *
     * @return list<array{hex: string, name: string, loaded: bool}>
     */
    public static function tiles(Palette $palette): array
    {
        $out = [];
        if ($palette->fromFarm()) {
            foreach ($palette->all() as $c) {
                $key = mb_strtolower($c['name']).'|'.$c['finish'];
                $hex = strtolower((string) $c['hex']);
                // one tile per colour: the spool that is loaded stands for it; the same hex twice is one tile too
                $at = $out[$key] ?? null;
                if ($at === null || (! $at['loaded'] && ! empty($c['loaded']))) {
                    $out[$key] = ['hex' => $hex, 'name' => (string) $c['name'], 'loaded' => ! empty($c['loaded'])];
                }
            }
            $seen = [];
            $out = array_values(array_filter($out, function (array $t) use (&$seen) {
                $new = ! isset($seen[$t['hex']]);
                $seen[$t['hex']] = true;

                return $new;
            }));
            usort($out, fn ($a, $b) => [$b['loaded'], 0] <=> [$a['loaded'], 0]);

            return $out;
        }

        return array_map(fn (array $c) => ['hex' => strtolower($c['hex']), 'name' => $c['name'], 'loaded' => false], Palette::basic());
    }

    /**
     * The record as it is kept: checked against the model and the limits.
     *
     * @param  array<string, mixed>  $record
     * @return array{v: int, triangles: int, palette: list<string>, heights: list<array{z: float, color: int}>}
     *
     * @throws EngineException bad_record | source_changed | too_many_colors
     */
    public static function clean(array $record, int $triangles): array
    {
        if ((int) ($record['v'] ?? 0) !== self::VERSION) {
            throw new EngineException('bad_record');
        }
        if ((int) ($record['triangles'] ?? -1) !== $triangles) {
            throw new EngineException('source_changed');
        }
        $palette = array_values(array_map(fn ($h) => is_string($h) ? strtolower($h) : '', (array) ($record['palette'] ?? [])));
        if (count($palette) < 2 || array_filter($palette, fn ($h) => ! preg_match('/^#[0-9a-f]{6}$/', $h))) {
            throw new EngineException('bad_record');
        }
        if (count($palette) > self::maxColors()) {
            throw new EngineException('too_many_colors');
        }
        $heights = [];
        foreach (array_slice(array_values((array) ($record['heights'] ?? [])), 0, self::MAX_HEIGHTS) as $h) {
            if (! is_array($h) || ! is_numeric($h['z'] ?? null) || ! is_numeric($h['color'] ?? null) || (int) $h['color'] < 0 || (int) $h['color'] >= count($palette) || (float) $h['z'] <= 0) {
                throw new EngineException('bad_record');
            }
            $heights[] = ['z' => round((float) $h['z'], 2), 'color' => (int) $h['color']];
        }
        usort($heights, fn ($a, $b) => $a['z'] <=> $b['z']);

        return ['v' => self::VERSION, 'triangles' => $triangles, 'palette' => $palette, 'heights' => $heights];
    }

    /**
     * A new model file painted as the record says: made in the queue (App\Jobs\PaintModel), the page waits for it.
     *
     * @param  array<string, mixed>  $record
     *
     * @throws EngineException
     */
    public function make(ModelFile $src, array $record, UploadedFile $states, ?UploadedFile $added, ?UploadedFile $addedStates, ?AnonymousSession $session, ?User $user): ModelFile
    {
        $stl = $src->isReady() ? $src->absoluteStlPath() : null;
        if (! $stl || ! is_file($stl)) {
            throw new EngineException('source_gone');
        }
        $limits = self::limits();
        $triangles = StlFile::binaryCount($stl);
        if ($triangles < 1 || $triangles > $limits['max_triangles']) {
            throw new EngineException('too_heavy');
        }
        $clean = self::clean($record, $triangles);
        if ($states->getSize() !== $triangles) {
            throw new EngineException('bad_record');
        }
        $addedCount = 0;
        if ($added) {
            $bytes = (int) $added->getSize();
            if ($bytes < 36 || $bytes % 36 !== 0 || ! $addedStates || (int) $addedStates->getSize() !== intdiv($bytes, 36)) {
                throw new EngineException('bad_record');
            }
            $addedCount = intdiv($bytes, 36);
            // what the brush added and the triangles it split to add them: generous, the engine checks the surface itself
            if ($addedCount > $limits['max_added'] * 2 + 1000) {
                throw new EngineException('too_heavy');
            }
        }

        $uuid = (string) Str::uuid();
        $dir = 'files/'.$uuid;
        $disk = Storage::disk(ModelFile::DISK);
        $disk->putFileAs($dir.'/paint', $states, 'states.bin');
        if ($added && $addedStates) {
            $disk->putFileAs($dir.'/paint', $added, 'added.bin');
            $disk->putFileAs($dir.'/paint', $addedStates, 'added-states.bin');
        }
        $name = Str::slug(pathinfo($src->original_name, PATHINFO_FILENAME)) ?: 'model';
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $user?->id ?? $src->owner_user_id, 'anonymous_session_id' => $session?->id ?? $src->anonymous_session_id,
            'original_name' => $name.'-barvy.3mf', 'ext' => '3mf', 'mime' => 'model/3mf', 'size_bytes' => 0, 'sha256' => '',
            'storage_path' => $dir.'/original.3mf', 'origin' => self::KIND, 'origin_ref' => $src->uuid, 'status' => ModelFile::STATUS_UPLOADED,
            'tool_params' => ['paint' => $clean + ['source' => $src->uuid, 'added' => $addedCount]],
        ]);
        PaintModel::dispatch($file->id);

        return $file->refresh();
    }

    /**
     * Writes the painted 3MF of a file made by make() (the queue's part). The engine's answer.
     *
     * @return array<string, mixed>
     *
     * @throws EngineException
     */
    public function build(ModelFile $file, ModelFile $src): array
    {
        $record = (array) ($file->tool_params['paint'] ?? []);
        $disk = Storage::disk(ModelFile::DISK);
        $dst = $disk->path($file->storage_path);
        $dir = dirname($dst).'/paint';
        File::ensureDirectoryExists($dir);
        $json = $dir.'/record.json';
        File::put($json, (string) json_encode([
            'triangles' => (int) ($record['triangles'] ?? 0), 'palette' => (array) ($record['palette'] ?? []), 'heights' => (array) ($record['heights'] ?? []),
            'states_file' => $dir.'/states.bin',
            'added_file' => is_file($dir.'/added.bin') ? $dir.'/added.bin' : null, 'added_states_file' => is_file($dir.'/added-states.bin') ? $dir.'/added-states.bin' : null,
            'speck_mm2' => (float) config('engines.paint.speck_mm2', 0.5), 'name' => pathinfo($file->original_name, PATHINFO_FILENAME),
        ]));
        $r = $this->python->runScript('paint_tool.py', ['build', (string) $src->absoluteStlPath(), $dst, '@'.$json], (int) config('engines.paint.timeout', 600));
        if (empty($r['ok']) || ! is_file($dst)) {
            throw new EngineException((string) ($r['code'] ?? 'paint_failed'));
        }

        return $r;
    }

    /** A model coloured here: the record it was painted by and what came of it, for the pages. Null for any other file. */
    public static function report(ModelFile $f): ?array
    {
        if ($f->origin !== self::KIND || ! is_array($f->tool_params['paint'] ?? null)) {
            return null;
        }
        $p = $f->tool_params['paint'];

        return ['source' => $p['source'] ?? null, 'palette' => $p['palette'] ?? [], 'heights' => $p['heights'] ?? [], 'report' => $p['report'] ?? null,
            // the colours were written but could not be read back: the model would print in one colour
            'lost' => $f->status === ModelFile::STATUS_READY && PaintedColors::printed($f) === []];
    }

    /** The address where a ready model is coloured (this file as the one to paint), null when the tool is not open to the visitor. */
    public static function urlFor(ModelFile $f, ?User $user): ?string
    {
        if (! $f->isReady() || in_array($f->origin, ['portfolio', 'catalog'], true) || ! ToolVisibility::canOpen($user, self::KIND)) {
            return null;
        }

        return route('tools.paint', ['from' => $f->uuid]);
    }
}
