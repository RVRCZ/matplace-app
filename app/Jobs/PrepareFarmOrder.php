<?php

namespace App\Jobs;

use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\ModelValidator;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\OrderService;
use App\Domain\Farm\PlateLayout;
use App\Domain\Farm\PrintProfile;
use App\Domain\Farm\PrintSettings;
use App\Domain\Farm\TestPrintService;
use App\Domain\Farm\TimelapseGcode;
use App\Domain\Farm\TowerGcode;
use App\Domain\Files\PaintedColors;
use App\Engines\Contracts\PrintPreparer;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\PreparedMesh;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Engines\Exceptions\PathConflictException;
use App\Engines\Exceptions\SlicerException;
use App\Engines\Exceptions\SupersededException;
use App\Engines\Farm\PhpPrintPreparer;
use App\Engines\Gcode\SupportLines;
use App\Engines\Mesh\MeshDecimator;
use App\Engines\Mesh\StlFile;
use App\Engines\Mesh\SupportsCheck;
use App\Engines\Mesh\ThreeMfWriter;
use App\Engines\Slicer\OrcaSlicer;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterSlot;
use App\Models\ModelFile;
use App\Support\RunGuard;
use App\Support\Stopwatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Farm order: uploaded → sliced. Check and repair the mesh, turn it for the plate, slice it with the printer's and the
 * material's profile, store the G-code with everything needed to produce it again, price it.
 * Started when the order is made and again by ProcessModelFile when its file is ready, like SliceCalculation; two
 * copies never run at once (WithoutOverlapping), and the later one finds the order no longer "uploaded".
 *
 * A big model's slice is asked for by the customer and may be asked for again while it runs (other choices,
 * docs/AL.md §11): every slice is a numbered run (`slice_run` on the order, `$run` here), the job checks between its
 * steps whether it is still the current run, and a run that was overtaken leaves the order to the next one (nothing
 * of its result moves the order on; the files it wrote are overwritten by the run that follows). An overtaken run does
 * not finish its slice either (docs/AL.md §22): while one of its engine processes runs – the repair and the pose, the
 * thinning, the slicer – the job asks once a second whether it is still the current run, and when it is not, the process
 * is killed with everything it started (RunGuard, ProcessTree) and the job ends without a verdict; the run that replaced
 * it waits for the order's lock two seconds at a time and starts as soon as this one has let go.
 *
 * A piece of very many triangles is thinned here, once, before its copies are laid out (thinPiece, docs/AL.md §11.9):
 * the plates are built from the thinned piece and the slicer is told not to thin them again (SliceParams::thinMesh).
 * The copies go to the slicer as instances of one object in a project (instanceProject, docs/AL.md §11.10), the glued
 * plate only beside it; when their paths conflict the glued plate is sliced as before (sliceCopies).
 */
class PrepareFarmOrder implements ShouldQueue
{
    use Queueable;

    /**
     * The attempts are mostly releases: a run that follows another of the same order waits for its lock two seconds
     * at a time (middleware), and a big model's slice takes minutes – enough releases to outlast the job's timeout.
     */
    public int $tries = 1000;

    /** The run this job works for once it has started: its own number, or the order's current one when it was given none. */
    private ?int $current = null;

    public int $backoff = 3;

    /** Seconds the whole run may take: the longest slice a plate of copies is allowed (engines.orca.timeout_max) and room for the rest, never under 900. */
    public int $timeout = 900;

    /** When it was put in the queue (epoch): the wait in the queue is part of the customer's wait. */
    public ?float $queuedAt = null;

    /** @param  int|null  $run  which slice of the order this is (FarmOrder::slice_run); null = whichever is current (the file's safety net) */
    public function __construct(public readonly int $orderId, public readonly ?int $run = null)
    {
        $this->onQueue(config('queue.interactive'));
        $this->queuedAt = microtime(true);
        $this->timeout = max(900, (int) config('engines.orca.timeout_max', 1200) + 300);
    }

    /**
     * Seconds the slicer may take over a plate of `$pieces` copies: the configured limit for one piece, engines.orca.timeout_per_piece
     * more for every further one, never over timeout_max (docs/AL.md §11.10; null = the configured limit as it is).
     */
    public static function sliceTimeout(int $pieces): ?int
    {
        $per = (int) config('engines.orca.timeout_per_piece', 0);
        if ($pieces <= 1 || $per <= 0) {
            return null;
        }
        $base = (int) config('engines.orca.timeout', 180);

        return min(max($base, (int) config('engines.orca.timeout_max', 1200)), $base + $per * ($pieces - 1));
    }

    /** May this slice build supports (the customer's "auto" or "on", an admin forcing them) – or are they off for sure? */
    public static function supportsPossible(SliceParams $params): bool
    {
        $forced = $params->overrides['process']['enable_support'] ?? null;

        return $forced === '1' || ($params->supports !== false && $forced !== '0');
    }

    /**
     * The project of a plate of copies (docs/AL.md §11.10): the piece as one object and a build item per copy where
     * PlateLayout laid them, the set centred on the bed as the slicer centres a mesh (StlFile::place), so Orca slices the
     * piece once and prints every instance ("need to slice 1"). Null when there is nothing to gain (one piece) or the piece
     * has more triangles than the project's XML should hold (farm.copies.instances_max_triangles): the glued plate then
     * goes as a mesh, as before.
     *
     * @param  array{placements: list<array{0: int, 1: float, 2: float}>, lift: float, origin: array{0: float, 1: float}}|null  $lay
     */
    private function instanceProject(FarmOrder $order, $disk, string $pieceRel, PreparedMesh $piece, PreparedMesh $plate, ?array $lay, Dimensions $bed, Stopwatch $clock, string $name = 'plate.3mf'): ?string
    {
        if ($lay === null || count($lay['placements']) < 2) {
            return null;
        }
        if ($piece->triangles > (int) config('farm.copies.instances_max_triangles', 1500000)) {
            $clock->note('instances_skipped', 'triangles');

            return null;
        }
        // the slicer puts a mesh's box in the middle of the bed: the set of instances goes there too
        $cx = $bed->x / 2 - $lay['origin'][0] - $plate->bbox->x / 2;
        $cy = $bed->y / 2 - $lay['origin'][1] - $plate->bbox->y / 2;
        $items = array_map(fn ($p) => [round($p[1] + $cx, 4), round($p[2] + $cy, 4), $lay['lift'], $p[0]], $lay['placements']);
        $path = $disk->path($order->dir().'/'.$name);
        $clock->measure('project', fn () => ThreeMfWriter::write($path, [['name' => 'print', 'stl' => $disk->path($pieceRel), 'extruder' => 1]], [], $items));

        return $path;
    }

    /**
     * The plate sliced as a project of instances when there is one, else (or when the slicer finds the paths of two instances
     * crossing – the supports of one in the other, PathConflictException) as the glued mesh.
     *
     * @return array{0: SliceResult, 1: ?bool} the result and how it went: true = instances, false = glued after a conflict, null = no project
     */
    private function sliceCopies(Slicer $slicer, string $meshPath, SliceParams $params, ?string $project, Stopwatch $clock, string $phase): array
    {
        if ($project !== null) {
            try {
                return [$clock->measure($phase, fn () => $slicer->slice($meshPath, $params->withProject($project))), true];
            } catch (SlicerException $e) {
                if (! $e instanceof PathConflictException && ! OrcaSlicer::pathConflict($e->getMessage())) {
                    throw $e;
                }
                Log::warning('The instances of the plate conflict, sliced as one mesh instead', ['order' => $this->orderId, 'error' => mb_substr($e->getMessage(), -300)]);
                $clock->note($phase.'_conflict', mb_substr($e->getMessage(), -200));
            }

            return [$clock->measure($phase, fn () => $slicer->slice($meshPath, $params)), false];
        }

        return [$clock->measure($phase, fn () => $slicer->slice($meshPath, $params)), null];
    }

    /**
     * The order asked for another slice since this run was dispatched (or, for a job without a number, since it started):
     * this run's result would be for choices nobody wants.
     */
    private function overtaken(FarmOrder $order): bool
    {
        $mine = $this->current ?? $this->run;

        return $mine !== null && (int) FarmOrder::whereKey($order->id)->value('slice_run') !== $mine;
    }

    /** A verdict of this run reaches the order only while it is the current run. */
    private function fail(OrderFlow $flow, FarmOrder $order, string $code, ?string $detail = null): void
    {
        if ($this->overtaken($order)) {
            return;
        }
        $flow->fail($order, $code, 'system', detail: $detail);
    }

    /** Several workers: two recalculations of one order never write its files at the same time. */
    public function middleware(): array
    {
        // two seconds: a run that replaced another starts right after the old one was stopped (docs/AL.md §22)
        return [(new WithoutOverlapping('farm-order-'.$this->orderId))->releaseAfter(2)->expireAfter($this->timeout + 60)];
    }

