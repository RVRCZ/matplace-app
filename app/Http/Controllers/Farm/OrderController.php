<?php

namespace App\Http\Controllers\Farm;

use App\Domain\Farm\Dispatcher;
use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\InsufficientCredit;
use App\Domain\Farm\ModelValidator;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\Palette;
use App\Domain\Farm\PlateLayout;
use App\Domain\Farm\PrintSettings;
use App\Domain\Farm\Shipping;
use App\Domain\Farm\Wallet;
use App\Domain\Social\SocialPublisher;
use App\Domain\Tools\ArtGenerator;
use App\Domain\YouTube\FarmVideos;
use App\Engines\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Calculation;
use App\Models\CatalogModel;
use App\Models\DesignerModel;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterSlot;
use App\Models\ModelFile;
use App\Models\Payment;
use App\Support\Countries;
use App\Support\Currency;
use App\Support\Money;
use App\Support\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** "Print on our printer" (the customer rents it): the customer's side of a farm order. Logged-in users only; an order is visible to its owner and admins. */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders, private readonly FarmSettings $settings, private readonly Wallet $wallet, private readonly Shipping $shipping) {}

    /** Entrance: from the calculator with ?file=<uuid>, or empty with an upload field. */
    public function start(Request $request): View
    {
        $file = $request->query('file') ? ModelFile::where('uuid', $request->query('file'))->first() : null;
        // a designer's model from the catalogue: the file is the card's, the customer never holds it
        $card = $request->query('designer_model') ? DesignerModel::with(['profile', 'modelFile', 'images'])->find((int) $request->query('designer_model')) : null;
        $card = $card?->isPrintable() ? $card : null;
        if ($card) {
            $file = $card->modelFile;
        }
        // the customer's own file of a model seen in the inspiration catalogue (any licence: they rent the printer and print for themselves)
        $inspiration = $request->query('source') ? CatalogModel::shown()->find((int) $request->query('source')) : null;
        $quality = (string) $request->query('quality', 'standard');
        $strength = (string) $request->query('strength', 'standard');
        $supports = $request->query('supports') === 'off' ? 'off' : 'auto';
        // a repeated print hands its settings over in the address (OrderController::repeat)
        $copies = max(1, min(PlateLayout::MAX_COPIES, (int) $request->query('copies', 1)));
        $scale = max(0.25, min((float) config('pricing.max_scale', 4), (float) $request->query('scale', 1)));
        $wantedColor = (int) $request->query('color', 0);
        $material = null;
        // from the calculator: the shared calculation knows the model, the quality and how many pieces the customer wanted
        if (! $file && $request->query('calc') && ($calc = Calculation::with('modelFile')->where('token', $request->query('calc'))->first())) {
            $file = $calc->modelFile;
            $quality = (string) ($calc->params['quality'] ?? $quality);
            $copies = max(1, min(PlateLayout::MAX_COPIES, (int) ($calc->params['quantity'] ?? 1)));
            $scale = max(0.25, min((float) config('pricing.max_scale', 4), (float) ($calc->params['scale'] ?? 1)));
            $material = $calc->params['material'] ?? null;
            // "no supports" stays "no"; the farm has no forced "yes", that one is left to the slicer
            $supports = ($calc->params['supports'] ?? null) === false ? 'off' : $supports;
            // the infill slider becomes the nearest strength preset
            if (isset($calc->params['infill']) && ($presets = collect($this->settings->get('strengths'))->map(fn ($s) => abs((int) $s['infill'] - (int) $calc->params['infill']))->sort())->isNotEmpty()) {
                $strength = (string) $presets->keys()->first();
            }
        }
        // a refused order (too big, daily limit…) comes back with what the customer had chosen, not with the defaults
        $old = (array) $request->session()->getOldInput();
        if ($old) {
            $file ??= isset($old['file']) && ! $card ? ModelFile::where('uuid', (string) $old['file'])->first() : null;
            $quality = (string) ($old['quality'] ?? $quality);
            $strength = (string) ($old['strength'] ?? $strength);
            $supports = ($old['supports'] ?? $supports) === 'off' ? 'off' : 'auto';
            $copies = max(1, min(PlateLayout::MAX_COPIES, (int) ($old['copies'] ?? $copies)));
            $scale = max(0.25, min((float) config('pricing.max_scale', 4), (float) ($old['scale'] ?? $scale)));
            $wantedColor = (int) ($old['color'] ?? $wantedColor);
        }
        // the colours loaded right now; those of the calculator's material kind come first
        $offered = $this->orders->offeredColors($quality);
        // a plate with a code or a text prints in two colours: the second one has to sit in the same machine (its ACE
        // changes the spool), so every colour lists the other spools of its machine that go with it
        $twoColor = $file?->colorChangeMm();
        // every change of the design (one for a plate with a text, one per colour for a picture in filament colours)
        $changes = $file ? $file->colorChanges() : [];
        $twoColor = $twoColor ?? ($changes[0]['z'] ?? null);
        // the separately printed parts of the design (a box and its lid, the plates of a layered picture): each may
        // take its own spool of the machine, printed one after another
        $parts = $file ? OrderService::designParts($file) : [];
        $colors = $offered->map(fn ($r) => [
            'id' => $r['color']->id, 'name' => $r['color']->displayName(), 'kind' => $r['color']->material->label(), 'code' => $r['color']->material->code, 'hex' => $r['color']->hex,
            'photo' => $r['color']->photoUrl(), 'printer' => $r['printer']->name, 'bed' => (int) $r['printer']->bed_x.' × '.(int) $r['printer']->bed_y.' mm', 'enough' => $r['slot']->availableGrams() > 50,
            'seconds' => $twoColor || $parts ? $this->orders->secondSpools($r['slot'])->map(fn ($s) => ['id' => $s->color->id, 'name' => $s->color->displayName(), 'kind' => $s->color->material->label(), 'hex' => $s->color->hex, 'photo' => $s->color->photoUrl()])->values()->all() : [],
        ])->sortBy(fn ($c) => [$c['code'] === $material ? 0 : 1, $c['name']])->values()->all();
        // the colour of the print being repeated when it is still loaded, else the first one on offer
        $preselect = collect($colors)->firstWhere('id', $wantedColor)['id'] ?? ($colors[0]['id'] ?? null);
        // a QR code reads only in two colours: unless the address names the colours, the pair of spools nearest to the
        // design is ticked (a light plate, a dark code), never "one colour"
        // what the customer had ticked (a refused order, "print again"), else the spools nearest to the design's colours
        $wantedChanges = array_values(array_map('intval', (array) ($old['change_color'] ?? $request->query('change', []))));
        if (! $wantedChanges && ($one = (int) ($old['second_color'] ?? $request->query('second')))) {
            $wantedChanges = [$one];
        }
        $bodyHex = $file ? (($file->tool_params['part_colors']['body']['hex'] ?? null) ?: ($file->codeColors()[0] ?? null)) : null;
        // only a design that names its colours (a picture in filament colours, a QR code) has them ticked in advance;
        // a plate with a raised text is fine in one colour and starts that way, as it always did
        $named = $file && (! empty($file->tool_params['color_changes']) || $file->codeColors() !== null);
        if ($named && $changes && ! $wantedColor && ! $wantedChanges && ($near = self::nearestSet($colors, $bodyHex, array_column($changes, 'hex')))) {
            [$preselect, $wantedChanges] = $near;
        }
        // a change is ticked only when the machine of the first colour really holds the spool (or it is that very colour)
        $machine = collect(collect($colors)->firstWhere('id', $preselect)['seconds'] ?? [])->pluck('id')->push($preselect);
        $changePreselect = array_map(fn ($i) => $machine->contains($wantedChanges[$i] ?? 0) ? (int) $wantedChanges[$i] : 0, array_keys($changes));
        $secondPreselect = $changePreselect[0] ?? 0;
        // by parts: what the customer had ticked ("print again", a refused order), else the spool of the machine nearest
        // to the colour the design names for the part (the first colour itself counts); ticked on from the start when
        // the design names different colours for its parts or is a picture of stacked plates
        $partWanted = array_map('intval', (array) ($old['part_color'] ?? $request->query('part', [])));
        $partHex = array_map(fn ($part) => ($file->tool_params['part_colors'][$part]['hex'] ?? null) ?: null, array_combine($parts, $parts) ?: []);
        $byParts = $old ? ! empty($old['by_parts']) : ($request->query('by_parts') !== null ? $request->boolean('by_parts') : (count($parts) >= 2 && ($file->kind() === ArtGenerator::KIND || count(array_unique(array_filter($partHex))) >= 2)));
        // a design in one colour chosen freely on its page (a blue box): the spool nearest to that colour is ticked;
        // designs that name several colours (changes, parts, a QR code) pick theirs below
        $oneHex = $file && ! $named && ! $byParts && ! $changes ? ((string) ($bodyHex ?: (collect((array) ($file->tool_params['part_colors'] ?? []))->first()['hex'] ?? '')) ?: null) : null;
        if ($oneHex && ! $wantedColor && ! $old && ($offeredNow = array_filter($colors, fn ($c) => $c['enough']))) {
            $preselect = collect($offeredNow)->sortBy(fn ($c) => self::hexDistance((string) $c['hex'], $oneHex))->first()['id'] ?? $preselect;
            $machine = collect(collect($colors)->firstWhere('id', $preselect)['seconds'] ?? [])->pluck('id')->push($preselect);
        }
        $partPreselect = [];
        if ($byParts && ! $wantedColor && ! $partWanted && count(array_filter($partHex)) >= 1 && ($near = self::nearestSet($colors, $partHex[$parts[0]] ?? null, array_values(array_map(fn ($h) => (string) ($h ?: ''), array_slice($partHex, 1)))))) {
            // nothing chosen yet: the first colour follows the first part (the box), the other parts take the spool of that
            // machine nearest to their own colour (the first colour itself counts: "the main colour")
            [$preselect, $picks] = $near;
            $machine = collect(collect($colors)->firstWhere('id', $preselect)['seconds'] ?? [])->pluck('id')->push($preselect);
            foreach (array_slice($parts, 1) as $i => $part) {
                $partWanted[$part] = $partHex[$part] ? (int) ($picks[$i] ?? 0) : 0;
            }
        }
        $spools = collect(collect($colors)->firstWhere('id', $preselect)['seconds'] ?? [])->push(['id' => $preselect, 'hex' => collect($colors)->firstWhere('id', $preselect)['hex'] ?? null]);
        foreach ($parts as $part) {
            $want = (int) ($partWanted[$part] ?? 0);
            $partPreselect[$part] = $machine->contains($want) && $want !== $preselect ? $want : 0;
        }

        return view('farm.start', [
            'file' => $file,
            'card' => $card,
            'inspiration' => $inspiration,
            // no 3D preview of a designer's file that is not offered for download
            'previewUrl' => $file && (! $card || $card->download_allowed || $request->user()->isAdmin() || $request->user()->id === $card->profile->user_id) ? route('api.files.stl', $file) : null,
            'bed' => $this->orders->largestBed(),
            'quality' => $quality,
            'strength' => $strength,
            'supports' => $supports,
            'preselect' => $preselect,
            'copies' => $copies,
            'maxCopies' => PlateLayout::MAX_COPIES,
            'scale' => $scale,
            'maxScale' => (float) config('pricing.max_scale', 4),
            'colors' => $colors,
            'twoColor' => $twoColor,
            'changes' => $changes,
            'changePreselect' => $changePreselect,
            'secondPreselect' => $secondPreselect,
            'codeColor' => $changes[0]['hex'] ?? null,
            'parts' => count($parts) >= 2 ? array_map(fn ($part) => ['name' => $part, 'label' => OrderService::partLabel($file, $part), 'hex' => $partHex[$part], 'preselect' => $partPreselect[$part]], $parts) : [],
            'byParts' => $byParts,
            'scaleNow' => $scale,
            'settings' => $this->settings->all(),
            'balance' => $this->wallet->balance($request->user()),
            // null = no daily count (the admin, or the limit switched off in the settings)
            'slicesLeft' => $this->settings->get('daily_slices_per_user') > 0 && ! $request->user()->isAdmin() ? max(0, (int) $this->settings->get('daily_slices_per_user') - $this->orders->slicesToday($request->user())) : null,
        ]);
    }

    /**
     * The first colour (its machine) and a spool of that machine for every change, together nearest to the colours the
     * design was made in. A spool may serve several changes; the first colour itself may be one of them (A, B, A).
     *
     * @param  list<array<string, mixed>>  $colors  the offer of the start page, every colour with its `seconds`
     * @param  list<string>  $wanted  hex of every change, bottom to top
     * @return array{0: int, 1: list<int>}|null the first colour and a colour per change; null when nothing is loaded
     */
    /** How far two colours are apart, the way the calculation measures it too (Palette::distance, CIE76 in Lab). */
    private static function hexDistance(string $a, string $b): float
    {
        return Palette::distance($a, $b);
    }

    private static function nearestSet(array $colors, ?string $bodyHex, array $wanted): ?array
    {
        $far = fn (?string $a, string $b) => Palette::distance($a, $b);
        $best = null;
        foreach ($colors as $c) {
            if (! $c['enough']) {
                continue;
            }
            $spools = array_merge([['id' => $c['id'], 'hex' => $c['hex']]], $c['seconds']);
            $d = $bodyHex ? $far($c['hex'], $bodyHex) : 0.0;
            $picks = [];
            foreach ($wanted as $hex) {
                $near = null;
                foreach ($spools as $s) {
                    $e = $far($s['hex'], $hex);
                    if ($near === null || $e < $near[0]) {
                        $near = [$e, (int) $s['id']];
                    }
                }
                $d += $near[0];
                $picks[] = $near[1];
            }
            // a design in several colours wants several spools: a machine with one spool scores worse than one with many
            $d += max(0, count(array_unique(array_merge([$c['id']], array_column($wanted ? $spools : [], 'id')))) < min(count($wanted) + 1, FarmOrder::MAX_COLORS) ? 200 : 0);
            if ($best === null || $d < $best[0]) {
                $best = [$d, (int) $c['id'], $picks];
            }
        }

        return $best ? [$best[1], $best[2]] : null;
    }

    /** "Print again": the start page with this order's model and settings; the colour is offered again when it is still loaded. */
    public function repeat(Request $request, FarmOrder $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        return redirect()->route('farm.start', array_filter([
            'file' => $order->modelFile?->uuid, 'quality' => $order->quality, 'strength' => $order->strength, 'copies' => $order->copies,
            'supports' => $order->supports === 'off' ? 'off' : null,
            'scale' => abs((float) $order->scale - 1) > 0.0005 ? (float) $order->scale : null, 'color' => $order->farm_color_id, 'second' => $order->second_color_id,
            'change' => array_column($order->colorSlots(), 'color_id') ?: null,
            'by_parts' => $order->isByParts() ? 1 : null,
            'part' => $order->isByParts() ? array_filter(array_column($order->partPlates(), 'color_id', 'part')) : null,
        ]));
    }

    /** "My prints" (/account/orders): every order of the account, optionally only those in one state. */
    public function index(Request $request): View
    {
        $filters = ['open', FarmOrder::STATUS_DONE, FarmOrder::STATUS_HANDED_OVER, FarmOrder::STATUS_CANCELLED, FarmOrder::STATUS_FAILED];
        $filter = in_array($request->query('status'), $filters, true) ? (string) $request->query('status') : null;
        $open = [FarmOrder::STATUS_UPLOADED, FarmOrder::STATUS_SLICED, FarmOrder::STATUS_PAID, FarmOrder::STATUS_QUEUED, FarmOrder::STATUS_PRINTING];

        return view('farm.orders', [
            'orders' => FarmOrder::with(['modelFile', 'color'])->where('user_id', $request->user()->id)->where('kind', FarmOrder::KIND_PRINT)
                ->when($filter, fn ($q) => $filter === 'open' ? $q->whereIn('status', $open) : $q->where('status', $filter))
                ->latest('id')->paginate(20)->withQueryString(),
            'balance' => $this->wallet->balance($request->user()),
            'filter' => $filter,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'uuid'],
            'quality' => ['nullable', 'string', 'max:12'],
            'strength' => ['nullable', 'string', 'max:12'],
            'unit' => ['nullable', 'in:'.implode(',', array_keys(ModelValidator::UNITS))],
            'copies' => ['nullable', 'integer', 'min:1', 'max:'.PlateLayout::MAX_COPIES],
            'scale' => ['nullable', 'numeric', 'min:0.25', 'max:'.config('pricing.max_scale', 4)],
            'color' => ['nullable', 'integer'],
            'second_color' => ['nullable', 'integer'],
            'change_color' => ['nullable', 'array', 'max:8'],
            'change_color.*' => ['nullable', 'integer'],
            'by_parts' => ['nullable', 'boolean'],
            'part_color' => ['nullable', 'array', 'max:16'],
            'part_color.*' => ['nullable', 'integer'],
            'supports' => ['nullable', 'in:auto,off'],
            'designer_model' => ['nullable', 'integer'],
            'catalog_model' => ['nullable', 'integer'],
        ]);
        $card = isset($data['designer_model']) ? DesignerModel::with('profile')->find((int) $data['designer_model']) : null;
        if (isset($data['designer_model'])) {
            // the card's own file, whatever uuid the form carries; a card that is no longer printable cannot be ordered
            abort_unless($card && $card->isPrintable(), 404);
            $file = $card->modelFile;
        } else {
            $file = ModelFile::where('uuid', $data['file'])->firstOrFail();
            $this->claim($request, $file);
        }
        $inspiration = isset($data['catalog_model']) && ! $card ? CatalogModel::shown()->find((int) $data['catalog_model']) : null;

        try {
            $order = $this->orders->create($request->user(), $file, $data['quality'] ?? 'standard', $data['strength'] ?? 'standard', $data['unit'] ?? null, (int) ($data['copies'] ?? 1), (float) ($data['scale'] ?? 1), isset($data['color']) ? (int) $data['color'] : null, $data['supports'] ?? 'auto', isset($data['second_color']) ? (int) $data['second_color'] : null, $card, $inspiration, isset($data['change_color']) ? array_map('intval', array_values($data['change_color'])) : null, $request->boolean('by_parts') ? array_map('intval', (array) ($data['part_color'] ?? [])) : null);
            Track::event('order_created', $card ?? $order->modelFile, array_filter(['order' => $order->id, 'tool' => $order->modelFile?->origin === 'tool' ? $order->modelFile->kind() : null]));
        } catch (FarmRefusal $e) {
            return $request->expectsJson()
                ? response()->json(['error' => $e->reason, 'message' => $e->text()], 422)
                : back()->withInput()->with('error', $e->text());
        }

        return $request->expectsJson()
            ? response()->json(['url' => route('farm.orders.show', $order)], 201)
            : redirect()->route('farm.orders.show', $order);
    }

    public function show(Request $request, FarmOrder $order): View
    {
        $this->authorizeOrder($request, $order);

        $order->load(['modelFile', 'color', 'printer', 'material']);

        $user = $request->user();
        $point = (array) $user->pickup_point;

        return view('farm.order', [
            'order' => $order,
            'packetaKey' => (string) config('services.packeta.api_key'),
            // what the delivery form starts with: the address and the favourite pickup point of the profile
            'prefill' => [
                'name' => $user->recipientName(), 'phone' => (string) $user->phone, 'street' => (string) $user->street, 'city' => (string) $user->city,
                'zip' => (string) $user->zip, 'country' => strtoupper((string) ($user->country ?: 'CZ')),
                'point' => ! empty($point['id']) ? ['id' => (string) $point['id'], 'name' => (string) ($point['name'] ?? ''), 'carrier_id' => (string) ($point['carrier_id'] ?? ''), 'country' => strtoupper((string) ($point['country'] ?? ''))] : null,
            ],
            'card' => $order->designer_model_id ? $order->designerModel?->load(['profile', 'images']) : null,
            'modelHidden' => $order->hidesModelFrom($request->user()),
            'state' => $this->describe($order, $request),
            // the quality buttons name the layer this order's machine really prints (a finer nozzle, a finer ladder)
            'settings' => $this->withPrinterLayers($this->settings->all(), $order->printer),
        ]);
    }

    /** Polled by the order page. */
    /** What the customer watches while the order is being prepared, in the order it happens. */
    private const STAGES = ['loading', 'repairing', 'orienting', 'placing', 'slicing'];

    /** While the model is being checked, the preparing tool says what it is doing right now (farm_tool.py). */
    private function stageOf(FarmOrder $order): ?string
    {
        if ($order->stage !== 'checking') {
            return $order->stage;
        }
        $file = Storage::disk(config('farm.disk'))->path($order->dir().'/print.stl.stage');
        $now = is_file($file) ? trim((string) @file_get_contents($file)) : '';

        return in_array($now, self::STAGES, true) ? $now : 'loading';
    }

    public function status(Request $request, FarmOrder $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);

        return response()->json($this->describe($order, $request));
    }

    public function reslice(Request $request, FarmOrder $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate([
            'quality' => ['required', 'string', 'max:12'],
            'strength' => ['required', 'string', 'max:12'],
            'unit' => ['nullable', 'in:'.implode(',', array_keys(ModelValidator::UNITS))],
            'copies' => ['nullable', 'integer', 'min:1', 'max:'.PlateLayout::MAX_COPIES],
            'scale' => ['nullable', 'numeric', 'min:0.25', 'max:'.config('pricing.max_scale', 4)],
            'supports' => ['nullable', 'in:auto,off'],
            'slot' => ['nullable', 'integer'],
            // the "advanced" numbers (PrintSettings::FIELDS); an empty field means the preset
            'settings' => ['nullable', 'array'],
            'settings.infill' => ['nullable', 'integer', 'min:5', 'max:100'],
            'settings.walls' => ['nullable', 'integer', 'min:1', 'max:6'],
            'settings.top' => ['nullable', 'integer', 'min:0', 'max:10'],
            'settings.bottom' => ['nullable', 'integer', 'min:0', 'max:10'],
        ]);
        try {
            $this->orders->reslice($order, $data['quality'], $data['strength'], $data['unit'] ?? null, isset($data['copies']) ? (int) $data['copies'] : null, isset($data['scale']) ? (float) $data['scale'] : null, $data['supports'] ?? null, isset($data['slot']) ? (int) $data['slot'] : null,
                $request->exists('settings') ? (array) $request->input('settings', []) : null);   // [] = back to the presets, absent = leave as they are
        } catch (FarmRefusal $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->text()], 422);
        }

        return response()->json($this->describe($order->refresh(), $request));
    }

    /** What the order would cost with this colour, this kind of delivery and this country. The page asks; it never adds up itself. */
    public function quote(Request $request, FarmOrder $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate(['slot' => ['required', 'integer'], 'delivery' => ['required', 'string', 'max:20'], 'country' => ['nullable', 'string', 'size:2']]);
        $offer = $order->status === FarmOrder::STATUS_SLICED ? $this->orders->availableColors($order)->first(fn ($r) => $r['slot']->id === (int) $data['slot']) : null;
        if (! $offer) {
            return response()->json(['error' => 'color_gone', 'message' => __('farm.refuse.color_gone')], 422);
        }
        $currency = $this->currencyFor($order, $request);
        $country = strtoupper((string) ($data['country'] ?? 'CZ'));
        if (! in_array($data['delivery'], $this->shipping->modes(), true) || ($data['delivery'] !== Shipping::PICKUP && ($this->shipping->tooBig($order)
            || $this->shipping->price($data['delivery'], $country, $this->shipping->parcelGrams($order), $currency) === null))) {
            return response()->json(['error' => 'delivery_country', 'message' => __('farm.refuse.delivery_country')], 422);
        }
        $price = $this->orders->priceFor($order, $offer['printer'], $data['delivery'], $offer['color']->material, $country, $currency);

        return response()->json(['price' => $price, 'total' => $price['total'], 'currency' => $currency]);
    }

    public function pay(Request $request, FarmOrder $order, OrderFlow $flow, FarmVideos $videos): JsonResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate(self::payRules(), ['terms.accepted' => __('farm.refuse.terms')]);

        try {
            return $this->payFromRequest($request, $order, $flow, $videos, $data);
        } catch (InsufficientCredit $e) {
            // the top-up page asks for what is missing, in the currency the order is priced in
            return response()->json(['error' => 'credit', 'message' => __('farm.refuse.credit', ['missing' => $e->missingMoney()->format()]), 'missing' => $e->missing(), 'topup_url' => route('account.credit', ['need' => $e->missing(), 'back' => $order->token])], 402);
        } catch (FarmRefusal $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->text()] + $e->data, 422);
        }
    }

    /**
     * "Pay by card": the same choices as a payment from credit. With enough credit the order is simply paid; otherwise
     * what is missing is paid at the gateway (a Payment for this order that remembers the choices), the customer comes
     * back to this page, and the webhook tops the credit up and pays the order with those choices (OrderFlow::payFromCard).
     */
    public function checkout(Request $request, FarmOrder $order, OrderFlow $flow, FarmVideos $videos, PaymentGateway $gateway): JsonResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate(self::payRules(), ['terms.accepted' => __('farm.refuse.terms')]);

        try {
            return $this->payFromRequest($request, $order, $flow, $videos, $data);
        } catch (InsufficientCredit $e) {
            $amount = (float) ceil($e->missing());   // whole crowns or euros; the odd heller stays as credit
            $payment = Payment::create([
                'user_id' => $request->user()->id, 'farm_order_id' => $order->id, 'gateway' => $gateway->name(), 'purpose' => Payment::PURPOSE_ORDER,
                'amount' => $amount, 'currency' => $e->currency, 'status' => Payment::STATUS_PENDING,
                'context' => array_filter($data, fn ($v) => $v !== null) + ['ip' => $request->ip(), 'currency' => $this->currencyFor($order, $request), 'video_consent' => $request->boolean('video_consent')],
            ]);
            try {
                $url = $gateway->checkoutUrl($payment->load('user'), route('farm.orders.show', ['order' => $order, 'paid' => $payment->id]), route('farm.orders.show', $order));
            } catch (\Throwable $ex) {
                Log::error('Order checkout failed', ['payment' => $payment->id, 'error' => $ex->getMessage()]);
                $payment->update(['status' => Payment::STATUS_FAILED]);

                return response()->json(['error' => 'gateway', 'message' => __('farm.credit.gateway_down')], 503);
            }

            return response()->json(['checkout_url' => $url, 'amount' => $amount, 'currency' => $e->currency]);
        } catch (FarmRefusal $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->text()] + $e->data, 422);
        }
    }

    /** @return array<string, list<string>> */
    private static function payRules(): array
    {
        return [
            'slot' => ['required', 'integer'],
            'second_slot' => ['nullable', 'integer'],
            'change_slots' => ['nullable', 'array', 'max:8'],
            'change_slots.*' => ['nullable', 'integer'],
            'part_slots' => ['nullable', 'array', 'max:16'],
            'part_slots.*' => ['nullable', 'integer'],
            'delivery' => ['required', 'string', 'max:20'],
            'terms' => ['accepted'],
            'expected_total' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:500'],
            // the recipient, for a parcel: checked against the kind of delivery in App\Domain\Farm\Shipping
            'address' => ['nullable', 'array'],
            'address.name' => ['nullable', 'string', 'max:120'],
            'address.street' => ['nullable', 'string', 'max:160'],
            'address.city' => ['nullable', 'string', 'max:120'],
            'address.zip' => ['nullable', 'string', 'max:12'],
            'address.country' => ['nullable', 'string', 'size:2'],
            'address.phone' => ['nullable', 'string', 'max:30'],
            'address.point' => ['nullable', 'array'],
            'address.point.id' => ['nullable', 'string', 'max:40'],
            'address.point.name' => ['nullable', 'string', 'max:200'],
            'address.point.carrier_id' => ['nullable', 'string', 'max:40'],
            'address.point.country' => ['nullable', 'string', 'size:2'],
            'video_consent' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Pays the order from credit with the validated choices and answers with its new state.
     *
     * @throws InsufficientCredit
     * @throws FarmRefusal
     */
    private function payFromRequest(Request $request, FarmOrder $order, OrderFlow $flow, FarmVideos $videos, array $data): JsonResponse
    {
        $flow->pay($order, FarmPrinterSlot::findOrFail($data['slot']), $data['delivery'], $data['address'] ?? null, true, $request->ip(), (float) $data['expected_total'], $data['note'] ?? null, isset($data['second_slot']) ? (int) $data['second_slot'] : null, $this->currencyFor($order, $request), isset($data['change_slots']) ? array_map('intval', array_values($data['change_slots'])) : null, isset($data['part_slots']) ? array_map('intval', (array) $data['part_slots']) : null);
        if ($request->boolean('video_consent')) {
            $videos->setConsent($order, true);
        }
        $order->refresh();
        Track::event('order_paid', $order->designer_model_id ? $order->designerModel : $order->modelFile, array_filter(['order' => $order->id, 'value' => (float) $order->price_total, 'currency' => (string) $order->currency, 'tool' => $order->modelFile?->origin === 'tool' ? $order->modelFile->kind() : null, 'event_id' => 'order-'.$order->id]));
        app(SocialPublisher::class)->conversion('order_paid', 'order-'.$order->id, $request, $request->user()->email, ['value' => (float) $order->price_total, 'currency' => (string) $order->currency]);

        return response()->json($this->describe($order->refresh(), $request));
    }

    /** The customer's YouTube switch on the order page: allow the time-lapse on the channel, or take it back (deletes it there). */
    public function videoConsent(Request $request, FarmOrder $order, FarmVideos $videos): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->user_id === $request->user()->id && $order->kind === FarmOrder::KIND_PRINT, 403);
        $consent = $request->boolean('consent');
        $videos->setConsent($order, $consent);

        return back()->with('status', __($consent ? 'youtube.order.saved_on' : 'youtube.order.saved_off'));
    }

    public function cancel(Request $request, FarmOrder $order, OrderFlow $flow): JsonResponse|RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        if (! $order->cancellableByCustomer()) {
            return response()->json(['error' => 'locked', 'message' => __('farm.refuse.locked')], 422);
        }
        // a start command on its way to the printer, or a print the agent lost sight of: only the operator can stop that
        if ($order->status !== FarmOrder::STATUS_PRINTING && $order->printJobs()->whereIn('status', ['sent', 'printing', 'paused', 'unknown'])->exists()) {
            return response()->json(['error' => 'locked', 'message' => __('farm.refuse.locked')], 422);
        }
        $flow->move($order, FarmOrder::STATUS_CANCELLED, 'user', $request->user()->id);

        return $request->expectsJson() ? response()->json($this->describe($order->refresh(), $request)) : redirect()->route('farm.orders');
    }

    /** The model as it will be printed (repaired, scaled, turned): what the viewer shows. */
    public function model(Request $request, FarmOrder $order): BinaryFileResponse
    {
        $this->authorizeOrder($request, $order);
        abort_if($order->hidesModelFrom($request->user()), 403);
        $path = $order->absolutePrintStlPath() ?: $order->modelFile?->absoluteStlPath();
        abort_unless($path && is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'model/stl', 'Cache-Control' => 'private, max-age=60']);
    }

    /** Support structures of the slice, as line segments for the 3D preview. */
    public function supports(Request $request, FarmOrder $order): BinaryFileResponse
    {
        $this->authorizeOrder($request, $order);
        $path = $order->absoluteSupportsPath();
        abort_unless($path, 404);

        return response()->file($path, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, max-age=60']);
    }

    /** Last camera picture of the customer's own print. */
    public function snapshot(Request $request, FarmOrder $order): BinaryFileResponse
    {
        $this->authorizeOrder($request, $order);
        $job = $order->latestJob();
        abort_unless($job && $job->snapshot_path && Storage::disk(config('farm.disk'))->exists($job->snapshot_path), 404);

        return response()->file(Storage::disk(config('farm.disk'))->path($job->snapshot_path), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']);
    }

    /** Time-lapse of the customer's own finished print. */
    public function timelapse(Request $request, FarmOrder $order): BinaryFileResponse
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->timelapse_path && Storage::disk(config('farm.disk'))->exists($order->timelapse_path), 404);

        return response()->file(Storage::disk(config('farm.disk'))->path($order->timelapse_path), ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, max-age=3600']);
    }

    /** The square Short of the customer's own print, to share (Instagram, TikTok, Shorts). */
    public function short(Request $request, FarmOrder $order): BinaryFileResponse
    {
        $this->authorizeOrder($request, $order);
        abort_unless($order->timelapse_short_path && Storage::disk(config('farm.disk'))->exists($order->timelapse_short_path), 404);

        return response()->download(Storage::disk(config('farm.disk'))->path($order->timelapse_short_path), 'matplace-'.$order->number.'-short.mp4', [
            'Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-cache',
        ]);
    }

    public function terms(): View
    {
        return view('farm.terms', ['version' => $this->settings->get('terms_version')]);
    }

    /** The quality ladder as the given machine prints it: the layers follow its nozzle. */
    private function withPrinterLayers(array $settings, ?FarmPrinter $printer): array
    {
        if (! $printer) {
            return $settings;
        }
        foreach ($settings['qualities'] as $key => $q) {
            $settings['qualities'][$key]['layer_mm'] = $printer->layerFor((float) ($q['layer_mm'] ?? 0.2));
        }

        return $settings;
    }

    /**
     * The currency the order is shown and paid in. Paid: its own, for good. Not paid yet: the account's, or (no money
     * has moved on the account) the one its owner sees prices in right now.
     */
    private function currencyFor(FarmOrder $order, Request $request): string
    {
        if ($order->paid_at !== null) {
            return (string) $order->currency;
        }
        $mine = $request->user()->id === $order->user_id;
        $owner = $mine ? $request->user() : $order->user;

        return (string) ($owner?->currency ?: ($mine ? Currency::current($owner) : $order->currency));
    }

    /** Everything the order page needs, in one JSON object. */
    private function describe(FarmOrder $order, Request $request): array
    {
        $order->loadMissing(['color', 'printer', 'material', 'modelFile']);
        $currency = $this->currencyFor($order, $request);
        $this->orders->alignCurrency($order, $currency);
        $job = $order->latestJob();
        $check = (array) $order->check;
        $colors = $order->status === FarmOrder::STATUS_SLICED
            ? $this->orders->availableColors($order)->map(fn ($r) => [
                'slot' => $r['slot']->id, 'name' => $r['color']->displayName(), 'kind' => $r['color']->material->label(), 'hex' => $r['color']->hex, 'photo' => $r['color']->photoUrl(),
                'enough' => $r['enough'], 'price' => $price = $this->orders->priceFor($order, $r['printer'], Shipping::PICKUP, $r['color']->material, null, $currency), 'total' => $price['total'],
                'starts_now' => $r['printer']->readyForAutoStart() && ! $this->settings->get('require_approval'),
                // a plate with a raised text: the colours the text can have next to this colour of the plate
                'second' => $this->orders->secondColors($order, $r['slot'])->map(fn ($s) => ['slot' => $s->id, 'name' => $s->color->displayName(), 'kind' => $s->color->material->label(), 'hex' => $s->color->hex, 'photo' => $s->color->photoUrl()])->all(),
                // the time, the weight and the price on the page were computed for this colour's machine and kind
                'sliced' => $r['printer']->id === $order->farm_printer_id && $r['color']->material->id === $order->farm_material_id
                    && ($order->farm_color_id === null || $order->farm_color_id === $r['color']->id),
            ])->all()
            : [];

        // every change of the design: where, the colour it was designed in, and the spool chosen for it so far
        $stored = $order->colorSlots();
        $spools = $stored ? FarmPrinterSlot::with('color')->whereIn('id', array_column($stored, 'slot_id'))->get()->keyBy('id') : collect();
        $changes = array_map(function (array $c) use ($stored, $spools): array {
            foreach ($stored as $s) {
                if (abs($s['z'] - $c['z']) < 0.005) {
                    return $c + ['slot_id' => $s['slot_id'], 'name' => $spools->get($s['slot_id'])?->color?->displayName()];
                }
            }

            return $c + ['slot_id' => null, 'name' => null];
        }, $order->wantedChanges());

        // an order printed by parts: every part, the colour the design names for it, and the spool chosen so far
        $partPlates = $order->partPlates();
        $partSpools = $partPlates ? FarmPrinterSlot::with('color')->whereIn('id', array_filter(array_column($partPlates, 'slot_id')))->get()->keyBy('id') : collect();
        $parts = array_map(fn ($p, $i) => [
            'part' => $p['part'], 'plate' => $i + 1, 'label' => OrderService::partLabel($order->modelFile, (string) $p['part']),
            'hex' => ($order->modelFile?->tool_params['part_colors'][$p['part']]['hex'] ?? null) ?: null,
            'slot_id' => ($p['slot_id'] ?? null) ?: null, 'name' => $partSpools->get($p['slot_id'] ?? 0)?->color?->displayName(),
            'copies' => (int) ($p['copies'] ?? $order->copies), 'minutes' => $p['minutes'] ?? null, 'grams' => $p['grams'] ?? null,
        ], $partPlates, array_keys($partPlates));

        return [
            'token' => $order->token,
            'number' => $order->number,
            'changes' => $parts ? [] : $changes,
            'by_parts' => $order->isByParts(),
            'parts' => $parts,
            'max_colors' => FarmOrder::MAX_COLORS,
            'status' => $order->status,
            'status_text' => $order->statusText(),
            'stage' => $stage = $this->stageOf($order),
            'stage_step' => $stage ? (array_search($stage, self::STAGES, true) ?: 0) + 1 : null,
            'stage_total' => count(self::STAGES),
            'error' => $order->error,
            'error_text' => $order->error ? __('farm.error.'.$order->error, $this->errorDataFor($order, $check)) : null,
            'quality' => $order->quality,
            'strength' => $order->strength,
            'copies' => $order->copies,
            'scale' => (float) ($order->scale ?: 1),
            'raw_bbox' => $order->modelFile?->bbox,
            'slot' => $order->farm_printer_slot_id,
            'printer' => $order->printer ? ['name' => $order->printer->name, 'bed' => (int) $order->printer->bed_x.' × '.(int) $order->printer->bed_y.' × '.(int) $order->printer->bed_z.' mm'] : null,
            'max_copies' => $check['max_copies'] ?? null,
            'plates' => $order->plates,
            'plates_done' => $order->plates_done,
            'plate_layout' => $order->plateLayout(),
            'plate_now' => $job && $job->isActive() ? $job->plate : null,
            'piece_dims' => $check['piece_dims'] ?? null,
            'unit' => array_search($order->unit_scale, ModelValidator::UNITS, false) ?: 'mm',
            'unit_guess' => $check['unit_guess'] ?? null,
            'dims' => $check['dims'] ?? null,
            'raw_dims' => $check['raw_dims'] ?? null,
            'warnings' => array_map(fn ($w) => __('farm.warn.'.$w['code'], $w['data'] ?? []), $check['warnings'] ?? []),
            'orientation_changed' => (bool) ($order->orientation['changed'] ?? false),
            'supports' => $order->supports_used,
            'supports_mode' => $order->supports ?: 'auto',
            'settings' => PrintSettings::of($order) ?: null,
            'admin_overrides' => PrintSettings::adminOverrides($order) ?: null,
            'color_change_mm' => $order->colorChangeMm(),
            'second_slot' => $order->second_slot_id,
            'second_color' => $order->second_color_id && $order->secondColor ? ['name' => $order->secondColor->displayName(), 'hex' => $order->secondColor->hex] : null,
            'minutes' => $order->est_minutes,
            'grams' => $order->est_grams,
            'meters' => $order->est_meters,
            'price' => $order->price,
            'total' => $order->price_total,
            'currency' => $currency,
            // what delivery is on offer and for how much, while it can still be chosen
            'shipping' => $order->status === FarmOrder::STATUS_SLICED ? $this->shipping->offer($order, $currency) : null,
            'destination' => $order->isParcel() ? $this->destinationText($order) : null,
            'tracking_url' => $order->tracking_url,
            'colors' => $colors,
            // the last card payment made for this order from this page: the page waits for the bank, or says the money stayed as credit
            'card' => ($card = Payment::where('farm_order_id', $order->id)->where('purpose', Payment::PURPOSE_ORDER)->latest('id')->first()) ? ['status' => $card->status, 'result' => $card->context['result'] ?? null] : null,
            'color' => $order->color ? ['name' => $order->color->material->label().' '.$order->color->displayName(), 'hex' => $order->color->hex] : null,
            'delivery' => $order->delivery,
            'balance' => $this->wallet->balance($request->user()->id === $order->user_id ? $request->user() : $order->user)->amount,
            'model_url' => ($order->print_stl_path || $order->modelFile?->stl_path) && ! $order->hidesModelFrom($request->user()) ? route('farm.orders.model', $order).'?v='.($order->updated_at?->timestamp ?? 0) : null,
            'supports_url' => $order->absoluteSupportsPath() ? route('farm.orders.supports', $order).'?v='.($order->updated_at?->timestamp ?? 0) : null,
            'queue' => app(Dispatcher::class)->estimate($order, $this->settings),
            'timelapse_url' => $order->timelapse_path ? route('farm.orders.timelapse', $order) : null,
            'short_url' => $order->timelapse_short_path ? route('farm.orders.short', $order) : null,
            // what stopping a running print would cost right now (the printed share)
            'cancel_keep' => $order->status === FarmOrder::STATUS_PRINTING && $job ? app(OrderFlow::class)->shareOfPrice((array) $order->price, (float) $job->progress / 100) : null,
            'print' => $job ? [
                'status' => $job->status, 'progress' => $job->progress,
                'snapshot_url' => $job->snapshot_path ? route('farm.orders.snapshot', $order).'?t='.($job->snapshot_at?->timestamp ?? 0) : null,
                'snapshot_at' => $job->snapshot_at?->toIso8601String(),
            ] : null,
            'can_cancel' => $order->cancellableByCustomer(),
            'final' => in_array($order->status, [FarmOrder::STATUS_HANDED_OVER, FarmOrder::STATUS_CANCELLED], true),
        ];
    }

    /** "Z-BOX Praha 9, Českomoravská 12" or "Roman Vzor, Dlouhá 1, 301 00 Plzeň, Česko": where the parcel goes, for the paid order. */
    private function destinationText(FarmOrder $order): string
    {
        $to = (array) $order->shipping_address;
        $country = Countries::name($to['country'] ?? null);

        return $order->delivery === Shipping::POINT
            ? implode(', ', array_filter([(string) ($to['pickup_point_name'] ?? ''), $country]))
            : implode(', ', array_filter([(string) ($to['name'] ?? ''), (string) ($to['street'] ?? ''), trim(($to['zip'] ?? '').' '.($to['city'] ?? '')), $country]));
    }

    /** The data of the order's error, a part named in it under the name its tool shows. */
    private function errorDataFor(FarmOrder $order, array $check): array
    {
        $data = $this->errorData($check, (string) $order->error);
        if (isset($data['part'])) {
            $data['part'] = OrderService::partLabel($order->modelFile, (string) $data['part']);
        }

        return $data;
    }

    private function errorData(array $check, string $code): array
    {
        foreach ($check['errors'] ?? [] as $e) {
            if ($e['code'] === $code) {
                return (array) $e['data'];
            }
        }

        return [];
    }

    private function authorizeOrder(Request $request, FarmOrder $order): void
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 404);
    }

    /** A file uploaded before logging in belongs to the anonymous session: take it over; somebody else's file is refused. */
    private function claim(Request $request, ModelFile $file): void
    {
        $user = $request->user();
        if ($file->owner_user_id === null) {
            $session = $request->attributes->get('anon_session');
            abort_unless($session && $file->anonymous_session_id === $session->id, 403);
            $file->forceFill(['owner_user_id' => $user->id])->save();
        }
        abort_unless($file->owner_user_id === $user->id || $user->isAdmin(), 403);
    }
}
