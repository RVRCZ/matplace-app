<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\PrintProfile;
use App\Domain\Farm\ProfileLibrary;
use App\Domain\Farm\TestPrintService;
use App\Http\Controllers\Controller;
use App\Models\FarmColor;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterMaterial;
use App\Models\FarmPrinterSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tuning filaments per machine: the grid of printer × kind rows with their state, one row's settings and history,
 * test prints from the loaded spools, and taking over the settings a test was printed with.
 */
class FarmTuningController extends Controller
{
    public function __construct(private readonly TestPrintService $tests, private readonly ProfileLibrary $library) {}

    public function index(): View
    {
        $this->library->sync();
        $rows = FarmPrinterMaterial::with(['printer', 'material', 'color'])->get()
            ->sortBy(fn ($r) => [$r->printer->id, $r->material->sort, $r->farm_color_id ?? 0]);
        $tests = FarmOrder::where('kind', FarmOrder::KIND_TEST)->whereNotNull('farm_printer_material_id')
            ->select('farm_printer_material_id', 'status')->get()->groupBy('farm_printer_material_id');

        return view('admin.farm.tuning', [
            'printers' => FarmPrinter::with('slots.color.material')->orderBy('id')->get(),
            'rows' => $rows->groupBy('farm_printer_id'),
            'tests' => $tests,
            'generator' => $this->tests->available(),
        ]);
    }

    public function edit(FarmPrinterMaterial $row): View
    {
        $row->load(['printer.slots.color.material', 'material', 'color']);
        $effective = PrintProfile::for($row->printer, $row->material, $row->color);
        $slots = $row->printer->slots->filter(fn (FarmPrinterSlot $s) => $s->color && $s->color->farm_material_id === $row->farm_material_id
            && (! $row->farm_color_id || $s->farm_color_id === $row->farm_color_id));

        return view('admin.farm.tuning_edit', [
            'row' => $row,
            'effective' => $effective,
            'slots' => $slots->values(),
            'tests' => $row->testOrders()->with(['printJobs', 'modelFile'])->limit(20)->get(),
            'objects' => TestPrintService::OBJECTS,
            'generator' => $this->tests->available(),
        ]);
    }

    public function save(Request $request, FarmPrinterMaterial $row): RedirectResponse
    {
        $data = $request->validate([
            'nozzle_temp' => ['nullable', 'integer', 'min:150', 'max:350'],
            'nozzle_temp_first' => ['nullable', 'integer', 'min:150', 'max:350'],
            'bed_temp' => ['nullable', 'integer', 'min:0', 'max:150'],
            'process' => ['nullable', 'json'],
            'filament' => ['nullable', 'json'],
            'status' => ['required', Rule::in([FarmPrinterMaterial::STATUS_UNTESTED, FarmPrinterMaterial::STATUS_TESTING, FarmPrinterMaterial::STATUS_TUNED])],
            'score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'change_note' => ['nullable', 'string', 'max:300'],
        ]);
        $overrides = [
            'nozzle_temp' => $data['nozzle_temp'] ?? null, 'nozzle_temp_first' => $data['nozzle_temp_first'] ?? null, 'bed_temp' => $data['bed_temp'] ?? null,
            'process' => ! empty($data['process']) ? json_decode($data['process'], true) : [],
            'filament' => ! empty($data['filament']) ? json_decode($data['filament'], true) : [],
        ];
        $row->forceFill(['notes' => $data['notes'] ?? null, 'score' => $data['score'] ?? null]);
        $row->revise($overrides, 'manual', $data['change_note'] ?? null, $data['status']);
        if ($data['status'] === FarmPrinterMaterial::STATUS_TUNED && ! $row->tested_at) {
            $row->update(['tested_at' => now()]);
        }

        return redirect()->route('admin.farm.tuning.edit', $row)->with('status', __('farm.admin.saved'));
    }