    public function handle(PrintPreparer $preparer, Slicer $slicer, FarmSettings $settings, OrderService $orders, OrderFlow $flow): void
    {
        $order = FarmOrder::with(['modelFile', 'printer', 'material'])->find($this->orderId);
        if (! $order || $order->status !== FarmOrder::STATUS_UPLOADED || $this->overtaken($order)) {
            return;
        }
        $file = $order->modelFile;
        if (! $file || $file->status === ModelFile::STATUS_FAILED) {
            $this->fail($flow, $order, 'file_unreadable', $file?->error);

            return;
        }
        if (! $file->isReady()) {
            return;   // ProcessModelFile starts it again when the file is ready (or fails it when the file failed)
        }
        $printer = $order->printer;
        if (! $printer) {
            $this->fail($flow, $order, 'no_printer');

            return;
        }

        $disk = Storage::disk(config('farm.disk'));
        $bed = new Dimensions($printer->bed_x, $printer->bed_y, $printer->bed_z);
        // a first slice is waited for from the upload, a re-slice (other quality, colour, printer) from the click
        $reslice = $order->slice_params !== null;
        $clock = (new Stopwatch)->note('started', round(microtime(true), 1))->note('reslice', $reslice)->since('queue', $this->queuedAt)
            ->note('triangles', $file->triangles)->note('run', $this->run);
        if (! $reslice) {
            $clock->since('wait', $order->created_at?->getTimestamp());
        }
        // from here on the engines' processes of this job are given up as soon as the order asks for another slice
        $this->current = $this->run ?? (int) $order->slice_run;
        RunGuard::watch(fn () => $this->overtaken($order));

        try {
            if ($order->isByParts() && ($main = $order->slot) && ($slotIds = array_filter(array_map('intval', array_column($order->partPlates(), 'slot_id'))))
                && FarmPrinterSlot::whereIn('id', $slotIds)->where('farm_printer_id', '!=', $main->farm_printer_id)->exists()) {
                // the parts' spools sit in another machine than the first colour (order 198, 20 Oct 2026: the page moved the
                // first colour and the parts kept the old machine's spools): the same colours on this machine, or the order
                // stops and says so rather than slicing for spools no machine of this G-code holds
                $plates = $orders->partPlatesOn($main, $order->partPlates());
                if (array_column($plates, 'color_id') !== array_map('intval', array_column($order->partPlates(), 'color_id'))) {
                    $this->fail($flow, $order, 'parts_machine');

                    return;
                }
                $order->update(['part_plates' => $plates]);
                $clock->note('part_spools_moved', count($slotIds));
            }
            if ($order->isByParts()) {
                // the parts together on one plate, a filament per spool, when the machine can change spools and they fit
                // (docs/AL.md §10); else every part its own plate from its own spool, one after another
                if ($this->prepareOnePlate($order, $preparer, $slicer, $settings, $orders, $flow, $clock, $disk, $bed)) {
                    return;
                }
                if ($file->onePlateRequired()) {
                    // the colours of this design share a layer: by parts the motif would come out loose – the order stops
                    // here and says what it needs (a machine with a changer, spools of one machine, room on the plate)
                    $this->fail($flow, $order, 'one_plate_needed', (string) ($clock->toArray()['one_plate_skipped'] ?? ''));

                    return;
                }
                if (PaintedColors::painted($file)) {
                    // a painted 3MF by parts: its inlays are cut out of a curved surface and hang in the air on their own,
                    // the slicer refuses them (the turtle of 12 copies on production, 29 Oct 2026: 125 s, then "failed").
                    // The order stops with the reason, and the page offers it in one colour (docs/AL.md §14.8)
                    $this->fail($flow, $order, 'painted_one_plate', (string) ($clock->toArray()['one_plate_skipped'] ?? ''));

                    return;
                }
                $this->prepareParts($order, $preparer, $slicer, $settings, $orders, $flow, $clock, $disk, $bed);

                return;
            }
            // ── 1. check, repair, orient ───────────────────────────────────────
            $order->update(['stage' => 'checking']);
            $stlRel = $order->dir().'/print.stl';
            File::ensureDirectoryExists(dirname($disk->path($stlRel)));
            @unlink($disk->path($stlRel).'.stage');
            // a test object is built closed, on Z = 0, the way it must be printed: nothing to repair or turn
            $mesh = $clock->measure('prepare', fn () => $order->isTest() ? (new PhpPrintPreparer)->prepare($file->absoluteStlPath(), $disk->path($stlRel), 1.0, $bed)
                : $preparer->prepare($file->absoluteStlPath(), $disk->path($stlRel), $order->unit_scale * (float) ($order->scale ?: 1), $bed, $file->builtForPrinting()));
            $clock->note('prepare_cached', (bool) ($mesh->orientation['reused'] ?? false));
            @unlink($disk->path($order->dir().'/print.stl.stage'));   // the tool's running commentary ends with it
            if ($this->overtaken($order)) {
                $clock->note('overtaken', 'prepare');

                return;
            }
            $margin = 2 * (float) $settings->get('bed_margin_mm');
            $usable = new Dimensions($bed->x - $margin, $bed->y - $margin, $bed->z);
            // the slicer's settings now: the layer decides how far the piece may be thinned
            $setup = $this->sliceSetup($order, $settings);
            // a piece of very many triangles is thinned once, here, before it is copied onto the plate: the plate, the last
            // plate and every slice after a change of colour take the thinned piece (its thinning is remembered); a plate of
            // pieces under the threshold is never thinned as a whole, whatever their sum (docs/AL.md §11.9). The piece as
            // prepared stays the order's print.stl: the preview and the record show the customer's own geometry
            $thinRel = $order->dir().'/print.thin.stl';
            $piece = $this->thinPiece($mesh, $disk->path($thinRel), (float) ($setup['params']->overrides['process']['layer_height'] ?? $setup['layer']), $clock, max(1, (int) $order->copies));
            $pieceRel = $piece === $mesh ? $stlRel : $thinRel;
            // "automatic" supports, when the check is switched on: a piece of which nothing can hang in the air is sliced
            // with supports off (docs/AL.md §22)
            if ($setup['supports_auto']) {
                $setup['params'] = $this->withoutNeedlessSupports($setup['params'], $piece, (float) ($setup['params']->overrides['process']['layer_height'] ?? $setup['layer']), $clock);
                $setup['overrides'] = $setup['params']->overrides;   // the record of the slice says what it was sliced with
            }
            $sliceRel = $pieceRel;
            // the copies of a piece that may need supports stand further apart (tree supports lean out beyond its shadow, two
            // instances whose paths cross stop the slicer, docs/AL.md §11.10): the capacity, the plate and the project agree on it
            $gap = PlateLayout::supportGap((float) $piece->bbox->z, self::supportsPossible($setup['params']));
            $capacity = PlateLayout::capacityOf($piece, $usable, $gap);
            $plates = 1;
            $perPlate = $order->copies;
            $rest = null;
            $lay = null;   // where the copies of a full plate stand (PlateLayout::placements), for the project of instances
            if ($order->copies > 1 && ! $order->isTest() && $capacity['max'] >= 1) {
                // several copies on one plate: the oriented piece repeated in a grid or in rows by its shadow, sliced and priced as one print;
                // more than a plate takes → full plates one after another, the last one holding what is left
                if ($order->copies > $capacity['max']) {
                    $plates = (int) ceil($order->copies / $capacity['max']);
                    $perPlate = $capacity['max'];
                    $rest = $order->copies - $perPlate * ($plates - 1);
                    if ($rest === $perPlate) {
                        $rest = null;
                    }
                }
                $plateRel = $order->dir().'/plate.stl';
                $lay = $clock->measure('layout', fn () => PlateLayout::placements($piece, $perPlate, $usable, $gap)) ?? throw new \RuntimeException('Plate layout failed although the capacity allowed it.');
                $mesh = $clock->measure('layout', fn () => PlateLayout::replicate($piece, $disk->path($plateRel), $perPlate, $usable, $gap)) ?? throw new \RuntimeException('Plate layout failed although the capacity allowed it.');
                $stlRel = $plateRel;
                $sliceRel = $plateRel;
            }
            $clock->note('gap_mm', $gap);
            $order->fill(['plates' => $plates, 'plates_done' => 0, 'plate_copies' => $perPlate, 'rest_copies' => $rest, 'rest_gcode_path' => null]);
            $verdict = ModelValidator::judge($mesh, $bed, [
                'min_model_mm' => (float) $settings->get('min_model_mm'),
                'bed_margin_mm' => (float) $settings->get('bed_margin_mm'),
            ]);
            $raw = Dimensions::fromArray((array) $file->bbox);
            $order->check = $verdict + ['mesh' => $mesh->toArray(), 'unit_guess' => ModelValidator::guessUnit($raw, $bed), 'raw_dims' => $raw->toArray(),
                'piece_dims' => $piece->bbox->toArray(), 'max_copies' => $capacity['max']];
            $order->orientation = $mesh->orientation;
            $order->print_stl_path = $stlRel;
            $order->save();
            if (! $verdict['ok']) {
                $this->fail($flow, $order, $verdict['errors'][0]['code']);

                return;
            }

            // ── 2. slice ───────────────────────────────────────────────────────
            $order->update(['stage' => 'slicing']);
            ['profiles' => $profiles, 'overrides' => $overrides, 'profile' => $profile, 'layer' => $layer, 'infill' => $infill, 'quality' => $quality] = $setup;
            // the piece was thinned above or is fine enough: the slicer takes the file as it is, a plate of copies is never thinned as a whole
            $base = $setup['params']->withoutThinning();
            // a tool changer prints from the tool its spool sits in and changes tools at the heights the design asks for: the
            // plate goes to the slicer as a project that names them (docs/AM.md §12j); every other machine takes the mesh
            $toolPlan = $printer->isToolChanger() ? self::toolPlan($order) : null;
            [$params, $toolFilaments] = $toolPlan ? $this->toolChangerParams($printer, $disk->path($sliceRel), $disk->path($order->dir().'/plate.3mf'), $base, $setup['profile'], $bed, $toolPlan, (float) $mesh->volumeMm3, (float) $layer, $clock) : [$base, []];
            // several copies on any other machine: the piece once in a project, placed per copy, so the slicer slices it once and
            // prints the instances (docs/AL.md §11.10); the glued plate stays beside it for the size, the preview and the record.
            // A plate of several pieces gets the slicer more time (sliceTimeout)
            $project = $toolPlan ? null : $this->instanceProject($order, $disk, $pieceRel, $piece, $mesh, $lay, $bed, $clock);
            $params = $params->withTimeout(self::sliceTimeout($perPlate));
            [$result, $instances] = $this->sliceCopies($slicer, $disk->path($sliceRel), $params, $project, $clock, 'slice');
            $clock->merge($result->timings);
            $postStart = hrtime(true);
            if ($this->overtaken($order)) {
                // the choices changed while the slicer ran: this G-code is for nobody, the next run writes its own
                $clock->note('overtaken', 'slice');
                if ($result->gcodePath) {
                    @unlink($result->gcodePath);
                }

                return;
            }
            if (! $result->gcodePath || ! is_file($result->gcodePath)) {
                throw new \RuntimeException('Slicer returned no G-code.');
            }
            $gcodeRel = $order->dir().'/print.gcode';
            File::move($result->gcodePath, $disk->path($gcodeRel));
            if ($order->isTest() && is_array($order->test_params['temps'] ?? null)) {
                // temperature tower: one temperature per floor, written after the slice
                $tower = TowerGcode::apply((string) File::get($disk->path($gcodeRel)), (float) ($order->test_params['floor_mm'] ?? TestPrintService::FLOOR_MM), $order->test_params['temps']);
                File::put($disk->path($gcodeRel), $tower['gcode']);
                $order->test_params = ['floors_set' => $tower['floors_set']] + $order->test_params;
            }
            // the supports the slicer built, for the customer's preview (a re-slice without them drops the old file)
            $supportsBin = $disk->path($order->dir().'/supports.bin');
            if (! $result->supportsUsed || ! SupportLines::extract($disk->path($gcodeRel), $supportsBin)) {
                @unlink($supportsBin);
            }
            // the last plate of a multi-plate order holds fewer pieces: its own layout and G-code
            $restRel = null;
            $restResult = null;
            $restInstances = null;
            if ($rest !== null) {
                $restStl = $disk->path($order->dir().'/rest.stl');
                $restLay = PlateLayout::placements($piece, $rest, $usable, $gap) ?? throw new \RuntimeException('Rest plate layout failed.');
                $restMesh = PlateLayout::replicate($piece, $restStl, $rest, $usable, $gap) ?? throw new \RuntimeException('Rest plate layout failed.');
                $clock->add('post', (hrtime(true) - $postStart) / 1e9);
                $restParams = $toolPlan ? $this->toolChangerParams($printer, $restStl, $disk->path($order->dir().'/rest.3mf'), $base, $setup['profile'], $bed, $toolPlan, (float) $piece->volumeMm3 * $rest, (float) $layer, $clock)[0] : $params;
                $restProject = $toolPlan ? null : $this->instanceProject($order, $disk, $pieceRel, $piece, $restMesh, $restLay, $bed, $clock, 'rest.3mf');
                [$restResult, $restInstances] = $this->sliceCopies($slicer, $restStl, $restParams->withTimeout(self::sliceTimeout($rest)), $restProject, $clock, 'rest_slice');
                $clock->merge($restResult->timings, 'rest_');
                $postStart = hrtime(true);
                if (! $restResult->gcodePath || ! is_file($restResult->gcodePath)) {
                    throw new \RuntimeException('Slicer returned no G-code for the last plate.');
                }
                $restRel = $order->dir().'/rest.gcode';
                File::move($restResult->gcodePath, $disk->path($restRel));
            }
            $fullPlates = $rest === null ? $plates : $plates - 1;
            $sum = fn (float $full, ?float $last) => $full * $fullPlates + ($last ?? 0.0);

            // the head parks for the time-lapse after every layer on this machine: that time is printing time too
            $timelapse = $printer->timelapseFor($order);
            $parkingOf = fn (?string $rel) => $timelapse && $rel ? TimelapseGcode::extraMinutesForFile($disk->path($rel), $timelapse, (float) $printer->bed_x, (float) $printer->bed_y) : 0;
            $parking = (int) round($sum($parkingOf($gcodeRel), $restRel ? $parkingOf($restRel) : null));

            $order->fill([
                'gcode_path' => $gcodeRel,
                'gcode_sha256' => hash_file('sha256', $disk->path($gcodeRel)),
                'rest_gcode_path' => $restRel,
                'slice_params' => [
                    'engine' => $slicer->name(), 'printer' => ['id' => $printer->id, 'key' => $printer->key, 'model' => $printer->model],
                    'material' => $order->material->code, 'quality' => $quality, 'layer_mm' => $layer, 'strength' => $order->strength, 'copies' => $order->copies,
                    'infill_percent' => $infill, 'unit_scale' => $order->unit_scale, 'scale' => (float) $order->scale, 'profiles' => $profiles, 'overrides' => $toolPlan ? $params->overrides : $overrides,
                    'profile_layers' => $profile->layers, 'profile_fingerprint' => $profile->sliceFingerprint(),
                    'profile_hashes' => $this->profileHashes($profiles), 'preparer' => $order->isTest() ? 'php-stl' : $preparer->name(), 'sliced_at' => now()->toIso8601String(),
                    'timelapse_minutes' => $parking,
                    // a tool changer: the tool and the tool changes this file was sliced for (the payment slices again when they differ)
                    'tool' => $toolPlan, 'filaments' => $toolPlan ? self::filamentRecord($toolFilaments) : null,
                ],
                // instances: true = the copies went to the slicer as instances of one object, false = glued into one mesh after a path
                // conflict, null = one piece, a tool changer's project or a piece too fine for the project (docs/AL.md §11.10)
                'slice_result' => $result->toArray() + ['plates' => $plates, 'plate_copies' => $perPlate, 'rest_copies' => $rest, 'instances' => $instances, 'gap_mm' => $gap,
                    'rest' => $restResult ? $restResult->toArray() + ['instances' => $restInstances] : null],
                // the whole order: every full plate, plus the last one when it differs
                'est_minutes' => (int) round($sum($result->minutes, $restResult?->minutes)) + $parking,
                'est_grams' => round($sum($result->grams, $restResult?->grams), 1),
                'est_meters' => round($sum((float) ($result->meters ?? 0), $restResult?->meters), 2),
                'supports_used' => $result->supportsUsed,
            ])->save();

            $clock->add('post', (hrtime(true) - $postStart) / 1e9)->note('print_minutes', $order->est_minutes);

            // ── 3. price ───────────────────────────────────────────────────────
            $this->price($order, $printer, $settings, $orders, $flow, $clock);
        } catch (SupersededException $e) {
            // the order asked for another slice and this run's process was stopped: no verdict, the next run has the order
            $clock->note('overtaken', 'stopped');
        } catch (\Throwable $e) {
            Log::warning('PrepareFarmOrder failed', ['order' => $order->id, 'error' => $e->getMessage()]);
            $clock->note('failed', true);
            $this->fail($flow, $order, 'slicing_failed', mb_substr($e->getMessage(), 0, 1000));
        } finally {
            RunGuard::clear();
            @unlink($disk->path($order->dir().'/print.thin.stl'));   // the thinned piece served its slices; its thinning stays remembered (MeshDecimator)
            // only the column: whatever the steps above saved stays as it is. An overtaken run leaves the timings to the
            // run that replaced it (which may have written its own already).
            $clock->note('finished', round(microtime(true), 1));
            $clock->add('total', (float) $clock->toArray()['finished'] - (float) $clock->toArray()['started']);
            if (! $this->overtaken($order)) {
                FarmOrder::whereKey($order->id)->update(['timings' => json_encode($clock->toArray())]);
            }
        }
    }

