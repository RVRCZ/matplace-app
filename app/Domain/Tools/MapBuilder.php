<?php

namespace App\Domain\Tools;

use App\Domain\Farm\Palette;
use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;
use App\Jobs\BuildMap;
use App\Models\AnonymousSession;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A 3D map of a city or a landscape (/tools/map, docs/W.md): the settings of the page, their rules, the design as a
 * model file made in the queue (App\Jobs\BuildMap), and the offline build over files on disk that the job, the
 * examples and the tests share (engines/python/map_tool.py). The data of a place come from App\Domain\Tools\MapData.
 */
final class MapBuilder
{
    public const KIND = 'map';

    /** field → [min, max, default, step] */
    public const FIELDS = [
        'size' => [80, 250, 150, 1],            // the plate, mm square, frame included
        'base_h' => [3, 10, 4, 0.5],            // the plate's height
        'frame_mm' => [3, 15, 6, 0.5],          // the frame round the window
        'default_h' => [3, 30, 6, 1],           // a building OpenStreetMap gives no height for, in metres (two floors)
        'exaggeration' => [1, 3, 1.5, 0.1],     // a landscape's heights, times
    ];

    public const CHOICES = ['type' => ['city', 'landscape'], 'style' => ['sleek', 'miniature'], 'side' => ['500', '1000', '2000', '5000', '10000', '20000'], 'roads' => ['raised', 'sunk'], 'roofs' => ['houses', 'data', 'flat']];

    /** the sides a type offers, metres: a city 0.5–2 km, a landscape 2–20 km */
    public const SIDES = ['city' => [500, 1000, 2000], 'landscape' => [2000, 5000, 10000, 20000]];

    public const FLAGS = ['frame', 'water', 'roads_on', 'rail', 'green', 'towns'];

    public const FLAGS_ON = ['frame', 'water', 'roads_on', 'rail', 'towns'];

    public const TEXTS = ['name' => 40, 'place' => 120];

    /** the parts a type prints in, in the order of the print (the plate first), each in its own colour */
    public const PARTS = ['city' => ['base', 'roads', 'buildings'], 'landscape' => ['base', 'terrain']];

    public const COLORS = ['base' => '#e8e4d8', 'roads' => '#2b2b2b', 'buildings' => '#c9a96b', 'water' => '#3b82c4', 'terrain' => '#2d6a4f'];

    /** maps a day: a visitor without an account, a signed-in one */
    public const DAILY = ['anon' => 3, 'user' => 20];

    /** the zoom of the height tiles: a landscape needs breadth, a city needs its slope only */
    public const ZOOM = ['city' => 14, 'landscape' => 12];

