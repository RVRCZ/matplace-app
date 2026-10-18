<?php

namespace Tests\Feature;

use App\Models\AnonymousSession;
use App\Models\Calculation;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use App\Models\FarmPrinter;
use App\Models\ModelFile;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/** Upload → process → calculation → precise result, with the fake slicer and a sync queue. */
class CalculationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
    }

    private function cube(string $ext = 'stl'): UploadedFile
    {
        $path = sys_get_temp_dir().'/mp_cube_'.uniqid().'.'.$ext;
        $ext === '3mf' ? MeshFixtures::cube3mf($path, 10) : MeshFixtures::cubeStl($path, 20);

        return new UploadedFile($path, 'cube.'.$ext, null, null, true);
    }

    public function test_home_renders_without_account(): void
    {
        $r = $this->get('/');
        $r->assertOk()->assertSee('matplace')->assertCookie(AnonymousSession::COOKIE);
        $this->assertSame(1, AnonymousSession::count());
    }

    public function test_upload_and_precise_calculation(): void
    {
        $up = $this->postJson('/api/uploads', ['file' => $this->cube()]);
        $up->assertCreated();
        $uuid = $up->json('file.uuid');

        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(ModelFile::STATUS_READY, $file->status);       // sync queue processed it
        $this->assertEqualsWithDelta(8000.0, $file->volume_mm3, 0.01);
        $this->assertNotNull($file->stl_path);
        $this->assertNotNull($file->anonymous_session_id);

        $calc = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA', 'quality' => 'standard', 'infill' => 15, 'quantity' => 2]);
        $calc->assertCreated();
        $token = $calc->json('calculation.token');
        $this->assertSame('done', $calc->json('calculation.status'));
        $this->assertSame('fake', Calculation::where('token', $token)->value('slicer_engine'));
        $this->assertGreaterThan(0, $calc->json('calculation.slicer.grams'));
        $this->assertCount(3, $calc->json('calculation.prices'));
        $this->assertSame(2, $calc->json('calculation.prices.0.quantity'));
        $this->assertNotNull($calc->json('calculation.rough.price_min'));

        // share page and public API work without any cookie
        $this->flushSession();
        $this->get('/c/'.$token)->assertOk()->assertSee('cube.stl');
        $this->getJson('/api/calculations/'.$token)->assertOk()->assertJsonPath('calculation.status', 'done');
        $this->get('/api/files/'.$uuid.'/model.stl')->assertOk()->assertHeader('Content-Type', 'model/stl');
    }

    /**
     * A calculation started on an English or Spanish page lives at /en/c/… or /es/c/… (AH.md §9.6 #7): the API answers
     * in the language of the page that calls it, and the calculator falls back to the start page of its own language,
     * never to "/" (which made the next calculation Czech on an English page).
     */
    public function test_a_calculation_keeps_the_language_of_the_page_it_came_from(): void
    {
        $uuid = $this->postJson('/api/uploads', ['file' => $this->cube()])->json('file.uuid');
        $params = ['file' => $uuid, 'material' => 'PLA', 'quality' => 'standard', 'infill' => 15];
        foreach (['en', 'es'] as $lang) {
            // what the browser sends: the page as the Referer (the tool page opens /en?open=<file>)
            $url = $this->postJson('/api/calculations', $params, ['Referer' => url("/{$lang}?open={$uuid}")])->assertCreated()->json('calculation.url');
            $this->assertSame(url("/{$lang}/c/".basename($url)), $url, $lang);
            $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->assertSee('<html lang="'.$lang.'"', false);
            $this->assertStringStartsWith(url("/{$lang}/c/"), (string) $this->postJson('/api/calculations', $params, ['X-Locale' => $lang])->json('calculation.url'));
            // the page tells the calculator where its start page is
            $this->get("/{$lang}?open={$uuid}")->assertOk()->assertSee('home: '.json_encode(url("/{$lang}")), false);
        }
        $url = $this->postJson('/api/calculations', $params, ['Referer' => url("/?open={$uuid}")])->json('calculation.url');
        $this->assertSame(url('/c/'.basename($url)), $url, 'Czech has no prefix');
        $this->get('/')->assertOk()->assertSee('home: '.json_encode(url('/')), false);
    }

    public function test_same_params_reuse_cached_slice(): void
    {
        $uuid = $this->postJson('/api/uploads', ['file' => $this->cube()])->json('file.uuid');
        $a = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'])->json('calculation');
        $b = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'PLA'])->json('calculation');
        $this->assertNotSame($a['token'], $b['token']);
        $this->assertSame($a['slicer']['grams'], $b['slicer']['grams']);
        $this->assertSame(2, Calculation::count());
    }

    public function test_3mf_is_converted_to_stl(): void
    {
        $up = $this->postJson('/api/uploads', ['file' => $this->cube('3mf')]);
        $up->assertCreated();
        $file = ModelFile::where('uuid', $up->json('file.uuid'))->firstOrFail();
        $this->assertSame(ModelFile::STATUS_READY, $file->status);
        $this->assertEqualsWithDelta(8000.0, $file->volume_mm3, 0.01); // 10 mm cube × build transform 2
    }

    public function test_non_sliceable_material_is_estimate_only(): void
    {
        $uuid = $this->postJson('/api/uploads', ['file' => $this->cube()])->json('file.uuid');
        $c = $this->postJson('/api/calculations', ['file' => $uuid, 'material' => 'RESIN'])->json('calculation');
        $this->assertSame('done', $c['status']);
        $this->assertNull($c['slicer']);
        $this->assertNotNull($c['rough']['price_min']);
    }

    public function test_rejects_unknown_format_and_bad_scale(): void
    {
        $path = sys_get_temp_dir().'/mp_'.uniqid().'.exe';
        file_put_contents($path, 'x');
        $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'x.exe', null, null, true)])->assertStatus(422);

        $uuid = $this->postJson('/api/uploads', ['file' => $this->cube()])->json('file.uuid');
        $this->postJson('/api/calculations', ['file' => $uuid, 'scale' => 99])->assertStatus(422);
    }

    public function test_homepage_keeps_the_working_entrance_on_top_of_the_new_look(): void
    {
        // the headline of the audit (10/2026): "Váš nápad vytiskneme ve 3D."
        foreach (['cs' => 'Váš nápad', 'en' => 'Your idea', 'es' => 'Su idea'] as $lang => $title) {
            $page = $this->get($this->localized('/', $lang))->assertOk()->assertSee($title);
            // the tools people use most, with their pictures, and the way to all of them
            $this->assertSame((int) config('home.tools_shown'), substr_count($page->getContent(), 'data-cats='));
            $page->assertSee('img/tools/gifts-800', false)->assertSee(route('tools'), false);
            // what the farm prints most often comes first and says so (config/home.php `popular`)
            $html = $page->getContent();
            $this->assertLessThan(strpos($html, route('tools.gifts')), strpos($html, route('tools.qr')));
            $this->assertGreaterThanOrEqual(1, substr_count($html, __('home.tools.popular')));
            $this->assertLessThan(strpos($html, route('tools.gifts')), strpos($html, __('home.tools.popular')), 'the badge sits on the first cards');
            // the inputs the scripts bind to are all there, before any marketing section
            $html = $page->getContent();
            foreach (['id="dropzone"', 'id="file-input"', 'id="hero-text-btn"', 'id="search-form"', 'id="result"', 'data-tile="idea"'] as $needle) {
                $this->assertStringContainsString($needle, $html);
            }
            $this->assertLessThan(strpos($html, 'id="home-tools"'), strpos($html, 'id="dropzone"'));
            $page->assertSee(__('home.benefit.real_prices'))->assertSee(route('tools.organizer'), false)->assertSee(route('tools.spare'), false);
        }
        foreach (['hero', 'organizer', 'vases'] as $img) {
            foreach (['-640.webp', '-1024.webp', '-1536.webp', '-1024.jpg'] as $suffix) {
                $this->assertFileExists(public_path('img/home/'.$img.$suffix));
            }
        }
        $this->assertLessThan(120 * 1024, filesize(public_path('img/home/hero-1536.webp')));      // a light first screen
    }

    /**
     * "Tisk u nás" prices in the farm's kind of the material chosen; when the farm has none, in the cheapest kind loaded,
     * weighed as that kind (the slicer weighed the model in the lighter material) and the label says which kind it took.
     * Before: every material was priced as PLA+ with its own grams, so ASA came out cheaper than PLA+ (18 Oct 2026).
     */
    public function test_the_farm_price_counts_with_the_kind_of_the_chosen_material_or_says_which_kind_it_took(): void
    {
        config(['features.marketplace' => false, 'farm.enabled' => true]);
        Storage::fake('farm');
        $this->seed(FarmSeeder::class);   // one spool loaded: white PLA+
        $uuid = $this->postJson('/api/uploads', ['file' => $this->cube()])->json('file.uuid');
        $calc = fn (string $material) => $this->postJson('/api/calculations', ['file' => $uuid, 'material' => $material, 'quality' => 'standard', 'infill' => 15])->assertCreated()->json('calculation');

        $pla = $calc('PLA');
        $this->assertCount(1, $pla['prices']);
        $this->assertSame(__('farm.price_label', ['material' => 'PLA+']), $pla['prices'][0]['label'], 'PLA+ is the farm\'s PLA');

        // ASA is not loaded: the farm would print it in PLA+, so the lighter ASA grams are weighed as PLA+ – the same plastic
        // volume costs the same, and the label says what was counted with
        $asa = $calc('ASA');
        $this->assertSame(__('farm.price_label_cheapest', ['material' => 'PLA+']), $asa['prices'][0]['label']);
        $this->assertLessThan($pla['slicer']['grams'], $asa['slicer']['grams'], 'the slicer weighs ASA lighter');
        $this->assertEqualsWithDelta($pla['prices'][0]['unit']['material'], $asa['prices'][0]['unit']['material'], 0.3);
        $this->assertStringContainsString('nejlevnější druh u nás: PLA+', $asa['prices'][0]['label']);

        // the farm loads ASA: the ASA calculation takes the ASA kind, its price per gram and its label; ASA is dearer than PLA+
        $kind = FarmMaterial::where('code', 'ASA')->firstOrFail();
        $kind->update(['enabled' => true]);
        $black = FarmColor::create(['farm_material_id' => $kind->id, 'code' => 'ASA_black', 'name' => 'černá', 'name_en' => 'black', 'hex' => '#111111', 'enabled' => true, 'in_stock' => true]);
        FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail()->slots()->where('slot', 0)->firstOrFail()->update(['farm_color_id' => $black->id, 'remaining_g' => 800, 'enabled' => true]);
        $loaded = $calc('ASA');
        $this->assertSame(__('farm.price_label', ['material' => 'ASA']), $loaded['prices'][0]['label']);
        $this->assertGreaterThan($pla['prices'][0]['unit']['material'], $loaded['prices'][0]['unit']['material'], 'ASA per piece costs more than PLA+, as /materials says');
        $this->assertEqualsWithDelta($asa['slicer']['grams'] * $kind->price_per_gram * 1.0, $loaded['prices'][0]['unit']['material'], 0.05, 'ASA grams at the ASA price');
        // a calculation shared later tells the same kind (the farm's list is read again, not the stored one)
        $this->assertSame($loaded['prices'][0]['label'], $this->getJson('/api/calculations/'.$loaded['token'])->json('calculation.prices.0.label'));
        // PLA stays PLA+: the dearer ASA kind is not "the cheapest"
        $this->assertSame(__('farm.price_label', ['material' => 'PLA+']), $calc('PLA')['prices'][0]['label']);
    }
}