    /**
     * What the slicer is given: the printer's profiles, the quality's layer, the strength's infill, the customer's and
     * the admin's overrides, supports as the model wants them.
     *
     * @return array{params: SliceParams, profiles: array, overrides: array, profile: PrintProfile, layer: float, infill: int, quality: string}
     */
    private function sliceSetup(FarmOrder $order, FarmSettings $settings): array
    {
        $printer = $order->printer;
        $quality = $order->quality;
        // the quality settings are written for a 0.4 nozzle; a finer nozzle prints the whole ladder finer
        $layer = $printer->layerFor($settings->layerFor($quality));
        // the customer's own numbers (the "advanced" settings) win over the presets; the admin's overrides over everything
        $custom = PrintSettings::of($order);
        $infill = $custom['infill'] ?? $settings->infillFor($order->strength);
        // the kind, then the kind on this machine, then the spool: the most specific layer wins (PrintProfile);
        // temperatures are written into the G-code copy for the chosen spool later
        $profile = PrintProfile::forOrder($order);
        $profiles = [
            'machine' => $printer->machine_profile,
            'process' => $printer->process_profiles[$quality] ?? null,
            'filament' => $profile->filamentProfile,
        ];
        // what the design asks of the process (a medal irons its top, ModelFile::processHints): below the farm's own tuning –
        // the layer, the kind's and the spool's rows, the printer's overrides – and below the customer's and the admin's choices
        $hints = $order->modelFile?->processHints() ?? [];
        $overrides = [
            'machine' => (array) $printer->machine_overrides,
            // the farm's support threshold is above the slicer profile and below everything of the farm's own (docs/AL.md §17)
            'process' => ['layer_height' => (string) $layer] + $profile->process + (array) $printer->process_overrides + $hints + ['support_threshold_angle' => (string) config('farm.support_threshold_angle', 25)],
            'filament' => $profile->filament,
        ];
        if ($order->isTest()) {
            // the bridges and overhangs of a test object are the test: never prop them up
            $overrides['process']['enable_support'] = '0';
            // a test object stands on its own base plate: a brim only glues it harder to the build plate, and a
            // fine-nozzle test bent while it was prised off (Kobra S1 #2, 26 Sep 2026)
            $overrides['process']['brim_type'] = 'no_brim';
        }
        // the customer knows the model (made to print in place, supports would only weld its joints together), or
        // the model comes from one of our tools and was built to print without them (a cap, a holder, a box)
        $builtWithout = ($order->modelFile?->printHints()['supports'] ?? null) === false;
        $noSupports = $order->supports === 'off' || ($order->supports !== 'on' && $builtWithout);
        if ($noSupports) {
            $overrides['process']['enable_support'] = '0';
        }
        $overrides['process'] = PrintSettings::process($custom) + $overrides['process'];
        if ((isset($custom['ironing']) || ($hints['ironing_type'] ?? 'no ironing') !== 'no ironing') && abs((float) $printer->nozzle_mm - 0.4) > 0.001) {
            // the profiles write the ironing pass for a 0.4 nozzle; a finer or coarser one irons at its own spacing
            // unless the kind's row on this machine says otherwise (TestPrintService::ironingFor, as the test prints do)
            $fine = TestPrintService::ironingFor((float) $printer->nozzle_mm);
            $overrides['process'] = $overrides['process'] + $fine;
            $overrides['process']['ironing_spacing'] = $profile->process['ironing_spacing'] ?? $fine['ironing_spacing'];
        }
        $overrides['process'] = PrintSettings::adminOverrides($order) + $overrides['process'];
        $params = (new SliceParams(materialCode: $order->material->code, quality: $quality, infillPercent: $infill, supports: $order->isTest() || $noSupports ? false : null, treeSupports: true))
            ->withFarmProfile($profiles, $overrides);
        // trial prints before the whole farm moves to another slicer: an admin's orders take it first (docs/AL.md §22)
        if (($trial = config('engines.orca.slicer_trial')) && $order->user?->isAdmin()) {
            $params = $params->withSlicer((string) $trial);
        }
        // supports are "automatic" when nobody chose: the printer's settings switch them on, the customer left the choice
        // alone and neither the customer's own numbers nor the admin's overrides name them
        $supports_auto = ! $order->isTest() && ! $noSupports && $order->supports !== 'on' && ($overrides['process']['enable_support'] ?? null) === '1'
            && ! array_key_exists('enable_support', PrintSettings::process($custom)) && ! array_key_exists('enable_support', PrintSettings::adminOverrides($order));

        return compact('params', 'profiles', 'overrides', 'profile', 'layer', 'infill', 'quality', 'supports_auto');
    }

    /**
     * The same slice with the supports left out when the piece's shape needs none (SupportsCheck, docs/AL.md §22): the
     * slicer is not asked to look for overhangs and still adds supports by itself if it cannot slice without. When the
     * check is unsure, off (as shipped: the measurement of 12 Nov 2026 found no time to save), or the piece is not a
     * closed mesh, nothing changes. What was asked and answered goes to the timings (`supports_check_s`,
     * `supports_needed`, `supports_check`).
     */
    private function withoutNeedlessSupports(SliceParams $params, PreparedMesh $piece, float $layer, Stopwatch $clock): SliceParams
    {
        $check = app(SupportsCheck::class);
        if (! $check->enabled()) {
            return $params;
        }
        if (! $piece->watertight) {
            $clock->note('supports_needed', true)->note('supports_check', 'open');

            return $params;
        }
        $threshold = (float) ($params->overrides['process']['support_threshold_angle'] ?? config('farm.support_threshold_angle', 25));
        $r = $check->check($piece->path, $threshold, $layer > 0 ? $layer : 0.2);
        $clock->note('supports_check_s', $r['seconds'])->note('supports_needed', $r['needed'])->note('supports_check', $r['reason']);

        return $r['needed'] ? $params : $params->withSupportsLeftOut();
    }

