<?php

namespace App\Http\Controllers\Printer;

use App\Domain\Calculation\MaterialCatalog;
use App\Engines\Converter\ConverterChain;
use App\Http\Controllers\Api\CalculationController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Controller;
use App\Models\Calculation;
use App\Models\PricingProfile;
use App\Models\PrinterMachine;
use App\Models\PrinterMaterial;
use App\Models\PrinterProfile;
use App\Models\Quote;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** /tiskar — the printer's own tools: profile with prices, calculator, quotes. No dashboards, no charts. */
class PrinterController extends Controller
{
    private function current(Request $request): PrinterProfile
    {
        return $request->user()->printerProfile()->with(['materials', 'machines', 'pricingProfiles'])->firstOrFail();
    }

    public function dashboard(Request $request): View
    {
        $profile = $this->current($request);
        $quotes = Quote::where('printer_profile_id', $profile->id)->latest('id')->limit(10)->get();
        $checklist = [
            'materials' => $profile->materials->isNotEmpty(),
            'pricing' => $profile->defaultPricing() !== null && (float) $profile->defaultPricing()->hourly_rate > 0,
            'machine' => $profile->machines->isNotEmpty(),
            'contact' => (bool) ($profile->contact_email || $profile->contact_phone),
            'location' => (bool) ($request->user()->zip || $request->user()->city),
        ];

        return view('printer.dashboard', ['profile' => $profile, 'quotes' => $quotes, 'checklist' => $checklist]);
    }

    public function profile(Request $request, MaterialCatalog $materials): View
    {
        $profile = $this->current($request);

        return view('printer.profile', [
            'profile' => $profile,
            'pricing' => $profile->defaultPricing() ?? new PricingProfile(['name' => 'Standard', 'hourly_rate' => 60, 'price_per_gram' => 2, 'setup_fee' => 0, 'margin_pct' => 0, 'lead_time_days' => 5]),
            'materials' => $materials->all(),
            'user' => $request->user(),
        ]);
    }

