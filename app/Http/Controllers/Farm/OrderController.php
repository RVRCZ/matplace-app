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

    /** What rides from the start page to the order (a repeated print, a refused order), under the names the store takes. */
    private const CARRIED = ['quality' => 'quality', 'strength' => 'strength', 'supports' => 'supports', 'copies' => 'copies', 'scale' => 'scale',
        'color' => 'color', 'second' => 'second_color', 'second_color' => 'second_color', 'change' => 'change_color', 'change_color' => 'change_color',
        'by_parts' => 'by_parts', 'part' => 'part_color', 'part_color' => 'part_color'];

    /**
     * The entrance for whatever does not come from the calculator with a signed-in customer: an own upload, a
     * designer's card, an inspiration page, "print again", a guest back from the login, a refused order. No colours
     * and no settings here: both are chosen once, on the order page. What the address hands over (the calculation,
     * the settings and colours of a repeated print) rides along to the order in hidden fields.
     */
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
        // a refused order (too big, daily limit…) comes back with what the customer had sent
        $old = (array) $request->session()->getOldInput();
        $calcToken = (string) ($old['calc'] ?? $request->query('calc', ''));
        // from the calculator (a guest who had to sign in first): the calculation knows the model and the settings
        $calc = ! $file && $calcToken !== '' ? Calculation::with('modelFile')->where('token', $calcToken)->first() : null;
        $file ??= $calc?->modelFile;
        if ($old && ! $card) {
            $file ??= isset($old['file']) ? ModelFile::where('uuid', (string) $old['file'])->first() : null;
        }
        $carry = [];
        foreach (self::CARRIED as $from => $to) {
            $value = $old ? ($old[$from] ?? null) : $request->query($from);
            if ($value !== null && $value !== '' && $value !== []) {
                $carry[$to] = $value;
            }
        }
        // the customer starts an order here (the calculator's signed-in way goes straight to store: order_created)
        Track::event('order_started', $card ?? $file, array_filter(['tool' => $file?->origin === 'tool' ? $file->kind() : null, 'kind' => $card ? 'card' : ($inspiration ? 'inspiration' : ($calc ? 'calculation' : 'file'))]));

        return view('farm.start', [
            'file' => $file,
            'card' => $card,
            'inspiration' => $inspiration,
            'calc' => $calc,
            'carry' => $carry,
            // no 3D preview of a designer's file that is not offered for download
            'previewUrl' => $file && (! $card || $card->download_allowed || $request->user()->isAdmin() || $request->user()->id === $card->profile->user_id) ? route('api.files.stl', $file) : null,
            'bed' => $this->orders->largestBed(),
            'settings' => $this->settings->all(),
            'balance' => $this->wallet->balance($request->user()),
            // null = no daily count (the admin, or the limit switched off in the settings)
            'slicesLeft' => $this->settings->get('daily_slices_per_user') > 0 && ! $request->user()->isAdmin() ? max(0, (int) $this->settings->get('daily_slices_per_user') - $this->orders->slicesToday($request->user())) : null,
        ]);
    }

    /**
     * What a calculation hands over to its order: the quality, how many pieces, the size, the kind of plastic (PLA,
     * PETG… the order starts on it when it is loaded), "no supports", and the infill as the nearest strength preset.
     *
     * @return array{quality: ?string, strength: ?string, supports: ?string, copies: int, scale: float, material: ?string}
     */
    private function fromCalculation(Calculation $calc): array
    {
        $p = (array) $calc->params;
        $strength = null;
        if (isset($p['infill']) && ($presets = collect($this->settings->get('strengths'))->map(fn ($s) => abs((int) $s['infill'] - (int) $p['infill']))->sort())->isNotEmpty()) {
            $strength = (string) $presets->keys()->first();
        }

        return [
            'quality' => isset($p['quality']) ? (string) $p['quality'] : null,
            'strength' => $strength,
            // "no supports" stays "no"; the farm has no forced "yes", that one is left to the slicer
            'supports' => ($p['supports'] ?? null) === false ? 'off' : null,
            'copies' => max(1, min(PlateLayout::MAX_COPIES, (int) ($p['quantity'] ?? 1))),
            'scale' => max(0.25, min((float) config('pricing.max_scale', 4), (float) ($p['scale'] ?? 1))),
            'material' => isset($p['material']) ? (string) $p['material'] : null,
        ];
    }

    /**
     * The colours a new order starts with, so that it is sliced and priced for the right machine at once; the
     * customer changes them on the order page. What was asked for (a repeated print, a refused order) wins while it
     * is still loaded; else a design that names its colours (a picture in filament colours, a QR code, a box and its
     * lid in two colours) gets the spools of one machine nearest to them, a design in one freely chosen colour (a blue
     * box) the spool nearest to it, and anything else the first colour of the calculation's kind.
     *
     * @param  array{color?: int|null, changes?: list<int|null>|null, by_parts?: bool|null, parts?: array<string, int|null>|null}  $wish
     * @return array{color: ?int, changes: list<int>, by_parts: bool, parts: array<string, int>}
     */
    private function preselect(ModelFile $file, string $quality, ?string $material, array $wish): array
    {
        // a plate with a code or a text prints in two colours: the second one has to sit in the same machine (its ACE
        // changes the spool), so every colour lists the other spools of its machine that go with it
        $changes = $file->colorChanges();
        $twoColor = $file->colorChangeMm() ?? ($changes[0]['z'] ?? null);
        // the separately printed parts of the design (a box and its lid, the plates of a layered picture): each may
        // take its own spool of the machine, printed one after another
        $parts = OrderService::designParts($file);
        $colors = $this->orders->offeredColors($quality)->map(fn ($r) => [
            'id' => $r['color']->id, 'name' => $r['color']->displayName(), 'code' => $r['color']->material->code, 'hex' => $r['color']->hex,
            'enough' => $r['slot']->availableGrams() > 50,
            // the second colours of every machine that holds this colour, not just the one the colour is listed with
            'seconds' => $twoColor || $parts ? $this->orders->secondSpoolsAcross($r['slot'], null, $quality)->map(fn ($s) => ['id' => $s->color->id, 'hex' => $s->color->hex])->values()->all() : [],
        ])->sortBy(fn ($c) => [$c['code'] === $material ? 0 : 1, $c['name']])->values()->all();
        $wantedColor = (int) ($wish['color'] ?? 0);
        $wantedChanges = array_values(array_map('intval', (array) ($wish['changes'] ?? [])));
        $partWanted = array_map('intval', (array) ($wish['parts'] ?? []));
        $said = $wantedColor || $wantedChanges || $partWanted || ($wish['by_parts'] ?? null) !== null;
        // the colour asked for when it is still loaded; else none yet (below: a design that names its colours): the
        // order starts on the cheapest kind loaded, or the calculation's, and the order page offers that machine's colours first
        $preselect = collect($colors)->firstWhere('id', $wantedColor)['id'] ?? null;
        $bodyHex = ($file->tool_params['part_colors']['body']['hex'] ?? null) ?: ($file->codeColors()[0] ?? null);
        // only a design that names its colours (a picture in filament colours, a QR code) has them ticked in advance;
        // a plate with a raised text is fine in one colour and starts that way, as it always did. A QR code reads only
        // in two colours: the pair of spools nearest to the design is ticked (a light plate, a dark code)
        $named = ! empty($file->tool_params['color_changes']) || $file->codeColors() !== null;
        if ($named && $changes && ! $wantedColor && ! $wantedChanges && ($near = self::nearestSet($colors, $bodyHex, array_column($changes, 'hex')))) {
            [$preselect, $wantedChanges] = $near;
        }
        // a change is ticked only when the machine of the first colour really holds the spool (or it is that very colour)
        $machine = collect(collect($colors)->firstWhere('id', $preselect)['seconds'] ?? [])->pluck('id')->push($preselect);
        $changePick = array_map(fn ($i) => ($want = (int) ($wantedChanges[$i] ?? 0)) && $machine->contains($want) ? $want : 0, array_keys($changes));
        // by parts: ticked on from the start when the design names different colours for its parts or is a picture of stacked plates
        $partHex = array_map(fn ($part) => ($file->tool_params['part_colors'][$part]['hex'] ?? null) ?: null, array_combine($parts, $parts) ?: []);
        // … or must print its colours together on one plate (a medal with its motif flush): by parts from the start, so the order reaches the one-plate slice
        $byParts = ($wish['by_parts'] ?? null) !== null ? (bool) $wish['by_parts'] : (count($parts) >= 2 && ($file->kind() === ArtGenerator::KIND || $file->onePlateRequired() || count(array_unique(array_filter($partHex))) >= 2));
        // a design in one colour chosen freely on its page (a blue box): the spool nearest to that colour; designs that
        // name several colours (changes, parts, a QR code) pick theirs above and below
        $oneHex = ! $named && ! $byParts && ! $changes ? ((string) ($bodyHex ?: (collect((array) ($file->tool_params['part_colors'] ?? []))->first()['hex'] ?? '')) ?: null) : null;
        if ($oneHex && ! $said && ($offeredNow = array_filter($colors, fn ($c) => $c['enough']))) {
            $preselect = collect($offeredNow)->sortBy(fn ($c) => self::hexDistance((string) $c['hex'], $oneHex))->first()['id'] ?? $preselect;
            $machine = collect(collect($colors)->firstWhere('id', $preselect)['seconds'] ?? [])->pluck('id')->push($preselect);
        }
        if ($byParts && ! $wantedColor && ! $partWanted && count(array_filter($partHex)) >= 1 && ($near = self::nearestSet($colors, $partHex[$parts[0]] ?? null, array_values(array_map(fn ($h) => (string) ($h ?: ''), array_slice($partHex, 1)))))) {
            // nothing chosen yet: the first colour follows the first part (the box), the other parts take the spool of that
            // machine nearest to their own colour (the first colour itself counts: "the main colour")
            [$preselect, $picks] = $near;
            $machine = collect(collect($colors)->firstWhere('id', $preselect)['seconds'] ?? [])->pluck('id')->push($preselect);
            foreach (array_slice($parts, 1) as $i => $part) {
                $partWanted[$part] = $partHex[$part] ? (int) ($picks[$i] ?? 0) : 0;
            }
        }
        $partPick = [];
        foreach ($parts as $part) {
            $want = (int) ($partWanted[$part] ?? 0);
            $partPick[$part] = $want && $machine->contains($want) && $want !== $preselect ? $want : 0;
        }

        return ['color' => $preselect, 'changes' => $changePick, 'by_parts' => $byParts && count($parts) >= 2, 'parts' => $partPick];
    }

    /** How far two colours are apart, the way the calculation measures it too (Palette::distance, CIE76 in Lab). */
    private static function hexDistance(string $a, string $b): float
    {
        return Palette::distance($a, $b);
    }

    /**
     * The first colour (its machine) and a spool of that machine for every change, together nearest to the colours the
     * design was made in. A spool may serve several changes; the first colour itself may be one of them (A, B, A).
     *
     * @param  list<array<string, mixed>>  $colors  the colours on offer (preselect), every colour with its `seconds`
     * @param  list<string>  $wanted  hex of every change, bottom to top
     * @return array{0: int, 1: list<int>}|null the first colour and a colour per change; null when nothing is loaded
     */
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

    /** "Print again": the start page with this order's model, settings and colours; a colour no longer loaded is replaced on the order page. */
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

    /**
     * A new order, sliced at once. From the calculator ("Print with us": `calc`, the calculation's settings and kind
     * of plastic), from the start page (`file`, or a designer's card), with the colours asked for or preselected
     * (preselect); the customer then picks material, settings and colours once, on the order page.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'calc' => ['nullable', 'string', 'max:64'],
            'file' => ['required_without_all:calc,designer_model', 'nullable', 'uuid'],
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
        $calc = isset($data['calc']) && ! $card ? Calculation::with('modelFile')->where('token', $data['calc'])->first() : null;
        if (isset($data['designer_model'])) {
            // the card's own file, whatever uuid the form carries; a card that is no longer printable cannot be ordered
            abort_unless($card && $card->isPrintable(), 404);
            $file = $card->modelFile;
        } else {
            // the calculation's own model, whatever uuid the form carries
            abort_if(isset($data['calc']) && ! $calc?->modelFile, 404);
            $file = $calc?->modelFile ?? ModelFile::where('uuid', (string) $data['file'])->firstOrFail();
            $this->claim($request, $file);
        }
        $inspiration = isset($data['catalog_model']) && ! $card ? CatalogModel::shown()->find((int) $data['catalog_model']) : null;
        // what the calculation was made with, unless the form names it
        $from = $calc ? $this->fromCalculation($calc) : [];
        $quality = (string) ($data['quality'] ?? $from['quality'] ?? 'standard');
        $pick = $this->preselect($file, $quality, $from['material'] ?? null, [
            'color' => $data['color'] ?? null,
            'changes' => $data['change_color'] ?? (isset($data['second_color']) ? [$data['second_color']] : null),
            'by_parts' => $request->has('by_parts') ? $request->boolean('by_parts') : null,
            'parts' => $data['part_color'] ?? null,
        ]);

        try {
            $order = $this->orders->create($request->user(), $file, $quality, (string) ($data['strength'] ?? $from['strength'] ?? 'standard'), $data['unit'] ?? null,
                (int) ($data['copies'] ?? $from['copies'] ?? 1), (float) ($data['scale'] ?? $from['scale'] ?? 1), $pick['color'], (string) ($data['supports'] ?? $from['supports'] ?? 'auto'), null, $card, $inspiration,
                $pick['changes'] ?: null, $pick['by_parts'] ? $pick['parts'] : null, $from['material'] ?? null);
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
            'settings.ironing' => ['nullable', 'in:'.implode(',', PrintSettings::CHOICES['ironing'])],
            'by_parts' => ['nullable', 'boolean'],
            // the spools picked for the parts so far: they travel with the first colour (one machine's only)
            'part_slots' => ['nullable', 'array', 'max:16'], 'part_slots.*' => ['nullable', 'integer'],
        ]);
        try {
            $this->orders->reslice($order, $data['quality'], $data['strength'], $data['unit'] ?? null, isset($data['copies']) ? (int) $data['copies'] : null, isset($data['scale']) ? (float) $data['scale'] : null, $data['supports'] ?? null, isset($data['slot']) ? (int) $data['slot'] : null,
                $request->exists('settings') ? (array) $request->input('settings', []) : null,   // [] = back to the presets, absent = leave as they are
                $request->has('by_parts') && $data['by_parts'] !== null ? (bool) $data['by_parts'] : null,
                isset($data['part_slots']) ? array_map('intval', (array) $data['part_slots']) : null);
        } catch (FarmRefusal $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->text()], 422);
        }

        return response()->json($this->describe($order->refresh(), $request));
    }

    /** What the order would cost with this colour, this kind of delivery and this country. The page asks; it never adds up itself. */
    public function quote(Request $request, FarmOrder $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate([
            'slot' => ['required', 'integer'], 'delivery' => ['required', 'string', 'max:20'], 'country' => ['nullable', 'string', 'size:2'],
            // the second, change and part spools chosen so far: when they sit in another machine that holds the first
            // colour too, the price is the one of that machine (the order goes there at payment)
            'change_slots' => ['nullable', 'array', 'max:8'], 'change_slots.*' => ['nullable', 'integer'],
            'part_slots' => ['nullable', 'array', 'max:16'], 'part_slots.*' => ['nullable', 'integer'],
        ]);
        try {
            $first = $order->status === FarmOrder::STATUS_SLICED ? FarmPrinterSlot::find((int) $data['slot']) : null;
            $offer = $first ? $this->orders->machineFor($order, $first, array_merge(array_values((array) ($data['change_slots'] ?? [])), array_values((array) ($data['part_slots'] ?? [])))) : null;
        } catch (FarmRefusal $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->text()], 422);   // spools of two machines, say
        }
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
        Track::event('checkout_started', $order->modelFile, ['order' => $order->id, 'kind' => 'credit']);

        try {
            return $this->payFromRequest($request, $order, $flow, $videos, $data);
        } catch (InsufficientCredit $e) {
            // the top-up page asks for what is missing, in the currency the order is priced in
            return response()->json(['error' => 'credit', 'message' => __('farm.refuse.credit', ['missing' => $e->missingMoney()->format()]), 'missing' => $e->missing(), 'topup_url' => route('account.credit', ['need' => $e->missing(), 'back' => $order->token])], 402);
        } catch (FarmRefusal $e) {
            Track::event('failed', $order->modelFile, ['step' => 'checkout', 'code' => $e->reason, 'order' => $order->id]);

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
        Track::event('checkout_started', $order->modelFile, ['order' => $order->id, 'kind' => 'card']);

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
                Track::event('failed', $order->modelFile, ['step' => 'checkout', 'code' => 'gateway', 'order' => $order->id]);

                return response()->json(['error' => 'gateway', 'message' => __('farm.credit.gateway_down')], 503);
            }

            return response()->json(['checkout_url' => $url, 'amount' => $amount, 'currency' => $e->currency]);
        } catch (FarmRefusal $e) {
            Track::event('failed', $order->modelFile, ['step' => 'checkout', 'code' => $e->reason, 'order' => $order->id]);

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
        Track::event('order_paid', $order->designer_model_id ? $order->designerModel : $order->modelFile, array_filter(['order' => $order->id, 'value' => (float) $order->price_total, 'currency' => (string) $order->currency, 'kind' => 'credit', 'tool' => $order->modelFile?->origin === 'tool' ? $order->modelFile->kind() : null, 'event_id' => 'order-'.$order->id]));
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

    /** One part of an order printed by parts, as its own plate will print it (prepared: repaired, scaled, turned). */
    public function partModel(Request $request, FarmOrder $order, string $part): BinaryFileResponse
    {
        $this->authorizeOrder($request, $order);
        abort_if($order->hidesModelFrom($request->user()), 403);
        $plate = collect($order->partPlates())->firstWhere('part', $part);
        $path = $plate && ! empty($plate['stl_path']) ? Storage::disk(config('farm.disk'))->path((string) $plate['stl_path']) : null;
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
        $order->loadMissing(['color', 'printer', 'material', 'modelFile', 'slot.printer']);
        $currency = $this->currencyFor($order, $request);
        $this->orders->alignCurrency($order, $currency);
        $job = $order->latestJob();
        $check = (array) $order->check;
        // a design that must print on one plate takes only machines that change spools (an operator feeds one at a time)
        $required = (bool) $order->modelFile?->onePlateRequired();
        $colors = $order->status === FarmOrder::STATUS_SLICED
            ? $this->orders->availableColors($order, $required)->map(fn ($r) => [
                'slot' => $r['slot']->id, 'name' => $r['color']->displayName(), 'kind' => $r['color']->material->label(), 'hex' => $r['color']->hex, 'finish' => $r['color']->material->finish, 'photo' => $r['color']->photoUrl(),
                // the spools of this colour that could print the order, this entry's own first ("U33"): farm, printer, slot
                'code' => $r['codes'][0] ?? null, 'codes' => $r['codes'],
                'enough' => $r['enough'], 'price' => $price = $this->orders->priceFor($order, $r['printer'], Shipping::PICKUP, $r['color']->material, null, $currency), 'total' => $price['total'],
                'starts_now' => $r['printer']->readyForAutoStart() && ! $this->settings->get('require_approval'),
                // a plate with a raised text: the colours the text can have next to this colour of the plate – from every
                // machine that holds this colour (the order goes where both spools are, machineFor)
                'second' => ($order->wantedChanges() || $order->isByParts() ? $this->orders->secondSpoolsAcross($r['slot'], $order) : collect())
                    ->filter(fn ($s) => ! $required || $s->printer->isAgentDriven())->values()
                    ->map(fn ($s) => ['slot' => $s->id, 'name' => $s->color->displayName(), 'kind' => $s->color->material->label(), 'hex' => $s->color->hex, 'finish' => $s->color->material->finish, 'photo' => $s->color->photoUrl(), 'main' => $s->main_slot_id, 'code' => $s->code()])->values()->all(),
                'color_id' => $r['color']->id,
                // printed by parts: every machine that holds this colour, with its first-colour slot and its other spools – the
                // page offers each part the colours one machine holds together with the first colour and the other parts'
                // choices, and sends that one machine's slots (the customer picks colours, the farm the machine)
                'machines' => $order->isByParts() ? $this->orders->firstsAcross($r['slot'], $order)
                    ->filter(fn ($f) => ! $required || $f->printer->isAgentDriven())
                    ->map(fn ($f) => ['main' => $f->id, 'name' => $f->printer->name, 'code' => $f->code(), 'colors' => $this->orders->secondSpools($f)
                        ->map(fn ($s) => ['slot' => $s->id, 'color_id' => $s->farm_color_id, 'name' => $s->color->displayName(), 'kind' => $s->color->material->label(), 'hex' => $s->color->hex, 'finish' => $s->color->material->finish, 'photo' => $s->color->photoUrl(), 'code' => $s->code()])->values()->all()])
                    ->values()->all() : [],
                // the time, the weight and the price on the page were computed for this colour's machine and kind
                'sliced' => $r['printer']->id === $order->farm_printer_id && $r['color']->material->id === $order->farm_material_id
                    && ($order->farm_color_id === null || $order->farm_color_id === $r['color']->id),
            ])->all()
            : [];

        // every change of the design: where, the colour it was designed in, and the spool chosen for it so far
        $stored = $order->colorSlots();
        $spools = $stored ? FarmPrinterSlot::with(['color', 'printer'])->whereIn('id', array_column($stored, 'slot_id'))->get()->keyBy('id') : collect();
        $changes = array_map(function (array $c) use ($stored, $spools): array {
            foreach ($stored as $s) {
                if (abs($s['z'] - $c['z']) < 0.005) {
                    $spool = $spools->get($s['slot_id']);

                    return $c + ['slot_id' => $s['slot_id'], 'name' => $spool?->color?->displayName(), 'slot_code' => $spool?->code()];
                }
            }

            return $c + ['slot_id' => null, 'name' => null, 'slot_code' => null];
        }, $order->wantedChanges());

        // an order printed by parts: every part, the colour the design names for it, and the spool chosen so far
        $partPlates = $order->partPlates();
        $partSpools = $partPlates ? FarmPrinterSlot::with(['color', 'printer'])->whereIn('id', array_filter(array_column($partPlates, 'slot_id')))->get()->keyBy('id') : collect();
        $parts = array_map(fn ($p, $i) => [
            'part' => $p['part'], 'plate' => $order->onePlate() ? 1 : $i + 1, 'label' => OrderService::partLabel($order->modelFile, (string) $p['part']),
            'hex' => ($order->modelFile?->tool_params['part_colors'][$p['part']]['hex'] ?? null) ?: null,
            'slot_id' => ($p['slot_id'] ?? null) ?: null, 'color_id' => ($p['color_id'] ?? null) ?: null, 'name' => $partSpools->get($p['slot_id'] ?? 0)?->color?->displayName(), 'slot_code' => $partSpools->get($p['slot_id'] ?? 0)?->code(),
            'copies' => (int) ($p['copies'] ?? $order->copies), 'minutes' => $p['minutes'] ?? null, 'grams' => $p['grams'] ?? null,
            // the part's own plate for the preview, in the colour of its spool (the design's colour until one is chosen)
            'spool_hex' => $partSpools->get($p['slot_id'] ?? 0)?->color?->hex,
            'model_url' => ! empty($p['stl_path']) && ! $order->hidesModelFrom($request->user()) ? route('farm.orders.part_model', [$order, $p['part']]).'?v='.($order->updated_at?->timestamp ?? 0) : null,
        ], $partPlates, array_keys($partPlates));

        return [
            'token' => $order->token,
            'number' => $order->number,
            'changes' => $parts ? [] : $changes,
            'by_parts' => $order->isByParts(),
            'parts' => $parts,
            // the parts print together on one plate, the machine changing spools (so many times) – or one plate each
            'one_plate' => $order->onePlate(),
            'filament_changes' => (int) ($order->slice_result['changes'] ?? 0),
            // the design's colours share a layer: one plate on a machine with a changer, one piece, or nothing (docs/AL.md §10.8)
            'one_plate_required' => $required,
            // how many separately printed parts the design has: two or more, and the order page offers "print them apart"
            'part_count' => $order->status === FarmOrder::STATUS_SLICED && $order->modelFile ? count(OrderService::designParts($order->modelFile)) : 0,
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
            'color' => $order->color ? ['name' => $order->color->material->label().' '.$order->color->displayName(), 'hex' => $order->color->hex, 'finish' => $order->color->material->finish, 'code' => $order->slot?->code()] : null,
            // every spool of the order as chosen so far, with its code: the summary over the payment, the line under the number
            'spools' => $order->spoolLines(),
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