    /**
     * The piece thinned for the slicer when it has more triangles than config engines.mesh.decimate_above (MeshDecimator,
     * docs/AL.md §10.11 and §11.9): the same piece with the thinned file as its path and its new triangle count, or the
     * piece itself when it is fine enough or could not be thinned. Done once per run, before the piece is copied onto a
     * plate, so the plate, the last plate and the slice after a change of colour all take it (the thinning is remembered
     * by the file's bytes). The numbers go to the timings the way the slicer's own thinning writes them.
     *
     * @param  int  $copies  how many pieces will be sliced from it: the thinning's time budget grows with them
     * @param  string  $suffix  for the keys of an order by parts (`_1`, `_2`…)
     */
    private function thinPiece(PreparedMesh $piece, string $thinPath, float $layer, Stopwatch $clock, int $copies, string $suffix = ''): PreparedMesh
    {
        $decimator = app(MeshDecimator::class);
        if (! $decimator->wants($piece->path)) {
            return $piece;
        }
        $r = $clock->measure('decimate', fn () => $decimator->decimate($piece->path, $thinPath, $layer > 0 ? $layer : null, $copies));
        $clock->note('triangles_in'.$suffix, $r['before'])->note('triangles_sliced'.$suffix, $r['after'] ?? $r['before'])->note('decimate_limits'.$suffix, $r['limits'] ?? null)->note('decimate_budget_s'.$suffix, $r['budget_s'] ?? null);
        if (! empty($r['cached'])) {
            $clock->note('decimate_cached'.$suffix, true);
        }
        if (! $r['applied']) {
            $clock->note('decimate_skipped'.$suffix, $r['reason'] ?? 'unknown');

            return $piece;
        }
        $clock->note('decimate_deviation_mm'.$suffix, $r['deviation_mm'])->note('decimate_peak_mm'.$suffix, $r['peak_mm'] ?? null);

        return new PreparedMesh(
            path: $thinPath,
            watertight: $piece->watertight,
            wasWatertight: $piece->wasWatertight,
            repaired: $piece->repaired,
            openEdges: $piece->openEdges,
            bbox: $piece->bbox,
            volumeMm3: $piece->volumeMm3,
            areaMm2: $piece->areaMm2,
            triangles: (int) $r['after'],
            shells: $piece->shells,
            orientation: $piece->orientation,
            engine: $piece->engine,
            footprint: $piece->footprint,
            holes: $piece->holes,
        );
    }

    /** The sliced order gets its price and moves on: to the customer (sliced), or straight on when nobody pays or has paid already. */
    private function price(FarmOrder $order, $printer, FarmSettings $settings, OrderService $orders, OrderFlow $flow, Stopwatch $clock): void
    {
        if ($this->overtaken($order)) {
            $clock->note('overtaken', 'price');   // sliced for choices the customer has changed meanwhile: the next run prices its own

            return;
        }
        if ($order->isFree()) {
            // the farm's own print (test or YouTube showcase): nobody pays, it goes straight to the queue
            $order->fill(['stage' => null])->save();
            $flow->move($order, FarmOrder::STATUS_SLICED, 'system');
            $flow->move($order, FarmOrder::STATUS_QUEUED, 'system');

            return;
        }
        if ($order->paid_at !== null) {
            // re-sliced for the chosen spool after payment: the price stays what the customer paid
            $order->fill(['stage' => null])->save();
            $flow->move($order, FarmOrder::STATUS_SLICED, 'system');
            $flow->move($order, FarmOrder::STATUS_PAID, 'system');
            if (! $settings->get('require_approval')) {
                $flow->move($order, FarmOrder::STATUS_QUEUED, 'system');
            }

            return;
        }
        $price = $clock->measure('price', fn () => $orders->priceFor($order, $printer));
        $order->fill(['price' => $price, 'price_total' => $price['total'], 'royalty_czk' => $price['royalty_unit'] ?? null, 'currency' => $price['currency'], 'stage' => null])->save();
        $flow->move($order, FarmOrder::STATUS_SLICED, 'system');
    }

    /**
     * An order printed by parts: every separately printed part of the design becomes a plate of its own (the copies of
     * that part laid out on it), sliced on its own, printed from the spool chosen for it. The order's figures are the
     * sums; its check, orientation and preview are those of the first plate.
     */
    private function prepareParts(FarmOrder $order, PrintPreparer $preparer, Slicer $slicer, FarmSettings $settings, OrderService $orders, OrderFlow $flow, Stopwatch $clock, $disk, Dimensions $bed): void
    {
        $file = $order->modelFile;
        $printer = $order->printer;
        $skeleton = $order->partPlates();
        if (count($skeleton) < 2) {
            throw new \RuntimeException('An order by parts needs at least two parts.');
        }
        $margin = 2 * (float) $settings->get('bed_margin_mm');
        $usable = new Dimensions($bed->x - $margin, $bed->y - $margin, $bed->z);
        $setup = $this->sliceSetup($order, $settings);
        $timelapse = $printer->timelapseFor($order);
        $scale = $order->unit_scale * (float) ($order->scale ?: 1);
        File::ensureDirectoryExists($disk->path($order->dir()));
        $plates = [];
        $first = null;
        $minutes = $grams = $meters = $parking = 0.0;
        $supports = false;
        $maxCopies = null;
        foreach ($skeleton as $i => $plate) {
            $n = $i + 1;
            $order->update(['stage' => 'checking']);
            $src = $orders->partStl($file, (string) $plate['part']) ?? throw new \RuntimeException('The part '.$plate['part'].' of the design cannot be built.');
            $stlRel = $order->dir().'/part-'.$n.'.stl';
            @unlink($disk->path($stlRel).'.stage');
            try {
                $mesh = $clock->measure('prepare', fn () => $preparer->prepare($src['path'], $disk->path($stlRel), $scale, $bed, $file->builtForPrinting()));
            } finally {
                if ($src['temp']) {
                    @unlink($src['path']);
                }
            }
            @unlink($disk->path($stlRel).'.stage');
            // the part thinned once before its copies are laid out (docs/AL.md §11.9); the part as prepared stays for the preview and the record
            $piece = $this->thinPiece($mesh, $disk->path($stlRel).'.thin', (float) ($setup['params']->overrides['process']['layer_height'] ?? $setup['layer']), $clock, max(1, (int) $order->copies), '_'.$n);
            $sliceRel = $piece === $mesh ? $stlRel : $stlRel.'.thin';
            $capacity = PlateLayout::capacityOf($piece, $usable);
            $maxCopies = $maxCopies === null ? $capacity['max'] : min($maxCopies, $capacity['max']);
            if ($order->copies > 1 && $capacity['max'] >= $order->copies) {
                $plateRel = $order->dir().'/part-'.$n.'-plate.stl';
                $mesh = $clock->measure('layout', fn () => PlateLayout::replicate($piece, $disk->path($plateRel), $order->copies, $usable)) ?? throw new \RuntimeException('Plate layout failed although the capacity allowed it.');
                $stlRel = $plateRel;
                $sliceRel = $plateRel;
            }
            $verdict = ModelValidator::judge($mesh, $bed, ['min_model_mm' => (float) $settings->get('min_model_mm'), 'bed_margin_mm' => (float) $settings->get('bed_margin_mm')]);
            if ($file->origin !== 'tool' && PaintedColors::printed($file) !== []) {
                $verdict = self::withoutWarnings($verdict, self::PAINTED_WARNINGS);
            }
            if ($verdict['ok'] && $order->copies > 1 && $capacity['max'] < $order->copies) {
                // the copies of this part do not fit one plate: an order by parts prints each part on one plate
                $verdict = ['ok' => false, 'errors' => [['code' => 'copies_fit', 'data' => ['part' => $plate['part'], 'max' => $capacity['max']]]], 'warnings' => $verdict['warnings'] ?? []] + $verdict;
            }
            if ($first === null) {
                $raw = Dimensions::fromArray((array) $file->bbox);
                $order->check = $verdict + ['mesh' => $mesh->toArray(), 'unit_guess' => ModelValidator::guessUnit($raw, $bed), 'raw_dims' => $raw->toArray(), 'piece_dims' => $piece->bbox->toArray(), 'max_copies' => $capacity['max']];
                $order->orientation = $mesh->orientation;
                $order->print_stl_path = $stlRel;
                $order->save();
            }
            if (! $verdict['ok']) {
                $order->check = $verdict + ['part' => $plate['part']] + (array) $order->check;
                $order->save();
                $this->fail($flow, $order, $verdict['errors'][0]['code'], (string) $plate['part']);

                return;
            }
            $order->update(['stage' => 'slicing']);
            $partParams = $setup['params']->withoutThinning();
            if ($printer->isToolChanger()) {
                // the plate of this part prints from the tool its spool sits in (docs/AM.md §12j)
                $spool = ($plate['slot_id'] ?? null) ? FarmPrinterSlot::find((int) $plate['slot_id']) : null;
                $position = $spool && (int) $spool->farm_printer_id === (int) $printer->id ? (int) $spool->slot : (int) ($order->slot?->slot ?? 0);
                $partParams = $this->toolChangerParams($printer, $disk->path($sliceRel), $disk->path($order->dir().'/part-'.$n.'.3mf'), $partParams, $setup['profile'], $bed, ['position' => $position, 'changes' => []], (float) $mesh->volumeMm3, (float) $setup['layer'], $clock)[0];
            }
            $result = $clock->measure('slice', fn () => $slicer->slice($disk->path($sliceRel), $partParams));
            @unlink($disk->path($order->dir().'/part-'.$n.'.stl.thin'));
            $clock->merge($result->timings, 'p'.$n.'_');
            if (! $result->gcodePath || ! is_file($result->gcodePath)) {
                throw new \RuntimeException('Slicer returned no G-code for the part '.$plate['part'].'.');
            }
            $gcodeRel = $order->dir().'/part-'.$n.'.gcode';
            File::move($result->gcodePath, $disk->path($gcodeRel));
            $park = $timelapse ? TimelapseGcode::extraMinutesForFile($disk->path($gcodeRel), $timelapse, (float) $printer->bed_x, (float) $printer->bed_y) : 0;
            $parking += $park;
            $minutes += $result->minutes;
            $grams += $result->grams;
            $meters += (float) ($result->meters ?? 0);
            $supports = $supports || $result->supportsUsed;
            $plates[] = ['part' => (string) $plate['part'], 'slot_id' => (int) ($plate['slot_id'] ?? 0), 'color_id' => (int) ($plate['color_id'] ?? 0), 'copies' => (int) $order->copies,
                'stl_path' => $stlRel, 'gcode_path' => $gcodeRel, 'sha256' => hash_file('sha256', $disk->path($gcodeRel)),
                'minutes' => (int) round($result->minutes), 'grams' => round($result->grams, 1), 'meters' => round((float) ($result->meters ?? 0), 2),
                'dims' => $mesh->bbox->toArray(), 'piece_dims' => $piece->bbox->toArray(), 'supports' => (bool) $result->supportsUsed];
            $first ??= $result;
        }
        $order->check = ['max_copies' => $maxCopies] + (array) $order->check;
        $order->fill([
            'plates' => count($plates), 'plates_done' => 0, 'plate_copies' => (int) $order->copies, 'rest_copies' => null, 'rest_gcode_path' => null,
            'part_plates' => $plates,
            'gcode_path' => $plates[0]['gcode_path'], 'gcode_sha256' => $plates[0]['sha256'],
            'slice_params' => [
                'engine' => $slicer->name(), 'printer' => ['id' => $printer->id, 'key' => $printer->key, 'model' => $printer->model],
                'material' => $order->material->code, 'quality' => $setup['quality'], 'layer_mm' => $setup['layer'], 'strength' => $order->strength, 'copies' => $order->copies,
                'infill_percent' => $setup['infill'], 'unit_scale' => $order->unit_scale, 'scale' => (float) $order->scale, 'profiles' => $setup['profiles'], 'overrides' => $setup['overrides'],
                'profile_layers' => $setup['profile']->layers, 'profile_fingerprint' => $setup['profile']->sliceFingerprint(),
                'profile_hashes' => $this->profileHashes($setup['profiles']), 'preparer' => $preparer->name(), 'sliced_at' => now()->toIso8601String(),
                'timelapse_minutes' => (int) round($parking), 'by_parts' => true,
            ],
            'slice_result' => $first->toArray() + ['plates' => count($plates), 'parts' => array_map(fn ($p) => ['part' => $p['part'], 'minutes' => $p['minutes'], 'grams' => $p['grams']], $plates)],
            'est_minutes' => (int) round($minutes) + (int) round($parking),
            'est_grams' => round($grams, 1),
            'est_meters' => round($meters, 2),
            'supports_used' => $supports,
        ])->save();
        $clock->note('print_minutes', $order->est_minutes)->note('parts', count($plates));
        $this->price($order, $printer, $settings, $orders, $flow, $clock);
    }

