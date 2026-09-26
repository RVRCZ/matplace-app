<?php

namespace App\Domain\Farm;

use App\Engines\Exceptions\EngineException;
use App\Engines\Repair\PythonTool;
use App\Jobs\PrepareFarmOrder;
use App\Jobs\ProcessModelFile;
use App\Models\FarmOrder;
use App\Models\FarmPrinterMaterial;
use App\Models\FarmPrinterSlot;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Test prints for tuning a filament on a printer: a test object from engines/python/calib_tool.py becomes a farm
 * order of kind `test` on the chosen spool, sliced with the candidate settings, queued without a customer or a
 * price, printed by the agent like any other job. The order remembers the tuning row and what was tried.
 */
final class TestPrintService
{
    public const OBJECTS = [
        'quick' => ['minutes' => 35, 'floors' => false, 'ironing' => false],
        'detailed' => ['minutes' => 90, 'floors' => false, 'ironing' => false],
        // ironing is a setting of the whole print, never of one surface: its own object keeps the big tests from
        // spending most of their time polishing their base plate (three quarters of an hour on a 0.2 nozzle)
        'ironing' => ['minutes' => 15, 'floors' => false, 'ironing' => true],
        'temp_tower' => ['minutes' => 60, 'floors' => true, 'ironing' => false],
    ];

    /** Ironing switched on for a test, with Orca's defaults where the row says nothing else. */
    public const IRONING = ['ironing_type' => 'top', 'ironing_flow' => '10%', 'ironing_speed' => '30', 'ironing_spacing' => '0.15'];

    /**
     * The same ironing for a machine with another nozzle. Orca's 0.15 mm spacing is a bit over a third of a 0.4
     * nozzle's line; kept as it is on a 0.2 nozzle the passes would overlap twice as much and lay down far too much
     * plastic, so the spacing follows the nozzle instead.
     */
    public static function ironingFor(float $nozzle): array
    {
        return ['ironing_spacing' => (string) round(0.375 * $nozzle, 3)] + self::IRONING;
    }

    public const FLOOR_MM = 10.0;

    public function __construct(private readonly PythonTool $python) {}

    public function available(): bool
    {
        return $this->python->available();
    }

