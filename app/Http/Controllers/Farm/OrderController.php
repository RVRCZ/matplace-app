<?php

namespace App\Http\Controllers\Farm;

use App\Domain\Farm\Dispatcher;
use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\InsufficientCredit;
use App\Domain\Farm\ModelValidator;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\Wallet;
use App\Http\Controllers\Controller;
use App\Models\FarmOrder;
use App\Models\FarmPrinterSlot;
use App\Models\ModelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** "Rent a printer": the customer's side of a farm order. Logged-in users only; an order is visible to its owner and admins. */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders, private readonly FarmSettings $settings, private readonly Wallet $wallet) {}

    /** Entrance: from the calculator with ?file=<uuid>, or empty with an upload field. */
    public function start(Request $request): View
    {
        $file = $request->query('file') ? ModelFile::where('uuid', $request->query('file'))->first() : null;

        return view('farm.start', [
            'file' => $file,
            'quality' => (string) $request->query('quality', 'standard'),
            'settings' => $this->settings->all(),
            'balance' => $this->wallet->balance($request->user()),
            'slicesLeft' => max(0, (int) $this->settings->get('daily_slices_per_user') - $this->orders->slicesToday($request->user())),
        ]);
    }

    public function index(Request $request): View
    {
        return view('farm.orders', [
            'orders' => FarmOrder::with(['modelFile', 'color'])->where('user_id', $request->user()->id)->latest('id')->paginate(20),
            'balance' => $this->wallet->balance($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'uuid'],
            'quality' => ['nullable', 'string', 'max:12'],
            'strength' => ['nullable', 'string', 'max:12'],
            'unit' => ['nullable', 'in:'.implode(',', array_keys(ModelValidator::UNITS))],
        ]);
        $file = ModelFile::where('uuid', $data['file'])->firstOrFail();
        $this->claim($request, $file);

        try {
            $order = $this->orders->create($request->user(), $file, $data['quality'] ?? 'standard', $data['strength'] ?? 'standard', $data['unit'] ?? null);
        } catch (FarmRefusal $e) {
            return $request->expectsJson()
                ? response()->json(['error' => $e->reason, 'message' => $e->text()], 422)
                : back()->with('error', $e->text());
        }

        return $request->expectsJson()
            ? response()->json(['url' => route('farm.orders.show', $order)], 201)
            : redirect()->route('farm.orders.show', $order);
    }

    public function show(Request $request, FarmOrder $order): View
    {
        $this->authorizeOrder($request, $order);

        return view('farm.order', [
            'order' => $order->load(['modelFile', 'color', 'printer', 'material']),
            'state' => $this->describe($order, $request),
            'settings' => $this->settings->all(),
        ]);
    }

    /** Polled by the order page. */
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
        ]);
        try {
            $this->orders->reslice($order, $data['quality'], $data['strength'], $data['unit'] ?? null);
        } catch (FarmRefusal $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->text()], 422);
        }

        return response()->json($this->describe($order->refresh(), $request));
    }

    public function pay(Request $request, FarmOrder $order, OrderFlow $flow): JsonResponse
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate([
            'slot' => ['required', 'integer'],
            'delivery' => ['required', 'string', 'max:10'],
            'terms' => ['accepted'],
            'expected_total' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:500'],
            'address' => ['required_if:delivery,shipping', 'nullable', 'array'],
            'address.name' => ['required_if:delivery,shipping', 'nullable', 'string', 'max:120'],
            'address.street' => ['required_if:delivery,shipping', 'nullable', 'string', 'max:160'],
            'address.city' => ['required_if:delivery,shipping', 'nullable', 'string', 'max:120'],
            'address.zip' => ['required_if:delivery,shipping', 'nullable', 'string', 'max:12'],
            'address.country' => ['nullable', 'string', 'size:2'],
            'address.phone' => ['nullable', 'string', 'max:30'],
        ], ['terms.accepted' => __('farm.refuse.terms')]);

        try {
            $flow->pay($order, FarmPrinterSlot::findOrFail($data['slot']), $data['delivery'], $data['address'] ?? null, true, $request->ip(), (float) $data['expected_total'], $data['note'] ?? null);
        } catch (InsufficientCredit $e) {
            return response()->json(['error' => 'credit', 'message' => __('farm.refuse.credit', ['missing' => number_format($e->missing(), 0, ',', ' ')]), 'missing' => $e->missing(), 'topup_url' => route('account.credit', ['need' => ceil($e->missing()), 'back' => $order->token])], 402);
        } catch (FarmRefusal $e) {
            return response()->json(['error' => $e->reason, 'message' => $e->text()] + $e->data, 422);
        }

        return response()->json($this->describe($order->refresh(), $request));
    }

    public function cancel(Request $request, FarmOrder $order, OrderFlow $flow): JsonResponse|RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        if (! $order->cancellableByCustomer()) {
            return response()->json(['error' => 'locked', 'message' => __('farm.refuse.locked')], 422);
        }
        // a start command may already be on its way to the printer: then only the operator can stop it
        if ($order->printJobs()->whereIn('status', ['sent', 'printing', 'paused', 'unknown'])->exists()) {
            return response()->json(['error' => 'locked', 'message' => __('farm.refuse.locked')], 422);
        }
        $flow->move($order, FarmOrder::STATUS_CANCELLED, 'user', $request->user()->id);

        return $request->expectsJson() ? response()->json($this->describe($order->refresh(), $request)) : redirect()->route('farm.orders');
    }

    /** The model as it will be printed (repaired, scaled, turned): what the viewer shows. */
    public function model(Request $request, FarmOrder $order): BinaryFileResponse
    {
        $this->authorizeOrder($request, $order);
        $path = $order->absolutePrintStlPath() ?: $order->modelFile?->absoluteStlPath();
        abort_unless($path && is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'model/stl', 'Cache-Control' => 'private, max-age=60']);
    }

    /** Last camera picture of the customer's own print. */
    public function snapshot(Request $request, FarmOrder $order): BinaryFileResponse
    {
        $this->authorizeOrder($request, $order);
        $job = $order->latestJob();
        abort_unless($job && $job->snapshot_path && Storage::disk(config('farm.disk'))->exists($job->snapshot_path), 404);

        return response()->file(Storage::disk(config('farm.disk'))->path($job->snapshot_path), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']);
    }

    public function terms(): View
    {
        return view('farm.terms', ['version' => $this->settings->get('terms_version')]);
    }

    /** Everything the order page needs, in one JSON object. */
    private function describe(FarmOrder $order, Request $request): array
    {
        $order->loadMissing(['color', 'printer', 'material', 'modelFile']);
        $job = $order->latestJob();
        $check = (array) $order->check;
        $colors = $order->status === FarmOrder::STATUS_SLICED
            ? $this->orders->availableColors($order)->map(fn ($r) => [
                'slot' => $r['slot']->id, 'name' => $r['color']->name, 'hex' => $r['color']->hex, 'photo' => $r['color']->photoUrl(),
                'enough' => $r['enough'], 'total' => $this->orders->priceFor($order, $r['printer'], 'pickup')['total'],
                'starts_now' => $r['printer']->readyForAutoStart() && ! $this->settings->get('require_approval'),
            ])->all()
            : [];

        return [
            'token' => $order->token,
            'number' => $order->number,
            'status' => $order->status,
            'status_text' => __('farm.status.'.$order->status),
            'stage' => $order->stage,
            'error' => $order->error,
            'error_text' => $order->error ? __('farm.error.'.$order->error, $this->errorData($check, (string) $order->error)) : null,
            'quality' => $order->quality,
            'strength' => $order->strength,
            'unit' => array_search($order->unit_scale, ModelValidator::UNITS, false) ?: 'mm',
            'unit_guess' => $check['unit_guess'] ?? null,
            'dims' => $check['dims'] ?? null,
            'raw_dims' => $check['raw_dims'] ?? null,
            'warnings' => array_map(fn ($w) => __('farm.warn.'.$w['code'], $w['data'] ?? []), $check['warnings'] ?? []),
            'orientation_changed' => (bool) ($order->orientation['changed'] ?? false),
            'supports' => $order->supports_used,
            'minutes' => $order->est_minutes,
            'grams' => $order->est_grams,
            'meters' => $order->est_meters,
            'price' => $order->price,
            'total' => $order->price_total,
            'currency' => $order->currency,
            'shipping_price' => (float) $this->settings->get('shipping_price'),
            'colors' => $colors,
            'color' => $order->color ? ['name' => $order->color->name, 'hex' => $order->color->hex] : null,
            'delivery' => $order->delivery,
            'balance' => $this->wallet->balance($request->user()->id === $order->user_id ? $request->user() : $order->user),
            'model_url' => $order->print_stl_path || $order->modelFile?->stl_path ? route('farm.orders.model', $order).'?v='.($order->updated_at?->timestamp ?? 0) : null,
            'queue' => app(Dispatcher::class)->estimate($order, $this->settings),
            'print' => $job ? [
                'status' => $job->status, 'progress' => $job->progress,
                'snapshot_url' => $job->snapshot_path ? route('farm.orders.snapshot', $order).'?t='.($job->snapshot_at?->timestamp ?? 0) : null,
                'snapshot_at' => $job->snapshot_at?->toIso8601String(),
            ] : null,
            'can_cancel' => $order->cancellableByCustomer(),
            'final' => in_array($order->status, [FarmOrder::STATUS_HANDED_OVER, FarmOrder::STATUS_CANCELLED], true),
        ];
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
