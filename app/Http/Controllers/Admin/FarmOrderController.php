<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Farm\Dispatcher;
use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\GcodeSlot;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\PrintProfile;
use App\Domain\Farm\PrintSettings;
use App\Domain\Farm\Wallet;
use App\Engines\Shipping\Parcel;
use App\Engines\Shipping\ShippingCarrier;
use App\Engines\Shipping\ShippingFailed;
use App\Http\Controllers\Controller;
use App\Models\FarmCommand;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Farm operator's desk: printers with their queues, orders, manual state changes, measured values after a print. */
class FarmOrderController extends Controller
{
    public function __construct(private readonly OrderFlow $flow, private readonly Dispatcher $dispatcher, private readonly FarmSettings $settings) {}

    /** Printers, what runs on them, what waits, and when each waiting order will get its turn. */
    public function dashboard(): View
    {
        $printers = FarmPrinter::with(['slots.color', 'agent'])->orderBy('id')->get()->map(function (FarmPrinter $p) {
            $queue = $this->dispatcher->queue($p)->load(['user', 'color', 'modelFile']);

            return [
                'printer' => $p,
                'job' => $p->activeJob()?->load('order.modelFile'),
                'queue' => $queue->map(fn (FarmOrder $o) => ['order' => $o, 'eta' => $this->dispatcher->estimate($o, $this->settings)]),
            ];
        });

        return view('admin.farm.dashboard', [
            'printers' => $printers,
            'attention' => FarmOrder::with(['user', 'modelFile'])->where(fn ($q) => $q->where('status', FarmOrder::STATUS_PAID)->orWhere('status', FarmOrder::STATUS_DONE)
                ->orWhere(fn ($f) => $f->where('status', FarmOrder::STATUS_FAILED)->whereNotNull('paid_at')))->latest('id')->limit(30)->get(),
            'requireApproval' => (bool) $this->settings->get('require_approval'),
        ]);
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        return view('admin.farm.orders', [
            'orders' => FarmOrder::with(['user', 'modelFile', 'color', 'printer'])
                ->when($status !== '', fn ($q) => $q->where('status', $status), fn ($q) => $q->whereNotNull('paid_at'))
                ->latest('id')->paginate(40)->withQueryString(),
            'status' => $status,
            'statuses' => array_keys(FarmOrder::TRANSITIONS),
        ]);
    }

    public function show(FarmOrder $order): View
    {
        $order->load(['user', 'modelFile', 'color', 'printer', 'slot', 'material', 'events', 'printJobs', 'printerMaterial']);

        return view('admin.farm.order', [
            'order' => $order,
            'targets' => FarmOrder::TRANSITIONS[$order->status] ?? [],
            'eta' => $this->dispatcher->estimate($order, $this->settings),
            'balance' => app(Wallet::class)->balance($order->user),
        ]);
    }

    public function gcode(FarmOrder $order): BinaryFileResponse
    {
        // the plate that prints next (the last one of several may be a smaller file)
        $plate = $order->nextPlate();
        $path = $order->absoluteGcodePath($plate);
        abort_unless($path && is_file($path), 404);
        // the operator sends this file by hand: it must already select the customer's slot
        $path = GcodeSlot::fileFor($path, (int) ($order->slot?->slot ?? 0), PrintProfile::tempsFor($order), $order->colorChange());

        return response()->download($path, 'matplace-'.($order->number ?: $order->token).($order->plates > 1 ? '-p'.$plate : '').'.gcode', ['Content-Type' => 'text/x.gcode']);
    }