    /**
     * The warnings an order of a painted 3MF leaves out (docs/AL.md §14): its parts are our own cut of the file, the
     * inlays come out in islands and the preparer closes their seams, so "26 separate bodies" and "we repaired the
     * surface" spoke of the cut, not of the customer's model (the turtle on production, 28 Oct 2026).
     */
    private const PAINTED_WARNINGS = ['multiple_shells', 'repaired'];

    /**
     * @param  array{warnings?: array<int, array{code: string, data: array}>}  $verdict
     * @param  list<string>  $codes
     */
    private static function withoutWarnings(array $verdict, array $codes): array
    {
        $verdict['warnings'] = array_values(array_filter((array) ($verdict['warnings'] ?? []), fn ($w) => ! in_array($w['code'] ?? null, $codes, true)));

        return $verdict;
    }

    /**
     * How a painted 3MF's copies go on plates that change filaments: `per` pieces on every full plate (the grid of
     * PlateLayout::towerGrid), `plates` plates, the last one holding `rest` pieces when that is fewer. A layout is the
     * set's size [x, y], the grid [cols, rows] and the offsets of its pieces from the first one (row by row). Null when
     * not even one piece leaves the tower its room.
     *
     * @param  array{0: float, 1: float, 2: float}  $size  one piece as it lies
     * @return array{max: int, per: int, plates: int, rest: ?int, full: array, last: ?array}|null
     */
    private static function copiesPlan(array $size, Dimensions $bed, float $edge, int $filaments, int $copies, bool $tower = true): ?array
    {
        $piece = new Dimensions($size[0], $size[1], $size[2]);
        $max = PlateLayout::towerGrid($piece, $bed, $edge, $filaments, tower: $tower)['max'];
        if ($max < 1) {
            return null;
        }
        $layout = function (int $n) use ($piece, $bed, $edge, $filaments, $tower): array {
            $g = PlateLayout::towerGrid($piece, $bed, $edge, $filaments, $n, tower: $tower);
            $offsets = [];
            for ($k = 0; $k < $n; $k++) {
                $offsets[] = [round(($k % $g['cols']) * ($piece->x + PlateLayout::GAP_MM), 3), round(intdiv($k, $g['cols']) * ($piece->y + PlateLayout::GAP_MM), 3), 0.0];
            }

            return ['set' => [$g['cols'] * ($piece->x + PlateLayout::GAP_MM) - PlateLayout::GAP_MM, $g['rows'] * ($piece->y + PlateLayout::GAP_MM) - PlateLayout::GAP_MM],
                'grid' => [$g['cols'], $g['rows']], 'offsets' => $offsets];
        };
        $per = min($copies, $max);
        $plates = (int) ceil($copies / $per);
        $rest = $copies - $per * ($plates - 1);

        return ['max' => $max, 'per' => $per, 'plates' => $plates, 'rest' => $rest === $per ? null : $rest, 'full' => $layout($per), 'last' => $rest === $per ? null : $layout($rest)];
    }

    /** The process settings of a plate that changes filaments: the prime tower on, the purge into the infill where it can go. */
    public const ONE_PLATE_PROCESS = ['enable_prime_tower' => '1', 'prime_tower_width' => '25', 'flush_into_infill' => '1', 'flush_into_objects' => '0', 'flush_into_support' => '1'];

    /**
     * The same on a tool changer (Prusa INDX): no prime tower, the machine purges every tool it picks on its cleaning
     * station (the change_filament_gcode of the machine profile, `G12`; docs/AM.md §12d, §12j). Two cubes: 2.2 g and
     * 18 min without the tower against 4.5 g and 22.5 min with it (Orca 2.5, 4 Nov 2026).
     */
    public const TOOL_CHANGER_PROCESS = ['enable_prime_tower' => '0'];

    /**
     * The prime tower's room: as wide as ONE_PLATE_PROCESS makes it, the profiles' 5 mm brim, a depth that grows with
     * the changes a layer can hold (Orca 2.4: 9 mm for two filaments of the medal, 19 Oct 2026), a gap to the parts.
     */
    public const TOWER = ['width' => 25.0, 'brim' => 5.0, 'depth_per_filament' => 12.0, 'gap' => 3.0];

    /**
     * Where the prime tower goes on a plate whose parts stay where the tool laid them (`size` = their bounds together,
     * centred on the bed): the free band below them when it is deep enough for the tower, else the band left of them
     * when it is wide enough for it, else the parts are pushed off the band that can be made deep enough, and the
     * tower goes there. Null when no band can hold it: Orca puts the tower where its profile says (the top left,
     * 15/220) and stops when it lands on a part (the medal, 19 Oct 2026: "gcode path conflicts between WipeTower
     * and body", -101), so a plate without room for the tower prints by parts.
     *
     * @param  array{0: float, 1: float, 2: float}  $size
     * @return ?array{x: float, y: float, shift: array{0: float, 1: float}}
     */
    public static function towerSpot(array $size, Dimensions $bed, float $margin, int $filaments): ?array
    {
        $t = self::TOWER;
        $depth = $t['depth_per_filament'] * max(1, $filaments - 1);
        $needY = $depth + 2 * $t['brim'] + $t['gap'];     // below the parts: the tower's depth runs across the band
        $needX = $t['width'] + 2 * $t['brim'] + $t['gap'];   // left of the parts: its width does
        $bandY = ($bed->y - $size[1]) / 2 - $margin;     // the free room on either side once the parts are centred
        $bandX = ($bed->x - $size[0]) / 2 - $margin;
        $below = ['x' => round($bed->x / 2 - $t['width'] / 2, 2), 'y' => round($margin + $t['brim'], 2)];
        $left = ['x' => round($margin + $t['brim'], 2), 'y' => round($bed->y / 2 - $depth / 2, 2)];
        if ($bandY >= $needY) {
            return $below + ['shift' => [0.0, 0.0]];
        }
        if ($bandX >= $needX) {
            return $left + ['shift' => [0.0, 0.0]];
        }
        if (2 * $bandY >= $needY) {
            return $below + ['shift' => [0.0, round($needY - $bandY, 2)]];   // pushed up: the band below grows to what the tower needs
        }
        if (2 * $bandX >= $needX) {
            return $left + ['shift' => [round($needX - $bandX, 2), 0.0]];
        }

        return null;
    }