    /** A spool that needs its own settings on this machine gets its own row, starting from the kind's row. */
    public function spoolRow(Request $request): RedirectResponse
    {
        $data = $request->validate(['farm_printer_id' => ['required', 'exists:farm_printers,id'], 'farm_color_id' => ['required', 'exists:farm_colors,id']]);
        $color = FarmColor::findOrFail($data['farm_color_id']);
        $kindRow = FarmPrinterMaterial::firstOrCreate(['farm_printer_id' => $data['farm_printer_id'], 'farm_material_id' => $color->farm_material_id, 'farm_color_id' => null]);
        $row = FarmPrinterMaterial::firstOrCreate(
            ['farm_printer_id' => $data['farm_printer_id'], 'farm_material_id' => $color->farm_material_id, 'farm_color_id' => $color->id],
            ['overrides' => $kindRow->overrides, 'source' => 'inherited', 'notes' => 'Vlastní řádek cívky, začíná hodnotami druhu na této tiskárně.'],
        );

        return redirect()->route('admin.farm.tuning.edit', $row);
    }

    public function test(Request $request, FarmPrinterMaterial $row): RedirectResponse
    {
        $data = $request->validate([
            'slot' => ['required', 'integer', 'exists:farm_printer_slots,id'],
            'object' => ['required', Rule::in(array_keys(TestPrintService::OBJECTS))],
            'nozzle_temp' => ['nullable', 'integer', 'min:150', 'max:350'],
            'bed_temp' => ['nullable', 'integer', 'min:0', 'max:150'],
            'process' => ['nullable', 'json'],
            'filament' => ['nullable', 'json'],
            'floors' => ['nullable', 'integer', 'min:3', 'max:10'],
            'start' => ['nullable', 'integer', 'min:150', 'max:350'],
            'step' => ['nullable', 'integer', 'min:-20', 'max:20', 'not_in:0'],
        ]);
        $slot = FarmPrinterSlot::findOrFail($data['slot']);
        abort_unless($slot->farm_printer_id === $row->farm_printer_id, 404);
        $candidate = [
            'nozzle_temp' => $data['nozzle_temp'] ?? null, 'bed_temp' => $data['bed_temp'] ?? null,
            'process' => ! empty($data['process']) ? json_decode($data['process'], true) : [],
            'filament' => ! empty($data['filament']) ? json_decode($data['filament'], true) : [],
        ];
        try {
            $order = $this->tests->create($row, $slot, $data['object'], $candidate, array_intersect_key($data, array_flip(['floors', 'start', 'step'])), $request->user());
        } catch (FarmRefusal $e) {
            return back()->withInput()->with('error', $e->text());
        }

        return redirect()->route('admin.farm.orders.show', $order)->with('status', __('farm.admin.test_started', ['number' => $order->number]));
    }

    /** "This test printed well": the row takes over what the test was printed with. */
    public function adopt(Request $request, FarmPrinterMaterial $row, FarmOrder $order): RedirectResponse
    {
        abort_unless($order->isTest() && $order->farm_printer_material_id === $row->id, 404);
        $data = $request->validate(['score' => ['nullable', 'integer', 'min:1', 'max:5'], 'nozzle_temp' => ['nullable', 'integer', 'min:150', 'max:350'], 'note' => ['nullable', 'string', 'max:300']]);
        $candidate = (array) ($order->test_params['candidate'] ?? []);
        if (! empty($data['nozzle_temp'])) {
            // a tower: the operator picked the floor that printed best
            $candidate['nozzle_temp'] = (int) $data['nozzle_temp'];
            $candidate['nozzle_temp_first'] = (int) $data['nozzle_temp'] + 5;
        }
        // values the kind already states stay the kind's: only what differs lives on the row
        $material = $row->material;
        foreach (['nozzle_temp' => 'nozzle_temp', 'nozzle_temp_first' => 'nozzle_temp_first', 'bed_temp' => 'bed_temp'] as $k => $m) {
            if (isset($candidate[$k]) && (int) $candidate[$k] === (int) $material->{$m}) {
                unset($candidate[$k]);
            }
        }
        $candidate['filament'] = array_diff_key((array) ($candidate['filament'] ?? []), $material->sliceOverrides());
        $row->forceFill(['score' => $data['score'] ?? $row->score, 'tested_at' => now()]);
        $row->revise($candidate, 'test', ($data['note'] ?? null) ?: 'z testu '.$order->number, FarmPrinterMaterial::STATUS_TUNED);
        if ($data['score'] ?? null) {
            $order->forceFill(['quality_rating' => (int) $data['score']])->save();
        }

        return redirect()->route('admin.farm.tuning.edit', $row)->with('status', __('farm.admin.saved'));
    }
}
