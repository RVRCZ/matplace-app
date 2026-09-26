<?php

namespace Tests\Feature;

use App\Models\FarmOrder;
use App\Models\FarmPrinterMaterial;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Photos of a test print: uploaded (phone) or taken in the photo box, read by the judge, which zooms in with its crop
 * tool and prefills the evaluation form; the operator's submit is still what counts.
 */
class FarmTestPhotosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->setRole(User::ROLE_ADMIN, true);
    }

    private function makeTestOrder(): FarmOrder
    {
        $row = FarmPrinterMaterial::whereNull('farm_color_id')->firstOrFail();
        $file = ModelFile::forceCreate(['uuid' => (string) Str::uuid(), 'original_name' => 'calib.stl', 'ext' => 'stl', 'size_bytes' => 1, 'sha256' => str_repeat('0', 64),
            'storage_path' => 'x/calib.stl', 'origin' => 'tool', 'origin_ref' => 'calib', 'status' => 'ready']);

        return FarmOrder::forceCreate([
            'token' => Str::random(32), 'number' => 'T26-000042', 'user_id' => $this->admin->id, 'kind' => FarmOrder::KIND_TEST, 'model_file_id' => $file->id,
            'status' => FarmOrder::STATUS_DONE, 'farm_printer_id' => $row->farm_printer_id, 'farm_material_id' => $row->farm_material_id,
            'farm_printer_material_id' => $row->id,
            'test_params' => ['object' => 'detailed', 'ironing' => true, 'candidate' => ['nozzle_temp' => 215, 'nozzle_temp_first' => 225, 'bed_temp' => 60],
                'features' => [['name' => 'stringing', 'gaps' => [10, 20], 'height' => 40]]],
        ]);
    }

    private function photo(int $w = 1200, int $h = 900): UploadedFile
    {
        $path = sys_get_temp_dir().'/mp_test_photo_'.uniqid().'.jpg';
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 90, 160, 220));
        imagejpeg($im, $path, 90);

        return new UploadedFile($path, 'IMG_0232.jpg', 'image/jpeg', null, true);
    }

    private function toolUse(string $name, array $input): array
    {
        return ['id' => 'msg_'.uniqid(), 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5', 'stop_reason' => 'tool_use',
            'content' => [['type' => 'tool_use', 'id' => 'toolu_'.uniqid(), 'name' => $name, 'input' => $input]],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 200, 'cache_read_input_tokens' => 0]];
    }

    public function test_photos_are_stored_upright_with_a_thumbnail_and_can_be_removed(): void
    {
        $order = $this->makeTestOrder();
        $this->actingAs($this->admin)->post("/admin/farm/orders/{$order->token}/photos", ['photos' => [$this->photo(), $this->photo(800, 1000)], 'views' => ['top', 'left']])
            ->assertRedirect()->assertSessionHas('status');
        $photos = $order->fresh()->test_params['photos'];
        $this->assertCount(2, $photos);
        $this->assertSame(['top', 'left'], array_column($photos, 'view'));
        $this->assertSame([1200, 900], [$photos[0]['w'], $photos[0]['h']]);
        Storage::disk('farm')->assertExists([$photos[0]['file'], $photos[0]['thumb']]);
        $this->actingAs($this->admin)->get("/admin/farm/orders/{$order->token}/photos/0?thumb=1")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($this->admin)->get('/admin/farm/photobox?order='.$order->token)->assertOk()->assertSee('data-camera="top"', false)->assertSee($order->number);

        // not a photo at all
        $bad = UploadedFile::fake()->createWithContent('notes.jpg', 'plain text');
        $this->actingAs($this->admin)->post("/admin/farm/orders/{$order->token}/photos", ['photos' => [$bad]])->assertSessionHas('error');

        $this->actingAs($this->admin)->post("/admin/farm/orders/{$order->token}/photos/0/delete")->assertRedirect();
        $this->assertCount(1, $order->fresh()->test_params['photos']);
        Storage::disk('farm')->assertMissing($photos[0]['file']);

        // nobody but an admin sees them
        $this->actingAs(User::factory()->create())->get("/admin/farm/orders/{$order->token}/photos/0")->assertRedirect();
    }

    public function test_the_judge_zooms_in_and_prefills_the_form_until_the_operator_submits_it(): void
    {
        config(['ai.anthropic.api_key' => 'test-key']);
        $order = $this->makeTestOrder();
        $this->actingAs($this->admin)->post("/admin/farm/orders/{$order->token}/photos", ['photos' => [$this->photo()], 'views' => ['top']]);

        $field = fn (string $v, string $why = 'vidět na fotce') => ['value' => $v, 'confidence' => 'high', 'reason' => $why];
        $form = [
            'stringing' => $field('2', 'jemné vlasy v mezeře 10 mm'), 'overhang_ok' => $field('70'), 'bridge' => $field('ok'), 'elephant' => $field('0'),
            'corners' => $field('ok'), 'ironing' => $field('lines', 'viditelné tahy'), 'top' => $field('ok'), 'wall' => $field('ok'), 'bond' => $field('ok'),
            'warp' => ['value' => 'unknown', 'confidence' => 'low', 'reason' => 'podložka není vidět zboku'],
            'score' => '4', 'note' => 'Lehký stringing, jinak čisté.', 'better_photos' => 'Boční záběr na podložku.',
        ];
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push($this->toolUse('crop', ['photo' => 1, 'x0' => 100, 'y0' => 100, 'x1' => 500, 'y1' => 400]))
            ->push($this->toolUse('submit_evaluation', $form))]);

        $this->actingAs($this->admin)->post("/admin/farm/orders/{$order->token}/judge")->assertRedirect()->assertSessionHas('status');

        $ai = $order->fresh()->test_params['ai'];
        $this->assertSame('done', $ai['status'], $ai['error'] ?? '');
        $this->assertSame('2', $ai['fields']['stringing']['value']);
        $this->assertNull($ai['fields']['warp']['value'], 'unknown stays unanswered');
        $this->assertSame(4, $ai['score']);
        $this->assertSame(1, $ai['crops']);

        // the first call carries the photo, the geometry and the tools; the second the enlarged crop
        Http::assertSentInOrder([
            fn ($r) => $r['model'] === 'claude-opus-5' && $r['fallbacks'] === 'default' && $r->hasHeader('anthropic-beta', 'server-side-fallback-2026-07-01')
                && $r['messages'][0]['content'][1]['type'] === 'image' && str_contains(json_encode($r['messages']), 'gaps')
                && array_column($r['tools'], 'name') === ['crop', 'submit_evaluation'],
            fn ($r) => ($last = end($r->data()['messages'])) && $last['content'][0]['type'] === 'tool_result' && $last['content'][0]['content'][0]['type'] === 'image',
        ]);

        // the form shows the reading, prefilled; nothing is evaluated yet
        $row = FarmPrinterMaterial::findOrFail($order->farm_printer_material_id);
        $this->actingAs($this->admin)->get("/admin/farm/tuning/{$row->id}")->assertOk()
            ->assertSee('Předvyplněno podle AI')->assertSee('jemné vlasy v mezeře 10 mm')->assertSee('<option value="2" selected>', false);
        $this->assertArrayNotHasKey('result', $order->fresh()->test_params);

        // the operator corrects stringing to 1 and submits: that is the result, the reading stays next to it
        $this->actingAs($this->admin)->post("/admin/farm/tuning/{$row->id}/evaluate/{$order->token}", ['stringing' => 1, 'ironing' => 'lines', 'score' => 4])->assertRedirect();
        $tp = $order->fresh()->test_params;
        $this->assertSame(1, $tp['result']['stringing']);
        $this->assertSame('done', $tp['ai']['status']);
    }

    public function test_without_photos_or_a_key_nothing_is_sent(): void
    {
        $order = $this->makeTestOrder();
        Http::fake();
        config(['ai.anthropic.api_key' => '']);
        $this->actingAs($this->admin)->post("/admin/farm/orders/{$order->token}/judge")->assertSessionHas('error');
        config(['ai.anthropic.api_key' => 'test-key']);
        $this->actingAs($this->admin)->post("/admin/farm/orders/{$order->token}/judge")->assertSessionHas('error');
        Http::assertNothingSent();
    }
}
