<?php

namespace Tests\Feature;

use App\Domain\Tools\ModelCheck;
use App\Mail\CustomerInquiryVerify;
use App\Models\Inquiry;
use App\Models\ModelFile;
use App\Models\PricingProfile;
use App\Models\PrinterMaterial;
use App\Models\PrinterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Tools page by intent, model check (errors vs advice), spare-part inquiry. */
class ToolsCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_lists_only_tools_that_really_exist(): void
    {
        $page = $this->get('/tools')->assertOk();
        foreach (['tools.intent.file', 'tools.intent.create', 'tools.intent.spare', 'tools.printers.title', 'tools.organizer.title', 'tools.box.title', 'tools.spare.title', 'tools.check.title'] as $k) {
            $page->assertSee(__($k));
        }
        foreach (config('tools') as $key => $tool) {
            if ($tool['available']) {
                $this->assertTrue(Route::has($tool['route']), "available tool {$key} has no route");
                $this->assertNotSame('tools.'.$key.'.title', __('tools.'.$key.'.title'), "tool {$key} has no title");
                $this->assertNotSame('tools.'.$key.'.action', __('tools.'.$key.'.action'), "tool {$key} has no action text");
            } else {
                $page->assertDontSee(route('home').'/tools/'.$key, false);      // nothing "coming soon" with a dead button
            }
        }
        // every listed tool has its product picture in both sizes (a missing one would fall back to the drawing)
        foreach (array_keys(array_filter(config('tools'), fn ($t) => $t['available'])) + [99 => 'printer_tools'] as $key) {
            foreach (['-480.webp', '-800.webp', '-800.jpg'] as $suffix) {
                $this->assertFileExists(public_path('img/tools/'.$key.$suffix));
            }
            $this->assertLessThan(60 * 1024, filesize(public_path('img/tools/'.$key.'-800.webp')), $key);
        }
        $page->assertSee('img/tools/modular-800.jpg', false)->assertSee('img/tools/printer_tools-800.jpg', false);
        $this->get('/tools?lang=en')->assertOk()->assertSee('I have a file');
        $this->get('/tools?lang=es')->assertOk()->assertSee('Tengo un archivo');
    }

    public function test_every_new_text_exists_in_all_three_languages(): void
    {
        $cs = json_decode((string) file_get_contents(lang_path('cs.json')), true);
        foreach (['en', 'es'] as $lang) {
            $other = json_decode((string) file_get_contents(lang_path($lang.'.json')), true);
            $missing = array_diff(array_keys($cs), array_keys($other));
            $this->assertSame([], array_values($missing), "missing in {$lang}");
        }
    }

    public function test_model_check_separates_errors_from_advice_and_never_claims_printability(): void
    {
        $file = fn (array $bbox, array $report, string $origin = 'upload', ?string $ref = null) => new ModelFile([
            'status' => ModelFile::STATUS_READY, 'stl_path' => 'x.stl', 'bbox' => $bbox, 'mesh_report' => $report, 'triangles' => $report['triangles'] ?? 5000, 'origin' => $origin, 'origin_ref' => $ref,
        ]);
        $codes = fn (array $r, string $level) => array_column(array_filter($r['items'], fn ($i) => $i['level'] === $level), 'code');

        $ok = ModelCheck::report($file(['x' => 80, 'y' => 40, 'z' => 20], ['watertight' => true, 'shells' => 1]));
        $this->assertSame('ok', $ok['status']);
        $this->assertSame(['size_ok', 'watertight_ok'], $codes($ok, 'ok'));

        $bad = ModelCheck::report($file(['x' => 0.08, 'y' => 0.04, 'z' => 0.02], ['watertight' => false, 'shells' => 3, 'flipped_normals' => true]));
        $this->assertSame('error', $bad['status']);
        $this->assertEqualsCanonicalizing(['units_tiny', 'not_watertight', 'flipped_normals'], $codes($bad, 'error'));
        $this->assertSame(['multiple_shells'], $codes($bad, 'advice'));

        $big = ModelCheck::report($file(['x' => 400, 'y' => 120, 'z' => 60], ['watertight' => true, 'shells' => 1, 'triangles' => 2000000]));
        $this->assertSame('advice', $big['status']);
        $this->assertEqualsCanonicalizing(['exceeds_bed', 'heavy_mesh'], $codes($big, 'advice'));

        $thin = ModelCheck::report($file(['x' => 100, 'y' => 60, 'z' => 0.3], ['watertight' => true]));
        $this->assertContains('too_thin', $codes($thin, 'error'));

        // our own box + lid: two bodies are intended
        $box = ModelCheck::report($file(['x' => 140, 'y' => 54, 'z' => 32], ['watertight' => true, 'shells' => 2], 'tool', 'box'));
        $this->assertSame('ok', $box['status']);

        // a modular set is judged by its biggest part: 300 mm of bins is fine, a 300 mm tray is not
        $setParams = ['inner_w' => 300, 'inner_d' => 150, 'height' => 40, 'cols' => 6, 'rows' => 3, 'bins' => [['x' => 0, 'y' => 0, 'w' => 4, 'h' => 1, 'color' => 'blue']]];
        $set = new ModelFile(['status' => ModelFile::STATUS_READY, 'stl_path' => 'x.stl', 'bbox' => ['x' => 299.4, 'y' => 149.4, 'z' => 40], 'mesh_report' => ['watertight' => true, 'shells' => 5], 'origin' => 'tool', 'origin_ref' => 'modular', 'tool_params' => $setParams]);
        $this->assertSame('ok', ModelCheck::report($set)['status']);
        $this->assertContains('parts_fit', $codes(ModelCheck::report($set), 'ok'));
        $set->tool_params = ['tray' => true] + $setParams;
        $this->assertContains('part_exceeds_bed', $codes(ModelCheck::report($set), 'advice'));

        $this->assertSame('pending', ModelCheck::report(new ModelFile(['status' => ModelFile::STATUS_UPLOADED]))['status']);
        foreach (['cs', 'en', 'es'] as $lang) {
            app()->setLocale($lang);
            $this->assertNotSame('check.disclaimer', __('check.disclaimer'));          // the "no guarantee" note exists in every language
            foreach (['not_watertight', 'too_thin', 'exceeds_bed', 'units_tiny'] as $c) {
                $this->assertNotSame('check.'.$c.'.impact', __('check.'.$c.'.impact'));
            }
        }
        $this->get('/tools/check')->assertOk();
    }

    public function test_spare_part_inquiry_goes_to_printers_who_design(): void
    {
        Mail::fake();
        Storage::fake('public');
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '50.08', 'lon' => '14.43']])]);
        $make = function (string $name, array $services) {
            $u = User::factory()->create(['country' => 'CZ', 'lat' => 50.1, 'lng' => 14.4]);
            $u->setRole(User::ROLE_PRINTER, true);
            $p = PrinterProfile::create(['user_id' => $u->id, 'display_name' => $name, 'slug' => PrinterProfile::makeSlug($name), 'visible' => true, 'services' => $services, 'contact_email' => strtolower($name).'@example.com']);
            PricingProfile::create(['printer_profile_id' => $p->id, 'name' => 'Standard', 'is_default' => true, 'hourly_rate' => 100, 'price_per_gram' => 3]);
            PrinterMaterial::create(['printer_profile_id' => $p->id, 'material_code' => 'PLA']);

            return $p;
        };
        $designer = $make('Modelar', ['design', 'express']);
        $make('Jentisk', ['express']);

        $this->get('/tools/spare-part')->assertOk()->assertSee(__('spare.honest'));
        $this->post('/api/spare-parts', ['what' => 'x'], ['Accept' => 'application/json'])->assertStatus(422);                       // photos + description are required
        $this->post('/api/spare-parts', ['what' => 'Krytka baterie', 'zip' => '11000', 'email' => 'a@b.cz', 'photos' => [UploadedFile::fake()->create('x.exe', 10)]], ['Accept' => 'application/json'])->assertStatus(422);

        $r = $this->post('/api/spare-parts', [
            'what' => 'Krytka baterie dálkového ovladače, ulomená západka', 'use' => 'Drží baterie', 'load' => 'light', 'environment' => ['heat'],
            'dim_x' => 42.5, 'dim_y' => 30, 'quantity' => 2, 'email' => 'zakaznik@example.com', 'zip' => '11000', 'original_available' => 1,
            'photos' => [UploadedFile::fake()->image('a.jpg', 800, 600), UploadedFile::fake()->image('b.png', 800, 600)],
        ], ['Accept' => 'application/json']);
        $r->assertCreated()->assertJsonPath('inquiry.needs_verification', true);
        $inquiry = Inquiry::firstOrFail();
        $this->assertSame('spare_part', $inquiry->kind);
        $this->assertNull($inquiry->calculation_id);
        $this->assertCount(2, $inquiry->details['photos']);
        Storage::disk('public')->assertExists($inquiry->details['photos'][0]);
        $this->assertEquals(['x' => 42.5, 'y' => 30.0], array_map('floatval', $inquiry->details['dims']));
        Mail::assertSent(CustomerInquiryVerify::class);

        // verified → only the printer who designs is asked
        $this->get(route('inquiry.verify', [$inquiry, $inquiry->verification_code]))->assertRedirect();
        $this->assertSame([$designer->id], $inquiry->fresh()->dispatches()->pluck('printer_profile_id')->all());
        $this->get(route('inquiry.show', $inquiry))->assertOk()->assertSee('Krytka baterie')->assertSee(__('spare.badge'));
        $this->actingAs($designer->user)->get(route('printer.inquiries.show', $inquiry))->assertOk()->assertSee('Krytka baterie')->assertSee('42,5');
    }
}
