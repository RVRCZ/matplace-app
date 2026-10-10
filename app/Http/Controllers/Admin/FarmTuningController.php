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
use Illuminate\Support\Collection;
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

    public function edit(Request $request, FarmPrinterMaterial $row): View
    {
        $row->load(['printer.slots.color.material', 'material', 'color']);
        // a hidden test is out of the way, not gone: its photos, evaluation and number stay (notes and history cite them)
        $showHidden = $request->boolean('skryte');
        $hidden = $row->testOrders()->where('test_params->hidden', true)->count();
        $effective = PrintProfile::for($row->printer, $row->material, $row->color);
        $slots = $row->printer->slots->filter(fn (FarmPrinterSlot $s) => $s->color && $s->color->farm_material_id === $row->farm_material_id
            && (! $row->farm_color_id || $s->farm_color_id === $row->farm_color_id));

        return view('admin.farm.tuning_edit', [
            'row' => $row,
            'effective' => $effective,
            'slots' => $slots->values(),
            'tests' => $row->testOrders()->with(['printJobs', 'modelFile'])
                ->when(! $showHidden, fn ($q) => $q->where(fn ($q) => $q->whereNull('test_params->hidden')->orWhere('test_params->hidden', false)))
                ->limit(20)->get(),
            'hiddenTests' => $hidden,
            'showHidden' => $showHidden,
            'objects' => TestPrintService::OBJECTS,
            'generator' => $this->tests->available(),
            'siblings' => $row->farm_color_id ? collect() : $this->siblings($row),
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

    /**
     * One row's settings onto the other machines of the same type with the same kind: the chosen groups are added to
     * what each of those rows holds (the same keys are replaced, the rest stays), every changed row gets a new version
     * and goes back to "testing". A tuned row is left alone - nothing overwrites it without a test print.
     */
    public function spread(Request $request, FarmPrinterMaterial $row): RedirectResponse
    {
        abort_if($row->farm_color_id !== null, 404);
        $data = $request->validate([
            'what' => ['required', 'array', 'min:1'],
            'what.*' => [Rule::in(['process', 'filament', 'temps'])],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $source = (array) $row->overrides;
        $take = [];
        foreach (['process', 'filament'] as $group) {
            if (in_array($group, $data['what'], true) && ! empty($source[$group])) {
                $take[$group] = (array) $source[$group];
            }
        }
        if (in_array('temps', $data['what'], true)) {
            $take += array_intersect_key($source, array_flip(['nozzle_temp', 'nozzle_temp_first', 'bed_temp']));
        }
        if (! $take) {
            return back()->with('error', __('farm.admin.tuning.spread_empty'));
        }

        $why = __('farm.admin.tuning.spread_history', ['id' => $row->id, 'printer' => $row->printer->name]).(! empty($data['note']) ? ', '.$data['note'] : '');
        $done = $same = $skipped = [];
        foreach ($this->siblings($row) as $target) {
            $name = ['printer' => $target->printer->name, 'kind' => $target->label()];
            if ($target->status === FarmPrinterMaterial::STATUS_TUNED) {
                $skipped[] = __('farm.admin.tuning.spread_skipped_item', $name);

                continue;
            }
            $overrides = (array) $target->overrides;
            foreach ($take as $key => $value) {
                $overrides[$key] = is_array($value) ? $value + (array) ($overrides[$key] ?? []) : $value;
            }
            $before = $target->version;
            $target->revise($overrides, 'manual', $why, FarmPrinterMaterial::STATUS_TESTING);
            if ($target->version === $before) {
                $same[] = $target->printer->name;
            } else {
                $done[] = __('farm.admin.tuning.spread_item', $name + ['from' => $before, 'to' => $target->version]);
            }
        }
        $said = array_filter([
            $done ? __('farm.admin.tuning.spread_done', ['list' => implode(', ', $done)]) : null,
            $same ? __('farm.admin.tuning.spread_same', ['list' => implode(', ', $same)]) : null,
            $skipped ? __('farm.admin.tuning.spread_skipped', ['list' => implode(', ', $skipped)]) : null,
        ]);

        return redirect()->route('admin.farm.tuning.edit', $row)->with('status', $said ? implode(' ', $said) : __('farm.admin.tuning.spread_none'));
    }

    /**
     * The same kind on the other machines of this row's type: the same printer key without its number
     * (kobra-s1-01 and kobra-s1-07, never a kobra-3-max) and the same nozzle.
     *
     * @return Collection<int, FarmPrinterMaterial>
     */
    private function siblings(FarmPrinterMaterial $row): Collection
    {
        $type = fn (FarmPrinter $p) => preg_replace('/-\d+$/', '', (string) $p->key).'|'.number_format((float) $p->nozzle_mm, 2);
        $own = $type($row->printer);

        return FarmPrinterMaterial::with(['printer', 'material'])->whereNull('farm_color_id')
            ->where('farm_material_id', $row->farm_material_id)->where('id', '!=', $row->id)->get()
            ->filter(fn (FarmPrinterMaterial $r) => $r->printer && $type($r->printer) === $own)
            ->sortBy(fn (FarmPrinterMaterial $r) => $r->printer->key)->values();
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
            't_ironing' => ['nullable', 'boolean'],
            't_scarf' => ['nullable', 'boolean'],
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
        if ($data['object'] === 'seam' && $request->boolean('t_scarf')) {
            $candidate['process'] += TestPrintService::SCARF;
        }
        try {
            $order = $this->tests->create($row, $slot, $data['object'], $candidate, array_intersect_key($data, array_flip(['floors', 'start', 'step'])), $request->user(), $request->boolean('t_ironing'));
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
            'corners' => ['nullable', Rule::in(['ok', 'bulge', 'round', 'gaps'])],
            'ironing' => ['nullable', Rule::in(['ok', 'lines', 'bumps', 'rough'])],
            'seam' => ['nullable', 'integer', 'min:0', 'max:3'],
            'seam_fault' => ['nullable', Rule::in(['none', 'bulge', 'gap'])],
            'top' => ['nullable', Rule::in(['ok', 'pillow', 'gaps'])],
            'wall' => ['nullable', Rule::in(['ok', 'gaps', 'missing'])],
            'bond' => ['nullable', Rule::in(['ok', 'weak'])],
            'warp' => ['nullable', Rule::in(['ok', 'lift'])],
            'cube_x' => ['nullable', 'numeric', 'min:10', 'max:25'],
            'cube_y' => ['nullable', 'numeric', 'min:10', 'max:25'],
            'cube_z' => ['nullable', 'numeric', 'min:10', 'max:25'],
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

        return redirect()->route('admin.farm.tuning.edit', $row)->withFragment('test-'.$order->id)
            ->with('status', $order->number.': navržené úpravy jsou v řádku jako verze '.$row->version.' (stav Testuje se). Tisky '
                .$row->label().' na '.$row->printer->name.' už pojedou s nimi – vytiskněte další test, ať víte, jestli pomohly.');
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
        // a test switches ironing on to judge it; the row keeps the tuned ironing values but must not iron every print
        if (! isset(((array) $row->overrides)['process']['ironing_type'])) {
            unset($candidate['process']['ironing_type']);
        }

        return $candidate;
    }

    /** A test out of the list (or back in): nothing is deleted, the number keeps its photos, evaluation and history. */
    public function hide(Request $request, FarmPrinterMaterial $row, FarmOrder $order): RedirectResponse
    {
        abort_unless($order->isTest() && $order->farm_printer_material_id === $row->id, 404);
        $hide = ! $request->boolean('show');
        if ($hide && in_array($order->status, [FarmOrder::STATUS_UPLOADED, FarmOrder::STATUS_SLICED, FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING], true)) {
            return back()->with('error', $order->number.' ještě běží, skrýt jde až hotový nebo zrušený test.');
        }
        $order->forceFill(['test_params' => ['hidden' => $hide] + (array) $order->test_params])->save();

        return redirect()->route('admin.farm.tuning.edit', $hide ? $row : [$row, 'skryte' => 1])->withFragment($hide ? 'testy' : 'test-'.$order->id)
            ->with('status', $hide ? $order->number.' je skrytý. Najdete ho pod „Zobrazit skryté testy“.' : $order->number.' je zase v seznamu.');
    }

    /** "This test printed well": the row takes over what the test was printed with. */
    public function adopt(Request $request, FarmPrinterMaterial $row, FarmOrder $order): RedirectResponse
    {
        abort_unless($order->isTest() && $order->farm_printer_material_id === $row->id, 404);
        $data = $request->validate(['score' => ['nullable', 'integer', 'min:1', 'max:5'], 'nozzle_temp' => ['nullable', 'integer', 'min:150', 'max:350'], 'note' => ['nullable', 'string', 'max:300']]);
        // only a test that printed well may become "tuned"; a poor one has a proposal of changes instead (one click
        // adopted a 2/5 test on 27 Sep 2026)
        $score = (int) ($data['score'] ?? $order->quality_rating ?? 0);
        if ($score < 4) {
            return back()->with('error', $score ? 'Test s hodnocením '.$score.'/5 nelze převzít jako vyladěný. Použijte návrh úprav a vytiskněte další test.' : 'Nejdřív test ohodnoťte (4 nebo 5), teprve pak jde převzít.');
        }
        $candidate = (array) ($order->test_params['candidate'] ?? []);
        // a good test also says what the machine manages without supports: the row prints with that from now on
        $tp = (array) $order->test_params;
        $candidate['process'] = TuningAdvisor::supportSettings((array) ($tp['result'] ?? []), (string) ($tp['object'] ?? 'quick')) + (array) ($candidate['process'] ?? []);
        if (! empty($data['nozzle_temp'])) {
            // a tower: the operator picked the floor that printed best
            $candidate['nozzle_temp'] = (int) $data['nozzle_temp'];
            $candidate['nozzle_temp_first'] = (int) $data['nozzle_temp'] + 5;
        }
        $row->forceFill(['score' => $data['score'] ?? $row->score, 'tested_at' => now()]);
        $before = $row->version;
        $row->revise($this->ownValues($row, $candidate), 'test', ($data['note'] ?? null) ?: 'z testu '.$order->number, FarmPrinterMaterial::STATUS_TUNED);
        if ($row->version === $before) {
            // the test printed with exactly what the row holds: no new version, so the confirmation goes to the notes
            // (T26-000021 on 29 Sep 2026 changed only the state, and nothing showed which test had confirmed it)
            $line = 'Potvrzeno testem '.$order->number.' ('.$score.'/5), '.now()->format('j. n. Y').(! empty($data['note']) ? ': '.$data['note'] : '').'.';
            $row->forceFill(['notes' => mb_substr(trim(($row->notes ? $row->notes."\n" : '').$line), -2000)])->save();
        }
        if ($data['score'] ?? null) {
            $order->forceFill(['quality_rating' => (int) $data['score']])->save();
        }

        return redirect()->route('admin.farm.tuning.edit', $row)->withFragment('test-'.$order->id)
            ->with('status', $order->number.': '.($row->version === $before ? 'nastavení se shoduje s řádkem, hodnoty beze změny' : 'nastavení testu je v řádku jako verze '.$row->version)
                .'; řádek je Vyladěný. Tisky '.$row->label().' na '.$row->printer->name.' s ním pojedou. Jiné tiskárny se nemění.');
    }
}
