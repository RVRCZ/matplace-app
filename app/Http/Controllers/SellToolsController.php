<?php

namespace App\Http\Controllers;

use App\Domain\Sell\Cost;
use App\Domain\Sell\Plan;
use App\Domain\Sell\Profit;
use App\Models\Calculation;
use App\Models\SellPlan;
use App\Support\Currency;
use App\Support\Money;
use Dompdf\Dompdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The selling and planning pages (session 4, docs/S.md): what a print costs at home, what a sale leaves on a
 * platform, a year's plan, where to sell. The arithmetic lives in App\Domain\Sell and is mirrored on the client
 * (resources/js/site/sell.ts); the pages compute as the visitor types and keep the settings in the browser.
 */
class SellToolsController extends Controller
{
    /** /tools/cost: the cost of a piece on your own printer; ?from=<calculation> fills in the grams and hours and shows our price beside it */
    public function cost(Request $request): View
    {
        $values = Cost::clean([]);
        $from = null;
        $token = (string) $request->query('from', '');
        if ($token !== '' && preg_match('/^[A-Za-z0-9_-]{8,80}$/', $token) && ($calc = Calculation::with('modelFile')->where('token', $token)->first())) {
            $facts = (array) ($calc->slicer ?: $calc->rough ?: []);
            $grams = (float) ($facts['grams'] ?? 0);
            $minutes = (int) ($facts['minutes'] ?? 0);
            if ($grams > 0 && $minutes > 0) {
                $quantity = max(1, (int) ($calc->params['quantity'] ?? 1));
                $values['grams'] = round($grams / $quantity, 1);
                $values['hours'] = round($minutes / 60 / $quantity, 2);
                $totals = array_filter(array_map(fn ($p) => (float) ($p['total'] ?? 0), (array) ($calc->prices ?? [])), fn ($v) => $v > 0);
                $from = ['name' => (string) ($calc->modelFile?->original_name ?? $token), 'grams' => $values['grams'], 'hours' => $values['hours'],
                    'price' => $totals ? round(min($totals) / $quantity, 2) : null, 'url' => $calc->modelFile ? route('home', ['open' => $calc->modelFile->uuid]) : route('home')];
            }
        }
        // a printer signed in: their own hourly rate and gram price are what their time and filament cost them to sell
        $printer = null;
        $user = $request->user();
        if ($user && $user->isPrinter() && ($profile = $user->printerProfile) && ($pricing = $profile->defaultPricing())) {
            $printer = true;
            if ((float) $pricing->hourly_rate > 0) {
                $values['labour_rate'] = (float) $pricing->hourly_rate;
            }
            if ((float) $pricing->price_per_gram > 0) {
                $values['filament_kg'] = round((float) $pricing->price_per_gram * 1000, 0);
            }
        }

        return view('tools.sell.cost', [
            'values' => $values, 'from' => $from, 'printer' => $printer,
            'payload' => ['fields' => Cost::FIELDS, 'values' => $values, 'money' => ['keys' => ['filament_kg', 'printer_price', 'labour_rate', 'other']], 'from' => $from, 'currency' => Currency::current(), 'rate' => Money::rate(),
                'profit' => route('tools.profit'), 'plan' => route('tools.plan')],
        ]);
    }

    /** /tools/profit: what a sale leaves after the platform's fees; ?cost= and ?price= come from the cost page */
    public function profit(Request $request): View
    {
        $values = Profit::clean(['cost' => $request->query('cost'), 'price' => $request->query('price')]);

        return view('tools.sell.profit', [
            'values' => $values,
            'payload' => ['fields' => Profit::FIELDS, 'values' => $values, 'platforms' => config('sell.platforms'), 'rates' => config('sell.rates'), 'vat_pct' => (float) config('sell.vat_pct', 21),
                'currency' => Currency::current(), 'rate' => Money::rate(), 'cost' => route('tools.cost')],
        ]);
    }

    /** /tools/vendors: markets and fairs round a town, where else to sell (built in a later round of session 4) */
    public function vendors(Request $request): View
    {
        abort_unless(view()->exists('tools.sell.vendors'), 404);

        return view('tools.sell.vendors');
    }

    /** /tools/plan: a year of selling, month by month, and the steps still to take */
    public function plan(Request $request): View
    {
        return view('tools.sell.plan', [
            'payload' => ['seasons' => config('sell.seasons'), 'max_products' => Plan::MAX_PRODUCTS, 'currency' => Currency::current(), 'rate' => Money::rate(), 'start' => (int) date('n'),
                'cost' => route('tools.cost'), 'profit' => route('tools.profit'), 'vendors' => route('tools.vendors'), 'pdf' => route('tools.plan.pdf'),
                'plans' => $request->user() ? route('api.sell.plans') : null],
        ]);
    }

    /** POST /tools/plan/pdf: the plan the page holds, as one PDF page (dompdf) in the visitor's currency */
    public function planPdf(Request $request): Response
    {
        $raw = $request->input('plan');
        $in = is_string($raw) ? (array) json_decode($raw, true) : (array) $raw;
        $plan = Plan::clean($in);
        $currency = Currency::current();
        $html = view('tools.sell.plan_pdf', ['plan' => $plan, 'result' => Plan::calculate($in), 'money' => fn (float $v) => Money::show(Money::of($v, $currency))])->render();
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'chroot' => base_path()]);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();
        $name = $plan['name'] !== '' ? Str::slug($plan['name']) : 'plan';

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.($name ?: 'plan').'.pdf"']);
    }

    /** GET /api/sell/plans: the account's saved plans, newest first */
    public function plans(Request $request): JsonResponse
    {
        return response()->json(['plans' => SellPlan::where('user_id', $request->user()->id)->orderByDesc('updated_at')->get()->map(fn (SellPlan $p) => ['id' => $p->id, 'name' => $p->name, 'data' => $p->data, 'updated_at' => $p->updated_at?->toIso8601String()])->all()]);
    }

    /** POST /api/sell/plans {name, data}: a plan saved under its name (the same name is replaced); 20 per account */
    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:1', 'max:80'], 'data' => ['required', 'array']]);
        $user = $request->user();
        $clean = Plan::clean($data['data']);
        $plan = SellPlan::where('user_id', $user->id)->where('name', $data['name'])->first();
        if (! $plan && SellPlan::where('user_id', $user->id)->count() >= SellPlan::MAX_PER_USER) {
            return response()->json(['error' => 'too_many', 'message' => __('sell.plan.save.too_many', ['n' => SellPlan::MAX_PER_USER])], 422);
        }
        $plan = $plan ?: new SellPlan(['user_id' => $user->id, 'name' => $data['name']]);
        $plan->data = $clean;
        $plan->save();

        return response()->json(['plan' => ['id' => $plan->id, 'name' => $plan->name, 'data' => $plan->data, 'updated_at' => $plan->updated_at?->toIso8601String()]], 201);
    }

    /** DELETE /api/sell/plans/{plan} */
    public function deletePlan(Request $request, int $plan): JsonResponse
    {
        SellPlan::where('user_id', $request->user()->id)->where('id', $plan)->delete();

        return response()->json(['ok' => true]);
    }
}