    public function __construct(private readonly PythonTool $python, private readonly MapData $data) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    /** Laravel rules for the page's settings (the same numbers the page prints as min/max). */
    public static function rules(): array
    {
        $rules = [
            'params' => ['required', 'array'],
            'params.lat' => ['required', 'numeric', 'min:-85', 'max:85'],
            'params.lon' => ['required', 'numeric', 'min:-180', 'max:180'],
            'params.part_colors' => ['nullable', 'array', 'max:6'],
            'params.part_colors.*' => ['nullable', Palette::rule()],
        ];
        foreach (self::FIELDS as $key => [$min, $max, , $step]) {
            $rules['params.'.$key] = ['nullable', $step === 1 ? 'integer' : 'numeric', 'min:'.$min, 'max:'.$max];
        }
        foreach (self::FLAGS as $flag) {
            $rules['params.'.$flag] = ['nullable', 'boolean'];
        }
        foreach (self::CHOICES as $key => $options) {
            $rules['params.'.$key] = ['nullable', Rule::in($options)];
        }
        foreach (self::TEXTS as $key => $max) {
            $rules['params.'.$key] = ['nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /** Known keys only, numbers as numbers, defaults filled in, the side one the type offers: what is stored and what the tool receives. */
    public static function clean(array $p): array
    {
        $out = ['lat' => round((float) ($p['lat'] ?? 0), 6), 'lon' => round((float) ($p['lon'] ?? 0), 6)];
        foreach (self::FIELDS as $key => [, , $default, $step]) {
            $v = $p[$key] ?? $default;
            $out[$key] = $step === 1 ? (int) $v : round((float) $v, 2);
        }
        foreach (self::FLAGS as $flag) {
            $out[$flag] = filter_var($p[$flag] ?? in_array($flag, self::FLAGS_ON, true), FILTER_VALIDATE_BOOLEAN);
        }
        foreach (self::CHOICES as $key => $options) {
            $out[$key] = in_array($p[$key] ?? null, $options, true) ? $p[$key] : $options[0];
        }
        $sides = self::SIDES[$out['type']];
        $out['side'] = (string) (in_array((int) $out['side'], $sides, true) ? (int) $out['side'] : $sides[1] ?? $sides[0]);
        foreach (self::TEXTS as $key => $max) {
            $out[$key] = mb_substr(trim((string) ($p[$key] ?? '')), 0, $max);
        }
        // the colours of the parts, as the page sends them ({code: hex, hex} or a hex), each one kept with its look
        foreach (self::PARTS[$out['type']] as $part) {
            $given = $p['part_colors'][$part] ?? null;
            $hex = is_array($given) ? ($given['hex'] ?? $given['code'] ?? null) : $given;
            if (is_string($hex) && ! Palette::isCustom($hex)) {
                $hex = app(Palette::class)->hex($hex);          // a spool's code or a built-in name: its look
            }
            $hex = Palette::isCustom($hex) ? strtolower($hex) : self::COLORS[$part];
            $out['part_colors'][$part] = ['code' => $hex, 'hex' => $hex];
        }

        return $out;
    }

    /** @return list<string> the parts printed one above the other, bottom first */
    public static function partsOf(array $p): array
    {
        return self::PARTS[$p['type'] ?? 'city'] ?? self::PARTS['city'];
    }

    /**
     * The design as a model file, made in the queue: the page asks /api/files/{uuid} for its state and reads the phase
     * (report()) until it is ready.
     */
    public function create(array $params, ?AnonymousSession $session, ?User $user): ModelFile
    {
        $clean = self::clean($params);
        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        File::ensureDirectoryExists(dirname(Storage::disk(ModelFile::DISK)->path($rel)));
        $name = Str::slug($clean['name'] ?: $clean['place'] ?: 'map') ?: 'map';
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $user?->id, 'anonymous_session_id' => $session?->id,
            'original_name' => 'map-'.$name.'-'.$clean['size'].'.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => 0, 'sha256' => '',
            'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => self::KIND, 'status' => ModelFile::STATUS_UPLOADED, 'tool_params' => $clean,
        ]);
        BuildMap::dispatch($file->id);

        return $file->refresh();
    }

    /**
     * The files the builder needs for a design, fetched or taken from the cache: the Overpass answer of the square
     * and, for a landscape, its height tiles. `$stage` is told what is being fetched (the page shows it).
     *
     * @return array{osm: string, dem: list<array{z: int, x: int, y: int, path: string}>, center: array{0: float, 1: float}, side_m: int, zoom: int, font: string, params: array<string, mixed>}
     *
     * @throws MapDataUnavailable
     */
    public function sources(array $clean, ?callable $stage = null): array
    {
        $side = (int) $clean['side'];
        $stage && $stage('osm');
        $osm = $this->data->osm((float) $clean['lat'], (float) $clean['lon'], $side, (string) $clean['type']);
        $dem = [];
        if ($clean['type'] === 'landscape') {
            $stage && $stage('terrain');
            $dem = $this->data->dem((float) $clean['lat'], (float) $clean['lon'], $side, self::ZOOM['landscape']);
        }

        return self::sourcesOf($clean, $osm, $dem);
    }

    /** The same, over files already on disk (a fixture, the examples): nothing is fetched. */
    public static function sourcesOf(array $clean, string $osm, array $dem = []): array
    {
        return [
            'osm' => str_replace('\\', '/', $osm), 'dem' => $dem, 'center' => [(float) $clean['lat'], (float) $clean['lon']], 'side_m' => (int) $clean['side'],
            'zoom' => self::ZOOM[$clean['type'] ?? 'city'], 'font' => str_replace('\\', '/', ParametricGenerator::fontPath('sans')),
            'params' => $clean + ['colors' => array_map(fn ($c) => $c['hex'], (array) ($clean['part_colors'] ?? []))],
        ];
    }

    /**
     * Builds the STL of a design over its source files: the job's work, and the examples' and the tests' way to a
     * map without the network.
     *
     * @return array{bbox: array{x: float, y: float, z: float}, volume_mm3: float, area_mm2: float, triangles: int, notes: array<string, mixed>}
     *
     * @throws EngineException
     */
    public function build(array $sources, string $dst, int $timeout = 300): array
    {
        $json = $dst.'.params.json';
        File::ensureDirectoryExists(dirname($dst));
        File::put($json, (string) json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        try {
            $r = $this->python->runScript('map_tool.py', ['build', $json, $dst], $timeout);
        } finally {
            @unlink($json);
        }
        if (empty($r['ok']) || ! is_file($dst)) {
            throw new EngineException((string) ($r['code'] ?? $r['error'] ?? 'map_failed'));
        }

        return ['bbox' => $r['bbox'], 'volume_mm3' => (float) $r['volume_mm3'], 'area_mm2' => (float) $r['area_mm2'], 'triangles' => (int) $r['triangles'], 'notes' => (array) ($r['notes'] ?? [])];
    }

    /**
     * The area from above as a PNG (600 × 600): what the plate will show, drawn from the same data before the solid is built.
     *
     * @return array<string, mixed> buildings, roads_m, scale
     *
     * @throws EngineException
     */
    public function preview(array $sources, string $png): array
    {
        $json = $png.'.params.json';
        File::ensureDirectoryExists(dirname($png));
        File::put($json, (string) json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        try {
            $r = $this->python->runScript('map_tool.py', ['preview', $json, $png], 120);
        } finally {
            @unlink($json);
        }
        if (empty($r['ok']) || ! is_file($png)) {
            throw new EngineException((string) ($r['code'] ?? $r['error'] ?? 'map_failed'));
        }

        return array_diff_key($r, ['ok' => 1]);
    }

    /** What the page shows of a map made here: the tool's notes, and while it works, the phase it is in. */
    public static function report(ModelFile $f): ?array
    {
        if ($f->origin !== 'tool' || $f->kind() !== self::KIND) {
            return null;
        }
        $notes = (array) ($f->tool_params['notes'] ?? []);
        $stage = Storage::disk(ModelFile::DISK)->path($f->storage_path).'.stage';
        if ($f->status !== ModelFile::STATUS_READY && is_file($stage)) {
            $notes['stage'] = trim((string) file_get_contents($stage));
        }

        return $notes + ['regions' => array_values((array) ($f->tool_params['regions'] ?? [])), 'parts' => self::partsOf((array) $f->tool_params)];
    }

    /**
     * Height tiles of a hill, drawn here, for the examples and the tests: the tiles a square needs at the landscape's
     * zoom, each 256 × 256 Terrarium pixels (height = R·256 + G + B/256 − 32768) from 300 m at the rim of the square's
     * middle tile to 700 m in its centre. Kept in storage/app/tmp/map/dem-fixture.
     *
     * @return list<array{z: int, x: int, y: int, path: string}>
     */
    public static function demFixture(float $lat, float $lon, int $sideM): array
    {
        $zoom = self::ZOOM['landscape'];
        [$south, $west, $north, $east] = MapData::bbox($lat, $lon, $sideM);
        [$x0, $y0] = MapData::tile($north, $west, $zoom);
        [$x1, $y1] = MapData::tile($south, $east, $zoom);
        [$cx, $cy] = MapData::tile($lat, $lon, $zoom);
        $tiles = [];
        for ($y = (int) floor($y0); $y <= (int) floor($y1); $y++) {
            for ($x = (int) floor($x0); $x <= (int) floor($x1); $x++) {
                $path = storage_path(sprintf('app/tmp/map/dem-fixture/%d/%d/%d.png', $zoom, $x, $y));
                if (! is_file($path)) {
                    File::ensureDirectoryExists(dirname($path));
                    $img = imagecreatetruecolor(256, 256);
                    $reach = 256 * max(0.5, $sideM / 2 / (40075016.686 * cos(deg2rad($lat)) / (1 << $zoom)));      // half the square, in tile pixels
                    for ($py = 0; $py < 256; $py++) {
                        for ($px = 0; $px < 256; $px++) {
                            $d = hypot($x + $px / 256 - $cx, $y + $py / 256 - $cy) * 256 / $reach;
                            $h = 300 + 400 * max(0.0, 1 - $d * $d) + 32768;
                            $r = (int) floor($h / 256);
                            imagesetpixel($img, $px, $py, imagecolorallocate($img, $r, (int) floor($h - $r * 256), (int) round(($h - floor($h)) * 255)));
                        }
                    }
                    imagepng($img, $path);
                    imagedestroy($img);
                }
                $tiles[] = ['z' => $zoom, 'x' => $x, 'y' => $y, 'path' => $path];
            }
        }

        return $tiles;
    }

    /** The name of the file an example is drawn from: tests/fixtures/maps/<name>.json (the examples and the tests are offline). */
    public static function fixture(string $name): string
    {
        return base_path('tests/fixtures/maps/'.$name.'.json');
    }
}