    /**
     * An order by parts whose parts print together: one plate, one G-code, a filament per spool (`T0`…`T<n-1>` in the
     * file, the prime tower between them), the machine changing spools as it goes. Possible when the machine is run
     * by an agent (a changer; an operator feeds one spool), the spools are no more than the machine has positions
     * (FarmPrinter::colorLimit) and all of this machine, the parts as the tool laid them fit the plate, and the slicer
     * manages it. Several copies share the plate (docs/AL.md §10.13): every part is an object of the project placed
     * once per copy (ThreeMfWriter instances, the parts of one piece sharing the offset), as many pieces as fit a plate
     * beside the tower's band, more on full plates one after another with the rest on the last one, like a painted
     * file's copies (§14.8). Returns false, with nothing of the order changed, when it is not: the caller prints the
     * parts one plate each.
     */
    private function prepareOnePlate(FarmOrder $order, PrintPreparer $preparer, Slicer $slicer, FarmSettings $settings, OrderService $orders, OrderFlow $flow, Stopwatch $clock, $disk, Dimensions $bed): bool
    {
        $file = $order->modelFile;
        $printer = $order->printer;
        $skeleton = $order->partPlates();
        // why it is not one plate, for the record (timings) and for the failure of a design that needs one
        $skip = fn (string $why) => (bool) $clock->note('one_plate_skipped', $why) && false;
        if (count($skeleton) < 2) {
            return $skip('parts');
        }
        if (! $printer->changesSpools()) {
            return $skip('manual');   // a person feeds one spool, or the changer is off the machine (docs/AL.md §24)
        }
        // a painted 3MF goes to the slicer as itself with its colours mapped (docs/AL.md §14.8), a tool's design as its parts
        $paintedFile = PaintedColors::painted($file);
        $slotIds = array_values(array_unique(array_filter(array_map(fn ($p) => (int) ($p['slot_id'] ?? 0), $skeleton))));
        $spools = $slotIds ? FarmPrinterSlot::with('color.material')->whereIn('id', $slotIds)->get()->keyBy('id') : collect();
        if ($spools->contains(fn (FarmPrinterSlot $s) => (int) $s->farm_printer_id !== (int) $printer->id)) {
            return $skip('two_machines');   // spools of two machines: no one plate prints that
        }
        // a filament per spool (parts of one spool share it); a part without a spool yet (no colour chosen) one of its own
        $index = [];
        $filamentOf = [];
        foreach ($skeleton as $i => $p) {
            $key = (int) ($p['slot_id'] ?? 0) ?: 'part'.$i;
            $index[$key] ??= count($index);
            $filamentOf[$i] = $index[$key];
        }
        $toolChanger = $printer->isToolChanger();
        if ($toolChanger) {
            // a tool changer prints a spool from the tool it sits in, the firmware maps T<n> to tool n one to one (docs/AM.md
            // §12d, §12j): filament n = position n, so a part's extruder is its spool's position + 1 whatever the order of the
            // parts; a part without a spool goes with the first colour's tool
            $positionOf = [];
            foreach ($index as $key => $n) {
                $positionOf[$n] = is_int($key) && ($s = $spools->get($key)) ? (int) $s->slot : (int) ($order->slot?->slot ?? 0);
            }
            $filamentOf = array_map(fn ($n) => $positionOf[$n], $filamentOf);
            $index = array_map(fn ($n) => $positionOf[$n], $index);
        }
        $nFilaments = count(array_unique($filamentOf));
        if ($nFilaments < 2 || $nFilaments > $printer->colorLimit() || $nFilaments > max(1, $printer->slots()->count())) {
            return $skip($nFilaments < 2 ? 'one_spool' : 'filaments');
        }

        $margin = 2 * (float) $settings->get('bed_margin_mm');
        $usable = new Dimensions($bed->x - $margin, $bed->y - $margin, $bed->z);
        $scale = $order->unit_scale * (float) ($order->scale ?: 1);
        File::ensureDirectoryExists($disk->path($order->dir()));
        $order->update(['stage' => 'checking']);
        $paths = [];
        $lo = [INF, INF, INF];
        $hi = [-INF, -INF, -INF];
        foreach ($skeleton as $i => $p) {
            $src = $orders->partStl($file, (string) $p['part']) ?? throw new \RuntimeException('The part '.$p['part'].' of the design cannot be built.');
            $rel = $order->dir().'/plate-part-'.($i + 1).'.stl';
            try {
                StlFile::scale($src['path'], $disk->path($rel).'.raw', $scale);
            } finally {
                if ($src['temp']) {
                    @unlink($src['path']);
                }
            }
            [$l, $h] = StlFile::bounds($disk->path($rel).'.raw');
            for ($k = 0; $k < 3; $k++) {
                $lo[$k] = min($lo[$k], $l[$k]);
                $hi[$k] = max($hi[$k], $h[$k]);
            }
            $paths[$i] = $rel;
        }
        $size = [$hi[0] - $lo[0], $hi[1] - $lo[1], $hi[2] - $lo[2]];
        // several copies: as many full plates as they need, the last one with the rest, every plate a grid that leaves
        // the tower its band (PlateLayout::towerGrid, the calculator counts the plates the same way)
        $plan = (int) $order->copies > 1 && is_finite($lo[0]) ? self::copiesPlan($size, $bed, $margin / 2, $nFilaments, (int) $order->copies, ! $toolChanger) : null;
        // a design ordered once may take more copies later: the page says how many fit a plate
        $maxCopies = $plan ? $plan['max'] : (is_finite($lo[0]) ? max(1, PlateLayout::towerGrid(new Dimensions($size[0], $size[1], $size[2]), $bed, $margin / 2, $nFilaments, tower: ! $toolChanger)['max']) : 1);
        if (! is_finite($lo[0]) || ((int) $order->copies > 1 && ! $plan) || $size[0] > $usable->x || $size[1] > $usable->y || $size[2] > $usable->z) {
            foreach ($paths as $rel) {
                @unlink($disk->path($rel).'.raw');
            }

            return $skip('fit');   // the parts as designed do not fit the plate together: one plate each
        }
        // the prime tower needs a band of its own beside the parts (it must not land on one); the parts give way to it.
        // A tool changer prints without the tower (TOOL_CHANGER_PROCESS): nothing to make room for
        $set = $plan ? $plan['full']['set'] : [$size[0], $size[1]];
        $tower = $toolChanger ? ['x' => null, 'y' => null, 'shift' => [0.0, 0.0]] : self::towerSpot([$set[0], $set[1], $size[2]], $bed, $margin / 2, $nFilaments);
        if (! $tower) {
            foreach ($paths as $rel) {
                @unlink($disk->path($rel).'.raw');
            }

            return $skip('fit');   // the parts fill the plate, no room for the tower: one plate each
        }
        // every part moved by the same vector: the middle of the plate (off the tower's band), standing on it; the parts
        // keep their places against each other
        $move = [$bed->x / 2 - $lo[0] - $set[0] / 2 + $tower['shift'][0], $bed->y / 2 - $lo[1] - $set[1] / 2 + $tower['shift'][1], -$lo[2]];
        $volumes = [];
        foreach ($paths as $i => $rel) {
            StlFile::translate($disk->path($rel).'.raw', $disk->path($rel), $move);
            @unlink($disk->path($rel).'.raw');
            $volumes[$i] = StlFile::stats($disk->path($rel))->volumeMm3;
        }
        // every part checked and repaired on its own, as a plate of its own would be: where the motif sits in a pocket of
        // the body the two share faces, and the parts glued into one mesh look non-manifold to any check (the medal of
        // 19 Oct 2026: 1 371 bad edges, "3 862 bodies") although each part is watertight. The preparer sets a part at
        // the origin; it is moved back to where the plate has it, so the parts keep their places against each other.
        $rules = ['min_model_mm' => (float) $settings->get('min_model_mm'), 'bed_margin_mm' => (float) $settings->get('bed_margin_mm')];
        $raw = Dimensions::fromArray((array) $file->bbox);
        // a painted 3MF is sliced as itself (PaintedColors::project): its parts are the plate's preview and record, and an
        // open edge or two from writing the inlays as STL (the turtle's yellow, 28 Oct 2026) is no reason to refuse it
        $judge = function ($mesh) use ($bed, $rules, $paintedFile): array {
            $verdict = ModelValidator::judge($mesh, $bed, $rules);
            if ($paintedFile && ! $verdict['ok']) {
                $verdict['errors'] = array_values(array_filter($verdict['errors'], fn ($e) => ($e['code'] ?? null) !== 'not_watertight'));
                $verdict['ok'] = $verdict['errors'] === [];
            }

            return $verdict;
        };
        $meshes = [];
        foreach ($paths as $i => $rel) {
            $placed = $disk->path($rel);
            $prepared = $placed.'.prepared';
            @unlink($prepared.'.stage');
            $partMesh = $clock->measure('prepare', fn () => $preparer->prepare($placed, $prepared, 1.0, $bed, true));
            @unlink($prepared.'.stage');
            [$wasLo, $wasHi] = StlFile::bounds($placed);
            [$nowLo, $nowHi] = StlFile::bounds($prepared);
            StlFile::translate($prepared, $placed, [($wasLo[0] + $wasHi[0] - $nowLo[0] - $nowHi[0]) / 2, ($wasLo[1] + $wasHi[1] - $nowLo[1] - $nowHi[1]) / 2, $wasLo[2] - $nowLo[2]]);
            @unlink($prepared);
            $volumes[$i] = $partMesh->volumeMm3;
            $verdict = $judge($partMesh);
            if (! $verdict['ok']) {
                $order->check = $verdict + ['part' => (string) $skeleton[$i]['part'], 'mesh' => $partMesh->toArray(), 'unit_guess' => ModelValidator::guessUnit($raw, $bed), 'raw_dims' => $raw->toArray(), 'piece_dims' => $partMesh->bbox->toArray(), 'max_copies' => 1];
                $order->save();
                $this->fail($flow, $order, $verdict['errors'][0]['code'], (string) $skeleton[$i]['part']);

                return true;
            }
            $meshes[$i] = $partMesh;
        }
        // the plate's copies of every part, for the preview and the record; the slicer gets the painted file placed again, or
        // the single parts as objects placed once per copy (`$single`: the part as prepared, before its copies were glued on)
        $per = $plan ? $plan['per'] : 1;
        $single = $paths;
        if ($per > 1) {
            foreach ($paths as $i => $rel) {
                $placed = $disk->path($rel);
                copy($placed, $placed.'.one');
                $single[$i] = $rel.'.one';
                $more = [$placed];
                foreach (array_slice($plan['full']['offsets'], 1) as $k => $offset) {
                    StlFile::translate($placed, $more[] = $placed.'.copy'.($k + 1), $offset);
                }
                $clock->measure('layout', fn () => StlFile::concat($more, $placed.'.all'));
                foreach (array_slice($more, 1) as $copy) {
                    @unlink($copy);
                }
                rename($placed.'.all', $placed);
                $volumes[$i] *= $per;
            }
        }
        // the plate as a whole for the preview, the size and the record: the parts side by side, nothing merged, nothing re-checked
        $plateRel = $order->dir().'/plate.stl';
        StlFile::concat(array_map(fn ($rel) => $disk->path($rel), $paths), $disk->path($plateRel));
        $printRel = $order->dir().'/print.stl';
        copy($disk->path($plateRel), $disk->path($printRel));
        // ($lo stays the parts' raw corner: the last plate of several is moved from it the way the full plate was)
        [$plateLo, $plateHi] = StlFile::bounds($disk->path($plateRel));
        $all = collect($meshes);
        $mesh = new PreparedMesh(
            path: $disk->path($printRel),
            watertight: $all->every(fn (PreparedMesh $m) => $m->watertight === true) ? true : ($all->contains(fn (PreparedMesh $m) => $m->watertight === false) ? false : null),
            wasWatertight: $all->every(fn (PreparedMesh $m) => $m->wasWatertight),
            repaired: $all->contains(fn (PreparedMesh $m) => $m->repaired),
            openEdges: (int) $all->sum(fn (PreparedMesh $m) => $m->openEdges),
            bbox: new Dimensions($plateHi[0] - $plateLo[0], $plateHi[1] - $plateLo[1], $plateHi[2] - $plateLo[2]),
            volumeMm3: (float) $all->sum(fn (PreparedMesh $m) => $m->volumeMm3) * $per,
            areaMm2: (float) $all->sum(fn (PreparedMesh $m) => $m->areaMm2) * $per,
            triangles: (int) $all->sum(fn (PreparedMesh $m) => $m->triangles) * $per,
            shells: (int) $all->sum(fn (PreparedMesh $m) => $m->shells) * $per,
            orientation: ['method' => 'as-built', 'changed' => false, 'parts' => count($meshes)] + ($plan ? ['copies' => $per, 'grid' => $plan['full']['grid']] : []),
            engine: $all->first()->engine,
            footprint: $all->first()->footprint,
            holes: $all->every(fn (PreparedMesh $m) => $m->holes !== null) ? (int) $all->sum(fn (PreparedMesh $m) => $m->holes) : null,
        );
        // the plate is its parts side by side: several bodies are the design, not a broken mesh (the upload drops the same
        // warning for a file with parts, UploadController::describe)
        $verdict = self::withoutWarnings($judge($mesh), $paintedFile ? self::PAINTED_WARNINGS : ['multiple_shells']);
        $order->check = $verdict + ['mesh' => $mesh->toArray(), 'unit_guess' => ModelValidator::guessUnit($raw, $bed), 'raw_dims' => $raw->toArray(),
            'piece_dims' => $plan ? ['x' => round($size[0], 2), 'y' => round($size[1], 2), 'z' => round($size[2], 2)] : $mesh->bbox->toArray(), 'max_copies' => $maxCopies];
        $order->orientation = $mesh->orientation;
        $order->print_stl_path = $printRel;
        $order->save();
        if (! $verdict['ok']) {
            $this->fail($flow, $order, $verdict['errors'][0]['code']);

            return true;
        }

        // ── slice: a 3MF with an object per part, each told its filament; a filament profile per spool ────────────
        $order->update(['stage' => 'slicing']);
        $setup = $this->sliceSetup($order, $settings);
        $filaments = [];
        // the spools first: on a tool changer a part without a spool shares the first colour's tool and that spool's profile
        foreach ([true, false] as $withSpool) {
            foreach ($index as $key => $n) {
                if (is_int($key) !== $withSpool || isset($filaments[$n])) {
                    continue;
                }
                $spool = is_int($key) ? $spools->get($key) : null;
                $profile = $spool && $spool->color ? PrintProfile::for($printer, $spool->color->material, $spool->color) : $setup['profile'];
                // the spool's colour goes to the slicer: Orca wants one per filament (its colour vector must reach every
                // filament a part uses) and purges by the distance between them
                $filaments[$n] = ['profile' => $profile->filamentProfile, 'overrides' => $profile->filament, 'colour' => $spool?->color?->hex, 'volume_mm3' => 0.0, 'slot_id' => $spool?->id, 'color_id' => $spool?->farm_color_id];
            }
        }
        foreach ($filamentOf as $i => $n) {
            $filaments[$n]['volume_mm3'] += (float) $volumes[$i];
        }
        if ($toolChanger) {
            // every position up to the last tool used gets a filament (Orca addresses extruder n only among n loaded filaments):
            // the first colour's profile and colour, flagged, nothing printed from it; Orca heats, calibrates and cleans the
            // used tools alone (`is_extruder_used` in the machine's start G-code, verified on the G-code of 2.5, docs/AM.md §12j)
            $filaments = self::withPlaceholders($filaments, (int) ($order->slot?->slot ?? 0));
        }
        ksort($filaments);
        $projectRel = $order->dir().'/plate.3mf';
        // the design's ironing is for its first part (the plaque of a medal): an inlay in a pocket (the motif, the rim) ends
        // below the plaque's next layer, ironing its top would only cost minutes – its object says "no ironing" itself
        $hints = $order->modelFile?->processHints() ?? [];
        $inlay = ($hints['ironing_type'] ?? 'no ironing') !== 'no ironing' ? ['ironing_type' => 'no ironing'] : [];
        $slicePaths = $single;
        // the files of the slicer's objects alone (the single parts kept beside their glued copies, their thinned copies): gone
        // once the projects hold them – or when the plate is given up for the parts one by one
        $cleanup = function () use (&$slicePaths, $single, $paths, $disk): void {
            foreach (array_unique(array_merge($slicePaths, $single)) as $i => $rel) {
                if (! in_array($rel, $paths, true)) {
                    @unlink($disk->path($rel));
                }
            }
        };
        // a painted 3MF goes to Orca as itself, its colours mapped onto the parts' filaments (exact painting, no inlays; the
        // inlays of a painted curved surface are refused as objects of their own, docs/AL.md §14); the parts' 3MF otherwise
        $partFilament = [];
        foreach ($skeleton as $i => $p) {
            $partFilament[(string) $p['part']] = $filamentOf[$i] + 1;
        }
        $painted = $paintedFile
            && $clock->measure('repaint', fn () => app(PaintedColors::class)->project($file, $disk->path($projectRel), $scale, $move, $partFilament, $plan ? $plan['full']['offsets'] : [[0.0, 0.0, 0.0]]));
        $clock->note('one_plate_project', $painted ? 'painted' : 'parts');
        // the parts' project: an object per part, placed once per copy of the plate (every part the same offsets, so the
        // motif stays set in its body; Orca slices every object once and prints its instances)
        $objects = [];
        if (! $painted) {
            // a part of millions of triangles is thinned for the slicer alone (MeshDecimator, docs/AL.md §10.11): the placed
            // parts stay as they are for the preview and the record, the 3MF takes the thinned copies. The painted project
            // has its own mesh: thinning its parts cost 89 s for 12 turtles and gave the slicer nothing (29 Oct 2026)
            $decimator = app(MeshDecimator::class);
            foreach ($single as $i => $rel) {
                if ($decimator->wants($disk->path($rel))) {
                    $r = $clock->measure('decimate', fn () => $decimator->decimate($disk->path($rel), $disk->path($rel).'.thin', (float) $setup['layer']));
                    $clock->note('triangles_in_'.($i + 1), $r['before'])->note('triangles_sliced_'.($i + 1), $r['after'] ?? $r['before']);
                    if ($r['applied']) {
                        $slicePaths[$i] = $rel.'.thin';
                    }
                }
            }
            $objects = array_map(fn ($i, $p) => ['name' => (string) $p['part'], 'stl' => $disk->path($slicePaths[$i]), 'extruder' => $filamentOf[$i] + 1, 'process' => $i > 0 ? $inlay : []], array_keys($skeleton), $skeleton);
            ThreeMfWriter::write($disk->path($projectRel), $objects, [], $plan ? $plan['full']['offsets'] : []);
            $clock->note('one_plate_instances', $per);
        }
        $process = $toolChanger ? self::TOOL_CHANGER_PROCESS : self::ONE_PLATE_PROCESS + ['wipe_tower_x' => [(string) $tower['x']], 'wipe_tower_y' => [(string) $tower['y']]];
        $params = $setup['params']->withFilaments(self::forSlicer($filaments), $disk->path($projectRel), $process);
        try {
            $result = $clock->measure('slice', fn () => $slicer->slice($disk->path($plateRel), $params));
        } catch (SupersededException $e) {
            $cleanup();
            throw $e;   // the order asked for another slice: no falling back on a plate per part
        } catch (\Throwable $e) {
            // the slicer could not do the plate (the tower found no room, the filaments were not kept apart…): one plate each
            Log::warning('One-plate slice failed, the parts print one plate each', ['order' => $order->id, 'error' => $e->getMessage()]);
            $clock->note('one_plate_failed', mb_substr($e->getMessage(), 0, 200));
            $cleanup();

            return $skip('slicer');
        }
        $clock->merge($result->timings);
        if (! $result->gcodePath || ! is_file($result->gcodePath)) {
            $cleanup();

            return $skip('no_gcode');
        }
        $gcodeRel = $order->dir().'/print.gcode';
        File::move($result->gcodePath, $disk->path($gcodeRel));
        $supportsBin = $disk->path($order->dir().'/supports.bin');
        if (! $result->supportsUsed || ! SupportLines::extract($disk->path($gcodeRel), $supportsBin)) {
            @unlink($supportsBin);
        }
        // more copies than a plate takes: full plates of the main G-code, the last one with the rest, laid out and sliced
        // on its own (its grid and its tower's spot are its own)
        $restRel = null;
        $restResult = null;
        if ($plan && $plan['rest'] !== null) {
            $last = $plan['last'];
            $restTower = $toolChanger ? $tower : (self::towerSpot([$last['set'][0], $last['set'][1], $size[2]], $bed, $margin / 2, $nFilaments) ?? $tower);
            $restMove = [$bed->x / 2 - $lo[0] - $last['set'][0] / 2 + $restTower['shift'][0], $bed->y / 2 - $lo[1] - $last['set'][1] / 2 + $restTower['shift'][1], -$lo[2]];
            $restProject = $order->dir().'/plate-rest.3mf';
            // the smaller set of the last plate: its grid's offsets from its own first piece, the set as a whole moved from
            // where the full plate's set stood
            $shift = [$restMove[0] - $move[0], $restMove[1] - $move[1]];
            if ($painted) {
                if (! $clock->measure('repaint', fn () => app(PaintedColors::class)->project($file, $disk->path($restProject), $scale, $restMove, $partFilament, $last['offsets']))) {
                    @unlink($disk->path($gcodeRel));
                    $cleanup();

                    return $skip('project');
                }
            } else {
                // the same objects, placed where the last plate has them
                ThreeMfWriter::write($disk->path($restProject), $objects, [], array_map(fn ($o) => [$o[0] + $shift[0], $o[1] + $shift[1]], $last['offsets']));
            }
            // the last plate as a mesh beside its project: the slicer takes the size from it (and the fake slicer its figures)
            $restPlateRel = $order->dir().'/plate-rest.stl';
            $pieces = [];
            foreach ($single as $i => $rel) {
                foreach ($last['offsets'] as $k => $o) {
                    StlFile::translate($disk->path($rel), $pieces[] = $disk->path($rel).'.rest'.$k, [$o[0] + $shift[0], $o[1] + $shift[1], 0.0]);
                }
            }
            $clock->measure('layout', fn () => StlFile::concat($pieces, $disk->path($restPlateRel)));
            foreach ($pieces as $p) {
                @unlink($p);
            }
            $restParams = $setup['params']->withFilaments(self::forSlicer($filaments, $plan['rest'] / $plan['per']),
                $disk->path($restProject), $toolChanger ? self::TOOL_CHANGER_PROCESS : self::ONE_PLATE_PROCESS + ['wipe_tower_x' => [(string) $restTower['x']], 'wipe_tower_y' => [(string) $restTower['y']]]);
            try {
                $restResult = $clock->measure('rest_slice', fn () => $slicer->slice($disk->path($restPlateRel), $restParams));
            } catch (SupersededException $e) {
                @unlink($disk->path($gcodeRel));
                $cleanup();
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('One-plate slice of the last plate failed', ['order' => $order->id, 'error' => $e->getMessage()]);
                $clock->note('one_plate_failed', 'rest: '.mb_substr($e->getMessage(), 0, 200));
                @unlink($disk->path($gcodeRel));
                $cleanup();

                return $skip('slicer');
            }
            $clock->merge($restResult->timings, 'rest_');
            if (! $restResult->gcodePath || ! is_file($restResult->gcodePath)) {
                @unlink($disk->path($gcodeRel));
                $cleanup();

                return $skip('no_gcode');
            }
            $restRel = $order->dir().'/rest.gcode';
            File::move($restResult->gcodePath, $disk->path($restRel));
        }
        $cleanup();
        $platesCount = $plan ? $plan['plates'] : 1;
        $fullPlates = $restResult ? $platesCount - 1 : $platesCount;
        $sum = fn (float $full, ?float $rest) => $full * $fullPlates + ($rest ?? 0.0);
        $timelapse = $printer->timelapseFor($order);
        $parkingOf = fn (?string $rel) => $timelapse && $rel ? TimelapseGcode::extraMinutesForFile($disk->path($rel), $timelapse, (float) $printer->bed_x, (float) $printer->bed_y) : 0;
        $parking = (int) round($sum($parkingOf($gcodeRel), $restRel ? $parkingOf($restRel) : null));
        $plates = [];
        foreach ($skeleton as $i => $p) {
            // the grams of filament n: the slicer lists one figure per filament loaded, a position nothing printed from 0.00
            $n = $filamentOf[$i];
            $plates[] = ['part' => (string) $p['part'], 'slot_id' => (int) ($p['slot_id'] ?? 0), 'color_id' => (int) ($p['color_id'] ?? 0), 'copies' => $per, 'extruder' => $n + 1,
                'stl_path' => $paths[$i], 'grams' => isset($result->filamentGrams[$n]) ? round($sum((float) $result->filamentGrams[$n], isset($restResult?->filamentGrams[$n]) ? (float) $restResult->filamentGrams[$n] : null), 1) : null,
                'minutes' => $i === 0 ? (int) round($sum($result->minutes, $restResult?->minutes)) : null];
        }
        $order->check = ['max_copies' => $maxCopies] + (array) $order->check;
        $order->fill([
            'plates' => $platesCount, 'plates_done' => 0, 'plate_copies' => $per, 'rest_copies' => $restResult ? $plan['rest'] : null, 'rest_gcode_path' => $restRel,
            'part_plates' => $plates,
            'gcode_path' => $gcodeRel, 'gcode_sha256' => hash_file('sha256', $disk->path($gcodeRel)),
            'slice_params' => [
                'engine' => $slicer->name(), 'printer' => ['id' => $printer->id, 'key' => $printer->key, 'model' => $printer->model],
                'material' => $order->material->code, 'quality' => $setup['quality'], 'layer_mm' => $setup['layer'], 'strength' => $order->strength, 'copies' => $order->copies,
                'infill_percent' => $setup['infill'], 'unit_scale' => $order->unit_scale, 'scale' => (float) $order->scale, 'profiles' => $setup['profiles'], 'overrides' => $params->overrides,
                'profile_layers' => $setup['profile']->layers, 'profile_fingerprint' => $setup['profile']->sliceFingerprint(),
                'profile_hashes' => $this->profileHashes($setup['profiles']), 'preparer' => $preparer->name(), 'sliced_at' => now()->toIso8601String(),
                'timelapse_minutes' => $parking, 'by_parts' => true, 'one_plate' => true, 'tool_changer' => $toolChanger,
                'filaments' => self::filamentRecord($filaments),
                'tower' => $toolChanger ? null : ['x' => $tower['x'], 'y' => $tower['y'], 'shift' => $tower['shift']],
                'copies_grid' => $plan ? ['per_plate' => $per, 'grid' => $plan['full']['grid'], 'rest_grid' => $plan['last']['grid'] ?? null] : null,
            ],
            'slice_result' => $result->toArray() + ['plates' => $platesCount, 'plate_copies' => $per, 'rest_copies' => $restResult ? $plan['rest'] : null, 'rest' => $restResult?->toArray(),
                'parts' => array_map(fn ($p) => ['part' => $p['part'], 'extruder' => $p['extruder'], 'grams' => $p['grams']], $plates)],
            'est_minutes' => (int) round($sum($result->minutes, $restResult?->minutes)) + $parking,
            'est_grams' => round($sum($result->grams, $restResult?->grams), 1),
            'est_meters' => round($sum((float) ($result->meters ?? 0), $restResult ? (float) ($restResult->meters ?? 0) : null), 2),
            'supports_used' => $result->supportsUsed,
        ])->save();
        $clock->note('print_minutes', $order->est_minutes)->note('parts', count($plates))->note('one_plate', true)->note('filament_changes', $result->changes);
        $this->price($order, $printer, $settings, $orders, $flow, $clock);

        return true;
    }

