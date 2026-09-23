<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\PrintProfile;
use App\Domain\Farm\ProfileLibrary;
use App\Domain\Farm\TestPrintService;
use App\Domain\Farm\TuningAdvisor;
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
            // t_ prefix: the row's own form on the same page uses the plain names, old() must not mix them up
            't_nozzle_temp' => ['nullable', 'integer', 'min:150', 'max:350'],
            't_bed_temp' => ['nullable', 'integer', 'min:0', 'max:150'],
            't_process' => ['nullable', 'json'],
            't_filament' => ['nullable', 'json'],
            'floors' => ['nullable', 'integer', 'min:3', 'max:10'],
            'start' => ['nullable', 'integer', 'min:150', 'max:350'],
            'step' => ['nullable', 'integer', 'min:-20', 'max:20', 'not_in:0'],
        ]);
        $slot = FarmPrinterSlot::findOrFail($data['slot']);
        abort_unless($slot->farm_printer_id === $row->farm_printer_id, 404);
        $candidate = [
            'nozzle_temp' => $data['t_nozzle_temp'] ?? null, 'bed_temp' => $data['t_bed_temp'] ?? null,
            'process' => ! empty($data['t_process']) ? json_decode($data['t_process'], true) : [],
            'filament' => ! empty($data['t_filament']) ? json_decode($data['t_filament'], true) : [],
        ];
        try {
            $order = $this->tests->create($row, $slot, $data['object'], $candidate, array_intersect_key($data, array_flip(['floors', 'start', 'step'])), $request->user());
        } catch (FarmRefusal $e) {
            return back()->withInput()->with('error', $e->text());
        }

        return redirect()->route('admin.farm.orders.show', $order)->with('status', __('farm.admin.test_started', ['number' => $order->number]));
    }

    /** What the operator saw on the test object → stored with the test, with the advisor's proposal next to it. */
    public function evaluate(Request $request, FarmPrinterMaterial $row, FarmOrder $order): RedirectResponse
    {
        abort_unless($order->isTest() && $order->farm_printer_material_id === $row->id, 404);
        $data = $request->validate([
            'stringing' => ['nullable', 'integer', 'min:0', 'max:3'],
            'overhang_ok' => ['nullable', 'integer', Rule::in([0, 30, 40, 50, 60, 70])],
            'bridge' => ['nullable', Rule::in(['ok', 'sag', 'fail'])],
            'elephant' => ['nullable', 'integer', 'min:0', 'max:2'],
            'corners' => ['nullable', Rule::in(['ok', 'bulge', 'gaps'])],
            'top' => ['nullable', Rule::in(['ok', 'pillow', 'gaps'])],
            'wall' => ['nullable', Rule::in(['ok', 'gaps', 'missing'])],
            'bond' => ['nullable', Rule::in(['ok', 'weak'])],
            'warp' => ['nullable', Rule::in(['ok', 'lift'])],
            'cube_x' => ['nullable', 'numeric', 'min:10', 'max:20'],
            'cube_y' => ['nullable', 'numeric', 'min:10', 'max:20'],
            'cube_z' => ['nullable', 'numeric', 'min:10', 'max:20'],
            'hole' => ['nullable', 'numeric', 'min:5', 'max:10'],
            'best_floor' => ['nullable', 'integer', 'min:1', 'max:10'],
            'score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $result = array_filter($data, fn ($v) => $v !== null && $v !== '');
        $tp = (array) $order->test_params;
        $proposal = TuningAdvisor::advise($result, (array) ($tp['candidate'] ?? []), (string) ($tp['object'] ?? 'quick'), $tp['temps'] ?? null);
        $order->forceFill([
            'test_params' => ['result' => $result, 'advice' => $proposal, 'evaluated_at' => now()->toIso8601String()] + $tp,
            'quality_rating' => $data['score'] ?? $order->quality_rating, 'quality_note' => $data['note'] ?? $order->quality_note,
        ])->save();
        if (! empty($data['score'])) {
            $row->update(['score' => (int) $data['score'], 'tested_at' => now()]);
        }

        return redirect()->route('admin.farm.tuning.edit', $row)->withFragment('test-'.$order->id)->with('status', $proposal['advice'] ? 'Vyhodnoceno, návrh úprav je u testu.' : 'Vyhodnoceno, bez návrhu změn.');
    }

    /** The advisor's proposal becomes the row's next version (still "testing": the next test print says whether it helped). */
    public function apply(FarmPrinterMaterial $row, FarmOrder $order): RedirectResponse
    {
        abort_unless($order->isTest() && $order->farm_printer_material_id === $row->id, 404);
        $proposal = $order->test_params['advice'] ?? null;
        if (! is_array($proposal) || empty($proposal['advice'])) {
            return back()->with('error', 'Tenhle test nemá žádný návrh úprav.');
        }
        $row->revise($this->ownValues($row, (array) $proposal['overrides']), 'test', 'návrh z testu '.$order->number, FarmPrinterMaterial::STATUS_TESTING);

        return redirect()->route('admin.farm.tuning.edit', $row)->with('status', 'Návrh je uložený jako verze '.$row->version.'. Vytiskněte další test.');
    }

    /** Values the kind already states stay the kind's: only what differs lives on the row. */
    private function ownValues(FarmPrinterMaterial $row, array $candidate): array
    {
        $material = $row->material;
        foreach (['nozzle_temp', 'nozzle_temp_first', 'bed_temp'] as $k) {
            if (isset($candidate[$k]) && (int) $candidate[$k] === (int) $material->{$k}) {
                unset($candidate[$k]);
            }
        }
        $candidate['filament'] = array_diff_key((array) ($candidate['filament'] ?? []), $material->sliceOverrides());

        return $candidate;
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
        $row->forceFill(['score' => $data['score'] ?? $row->score, 'tested_at' => now()]);
        $row->revise($this->ownValues($row, $candidate), 'test', ($data['note'] ?? null) ?: 'z testu '.$order->number, FarmPrinterMaterial::STATUS_TUNED);
        if ($data['score'] ?? null) {
            $order->forceFill(['quality_rating' => (int) $data['score']])->save();
        }

        return redirect()->route('admin.farm.tuning.edit', $row)->with('status', __('farm.admin.saved'));
    }
}
