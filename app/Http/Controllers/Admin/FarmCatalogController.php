<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\ProfileLibrary;
use App\Domain\Farm\Wallet;
use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\FarmAgent;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Farm data an admin maintains: printers and their slots, materials, colours, prices and limits, agents, credit corrections. */
class FarmCatalogController extends Controller
{
    // ── printers ─────────────────────────────────────────────────────────────
    public function printers(): View
    {
        return view('admin.farm.printers', [
            'printers' => FarmPrinter::with(['slots.color', 'agent'])->orderBy('id')->get(),
            'calibration' => $this->calibration(),
        ]);
    }

    public function editPrinter(?FarmPrinter $printer = null): View
    {
        return view('admin.farm.printer_edit', [
            'printer' => $printer ?? new FarmPrinter(['mode' => FarmPrinter::MODE_MANUAL, 'bed_x' => 250, 'bed_y' => 250, 'bed_z' => 250, 'nozzle_mm' => 0.4, 'time_factor' => 1, 'weight_factor' => 1, 'enabled' => true, 'machine_profile' => 'machine.json', 'process_profiles' => ['draft' => 'process_draft.json', 'standard' => 'process_standard.json', 'fine' => 'process_fine.json']]),
            'agents' => FarmAgent::whereNull('revoked_at')->orderBy('name')->get(),
            'colors' => FarmColor::with('material')->where('enabled', true)->get()->sortBy(fn ($c) => [$c->material->sort, $c->sort])->values(),
            'calibration' => $printer ? ($this->calibration()[$printer->id] ?? null) : null,
        ]);
    }

