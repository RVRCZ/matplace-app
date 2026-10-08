<?php

namespace Tests\Feature;

use App\Domain\Tools\ImageMaker;
use App\Domain\Tools\PhotoCut;
use App\Engines\Ai\Assistant;
use App\Engines\Ai\ClaudeAssistant;
use App\Engines\Ai\FakeAssistant;
use App\Engines\Exceptions\EngineException;
use App\Engines\Image\FakeImageGenerator;
use App\Engines\Image\GeminiImageGenerator;
use App\Engines\Image\ImageGenerator;
use App\Engines\Image\ImageResult;
use App\Engines\Photo\BackgroundRemover;
use App\Engines\Photo\FakeBackgroundRemover;
use App\Engines\Photo\NoBackgroundRemover;
use App\Engines\Photo\RembgBackgroundRemover;
use App\Http\Controllers\StudioToolsController;
use App\Models\AiCall;
use App\Models\AnonymousSession;
use App\Models\CatalogModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The studio of session 4: a picture from a description, the texts of a listing, a product photo without its background. */
class StudioToolsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> the pictures made in a test: they live in storage/app/artwork, which the database reset does not touch */
    private array $made = [];

    protected function setUp(): void
    {
        parent::setUp();
        FakeImageGenerator::reset();
        FakeAssistant::reset();
        FakeBackgroundRemover::reset();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->made as $ref) {
            foreach (glob(storage_path('app/artwork/*/'.$ref.'.*')) ?: [] as $file) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    private function make(array $data, ?User $as = null): TestResponse
    {
        $r = ($as ? $this->actingAs($as) : $this)->postJson('/api/tools/image', $data);
        if ($r->json('ref')) {
            $this->made[] = $r->json('ref');
        }

        return $r;
    }

    public function test_a_picture_from_a_description_lands_among_my_pictures_and_is_counted(): void
    {
        $this->get('/tools/image')->assertOk()->assertSee(__('tools.image.title'))->assertSee('data-sell="image"', false)->assertSee('id="image-prompt"', false)->assertSee(__('tools.cutter.title'))->assertSee(__('tools.compose.title'));
        $this->get('/en/tools/image')->assertOk()->assertSee('Make the picture');
        $this->get('/es/tools/image')->assertOk()->assertSee('Crear la imagen');
        config(['ai.daily_limits.image_guest' => 2]);
        $r = $this->make(['prompt' => 'sedící kočka', 'style' => 'silhouette', 'size' => 'square'])->assertCreated();
        $this->assertSame(1, $r->json('left'));
        $this->assertSame('fake', $r->json('model'));
        $this->assertSame(['prompt' => 'sedící kočka', 'style' => 'silhouette', 'size' => 'square'], FakeImageGenerator::$calls[0]);
        $this->assertSame('sedící kočka.png', $r->json('name'));
        // the picture is among the visitor's own pictures (the test client forgets cookies: the session is handed back by hand), a PNG in pure black and white; the call is booked
        $cookie = [AnonymousSession::COOKIE => AnonymousSession::query()->latest('id')->value('token')];
        $mine = $this->withUnencryptedCookies($cookie)->withCredentials()->getJson('/api/artwork/mine')->assertOk()->json('items');
        $this->assertNotNull(collect($mine)->firstWhere('ref', $r->json('ref')));
        $img = imagecreatefromstring((string) file_get_contents($this->withUnencryptedCookies($cookie)->get($r->json('url'))->assertOk()->baseResponse->getFile()->getPathname()));
        $colours = [];
        for ($y = 0; $y < imagesy($img); $y += 37) {
            for ($x = 0; $x < imagesx($img); $x += 41) {
                $colours[imagecolorat($img, $x, $y) & 0xFFFFFF] = true;
            }
        }
        $keys = array_keys($colours);
        sort($keys);
        $this->assertSame([0x000000, 0xFFFFFF], $keys);
        $this->assertSame(1, AiCall::where('kind', 'image')->count());
        // the second is the last of the day; the third waits for tomorrow; the site's own cap stops everyone
        $this->make(['prompt' => 'traktor z boku', 'style' => 'colour'])->assertCreated()->assertJsonPath('left', 0);
        $this->postJson('/api/tools/image', ['prompt' => 'jedle'])->assertStatus(429)->assertJsonPath('error', 'daily_limit');
        $this->assertCount(2, FakeImageGenerator::$calls);
        config(['ai.daily_limits.image_global' => 2]);
        $this->postJson('/api/tools/image', ['prompt' => 'jedle'])->assertStatus(429)->assertJsonPath('error', 'site_limit');
        config(['ai.daily_limits.image_global' => 150]);
        // an account has its own count; a failure of the model is a 502 and is not counted
        $user = User::factory()->create();
        $this->make(['prompt' => 'srdce', 'style' => 'line', 'size' => 'tall'], $user)->assertCreated()->assertJsonPath('left', (int) config('ai.daily_limits.image_user', 10) - 1);
        FakeImageGenerator::$fail = 'boom';
        $this->actingAs($user)->postJson('/api/tools/image', ['prompt' => 'srdce'])->assertStatus(502)->assertJsonPath('error', 'failed');
        $this->assertSame((int) config('ai.daily_limits.image_user', 10) - 1, ImageMaker::left($user, '127.0.0.1'));
        $this->postJson('/api/tools/image', ['prompt' => 'x'])->assertStatus(422);
        // without a key the page says so and the API refuses
        $this->app->instance(ImageGenerator::class, new GeminiImageGenerator(['api_key' => '']));
        $this->get('/tools/image')->assertOk()->assertSee('note-warn', false)->assertSee(__('sell.image.unavailable'));
        $this->postJson('/api/tools/image', ['prompt' => 'jedle'])->assertStatus(503);
    }

    public function test_the_gemini_generator_reads_the_inline_picture_and_never_tells_the_key(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'here it is'], ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => base64_encode(str_repeat('x', 200))]]]], 'finishReason' => 'STOP']], 'usageMetadata' => ['promptTokenCount' => 30, 'candidatesTokenCount' => 1120, 'totalTokenCount' => 1150]])
            ->push(['error' => ['code' => 400, 'message' => 'API key not valid: AQ.secret-key-here-abc']], 400)
            ->push(['candidates' => [['content' => ['parts' => []], 'finishReason' => 'IMAGE_SAFETY']]])
            ->push(['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode(str_repeat('y', 150))]]]]]]]),
        ]);
        $g = new GeminiImageGenerator(['api_key' => 'AQ.test-key', 'image_model' => 'gemini-3-pro-image', 'cheap_model' => 'gemini-3.1-flash-lite-image']);
        $this->assertTrue($g->available());
        $r = $g->fromText('a cat', 'silhouette', 'wide');
        $this->assertSame('image/jpeg', $r->mime);
        $this->assertSame('jpg', $r->extension());
        $this->assertSame(str_repeat('x', 200), $r->bytes);
        $this->assertSame('gemini-3-pro-image', $r->model);
        $this->assertSame(1150, $r->tokens);
        Http::assertSent(fn ($req) => $req->hasHeader('x-goog-api-key', 'AQ.test-key') && str_contains($req->url(), '/v1beta/models/gemini-3-pro-image:generateContent')
            && $req['generationConfig']['responseModalities'] === ['IMAGE'] && str_contains($req['contents'][0]['parts'][0]['text'], 'silhouette of a cat') && str_contains($req['contents'][0]['parts'][0]['text'], 'landscape'));
        // the call is booked at the pro model's flat price
        $call = AiCall::where('kind', 'image')->latest('id')->first();
        $this->assertSame('gemini-3-pro-image', $call->engine);
        $this->assertEqualsWithDelta(0.08 * (float) config('ai.prices.usd_czk'), (float) $call->cost_czk, 0.01);
        // an error carries the status and the message, never the key
        try {
            $g->fromText('a cat', 'line', 'square');
            $this->fail('no exception');
        } catch (EngineException $e) {
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
            $this->assertStringNotContainsString('secret-key', $e->getMessage());
        }
        try {
            $g->fromText('a cat', 'colour', 'tall');
            $this->fail('no exception');
        } catch (EngineException $e) {
            $this->assertStringContainsString('IMAGE_SAFETY', $e->getMessage());
        }
        // the cheap model when asked for; nothing without a key
        $cheap = new GeminiImageGenerator(['api_key' => 'AQ.test-key', 'cheap' => true]);
        $this->assertSame('gemini-3.1-flash-lite-image', $cheap->fromText('a dog', 'line', 'square')->model);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'gemini-3.1-flash-lite-image:generateContent'));
        $this->assertFalse((new GeminiImageGenerator([]))->available());
        $this->assertStringContainsString('line drawing', GeminiImageGenerator::prompt('a dog', 'line', 'square'));
        $this->assertStringContainsString('flat solid colours', GeminiImageGenerator::prompt('a dog', 'colour', 'square'));
    }

    public function test_a_silhouette_becomes_pure_black_and_white_and_colour_stays(): void
    {
        // a grey ramp: everything darker than the middle becomes black, the rest white; a colour picture becomes a JPEG
        $img = imagecreatetruecolor(256, 20);
        for ($x = 0; $x < 256; $x++) {
            imageline($img, $x, 0, $x, 19, imagecolorallocate($img, $x, $x, $x));
        }
        ob_start();
        imagepng($img);
        $ramp = new ImageResult((string) ob_get_clean(), 'image/png', 'test');
        $tmp = tempnam(sys_get_temp_dir(), 'mp_t');
        $this->assertSame('png', ImageMaker::prepare($ramp, 'silhouette', $tmp));
        $out = imagecreatefromstring((string) file_get_contents($tmp));
        $this->assertSame(0x000000, imagecolorat($out, 10, 5) & 0xFFFFFF);
        $this->assertSame(0x000000, imagecolorat($out, 127, 5) & 0xFFFFFF);
        $this->assertSame(0xFFFFFF, imagecolorat($out, 130, 5) & 0xFFFFFF);          // GD's grey of 128 rounds to 127
        $this->assertSame(0xFFFFFF, imagecolorat($out, 250, 5) & 0xFFFFFF);
        $this->assertSame('jpg', ImageMaker::prepare($ramp, 'colour', $tmp));
        $this->assertSame('image/jpeg', getimagesize($tmp)['mime']);
        // a big picture is brought down to 1600 px
        $big = imagecreatetruecolor(3200, 1600);
        ob_start();
        imagejpeg($big);
        $this->assertSame('png', ImageMaker::prepare(new ImageResult((string) ob_get_clean(), 'image/jpeg', 'test'), 'line', $tmp));
        $this->assertSame([1600, 800], array_slice(getimagesize($tmp), 0, 2));
        @unlink($tmp);
    }

    public function test_listing_texts_come_from_the_assistant_within_the_platform_limits(): void
    {
        $this->get('/tools/listing')->assertOk()->assertSee(__('tools.listing.title'))->assertSee('data-sell="listing"', false)->assertSee('name="materials"', false)->assertSee(__('sell.platform.fler'));
        $this->get('/en/tools/listing')->assertOk()->assertSee('Write the listing');
        $this->get('/es/tools/listing')->assertOk()->assertSee('Escribir el anuncio');
        // a model of the catalogue fills the form in
        $model = CatalogModel::create(['slug' => 'stojanek-na-telefon', 'title' => 'Stojánek na telefon', 'description' => ['cs' => 'Drží telefon i tablet.'], 'source_locale' => 'cs', 'visible' => true]);
        $this->get('/tools/listing?model=stojanek-na-telefon')->assertOk()->assertSee('Stojánek na telefon')->assertSee('Drží telefon i tablet.')->assertSee(__('sell.listing.from_model'));
        $this->get('/tools/listing?model=neni')->assertOk()->assertDontSee(__('sell.listing.from_model'));
        // the answer is cut to the platform's limits: Etsy, 140 characters and 13 tags of 20
        $tags = array_map(fn ($i) => 'štítek číslo '.$i.' a ještě něco navíc', range(1, 16));
        FakeAssistant::$answers['listing'] = ['title' => str_repeat('Stojánek na telefon se jménem ', 8), 'description' => "První odstavec.\n\nDruhý odstavec.", 'tags' => $tags, 'materials' => ['PLA', 'PETG'], 'keywords' => ['stojánek na telefon', 'dárek'], 'alt' => 'Černý stojánek s telefonem.'];
        $r = $this->post('/api/tools/listing', ['what' => 'Stojánek na telefon se jménem', 'materials' => ['pla', 'petg'], 'size' => '120 mm', 'platform' => 'etsy', 'language' => 'cs', 'tone' => 'warm'], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(140, mb_strlen($r->json('title')));
        $this->assertCount(13, $r->json('tags'));
        $this->assertSame(20, max(array_map('mb_strlen', $r->json('tags'))));
        $this->assertSame(['PLA', 'PETG'], $r->json('materials'));
        $this->assertSame((int) config('ai.daily_limits.listing', 5) - 1, $r->json('left'));
        $this->assertSame('listing', FakeAssistant::$calls[0]['kind']);
        $this->assertStringContainsString('Etsy', FakeAssistant::$calls[0]['system']);
        $this->assertStringContainsString('warm and personal', FakeAssistant::$calls[0]['system']);
        $this->assertStringContainsString('"petg"', FakeAssistant::$calls[0]['user']);
        $this->assertSame([], FakeAssistant::$calls[0]['images']);
        $this->assertSame(1, AiCall::where('kind', 'listing')->count());
        // a photo goes to the assistant as a file and is gone afterwards; Fler's title is 60
        $r = $this->post('/api/tools/listing', ['what' => 'Váza na sušené květiny', 'platform' => 'fler', 'photo' => UploadedFile::fake()->image('vaza.jpg', 400, 300)], ['Accept' => 'application/json'])->assertOk();
        $this->assertCount(1, FakeAssistant::$calls[1]['images']);
        $this->assertStringContainsString('tmp/listing', str_replace('\\', '/', FakeAssistant::$calls[1]['images'][0]));
        $this->assertFileDoesNotExist(FakeAssistant::$calls[1]['images'][0]);
        $this->assertStringContainsString('photo of the thing is attached', FakeAssistant::$calls[1]['user']);
        $this->assertSame(60, mb_strlen($r->json('title')));
        $this->assertSame('fler', $r->json('platform'));
        // the daily count, the validation, the assistant away
        config(['ai.daily_limits.listing' => 2]);
        $this->post('/api/tools/listing', ['what' => 'Váza'], ['Accept' => 'application/json'])->assertStatus(429)->assertJsonPath('error', 'daily_limit');
        $this->assertCount(2, FakeAssistant::$calls);
        $this->post('/api/tools/listing', ['what' => 'x'], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/api/tools/listing', ['what' => 'Váza', 'platform' => 'ebay'], ['Accept' => 'application/json'])->assertStatus(422);
        $this->app->instance(Assistant::class, new ClaudeAssistant(['api_key' => '']));
        $this->get('/tools/listing')->assertOk()->assertSee('note-warn', false);
        $this->post('/api/tools/listing', ['what' => 'Váza na sušené květiny'], ['Accept' => 'application/json'])->assertStatus(503);
        $this->assertSame(1, CatalogModel::count(), $model->slug);
    }

    public function test_a_product_photo_loses_its_background_and_the_cut_out_lasts_a_day(): void
    {
        $this->get('/tools/photo')->assertOk()->assertSee(__('tools.photo.title'))->assertSee('data-sell="photo"', false)->assertSee('id="photo-input"', false)->assertSee('wood.jpg');
        $this->get('/en/tools/photo')->assertOk()->assertSee('Download all');
        $this->get('/es/tools/photo')->assertOk()->assertSee('Descargar todas');
        foreach (StudioToolsController::TEXTURES as $k) {
            $this->assertFileExists(public_path('img/backgrounds/'.$k.'.jpg'));
        }
        $r = $this->post('/api/tools/photo', ['photo' => UploadedFile::fake()->image('vaza.jpg', 600, 400)], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame([400, 267], [$r->json('width'), $r->json('height')]);
        $this->assertEqualsWithDelta(0.6, $r->json('coverage'), 0.05);
        $this->assertCount(1, FakeBackgroundRemover::$calls);
        // the cut-out: transparent in the corner, the thing in the middle
        $img = imagecreatefromstring((string) file_get_contents($this->get($r->json('url'))->assertOk()->baseResponse->getFile()->getPathname()));
        $this->assertSame(127, (imagecolorat($img, 2, 2) >> 24) & 0x7F);
        $this->assertSame(0, (imagecolorat($img, 200, 133) >> 24) & 0x7F);
        // nothing in the photo, the engine failing, not a picture
        FakeBackgroundRemover::$fail = 'photo_cut.py: nothing_found';
        $this->post('/api/tools/photo', ['photo' => UploadedFile::fake()->image('x.jpg', 50, 50)], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('error', 'nothing_found');
        FakeBackgroundRemover::$fail = 'boom';
        $this->post('/api/tools/photo', ['photo' => UploadedFile::fake()->image('x.jpg', 50, 50)], ['Accept' => 'application/json'])->assertStatus(502);
        FakeBackgroundRemover::$fail = null;
        $this->post('/api/tools/photo', ['photo' => UploadedFile::fake()->create('x.txt', 10, 'text/plain')], ['Accept' => 'application/json'])->assertStatus(422);
        // the cut-out is pruned after a day
        $path = PhotoCut::path($r->json('id'));
        $this->assertNotNull($path);
        $this->assertSame(0, PhotoCut::prune());
        touch($path, time() - 25 * 3600);
        $this->assertSame(1, PhotoCut::prune(true));
        $this->assertSame(1, PhotoCut::prune());
        $this->assertNull(PhotoCut::path($r->json('id')));
        $this->get($r->json('url'))->assertNotFound();
        $this->get('/api/tools/photo/not-an-id')->assertNotFound();
        // no engine: the page says so, the API refuses
        $this->app->instance(BackgroundRemover::class, new NoBackgroundRemover);
        $this->get('/tools/photo')->assertOk()->assertSee('note-warn', false)->assertSee(__('sell.photo.unavailable'));
        $this->post('/api/tools/photo', ['photo' => UploadedFile::fake()->image('x.jpg', 50, 50)], ['Accept' => 'application/json'])->assertStatus(503);
    }

    public function test_the_rembg_runner_passes_the_model_home_and_reads_the_json(): void
    {
        Process::fake([
            '*--probe*' => Process::sequence()->push('{"ok": true, "rembg": "2.0.85", "model_present": true}')->push('{"ok": false, "error": "No module named rembg"}'),
            '*photo_cut.py*' => Process::sequence()->push('{"ok": true, "width": 10, "height": 12, "coverage": 0.5}')->push('{"ok": false, "error": "nothing_found"}'),
        ]);
        $r = new RembgBackgroundRemover(['bin' => 'python3', 'timeout' => 10, 'photo_home' => '/opt/matplace-py/u2net']);
        $this->assertTrue($r->available());
        $this->assertSame(['width' => 10, 'height' => 12, 'coverage' => 0.5], $r->cut('/tmp/a.jpg', '/tmp/b.png'));
        Process::assertRan(fn ($process) => ($process->environment['U2NET_HOME'] ?? null) === '/opt/matplace-py/u2net' && str_contains(implode(' ', (array) $process->command), '--probe'));
        Process::assertRan(fn ($process) => ($process->environment['U2NET_HOME'] ?? null) === '/opt/matplace-py/u2net' && str_contains(implode(' ', (array) $process->command), '/tmp/a.jpg /tmp/b.png'));
        // the answer of the probe is kept an hour; a failing cut is an engine error with the script's reason
        $this->assertTrue($r->available());
        try {
            $r->cut('/tmp/a.jpg', '/tmp/b.png');
            $this->fail('no exception');
        } catch (EngineException $e) {
            $this->assertStringContainsString('nothing_found', $e->getMessage());
        }
        Cache::flush();
        $this->assertFalse((new RembgBackgroundRemover(['bin' => 'python3']))->available());
    }
}