    public function snapshot(FarmOrder $order): BinaryFileResponse
    {
        $job = $order->latestJob();
        abort_unless($job && $job->snapshot_path && Storage::disk(config('farm.disk'))->exists($job->snapshot_path), 404);

        return response()->file(Storage::disk(config('farm.disk'))->path($job->snapshot_path), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store']);
    }

    public function timelapse(FarmOrder $order): BinaryFileResponse
    {
        abort_unless($order->timelapse_path && Storage::disk(config('farm.disk'))->exists($order->timelapse_path), 404);

        return response()->file(Storage::disk(config('farm.disk'))->path($order->timelapse_path), ['Content-Type' => 'video/mp4']);
    }

    /** The admin's process overrides for one order (JSON object of slicer settings), then a new slice. */
    public function overrides(Request $request, FarmOrder $order, OrderService $orders): RedirectResponse
    {
        $data = $request->validate(['overrides' => ['nullable', 'string', 'max:4000']]);
        $raw = trim((string) ($data['overrides'] ?? ''));
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        if (! is_array($decoded)) {
            return back()->with('error', 'Přepisy nejsou platný JSON objekt, například {"wall_loops": "3", "sparse_infill_pattern": "gyroid"}.');
        }
        $unknown = array_diff_key($decoded, PrintSettings::cleanOverrides($decoded));
        if ($unknown) {
            return back()->with('error', 'Tyhle klíče nejsou nastavení sliceru (jen malá písmena, číslice a podtržítka; hodnoty čísla nebo texty): '.implode(', ', array_keys($unknown)));
        }
        try {
            $orders->overrideByAdmin($order, $decoded);
        } catch (FarmRefusal $e) {
            return back()->with('error', 'Zakázka už stojí ve frontě nebo se tiskne; přepisy jdou měnit jen před zařazením.');
        }

        return back()->with('status', $decoded ? 'Přepisy uložené, zakázka se slicuje znovu.' : 'Přepisy zrušené, zakázka se slicuje znovu.');
    }

    public function approve(Request $request, FarmOrder $order): RedirectResponse
    {
        $this->flow->approve($order, $request->user()->id);

        return back()->with('status', __('farm.admin.saved'));
    }

    /** Manual status change. On a manual printer this is how "printing" and "done" happen at all. */
    public function status(Request $request, FarmOrder $order): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'string', 'max:14'], 'note' => ['nullable', 'string', 'max:500'], 'tracking' => ['nullable', 'string', 'max:80']]);
        if (! $order->canMoveTo($data['to'])) {
            return back()->with('error', __('farm.admin.bad_transition'));
        }
        if ($data['to'] === FarmOrder::STATUS_FAILED) {
            $this->flow->fail($order, 'operator', 'admin', $request->user()->id, $data['note'] ?? null);
        } else {
            if ($data['to'] === FarmOrder::STATUS_PRINTING && ! $order->printJobs()->whereIn('status', FarmPrintJob::ACTIVE)->exists()) {
                // started by hand: keep a job row so the history and the calibration look the same as with an agent
                FarmPrintJob::create(['farm_order_id' => $order->id, 'farm_printer_id' => $order->farm_printer_id, 'slot' => (int) ($order->slot?->slot ?? 0), 'plate' => $order->nextPlate(), 'status' => FarmPrintJob::STATUS_PRINTING, 'started_at' => now()]);
                $order->printer?->update(['bed_clear' => false]);
            }
            if ($data['to'] === FarmOrder::STATUS_DONE) {
                $order->printJobs()->whereIn('status', FarmPrintJob::ACTIVE)->update(['status' => FarmPrintJob::STATUS_DONE, 'progress' => 100, 'finished_at' => now()]);
            }
            if (! empty($data['tracking'])) {
                $order->forceFill(['tracking' => $data['tracking']])->save();
            }
            if ($data['to'] === FarmOrder::STATUS_DONE && $order->status === FarmOrder::STATUS_PRINTING && $order->plates > 1) {
                // one plate of several came off: back to the queue for the next one, done only after the last
                $this->flow->plateFinished($order, 'admin', $request->user()->id);
            } else {
                $this->flow->move($order, $data['to'], 'admin', $request->user()->id, $data['note'] ?? null);
            }
        }

        return back()->with('status', __('farm.admin.saved'));
    }

    /** Real time and weight after the print: the data the correction factors are calibrated from. */
    public function actuals(Request $request, FarmOrder $order): RedirectResponse
    {
        $data = $request->validate(['actual_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'], 'actual_grams' => ['nullable', 'numeric', 'min:0.1', 'max:100000'],
            'quality_rating' => ['nullable', 'integer', 'min:1', 'max:5'], 'quality_note' => ['nullable', 'string', 'max:500']]);
        $this->flow->recordActuals($order, isset($data['actual_minutes']) ? (int) $data['actual_minutes'] : null, isset($data['actual_grams']) ? (float) $data['actual_grams'] : null, 'admin');
        if (array_key_exists('quality_rating', $data) || array_key_exists('quality_note', $data)) {
            $order->forceFill(['quality_rating' => $data['quality_rating'] ?? $order->quality_rating, 'quality_note' => $data['quality_note'] ?? $order->quality_note])->save();
        }

        return back()->with('status', __('farm.admin.saved'));
    }

    public function refund(Request $request, FarmOrder $order, Wallet $wallet): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:300']]);
        $amount = $wallet->giveBack($order, $request->user()->id, $data['note']);

        return back()->with('status', __('farm.admin.refunded', ['amount' => Money::of($amount, $order->currency)->format()]));
    }

    /** "Create the parcel": the finished print is announced to Packeta, handed over, and the customer gets the tracking link. */
    public function ship(Request $request, FarmOrder $order): RedirectResponse
    {
        try {
            $this->flow->ship($order, $request->user()->id);
        } catch (ShippingFailed $e) {
            // the carrier's own words; the order stays "done" so the operator can fix the address and try again
            return back()->with('error', __('farm.admin.ship_failed', ['reason' => $e->getMessage()]));
        } catch (\DomainException) {
            return back()->with('error', __('farm.admin.bad_transition'));
        }

        return back()->with('status', __('farm.admin.shipped', ['barcode' => $order->packeta_barcode]));
    }

    /** The label to stick on the box. */
    public function label(FarmOrder $order, ShippingCarrier $carrier): Response|RedirectResponse
    {
        abort_unless($order->packeta_packet_id, 404);
        try {
            $pdf = $carrier->labelPdf((string) $order->packeta_packet_id, Parcel::forOrder($order)->external);
        } catch (ShippingFailed $e) {
            return back()->with('error', __('farm.admin.ship_failed', ['reason' => $e->getMessage()]));
        }

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="matplace-'.$order->number.'-label.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    /** "The plate is empty" — the one confirmation without which nothing starts by itself. */
    public function bed(Request $request, FarmPrinter $printer): RedirectResponse|JsonResponse
    {
        $clear = $request->boolean('clear');
        // a running print sits on the plate: "clear" would let the next job start onto it
        if ($clear && $printer->isPrintingNow()) {
            return $this->answer($request, false, __('farm.admin.bed_locked'));
        }
        $printer->update(['bed_clear' => $clear, 'bed_cleared_at' => $clear ? now() : $printer->bed_cleared_at]);
        $started = $clear ? $this->dispatcher->kick($printer) : null;

        return $this->answer($request, true, $started ? __('farm.admin.started', ['number' => $started->order->number]) : __('farm.admin.saved'));
    }

    /** Pause / resume / cancel of the running print (and the chamber light, the spool dryer), carried out by the agent. */
    public function command(Request $request, FarmPrinter $printer): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:pause,resume,cancel,light_on,light_off,dry_on,dry_off'],
            'dry_temp' => ['nullable', 'integer', 'min:35', 'max:70'],
            'dry_hours' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);
        if (! $printer->isAgentDriven()) {
            return $this->answer($request, false, __('farm.admin.no_job'));
        }
        if (str_starts_with($data['type'], 'light_')) {
            FarmCommand::create(['farm_printer_id' => $printer->id, 'type' => FarmCommand::TYPE_LIGHT, 'payload' => ['on' => $data['type'] === 'light_on'], 'created_by' => $request->user()->id]);

            return $this->answer($request, true, __('farm.admin.command_sent'));
        }
        if (str_starts_with($data['type'], 'dry_')) {
            // the ACE dries every spool in the box at once; PLA 45–50 °C, PETG 55 °C (the ACE Pro stops at 55)
            $payload = ['on' => $data['type'] === 'dry_on', 'temp' => (int) ($data['dry_temp'] ?? 50), 'minutes' => 60 * (int) ($data['dry_hours'] ?? 4)];
            FarmCommand::create(['farm_printer_id' => $printer->id, 'type' => FarmCommand::TYPE_DRY, 'payload' => $payload, 'created_by' => $request->user()->id]);

            return $this->answer($request, true, __('farm.admin.command_sent'));
        }
        $job = $printer->activeJob();
        if (! $job) {
            return $this->answer($request, false, __('farm.admin.no_job'));
        }
        FarmCommand::create(['farm_printer_id' => $printer->id, 'farm_print_job_id' => $job->id, 'type' => $data['type'], 'created_by' => $request->user()->id]);

        return $this->answer($request, true, __('farm.admin.command_sent'));
    }

    /** The dashboard buttons post over fetch and show the answer as a short toast; without JavaScript it is the redirect + flash. */
    private function answer(Request $request, bool $ok, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'status' : 'error', $message);
    }

    public function printerSnapshot(FarmPrinter $printer): BinaryFileResponse
    {
        abort_unless($printer->snapshot_path && Storage::disk(config('farm.disk'))->exists($printer->snapshot_path), 404);

        return response()->file(Storage::disk(config('farm.disk'))->path($printer->snapshot_path), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store']);
    }
}
