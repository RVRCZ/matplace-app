<?php

namespace Tests\Feature;

use App\Domain\Calculation\RoughEstimator;
use App\Domain\Farm\PlateLayout;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Engines\Exceptions\PathConflictException;
use App\Engines\Exceptions\SlicerException;
use App\Engines\Mesh\StlFile;
use App\Engines\Slicer\FakeSlicer;
use App\Jobs\PrepareFarmOrder;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Several copies of one piece go to the slicer as instances of one object (TODO #77, docs/AL.md §11.10): a project
 * with the piece once and a build item per copy where PlateLayout laid them, the glued plate beside it for the size and
 * the preview; Orca slices the piece once and prints the instances (the tree with supports: 65 s instead of 253 s).
 * Copies that may need supports stand further apart; two instances whose paths cross make the slicer refuse the plate,
 * and the glued plate is sliced instead. A plate of several pieces gets the slicer more time.
 */
class FarmCopiesInstancesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->user = User::factory()->create(['email' => 'customer@example.com']);
        $this->dir = sys_get_temp_dir().'/mp_instances_'.uniqid();
        mkdir($this->dir);
        // the S1 of the seed alone (250 × 250): the plates below are counted for it
        FarmPrinter::where('key', '!=', 'kobra-s1-01')->update(['enabled' => false]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** The fake slicer with a memory of what it was given; the first slice of a project throws `$throw` when one is given. */
    private function recording(?\Throwable $throw = null): object
    {
        $slicer = new class(new FakeSlicer(app(RoughEstimator::class)), $throw) implements Slicer
        {
            /** @var list<array{0: string, 1: SliceParams}> */
            public array $calls = [];

            public function __construct(private readonly FakeSlicer $fake, private ?\Throwable $throw) {}

            public function slice(string $meshPath, SliceParams $params): SliceResult
            {
                $this->calls[] = [$meshPath, $params];
                if ($params->project !== null && $this->throw) {
                    $e = $this->throw;
                    $this->throw = null;
                    throw $e;
                }

                return $this->fake->slice($meshPath, $params);
            }

            public function measure(string $meshPath): Dimensions
            {
                return $this->fake->measure($meshPath);
            }

            public function supportedFormats(): array
            {
                return ['stl'];
            }

            public function name(): string
            {
                return 'fake';
            }
        };
        $this->app->instance(Slicer::class, $slicer);

        return $slicer;
    }

    private function upload(float $size = 20.0, ?callable $writer = null): string
    {
        $path = $this->dir.'/piece_'.uniqid().'.stl';
        $writer ? $writer($path) : MeshFixtures::cubeStl($path, $size);

        return $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'piece.stl', null, null, true)])->assertCreated()->json('file.uuid');
    }

    private function order(array $body): FarmOrder
    {
        $r = $this->actingAs($this->user)->postJson('/farm/orders', $body)->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    /** @return list<array{0: string, 1: array<int, float>}> the build items of a project as [objectid, the twelve numbers] */
    private function items(string $path): array
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'the project '.$path);
        $model = (string) $zip->getFromName('3D/3dmodel.model');
        $zip->close();
        $this->assertSame(1, substr_count($model, '<object id='), 'the piece is in the project once');
        preg_match_all('/<item objectid="(\d+)" transform="([^"]+)"\/>/', $model, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => [$x[1], array_map('floatval', explode(' ', $x[2]))], $m);
    }

    public function test_four_copies_go_to_the_slicer_as_instances_of_one_object_beside_the_glued_plate(): void
    {
        $slicer = $this->recording();
        $order = $this->order(['file' => $this->upload(20.0), 'copies' => 4]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings));
        $this->assertCount(1, $slicer->calls);
        [$mesh, $params] = $slicer->calls[0];
        $disk = Storage::disk('farm');
        $this->assertSame($disk->path($order->dir().'/plate.stl'), $mesh, 'the glued plate beside the project gives the size');
        $this->assertSame($disk->path($order->dir().'/plate.3mf'), $params->project);
        $this->assertSame([], $params->filaments, 'one filament: no list');
        $this->assertFalse($params->thinMesh);
        $this->assertSame((int) config('engines.orca.timeout') + 3 * (int) config('engines.orca.timeout_per_piece'), $params->timeout, 'a plate of four pieces gets the slicer three pieces more time');
        // the project: the cube once, four items in the 2 × 2 grid of 20 mm pieces 5 mm apart, the set centred on the 250 bed
        $items = $this->items($params->project);
        $this->assertCount(4, $items);
        $this->assertSame(['1', '1', '1', '1'], array_column($items, 0));
        $moves = array_map(fn ($i) => array_slice($i[1], 9), $items);
        $this->assertEqualsWithDelta([[102.5, 102.5, 0.0], [127.5, 102.5, 0.0], [102.5, 127.5, 0.0], [127.5, 127.5, 0.0]], $moves, 0.01, json_encode($moves));
        foreach ($items as $i) {
            $this->assertSame([1.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 1.0], array_slice($i[1], 0, 9), 'the grid keeps the piece as prepared');
        }
        // the record: instances, the gap, the glued plate as the order's preview and size as before
        $this->assertTrue($order->slice_result['instances']);
        $this->assertSame(5.0, (float) $order->slice_result['gap_mm'], 'a flat cube with supports on auto: the usual gap');
        $this->assertNull($order->slice_result['rest']);
        $this->assertSame(48, iterator_count(StlFile::triangles($order->absolutePrintStlPath())));
        $this->assertEqualsWithDelta(45.0, $order->check['dims']['x'], 0.01);
        $this->assertSame([1, 4, null], [$order->plates, $order->plate_copies, $order->rest_copies]);
        $this->assertSame(5.0, (float) $order->timings['gap_mm']);
        $this->assertArrayNotHasKey('slice_conflict', $order->timings);
        $this->assertArrayHasKey('project_s', $order->timings, 'writing the project is a step of its own in the timings');

        // one piece: the mesh as before, nothing to place twice
        $one = $this->order(['file' => $this->upload(20.0)]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $one->status);
        [, $params] = $slicer->calls[1];
        $this->assertNull($params->project);
        $this->assertNull($params->timeout, 'one piece: the configured limit');
        $this->assertNull($one->slice_result['instances']);
        $this->assertSame(5.0, (float) $one->slice_result['gap_mm']);
    }

    public function test_a_path_conflict_between_the_instances_falls_back_to_the_glued_plate_and_another_failure_stops(): void
    {
        $slicer = $this->recording(new PathConflictException('Slice failed (no gcode): gcode path conflicts between the objects of the plate: gcode path conflicts found between print and print'));
        $order = $this->order(['file' => $this->upload(20.0), 'copies' => 4]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings));
        $this->assertCount(2, $slicer->calls, 'the project, then the glued plate');
        $this->assertNotNull($slicer->calls[0][1]->project);
        $this->assertNull($slicer->calls[1][1]->project);
        $this->assertSame($slicer->calls[0][0], $slicer->calls[1][0], 'the same glued plate both times');
        $this->assertFalse($order->slice_result['instances'], 'glued after the conflict');
        $this->assertStringContainsString('gcode path conflicts', $order->timings['slice_conflict']);
        $this->assertNotNull($order->gcode_path);
        $this->assertGreaterThan(0, $order->est_grams);

        // the slicer's plain words count too (an older binary without the exception's class)
        $slicer = $this->recording(new SlicerException('Slice failed (no gcode): … plate 1: found slicing result conflict! …'));
        $order = $this->order(['file' => $this->upload(20.0), 'copies' => 4]);
        $this->assertSame([FarmOrder::STATUS_SLICED, false], [$order->status, $order->slice_result['instances']], json_encode($order->timings));
        $this->assertCount(2, $slicer->calls);

        // any other failure of the project is the slice failing, as before: no second run over the glued plate
        $slicer = $this->recording(new SlicerException('Slice failed (no gcode): Segmentation fault'));
        $order = $this->order(['file' => $this->upload(20.0), 'copies' => 4]);
        $this->assertSame([FarmOrder::STATUS_FAILED, 'slicing_failed'], [$order->status, $order->error]);
        $this->assertCount(1, $slicer->calls);
    }

    public function test_copies_that_may_need_supports_stand_further_apart_and_the_capacity_follows(): void
    {
        // a box of 115 × 115 × 200: four fit the 246 mm plate 5 mm apart (235 mm), not 30 mm apart (260 mm)
        $tall = fn (string $path) => StlFile::writeBox($path, 115, 115, 200);
        $this->assertSame(5.0, PlateLayout::supportGap(20.0, true), 'a flat piece: the usual gap even with supports');
        $this->assertSame(30.0, PlateLayout::supportGap(200.0, true), '15 % of the height, 30 mm at most');
        $this->assertSame(30.0, PlateLayout::supportGap(400.0, true));
        $this->assertSame(5.0, PlateLayout::supportGap(200.0, false), 'supports off: nothing leans out');
        $this->assertSame(12.0, PlateLayout::supportGap(80.0, true));
        $auto = new SliceParams('PLA', supports: null);
        $this->assertTrue(PrepareFarmOrder::supportsPossible($auto));
        $this->assertFalse(PrepareFarmOrder::supportsPossible(new SliceParams('PLA', supports: false)));
        $this->assertTrue(PrepareFarmOrder::supportsPossible((new SliceParams('PLA', supports: false))->withProcess(['enable_support' => '1'])), 'an admin forcing them');
        $this->assertFalse(PrepareFarmOrder::supportsPossible($auto->withProcess(['enable_support' => '0'])), 'an override switching them off for good');

        $slicer = $this->recording();
        $uuid = $this->upload(writer: $tall);
        $order = $this->order(['file' => $uuid, 'copies' => 4]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings));
        $this->assertSame(30.0, (float) $order->slice_result['gap_mm']);
        $this->assertSame(1, $order->check['max_copies'], 'with the support reserve one piece fits a plate');
        $this->assertSame([4, 1, null], [$order->plates, $order->plate_copies, $order->rest_copies]);
        $this->assertNull($order->slice_result['instances'], 'one piece a plate: no project');
        $this->assertSame(1, $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json('max_copies'));

        // supports off: the usual gap, four on one plate as instances – the page's "how many fit" follows the re-slice
        $this->actingAs($this->user)->postJson("/farm/orders/{$order->token}/reslice", ['quality' => 'standard', 'strength' => 'standard', 'supports' => 'off', 'copies' => 4])->assertOk();
        $order->refresh();
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings));
        $this->assertSame(5.0, (float) $order->slice_result['gap_mm']);
        $this->assertSame(4, $order->check['max_copies']);
        $this->assertSame([1, 4, null], [$order->plates, $order->plate_copies, $order->rest_copies]);
        $this->assertTrue($order->slice_result['instances']);
        $this->assertSame(4, $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json('max_copies'));
        $last = end($slicer->calls);
        $this->assertCount(4, $this->items($last[1]->project));
    }

    public function test_more_copies_than_a_plate_takes_place_the_full_plate_as_instances_and_a_single_rest_as_a_mesh(): void
    {
        $slicer = $this->recording();
        // nine cubes of 100 mm on the 246 mm plate: 4 + 4 + 1
        $order = $this->order(['file' => $this->upload(100.0), 'copies' => 9]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, (string) $order->error.' '.json_encode($order->timings));
        $this->assertSame([3, 4, 1], [$order->plates, $order->plate_copies, $order->rest_copies]);
        $this->assertCount(2, $slicer->calls, 'the full plate and the last one');
        $disk = Storage::disk('farm');
        [$full, $rest] = $slicer->calls;
        $this->assertSame($disk->path($order->dir().'/plate.3mf'), $full[1]->project);
        $this->assertCount(4, $this->items($full[1]->project));
        $this->assertSame((int) config('engines.orca.timeout') + 3 * (int) config('engines.orca.timeout_per_piece'), $full[1]->timeout);
        $this->assertSame($disk->path($order->dir().'/rest.stl'), $rest[0]);
        $this->assertNull($rest[1]->project, 'one piece on the last plate: nothing to place twice');
        $this->assertNull($rest[1]->timeout);
        $this->assertTrue($order->slice_result['instances']);
        $this->assertNull($order->slice_result['rest']['instances']);
        $this->assertNotNull($order->rest_gcode_path);
        $this->assertSame([4, 4, 1], $order->plateLayout());

        // five pieces per plate and a rest of two: both plates as instances, the last one with two items
        $many = $this->order(['file' => $this->upload(100.0), 'copies' => 6]);
        $this->assertSame([2, 4, 2], [$many->plates, $many->plate_copies, $many->rest_copies]);
        $last = end($slicer->calls);
        $this->assertSame($disk->path($many->dir().'/rest.3mf'), $last[1]->project);
        $this->assertCount(2, $this->items($last[1]->project));
        $this->assertSame((int) config('engines.orca.timeout') + (int) config('engines.orca.timeout_per_piece'), $last[1]->timeout);
        $this->assertTrue($many->slice_result['rest']['instances']);
    }

    public function test_the_slicers_time_grows_with_the_pieces_up_to_the_cap_and_the_job_outlasts_it(): void
    {
        config(['engines.orca.timeout' => 600, 'engines.orca.timeout_per_piece' => 60, 'engines.orca.timeout_max' => 1200]);
        $this->assertNull(PrepareFarmOrder::sliceTimeout(1));
        $this->assertSame(660, PrepareFarmOrder::sliceTimeout(2));
        $this->assertSame(780, PrepareFarmOrder::sliceTimeout(4));
        $this->assertSame(1200, PrepareFarmOrder::sliceTimeout(11), 'the cap');
        $this->assertSame(1200, PrepareFarmOrder::sliceTimeout(64));
        $this->assertSame(1500, (new PrepareFarmOrder(1))->timeout, 'the job outlasts the longest slice it allows');
        config(['engines.orca.timeout_per_piece' => 0]);
        $this->assertNull(PrepareFarmOrder::sliceTimeout(4), 'switched off: every plate gets the configured limit');
        config(['engines.orca.timeout_max' => 600]);
        $this->assertSame(900, (new PrepareFarmOrder(1))->timeout, 'never under the 900 s it always had');
    }

    /**
     * The acceptance with the real OrcaSlicer 2.4 on this machine (ORCA_LOCAL = its orca-slicer.exe): four cubes as
     * instances of one object slice once ("need to slice 1") and weigh four cubes. Skipped elsewhere.
     */
    public function test_the_real_orca_slices_the_piece_once_for_four_instances(): void
    {
        $exe = (string) env('ORCA_LOCAL', '');
        if ($exe === '' || ! is_file($exe)) {
            $this->markTestSkipped('ORCA_LOCAL not set: the real slice runs on Roman\'s PC only');
        }
        config(['engines.slicer' => 'orca', 'engines.orca.bin' => $exe, 'engines.orca.xvfb' => false, 'engines.orca.cache' => false,
            'engines.orca.work_dir' => $this->dir.'/orca', 'engines.orca.timeout' => 600]);
        $this->app->forgetInstance(Slicer::class);
        $uuid = $this->upload(30.0);
        $one = $this->order(['file' => $uuid]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $one->status, (string) $one->error.' '.json_encode($one->timings));
        $four = $this->order(['file' => $uuid, 'copies' => 4]);
        $this->assertSame(FarmOrder::STATUS_SLICED, $four->status, (string) $four->error.' '.json_encode($four->timings));
        $this->assertTrue($four->slice_result['instances'], json_encode($four->timings));
        $this->assertSame([4, 1], [$four->timings['orca_objects'] ?? null, $four->timings['orca_sliced'] ?? null], 'the piece sliced once for four instances: '.json_encode($four->timings));
        $this->assertEqualsWithDelta(4 * (float) $one->est_grams, (float) $four->est_grams, 0.1 * (float) $four->est_grams, 'four cubes weigh four cubes');
        // the minutes grow less than fourfold: the purge line, the heating and the first layer are paid once (23 → 52 min for 30 mm cubes, 8 Nov 2026)
        $this->assertGreaterThan(2 * $one->est_minutes, $four->est_minutes);
    }
}