    /**
     * The filaments of a tool changer's plate with every position up to the last tool used filled in (`$filaments` by
     * position): a position nothing prints from takes the profile and colour of the first colour's spool (`$main`, else
     * the lowest position), flagged `placeholder`, so Orca has a filament n for every extruder n a part names.
     *
     * @param  array<int, array{profile: string, overrides: array, colour: ?string, volume_mm3: float, slot_id: ?int, color_id: ?int}>  $filaments
     * @return array<int, array{profile: string, overrides: array, colour: ?string, volume_mm3: float, slot_id: ?int, color_id: ?int, placeholder?: bool}>
     */
    public static function withPlaceholders(array $filaments, int $main): array
    {
        if (! $filaments) {
            return [];
        }
        $template = $filaments[$main] ?? $filaments[min(array_keys($filaments))];
        $last = (int) max(array_keys($filaments));
        for ($n = 0; $n < $last; $n++) {
            $filaments[$n] ??= ['profile' => $template['profile'], 'overrides' => $template['overrides'], 'colour' => $template['colour'], 'volume_mm3' => 0.0, 'slot_id' => null, 'color_id' => null, 'placeholder' => true];
        }
        ksort($filaments);

        return $filaments;
    }

    /** The filament list as SliceParams::withFilaments takes it; `$share` scales the volumes (the last plate of several). */
    private static function forSlicer(array $filaments, float $share = 1.0): array
    {
        return array_map(fn ($f) => ['profile' => $f['profile'], 'overrides' => $f['overrides'], 'colour' => $f['colour'], 'volume_mm3' => round($f['volume_mm3'] * $share, 1)] + (empty($f['placeholder']) ? [] : ['placeholder' => true]), $filaments);
    }