    public function savePrinter(Request $request, ?FarmPrinter $printer = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'model' => ['required', 'string', 'max:80'],
            'key' => ['required', 'alpha_dash', 'max:40', Rule::unique('farm_printers', 'key')->ignore($printer?->id)],
            'farm_agent_id' => ['nullable', 'exists:farm_agents,id'],
            'mode' => ['required', 'in:manual,agent'],
            'enabled' => ['nullable', 'boolean'],
            'bed_x' => ['required', 'numeric', 'min:50', 'max:1000'],
            'bed_y' => ['required', 'numeric', 'min:50', 'max:1000'],
            'bed_z' => ['required', 'numeric', 'min:50', 'max:1000'],
            'nozzle_mm' => ['required', 'numeric', 'min:0.1', 'max:2'],
            'machine_profile' => ['required', 'string', 'max:120'],
            'process_profiles' => ['required', 'json'],
            'machine_overrides' => ['nullable', 'json'],
            'process_overrides' => ['nullable', 'json'],
            'time_factor' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'weight_factor' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'slots' => ['nullable', 'array', 'max:16'],
            'slots.*.color' => ['nullable', 'exists:farm_colors,id'],
            'slots.*.remaining_g' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'slots.*.enabled' => ['nullable', 'boolean'],
        ]);
        if ($data['mode'] === FarmPrinter::MODE_AGENT && empty($data['farm_agent_id'])) {
            throw ValidationException::withMessages(['farm_agent_id' => __('farm.admin.agent_needed')]);
        }
        foreach (['process_profiles', 'machine_overrides', 'process_overrides'] as $json) {
            $data[$json] = isset($data[$json]) && $data[$json] !== '' ? json_decode($data[$json], true) : null;
        }
        $slots = $data['slots'] ?? [];
        unset($data['slots']);
        $data['enabled'] = $request->boolean('enabled');

        $printer = $printer ?? new FarmPrinter;
        $printer->fill($data)->save();
        foreach ($slots as $index => $s) {
            $printer->slots()->updateOrCreate(['slot' => (int) $index], [
                'farm_color_id' => $s['color'] ?? null, 'remaining_g' => (float) ($s['remaining_g'] ?? 0), 'enabled' => ! empty($s['enabled']) && ! empty($s['color']),
            ]);
        }
        app(ProfileLibrary::class)->sync();

        return redirect()->route('admin.farm.printers')->with('status', __('farm.admin.saved'));
    }

    /**
     * Measured ÷ estimated over finished prints of each printer: the factor that would have made the estimates right.
     *
     * @return array<int, array{n: int, time: float|null, weight: float|null}>
     */
    private function calibration(): array
    {
        $out = [];
        $orders = FarmOrder::whereIn('status', [FarmOrder::STATUS_DONE, FarmOrder::STATUS_HANDED_OVER])->whereNotNull('farm_printer_id')
            ->where(fn ($q) => $q->whereNotNull('actual_minutes')->orWhereNotNull('actual_grams'))->latest('id')->limit(500)->get();
        foreach ($orders->groupBy('farm_printer_id') as $printerId => $rows) {
            $t = $rows->filter(fn ($o) => $o->actual_minutes && $o->est_minutes);
            $w = $rows->filter(fn ($o) => $o->actual_grams && $o->est_grams);
            $out[(int) $printerId] = [
                'n' => $rows->count(),
                'time' => $t->count() ? round($t->sum('actual_minutes') / $t->sum('est_minutes'), 3) : null,
                'weight' => $w->count() ? round($w->sum('actual_grams') / $w->sum('est_grams'), 3) : null,
            ];
        }

        return $out;
    }

    // ── materials and colours ────────────────────────────────────────────────
    public function materials(): View
    {
        return view('admin.farm.materials', ['materials' => FarmMaterial::with(['colors' => fn ($q) => $q->orderBy('sort')])->orderBy('sort')->get()]);
    }

    public function saveMaterial(Request $request, ?FarmMaterial $material = null): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'regex:/^[A-Za-z0-9+\-]+$/', 'max:20', Rule::unique('farm_materials', 'code')->where('finish', $request->input('finish', 'solid'))->ignore($material?->id)],
            'finish' => ['required', Rule::in(FarmMaterial::FINISHES)],
            'name' => ['required', 'string', 'max:80'],
            'filament_profile' => ['required', 'string', 'max:120'],
            'filament_overrides' => ['nullable', 'json'],
            'density' => ['required', 'numeric', 'min:0.5', 'max:5'],
            'nozzle_temp' => ['nullable', 'integer', 'min:150', 'max:350'],
            'nozzle_temp_first' => ['nullable', 'integer', 'min:150', 'max:350'],
            'bed_temp' => ['nullable', 'integer', 'min:0', 'max:150'],
            'price_per_gram' => ['required', 'numeric', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['filament_overrides'] = ! empty($data['filament_overrides']) ? json_decode($data['filament_overrides'], true) : null;
        $data['enabled'] = $request->boolean('enabled');
        ($material ?? new FarmMaterial)->fill($data)->save();
        // a new kind (or a re-enabled one) gets its tuning row on every machine with the best known starting values
        app(ProfileLibrary::class)->sync();

        return back()->with('status', __('farm.admin.saved'));
    }

    public function saveColor(Request $request, ?FarmColor $color = null): RedirectResponse
    {
        $data = $request->validate([
            'farm_material_id' => ['required', 'exists:farm_materials,id'],
            'name' => ['required', 'string', 'max:80'],
            'name_en' => ['nullable', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:80'],
            'hex' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'photo' => ['nullable', 'image', 'max:8192'],
            'print_overrides' => ['nullable', 'json'],
            'test_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $color = $color ?? new FarmColor;
        $color->fill(['farm_material_id' => $data['farm_material_id'], 'name' => $data['name'], 'name_en' => $data['name_en'] ?? null, 'code' => $data['code'] ?? null,
            'hex' => strtolower($data['hex']), 'enabled' => $request->boolean('enabled'), 'in_stock' => $request->boolean('in_stock', true),
            'print_overrides' => ! empty($data['print_overrides']) ? json_decode($data['print_overrides'], true) : null, 'test_notes' => $data['test_notes'] ?? null]);
        if ($request->hasFile('photo')) {
            $color->photo_path = $request->file('photo')->storeAs('farm/colors', Str::uuid().'.'.$request->file('photo')->extension(), 'public');
        }
        $color->save();

        return back()->with('status', __('farm.admin.saved'));
    }

    // ── settings ─────────────────────────────────────────────────────────────
    public function settings(FarmSettings $settings): View
    {
        return view('admin.farm.settings', ['settings' => $settings->all()]);
    }

    public function saveSettings(Request $request, FarmSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'max_upload_mb' => ['required', 'integer', 'min:1', 'max:500'],
            'daily_slices_per_user' => ['required', 'integer', 'min:0', 'max:1000'],
            'min_model_mm' => ['required', 'numeric', 'min:0', 'max:100'],
            'bed_margin_mm' => ['required', 'numeric', 'min:0', 'max:50'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'fixed_fee' => ['required', 'numeric', 'min:0'],
            'min_price' => ['required', 'numeric', 'min:0'],
            'vat_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'rounding' => ['required', 'numeric', 'min:0', 'max:1000'],
            'shipping_price' => ['required', 'numeric', 'min:0'],
            'topup_amounts' => ['required', 'string', 'max:120'],
            'topup_min' => ['required', 'integer', 'min:1'],
            'topup_max' => ['required', 'integer', 'gte:topup_min'],
            'generation_price' => ['required', 'numeric', 'min:0', 'max:10000'],
            'changeover_minutes' => ['required', 'integer', 'min:0', 'max:600'],
            'offline_after_seconds' => ['required', 'integer', 'min:30', 'max:3600'],
            'terms_version' => ['required', 'string', 'max:20'],
            'admin_email' => ['nullable', 'email'],
            'qualities' => ['required', 'json'],
            'strengths' => ['required', 'json'],
        ]);
        $data['topup_amounts'] = array_values(array_filter(array_map('intval', preg_split('/[\s,;]+/', $data['topup_amounts']))));
        $data['qualities'] = json_decode($data['qualities'], true);
        $data['strengths'] = json_decode($data['strengths'], true);
        $data['require_approval'] = $request->boolean('require_approval');
        $data['marketplace'] = $request->boolean('marketplace');
        $data['farm_open'] = $request->boolean('farm_open');
        $data['delivery_modes'] = array_values(array_intersect(['pickup', 'shipping'], (array) $request->input('delivery_modes', ['pickup']))) ?: ['pickup'];
        foreach ($data as $key => $value) {
            $settings->set($key, $value);
        }

        return back()->with('status', __('farm.admin.saved'));
    }

    // ── agents ───────────────────────────────────────────────────────────────
    public function agents(): View
    {
        return view('admin.farm.agents', ['agents' => FarmAgent::with('printers')->orderBy('id')->get()]);
    }

    public function createAgent(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        [$agent, $token] = FarmAgent::issue($data['name']);

        return back()->with('agent_token', ['name' => $agent->name, 'token' => $token]);
    }

    public function rotateAgent(FarmAgent $agent): RedirectResponse
    {
        return back()->with('agent_token', ['name' => $agent->name, 'token' => $agent->rotateToken()]);
    }

    public function revokeAgent(FarmAgent $agent): RedirectResponse
    {
        $agent->update(['revoked_at' => now()]);

        return back()->with('status', __('farm.admin.saved'));
    }

    // ── credit corrections ───────────────────────────────────────────────────
    public function credit(Request $request, Wallet $wallet): View
    {
        $user = $request->query('email') ? User::where('email', $request->query('email'))->first() : null;

        return view('admin.farm.credit', [
            'user' => $user,
            'email' => (string) $request->query('email', ''),
            'balance' => $user ? $wallet->balance($user) : null,
            'transactions' => $user ? CreditTransaction::where('user_id', $user->id)->latest('id')->limit(50)->get() : collect(),
        ]);
    }

    public function adjustCredit(Request $request, Wallet $wallet): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'amount' => ['required', 'numeric', 'not_in:0', 'min:-100000', 'max:100000'], 'note' => ['required', 'string', 'max:300']]);
        $wallet->adjust(User::findOrFail($data['user_id']), (float) $data['amount'], $data['note'], $request->user()->id);

        return back()->with('status', __('farm.admin.saved'));
    }
}
