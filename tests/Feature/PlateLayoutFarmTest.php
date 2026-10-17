<?php

namespace Tests\Feature;

use App\Domain\Farm\PlateLayout;
use App\Engines\DTO\Dimensions;
use App\Engines\Mesh\StlFile;
use App\Models\FarmOrder;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Unit\PlateLayoutTest;

/** The farm lays triangular signs by their shadow: more on one plate than the grid, and the order page says so. */
class PlateLayoutFarmTest extends TestCase
{
    use RefreshDatabase;

    public function test_twenty_triangular_signs_go_on_one_plate_where_the_grid_took_fifteen(): void
    {
        Storage::fake('models');
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $user = User::factory()->create();

        $path = sys_get_temp_dir().'/mp_sign_'.uniqid().'.stl';
        PlateLayoutTest::prism($path, [[0, 0], [40, 0], [20, 60]], 3.0);
        $uuid = $this->actingAs($user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'sign.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $r = $this->actingAs($user)->postJson('/farm/orders', ['file' => $uuid, 'copies' => 20])->assertCreated();
        $order = FarmOrder::where('token', basename($r->json('url')))->firstOrFail();

        $this->assertSame(FarmOrder::STATUS_SLICED, $order->status, json_encode($order->check));
        $bed = $order->printer;
        $usable = new Dimensions($bed->bed_x - 4, $bed->bed_y - 4, $bed->bed_z);
        $grid = PlateLayout::capacity(new Dimensions(40, 60, 3), $usable)['max'];
        $this->assertLessThan(20, $grid, 'the grid alone needs two plates');
        $this->assertSame([1, 20, null], [$order->plates, $order->plate_copies, $order->rest_copies]);
        $this->assertGreaterThan($grid, $order->check['max_copies']);
        $this->assertSame('rows', $order->orientation['layout']);
        $this->assertSame(20 * 8, iterator_count(StlFile::triangles($order->absolutePrintStlPath())));
        $this->assertLessThanOrEqual($usable->x + 0.001, $order->check['dims']['x']);
        $this->assertLessThanOrEqual($usable->y + 0.001, $order->check['dims']['y']);
        $state = $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertSame($order->check['max_copies'], $state['max_copies']);
        @unlink($path);
    }
}