    /** One page, five fields on top, everything else under "More". */
    public function updateProfile(Request $request, MaterialCatalog $materials): RedirectResponse
    {
        $profile = $this->current($request);
        $user = $request->user();
        $codes = array_map(fn ($m) => $m['code'], $materials->all());

        $data = $request->validate([
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:100000'],
            'price_per_gram' => ['required', 'numeric', 'min:0', 'max:10000'],
            'setup_fee' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'margin_pct' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'express_pct' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'qty_discounts' => ['nullable', 'string', 'max:200'],   // "5:10, 20:20"
            'materials' => ['nullable', 'array'],
            'materials.*' => ['in:'.implode(',', $codes)],
            'material_price' => ['nullable', 'array'],
            'material_price.*' => ['nullable', 'numeric', 'min:0'],
            'display_name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'ico' => ['nullable', 'string', 'max:12'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'delivery_options' => ['nullable', 'array'],
            'delivery_options.*' => ['in:pickup,zasilkovna,courier'],
            'capacity' => ['required', 'in:open,busy,paused'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'machine_name' => ['nullable', 'string', 'max:120'],
            'machine_technology' => ['nullable', 'in:fdm,resin'],
            'machine_bed_x' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'machine_bed_y' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'machine_bed_z' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'machine_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'visible' => ['nullable', 'boolean'],
        ]);

        // pricing (the five fields + more)
        $pricing = $profile->defaultPricing() ?? new PricingProfile(['printer_profile_id' => $profile->id, 'name' => 'Standard', 'is_default' => true]);
        $pricing->fill([
            'printer_profile_id' => $profile->id,
            'hourly_rate' => $data['hourly_rate'],
            'price_per_gram' => $data['price_per_gram'],
            'setup_fee' => $data['setup_fee'] ?? 0,
            'margin_pct' => $data['margin_pct'] ?? 0,
            'min_price' => $data['min_price'] ?? 0,
            'lead_time_days' => $data['lead_time_days'],
            'express_pct' => $data['express_pct'] ?? 0,
            'qty_discounts' => self::parseDiscounts($data['qty_discounts'] ?? ''),
        ])->save();

        // materials offered (+ optional per-material gram price)
        $wanted = array_values(array_unique($data['materials'] ?? []));
        PrinterMaterial::where('printer_profile_id', $profile->id)->whereNotIn('material_code', $wanted ?: [''])->delete();
        foreach ($wanted as $code) {
            $price = $data['material_price'][$code] ?? null;
            PrinterMaterial::updateOrCreate(
                ['printer_profile_id' => $profile->id, 'material_code' => $code],
                ['price_per_gram' => $price === null || $price === '' ? null : (float) $price, 'in_stock' => true],
            );
        }

        // first machine (more machines later in the list UI)
        if (! empty($data['machine_name'])) {
            $machine = $profile->machines->first() ?? new PrinterMachine(['printer_profile_id' => $profile->id]);
            $machine->fill([
                'name' => $data['machine_name'],
                'technology' => $data['machine_technology'] ?? 'fdm',
                'bed_x' => $data['machine_bed_x'] ?? null,
                'bed_y' => $data['machine_bed_y'] ?? null,
                'bed_z' => $data['machine_bed_z'] ?? null,
                'count' => $data['machine_count'] ?? 1,
            ])->save();
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos/'.$profile->id, 'public');
            $profile->logo_path = $path;
        }

        $profile->fill([
            'display_name' => $data['display_name'],
            'company' => $data['company'] ?? null,
            'ico' => $data['ico'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'pickup_address' => $data['pickup_address'] ?? null,
            'delivery_options' => array_values($data['delivery_options'] ?? []),
            'capacity' => $data['capacity'],
            'bio' => $data['bio'] ?? null,
            'lead_time_days' => $data['lead_time_days'],
            'visible' => $request->boolean('visible', true) && $wanted !== [],
        ])->save();

        $user->fill(['zip' => $data['zip'] ?? $user->zip, 'city' => $data['city'] ?? $user->city])->save();

        return redirect()->route('printer.profile')->with('status', __('printer.profile_saved'));
    }

    /** Calculator in printer mode: same screen, the printer's own price list first, "Create quote" button. */
    public function calculator(Request $request, MaterialCatalog $materials, ConverterChain $converters, ?Calculation $calculation = null): View
    {
        $profile = $this->current($request);
        $initial = null;
        if ($calculation) {
            abort_unless($calculation->owner_user_id === $request->user()->id, 403);
            $initial = CalculationController::describe($calculation->load('modelFile'));
        }

        return view('calculator.index', [
            'config' => ConfigController::payload($materials, $converters),
            'initial' => $initial,
            'mode' => 'printer',
            'ownProfileId' => $profile->id,
        ]);
    }

    /** "5:10, 20:20" → [{from:5,pct:10},{from:20,pct:20}] */
    public static function parseDiscounts(string $s): array
    {
        $out = [];
        foreach (preg_split('/[,;]+/', $s) as $pair) {
            if (preg_match('/^\s*(\d+)\s*[:=]\s*(\d+(?:[.,]\d+)?)\s*%?\s*$/', $pair, $m)) {
                $out[] = ['from' => (int) $m[1], 'pct' => (float) str_replace(',', '.', $m[2])];
            }
        }
        usort($out, fn ($a, $b) => $a['from'] <=> $b['from']);

        return $out;
    }

    public static function discountsToString(?array $d): string
    {
        return implode(', ', array_map(fn ($x) => $x['from'].':'.rtrim(rtrim(number_format($x['pct'], 1, '.', ''), '0'), '.'), $d ?? []));
    }

    public static function logoUrl(PrinterProfile $p): ?string
    {
        return $p->logo_path ? Storage::disk('public')->url($p->logo_path) : null;
    }
}