    /** The filament list as the order keeps it (slice_params.filaments): T<n> = entry n, a placeholder flagged. */
    private static function filamentRecord(array $filaments): array
    {
        return array_values(array_map(fn ($f) => ['slot_id' => $f['slot_id'] ?? null, 'color_id' => $f['color_id'] ?? null, 'profile' => $f['profile'], 'colour' => $f['colour'] ?? null] + (empty($f['placeholder']) ? [] : ['placeholder' => true]), $filaments));
    }

    /**
     * What a tool changer's slice of an order printed as one piece has to name (docs/AM.md §12j): the tool of the first
     * colour (the spool's position, slot 0 = tool 1 = T0) and the tool changes at the design's heights
     * (FarmOrder::colorChanges: positions of this machine, a change back to the running tool left out). The payment
     * compares it with what the order was sliced with and slices again when it differs (OrderFlow::pay).
     *
     * @return array{position: int, changes: list<array{z: float, position: int}>}
     */
    public static function toolPlan(FarmOrder $order): array
    {
        return ['position' => (int) ($order->slot?->slot ?? 0), 'changes' => array_values(array_map(fn ($c) => ['z' => round((float) $c['z'], 3), 'position' => (int) $c['slot']], $order->colorChanges()))];
    }

    /** Do two plans name the same tools (a plan the order keeps came through JSON, which writes 3.0 as 3)? False for a missing plan. */
    public static function sameToolPlan(?array $a, ?array $b): bool
    {
        $norm = fn (?array $p) => $p === null ? null : ['position' => (int) ($p['position'] ?? 0),
            'changes' => array_map(fn ($c) => [number_format((float) ($c['z'] ?? 0), 3, '.', ''), (int) ($c['position'] ?? 0)], array_values((array) ($p['changes'] ?? [])))];

        return $a !== null && $b !== null && $norm($a) === $norm($b);
    }

    /**
     * The slice of one plate on a tool changer as a project (docs/AM.md §12j): the mesh placed in the middle of the bed
     * (the slicer centres a mesh, not a project), one object on the first colour's tool, the tool changes at their
     * heights (Orca's own per-layer tool change, ThreeMfWriter), a filament per position up to the last tool used (the
     * spools of this machine by position, the positions between them placeholders), no prime tower. The mesh beside
     * the project only gives the slicer the size. A change at `z` takes effect from the first layer whose middle lies
     * above it (Orca switches from the layer whose print_z reaches `top_z`), the rule GcodeSlot::colorChanges applies
     * on the machines with an ACE.
     *
     * @param  array{position: int, changes: list<array{z: float, position: int}>}  $plan
     * @param  float  $layer  the layer height of this slice (mm)
     * @return array{0: SliceParams, 1: array} the parameters of the slice and the filaments by position (slot, colour, placeholder) for the record
     */
    private function toolChangerParams(FarmPrinter $printer, string $meshPath, string $projectPath, SliceParams $params, PrintProfile $fallback, Dimensions $bed, array $plan, float $volumeMm3, float $layer, Stopwatch $clock): array
    {
        $positions = array_values(array_unique(array_merge([(int) $plan['position']], array_map('intval', array_column($plan['changes'], 'position')))));
        $spools = $printer->slots()->with('color.material')->whereIn('slot', $positions)->get()->keyBy('slot');
        $placed = $projectPath.'.placed.stl';
        $clock->measure('layout', fn () => StlFile::place($meshPath, $placed, 1.0, $bed->x / 2, $bed->y / 2));
        $changes = array_map(fn ($c) => ['z' => round((float) $c['z'] + $layer / 2, 3), 'extruder' => (int) $c['position'] + 1, 'colour' => $spools->get((int) $c['position'])?->color?->hex], $plan['changes']);
        ThreeMfWriter::write($projectPath, [['name' => 'print', 'stl' => $placed, 'extruder' => (int) $plan['position'] + 1]], $changes);
        @unlink($placed);
        $filaments = [];
        foreach ($positions as $n) {
            $spool = $spools->get($n);
            $profile = $spool && $spool->color ? PrintProfile::for($printer, $spool->color->material, $spool->color) : $fallback;
            $filaments[$n] = ['profile' => $profile->filamentProfile, 'overrides' => $profile->filament, 'colour' => $spool?->color?->hex, 'volume_mm3' => $n === (int) $plan['position'] ? $volumeMm3 : 0.0, 'slot_id' => $spool?->id, 'color_id' => $spool?->farm_color_id];
        }
        $filaments = self::withPlaceholders($filaments, (int) $plan['position']);
        $clock->note('tool_positions', implode(',', array_map(fn ($n) => $n + 1, $positions)));

        return [$params->withFilaments(self::forSlicer($filaments), $projectPath, self::TOOL_CHANGER_PROCESS), $filaments];
    }

    /** Fingerprints of the profile files: the same names with different content would not be the same print. */
    private function profileHashes(array $profiles): array
    {
        $out = [];
        foreach ($profiles as $kind => $name) {
            foreach ([config('farm.profiles_dir'), config('engines.orca.profiles')] as $dir) {
                if ($name && $dir && is_file($dir.'/'.basename($name))) {
                    $out[$kind] = sha1_file($dir.'/'.basename($name));
                    break;
                }
            }
        }

        return $out;
    }
}