    /**
     * @param  array  $candidate  overrides to try (shape of FarmPrinterMaterial::overrides); empty = what the row has now
     * @param  array{floors?: int, start?: int, step?: int}  $tower  temperature ladder for a tower, bottom floor first
     *
     * @throws FarmRefusal slot_kind (spool is another kind), no_python, object
     */
    public function create(FarmPrinterMaterial $row, FarmPrinterSlot $slot, string $object, array $candidate, array $tower, User $admin, bool $ironing = false): FarmOrder
    {
        if (! isset(self::OBJECTS[$object])) {
            throw new FarmRefusal('object');
        }
        $color = $slot->color;
        if (! $color || $color->farm_material_id !== $row->farm_material_id || ($row->farm_color_id && $row->farm_color_id !== $color->id)) {
            throw new FarmRefusal('slot_kind');
        }
        if (! $this->available()) {
            throw new FarmRefusal('no_python');
        }
        $printer = $row->printer;
        $material = $row->material;

        // what this test prints with: the row's current values under the candidate, temperatures always known
        $base = PrintProfile::for($printer, $material, $color);
        $candidate = FarmPrinterMaterial::clean($candidate);
        $candidate['process'] = ($candidate['process'] ?? []) + $base->process;
        $candidate['filament'] = ($candidate['filament'] ?? []) + $base->filament;
        $candidate['nozzle_temp'] = (int) ($candidate['nozzle_temp'] ?? $base->temps['nozzle'] ?? 0);
        $candidate['nozzle_temp_first'] = (int) ($candidate['nozzle_temp_first'] ?? $base->temps['nozzle_first'] ?? 0);
        $candidate['bed_temp'] = (int) ($candidate['bed_temp'] ?? $base->temps['bed'] ?? 0);
        if ($ironing && self::OBJECTS[$object]['ironing']) {
            // the plateau gets ironed; the row may already carry its own ironing flow / speed / spacing
            $ironingDefaults = self::ironingFor((float) $printer->nozzle_mm);
            $candidate['process'] = ['ironing_type' => $ironingDefaults['ironing_type']] + $candidate['process'] + $ironingDefaults;
        }

        $params = ['object' => $object, 'candidate' => $candidate, 'row_version' => $row->version, 'ironing' => $ironing && self::OBJECTS[$object]['ironing']];
        // walls, stringing pillars and the bond bar are counted in nozzle widths, so the test measures what this nozzle can do
        $toolParams = ['nozzle' => (float) $printer->nozzle_mm];
        if (self::OBJECTS[$object]['floors']) {
            $floors = max(3, min(10, (int) ($tower['floors'] ?? 5)));
            $step = (int) ($tower['step'] ?? -5) ?: -5;
            $start = (int) ($tower['start'] ?? 0) ?: ($candidate['nozzle_temp'] ?: 220) - (int) floor($floors / 2) * $step;
            $params['floor_mm'] = self::FLOOR_MM;
            $params['temps'] = TowerGcode::ladder($floors, $start, $step);
            // the bottom floor is what the whole object is sliced at; the other floors are rewritten into the G-code
            $params['candidate']['nozzle_temp'] = $params['temps'][0];
            $params['candidate']['nozzle_temp_first'] = max($params['temps'][0], $candidate['nozzle_temp_first']);
            $toolParams['floors'] = $floors;
        }

        $file = $this->buildFile($object, $toolParams, $admin);
        $params['features'] = $file->tool_params['features'] ?? [];

        $order = FarmOrder::create([
            'token' => Str::random(32), 'number' => $this->nextNumber(), 'kind' => FarmOrder::KIND_TEST,
            'user_id' => $admin->id, 'model_file_id' => $file->id, 'status' => FarmOrder::STATUS_UPLOADED, 'stage' => 'checking',
            'quality' => 'standard', 'strength' => 'standard', 'unit_scale' => 1,
            'farm_material_id' => $material->id, 'farm_color_id' => $color->id, 'farm_printer_id' => $printer->id, 'farm_printer_slot_id' => $slot->id,
            'farm_printer_material_id' => $row->id, 'test_params' => $params, 'note' => __('farm.test.object.'.$object).' · '.$row->label(),
        ]);
        $order->events()->create(['to' => FarmOrder::STATUS_UPLOADED, 'actor' => 'admin', 'actor_id' => $admin->id, 'note' => 'test '.$object]);
        if ($row->status === FarmPrinterMaterial::STATUS_UNTESTED) {
            $row->update(['status' => FarmPrinterMaterial::STATUS_TESTING]);
        }
        PrepareFarmOrder::dispatch($order->id);

        return $order;
    }

    /** The test object as a model file of the admin (origin `calib`), processed like any upload. */
    private function buildFile(string $object, array $toolParams, User $admin): ModelFile
    {
        $uuid = (string) Str::uuid();
        $rel = 'files/'.$uuid.'/original.stl';
        $abs = Storage::disk(ModelFile::DISK)->path($rel);
        File::ensureDirectoryExists(dirname($abs));
        try {
            $r = $this->python->runScript('calib_tool.py', [$object, $abs, json_encode((object) $toolParams)], 60);
        } catch (EngineException $e) {
            throw new FarmRefusal('object', ['error' => $e->getMessage()]);
        }
        if (empty($r['ok']) || ! is_file($abs)) {
            throw new FarmRefusal('object', ['error' => (string) ($r['error'] ?? '')]);
        }
        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $admin->id, 'original_name' => 'test-'.$object.'.stl', 'ext' => 'stl', 'mime' => 'model/stl',
            'size_bytes' => filesize($abs), 'sha256' => hash_file('sha256', $abs), 'storage_path' => $rel, 'origin' => 'tool', 'origin_ref' => 'calib',
            'tool_params' => ['object' => $object, 'features' => $r['features'] ?? []] + $toolParams, 'status' => ModelFile::STATUS_UPLOADED,
        ]);
        ProcessModelFile::dispatch($file->id);

        return $file->refresh();
    }

    /** T26-000007: test prints have their own numbering so they never look like a customer's order. */
    private function nextNumber(): string
    {
        $prefix = 'T'.now()->format('y').'-';
        $last = FarmOrder::where('number', 'like', $prefix.'%')->max('number');

        return $prefix.str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), 6, '0', STR_PAD_LEFT);
    }
}
