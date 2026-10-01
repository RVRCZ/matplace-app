<?php

namespace Tests\Feature;

use App\Domain\Designer\DesignerProfiles;
use App\Domain\Farm\OrderFlow;
use App\Domain\Farm\Wallet;
use App\Engines\Translate\FakeTranslator;
use App\Engines\Translate\Translator;
use App\Models\AiCall;
use App\Models\CatalogCategory;
use App\Models\CatalogModel;
use App\Models\Collection;
use App\Models\CreditTransaction;
use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use App\Models\Event;
use App\Models\FarmOrder;
use App\Models\Payment;
use App\Models\User;
use App\Support\LanguageGuess;
use Database\Seeders\FarmSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Two catalogues: /models (designers' models the farm prints, with the designer's reward) and /model/{slug}
 * (the old site's catalogue of models that live elsewhere, with a call to print only where the licence allows it).
 */
class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $designerUser;

    private DesignerProfile $designer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Storage::fake('public');
        Mail::fake();
        FakeTranslator::reset();
        $this->seed(FarmSeeder::class);
        $this->customer = User::factory()->create(['email' => 'customer@example.com']);
        $this->designerUser = User::factory()->create(['name' => 'Jana Nováková', 'country' => 'CZ']);
        $this->designer = app(DesignerProfiles::class)->enable($this->designerUser);
        $this->designer->forceFill(['visible' => true, 'published_at' => now()])->save();
    }

    /** A card with an uploaded, checked file: printable. */
    private function card(string $title = 'Stojánek na telefon', float $mm = 40, array $attributes = []): DesignerModel
    {
        $card = DesignerModel::create($attributes + ['designer_profile_id' => $this->designer->id, 'title' => $title, 'slug' => DesignerModel::makeSlug($title), 'description' => ['cs' => 'Stojánek, který drží.', 'en' => 'A stand that holds.']]);
        $path = sys_get_temp_dir().'/mp_cat_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, $mm);
        $this->actingAs($this->designerUser)->post("/account/designer/models/{$card->id}/file", ['file' => new UploadedFile($path, 'model.stl', null, null, true), 'author' => 1])->assertSessionHas('status');
        $this->post('/logout');

        return $card->refresh();
    }

    private function credit(User $user, int $amount): void
    {
        $this->actingAs($user)->post('/account/credit', ['amount' => $amount])->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();
        $this->postJson('/webhooks/payments/fake', ['ref' => $payment->gateway_ref, 'paid' => true], ['X-Fake-Signature' => 'fake'])->assertOk();
    }

    private function orderCard(User $user, DesignerModel $card, array $extra = []): FarmOrder
    {
        $r = $this->actingAs($user)->postJson('/farm/orders', ['file' => $card->modelFile->uuid, 'designer_model' => $card->id] + $extra)->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    private function pay(User $user, FarmOrder $order): array
    {
        $state = $this->actingAs($user)->getJson("/farm/orders/{$order->token}/status")->json();
        $this->actingAs($user)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total']])->assertOk();

        return $state['colors'][0]['price'];
    }

    private function finish(FarmOrder $order): FarmOrder
    {
        $flow = app(OrderFlow::class);
        $flow->move($order->refresh(), FarmOrder::STATUS_PRINTING, 'admin');

        return $flow->plateFinished($order, 'admin')->refresh();
    }

    // ── the catalogue of models the farm prints ──────────────────────────────

    public function test_models_lists_only_cards_the_farm_can_print(): void
    {
        $stand = $this->card('Stojánek na telefon', 40);
        $big = $this->card('Velká váza', 180, ['download_allowed' => true, 'download_license' => 'cc_by']);
        DesignerModel::create(['designer_profile_id' => $this->designer->id, 'title' => 'Jen odkaz', 'slug' => 'jen-odkaz', 'external_url' => 'https://www.printables.com/model/5-x']);
        $category = CatalogCategory::create(['slug' => 'domacnost', 'name' => ['cs' => 'Domácnost', 'en' => 'Household']]);
        $stand->update(['catalog_category_id' => $category->id]);

        $page = $this->get('/models')->assertOk();
        $page->assertSee('Stojánek na telefon')->assertSee('Velká váza')->assertDontSee('Jen odkaz')->assertSee('Jana Nováková')
            ->assertSee(route('models.show', 'stojanek-na-telefon'), false)->assertSee('Domácnost');
        $this->assertMatchesRegularExpression('/od \d[\d\s]*\sKč/u', $page->getContent(), 'a price "from" on the tiles');

        // filters: size by the longest side, category, what can be downloaded
        $this->get('/models?size=s')->assertOk()->assertSee('Stojánek na telefon')->assertDontSee('Velká váza');
        $this->get('/models?size=l')->assertOk()->assertSee('Velká váza')->assertDontSee('Stojánek na telefon');
        $this->get('/models?category=domacnost')->assertOk()->assertSee('Stojánek na telefon')->assertDontSee('Velká váza');
        $this->get('/models?download=1')->assertOk()->assertSee('Velká váza')->assertDontSee('Stojánek na telefon');
        $this->get('/en/models')->assertOk()->assertSee(__('models.title', [], 'en'))->assertSee('Household');

        // the portfolio now leads to the model's own page; a hidden profile takes its models out of the catalogue
        $this->get('/d/jana-novakova')->assertOk()->assertSee(route('models.show', 'stojanek-na-telefon'), false);
        $this->designer->update(['visible' => false]);
        $this->get('/models')->assertOk()->assertDontSee('Stojánek na telefon');
        $this->get('/models/stojanek-na-telefon')->assertNotFound();
    }

    public function test_a_model_page_shows_the_price_with_the_reward_apart_and_follows_the_choice(): void
    {
        $card = $this->card('Stojánek na telefon', 40, ['royalty_czk' => 25]);

        $page = $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/140')->get('/models/stojanek-na-telefon')->assertOk();
        $page->assertSee('Stojánek na telefon')->assertSee('Stojánek, který drží.')->assertSee('Jana Nováková')
            ->assertSee(__('models.price.to_author'))->assertSee("data-quote-royalty>25\u{00A0}Kč<", false)
            ->assertSee(route('farm.start', ['designer_model' => $card->id]), false)
            ->assertSee('"@type":"Product"', false)->assertSee('"priceCurrency":"CZK"', false)->assertSee('"@type":"Person"', false)
            ->assertSee(__('models.facts.print_only'))->assertDontSee(__('models.download.for_printer'));
        $this->flushHeaders();
        $this->assertSame(1, $card->refresh()->view_count);
        $this->assertSame(1, Event::where('type', 'view')->where('subject_type', 'designer_model')->count());
        $this->get('/en/models/stojanek-na-telefon')->assertOk()->assertSee('A stand that holds.');
        // no Spanish text: no Spanish page, and the 404 points to the Czech one
        $this->get('/es/models/stojanek-na-telefon')->assertNotFound()->assertSee('id="czech-version" href="'.url('/models/stojanek-na-telefon').'"', false);

        // the quote: print + reward; three pieces cost more and the reward is per piece
        $one = $this->getJson('/api/models/stojanek-na-telefon/quote')->assertOk()->json();
        $three = $this->getJson('/api/models/stojanek-na-telefon/quote?copies=3')->assertOk()->json();
        $this->assertTrue($one['available']);
        $this->assertSame(25.0, (float) $one['royalty']);
        $this->assertSame((float) $one['print'] + 25.0, (float) $one['total']);
        $this->assertGreaterThan($one['print'], $three['print']);
        // three pieces share the order's fixed fee, so one piece is cheaper and the 30 % cap may bite sooner
        $this->assertSame(3 * (float) floor(min(25, 0.30 * $three['print'] / 3)), (float) $three['royalty']);
        $this->assertSame((float) ($three['print'] + $three['royalty']), (float) $three['total']);
        // the designer looking at their own model sees no reward in the price
        $this->assertSame(0.0, (float) $this->actingAs($this->designerUser)->getJson('/api/models/stojanek-na-telefon/quote')->json('royalty'));
    }

    public function test_the_file_of_a_card_goes_out_only_when_its_designer_allows_it(): void
    {
        $card = $this->card('Stojánek na telefon', 40);
        $uuid = $card->modelFile->uuid;

        // print only: nobody but the designer and the operators gets the geometry, whatever address they try
        $this->get("/api/files/{$uuid}/model.stl")->assertForbidden();
        $this->actingAs($this->customer)->get("/api/files/{$uuid}/model.stl")->assertForbidden();
        $this->actingAs($this->customer)->get("/api/files/{$uuid}/project.3mf?printer=prusa-mk4s")->assertForbidden();
        $this->actingAs($this->customer)->postJson("/api/files/{$uuid}/repair")->assertForbidden();
        $this->actingAs($this->designerUser)->get("/api/files/{$uuid}/model.stl")->assertOk();
        // an ordinary upload is not touched by any of this
        $own = sys_get_temp_dir().'/mp_own_'.uniqid().'.stl';
        MeshFixtures::cubeStl($own, 20);
        $mine = $this->actingAs($this->customer)->postJson('/api/uploads', ['file' => new UploadedFile($own, 'a.stl', null, null, true)])->json('file.uuid');
        $this->post('/logout');
        $this->get("/api/files/{$mine}/model.stl")->assertOk();

        // a customer who orders the model sees its picture, not its file
        $order = $this->orderCard($this->customer, $card);
        $state = $this->actingAs($this->customer)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertNull($state['model_url']);
        $this->actingAs($this->customer)->get("/farm/orders/{$order->token}/model.stl")->assertForbidden();
        $this->actingAs($this->customer)->get("/farm/orders/{$order->token}")->assertOk();
        $this->actingAs($this->customer)->get('/farm?designer_model='.$card->id)->assertOk()->assertSee(__('models.farm.no_preview'))->assertSee('name="designer_model" value="'.$card->id.'"', false);

        // the designer allows a free download under a licence: page, project and STL open up, and downloads are counted
        $card->update(['download_allowed' => true, 'download_license' => 'cc_by_nc']);
        $this->post('/logout');
        $this->get('/models/stojanek-na-telefon')->assertOk()->assertSee(__('models.download.for_printer'))->assertSee('data-pick-printer="'.$uuid.'"', false)->assertSee(__('designer.license.cc_by_nc'));
        $this->get("/api/files/{$uuid}/model.stl")->assertOk();
        $this->assertSame(0, Event::where('type', 'download')->count(), 'the 3D preview fetching the STL is not a download');
        $this->get("/api/files/{$uuid}/model.stl?download=1")->assertOk();
        $this->get("/api/files/{$uuid}/project.3mf?printer=prusa-mk4s")->assertOk();
        $downloads = Event::where('type', 'download')->orderBy('id')->get();
        $this->assertSame([['designer_model', $card->id, 'stl'], ['designer_model', $card->id, '3mf']], $downloads->map(fn ($e) => [$e->subject_type, $e->subject_id, $e->meta['kind']])->all());
    }

    // ── the reward ───────────────────────────────────────────────────────────

    public function test_an_order_from_the_catalogue_carries_the_reward_and_done_credits_it(): void
    {
        $card = $this->card('Stojánek na telefon', 40, ['royalty_czk' => 25]);
        $this->credit($this->customer, 2000);

        $order = $this->orderCard($this->customer, $card);
        $this->assertSame([$card->id, FarmOrder::STATUS_SLICED], [$order->designer_model_id, $order->status]);
        $price = $this->pay($this->customer, $order);
        $order->refresh();
        // the customer sees print, reward and total; the reward per piece is frozen on the order
        $this->assertSame(25.0, (float) $price['royalty_unit']);
        $this->assertSame(25.0, (float) $price['royalty']);
        $this->assertSame((float) $price['print_total'] + 25.0, (float) $price['total']);
        $this->assertSame([25.0, (float) $price['total']], [$order->royalty_czk, $order->price_total]);
        $this->assertSame(2000.0 - $order->price_total, app(Wallet::class)->balance($this->customer)->amount);
        // nothing for the designer before the print is done
        $this->assertSame(0.0, app(Wallet::class)->balance($this->designerUser)->amount);

        // a later change of the card's reward does not touch an order already made
        $card->update(['royalty_czk' => 5]);
        $this->finish($order);
        $royalty = CreditTransaction::where('type', CreditTransaction::TYPE_ROYALTY)->sole();
        $this->assertSame([$this->designerUser->id, 25.0, 'CZK', $order->id, $card->id], [$royalty->user_id, $royalty->amount, $royalty->currency, $royalty->farm_order_id, $royalty->designer_model_id]);
        $this->assertSame(25.0, app(Wallet::class)->balance($this->designerUser)->amount);
        $this->assertSame('CZK', $this->designerUser->refresh()->currency, 'the first reward fixes the currency of the account');
        $this->assertSame(1, $card->refresh()->order_count);
        // done twice (a repeated report) credits once
        app(Wallet::class)->creditRoyalty($order);
        $this->assertSame(1, CreditTransaction::where('type', CreditTransaction::TYPE_ROYALTY)->count());

        // the designer sees it
        $this->actingAs($this->designerUser)->get('/account/designer')->assertOk()->assertSee(__('designer.rewards.title'))->assertSee($order->number);

        // the admin refunds the finished print: the reward goes back, once
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        $this->actingAs($admin)->post("/admin/farm/orders/{$order->token}/refund", ['note' => 'reklamace'])->assertRedirect();
        $reversal = CreditTransaction::where('type', CreditTransaction::TYPE_ROYALTY_REVERSAL)->sole();
        $this->assertSame([-25.0, $this->designerUser->id, $admin->id], [$reversal->amount, $reversal->user_id, $reversal->created_by]);
        $this->assertSame(0.0, app(Wallet::class)->balance($this->designerUser)->amount);
        $this->assertSame(2000.0, app(Wallet::class)->balance($this->customer)->amount);
        app(Wallet::class)->giveBack($order, $admin->id, 'again');
        $this->assertSame(1, CreditTransaction::where('type', CreditTransaction::TYPE_ROYALTY_REVERSAL)->count());
    }

    public function test_the_reward_is_capped_at_thirty_percent_of_the_print_price_of_one_piece(): void
    {
        $card = $this->card('Drobnost', 12, ['royalty_czk' => 400]);
        $this->credit($this->customer, 2000);
        $order = $this->orderCard($this->customer, $card);
        $price = $this->pay($this->customer, $order);

        $this->assertSame((float) floor(0.30 * $price['print_total']), (float) $price['royalty_unit']);
        $this->assertLessThan(400, $price['royalty_unit']);
        $this->assertSame((float) $price['royalty_unit'], (float) $order->refresh()->royalty_czk);
        // a cancelled order gives everything back and no reward ever appears
        $this->actingAs($this->customer)->postJson("/farm/orders/{$order->token}/cancel")->assertOk();
        $this->assertSame(2000.0, app(Wallet::class)->balance($this->customer)->amount);
        $this->assertSame(0, CreditTransaction::whereIn('type', [CreditTransaction::TYPE_ROYALTY, CreditTransaction::TYPE_ROYALTY_REVERSAL])->count());
    }

    public function test_a_designer_printing_their_own_model_pays_no_reward_and_a_foreign_one_is_paid_in_euros(): void
    {
        $card = $this->card('Stojánek na telefon', 40, ['royalty_czk' => 25]);
        $this->credit($this->designerUser, 1000);
        $own = $this->orderCard($this->designerUser, $card);
        $price = $this->pay($this->designerUser, $own);
        $this->assertSame(0.0, (float) $price['royalty']);
        $this->assertSame((float) $price['print_total'], (float) $price['total']);
        $this->finish($own);
        $this->assertSame(0, CreditTransaction::where('type', CreditTransaction::TYPE_ROYALTY)->count());
        $this->assertSame(0, $card->refresh()->order_count);

        // a designer in Spain who never paid anything: the reward comes in EUR at the fixed rate and fixes the currency
        $spaniard = User::factory()->create(['name' => 'Carlos', 'country' => 'ES']);
        $this->designer->update(['user_id' => $spaniard->id]);
        $this->credit($this->customer, 1000);
        $order = $this->orderCard($this->customer, $card->refresh());
        $this->pay($this->customer, $order);
        $this->finish($order);
        $royalty = CreditTransaction::where('type', CreditTransaction::TYPE_ROYALTY)->sole();
        $this->assertSame([$spaniard->id, 'EUR', 1.0], [$royalty->user_id, $royalty->currency, $royalty->amount], '25 CZK at 25 CZK per euro');
        $this->assertSame('EUR', $spaniard->refresh()->currency);
    }

    // ── the inspiration catalogue ────────────────────────────────────────────

    /** An "old site" database with five models, in a file of its own. */
    private function legacy(): string
    {
        $file = sys_get_temp_dir().'/mp_legacy_'.uniqid().'.sqlite';
        touch($file);
        config(['database.connections.legacy_test' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => false]]);
        $schema = Schema::connection('legacy_test');
        $schema->create('categories', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('slug');
            $t->unsignedInteger('parent_id')->nullable();
            $t->integer('sort_order')->default(0);
            $t->string('name_en')->nullable();
            $t->boolean('hidden')->default(false);
        });
        $schema->create('models', function (Blueprint $t) {
            $t->increments('id');
            $t->string('title');
            $t->string('slug');
            $t->text('description')->nullable();
            $t->unsignedInteger('category_id')->nullable();
            $t->unsignedInteger('collection_id')->nullable();
            $t->unsignedInteger('collection_order')->nullable();
            $t->string('source')->default('other');
            $t->boolean('nsfw')->default(false);
            $t->string('external_url')->nullable();
            $t->string('thumbnail')->nullable();
            $t->string('tags')->nullable();
            $t->text('keywords_cs')->nullable();
            $t->string('status')->default('active');
            $t->unsignedInteger('view_count')->default(0);
            $t->string('license')->default('free_personal');
            $t->string('author_name')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
        $schema->create('model_images', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('model_id');
            $t->string('path');
            $t->boolean('is_main')->default(false);
            $t->integer('sort_order')->default(0);
        });
        $schema->create('model_collections', function (Blueprint $t) {
            $t->increments('id');
            $t->string('title');
            $t->string('slug');
            $t->text('description')->nullable();
        });
        $db = DB::connection('legacy_test');
        $db->table('categories')->insert([
            ['id' => 5, 'name' => 'Domácnost', 'slug' => 'domacnost', 'parent_id' => null, 'name_en' => 'Household', 'hidden' => 0],
            ['id' => 6, 'name' => 'Kuchyně', 'slug' => 'kuchyne', 'parent_id' => 5, 'name_en' => 'Kitchen', 'hidden' => 0],
        ]);
        $db->table('model_collections')->insert(['id' => 3, 'title' => 'Do kuchyně', 'slug' => 'do-kuchyne', 'description' => 'Věci do kuchyně.']);
        $db->table('models')->insert([
            ['id' => 11, 'title' => 'Phone stand', 'slug' => 'phone-stand', 'description' => 'A simple stand for the phone.&nbsp;It prints without supports and holds the phone well.', 'category_id' => 5, 'collection_id' => null, 'collection_order' => null, 'source' => 'printables', 'nsfw' => 0, 'external_url' => 'https://www.printables.com/model/100-phone-stand', 'thumbnail' => '9e/stand.jpg', 'tags' => '["phone","desk"]', 'keywords_cs' => 'stojanek telefon', 'status' => 'active', 'view_count' => 300, 'license' => 'cc_by', 'author_name' => 'jana'],
            ['id' => 12, 'title' => 'Držák na houbičku', 'slug' => 'drzak-na-houbicku', 'description' => 'Držák na houbičku, který se přilepí na dřez a nepřekáží.', 'category_id' => 6, 'collection_id' => 3, 'collection_order' => 1, 'source' => 'makerworld', 'nsfw' => 0, 'external_url' => 'https://makerworld.com/en/models/777-holder', 'thumbnail' => 'https://cdn.example.com/x.jpg', 'tags' => 'kuchyň, dřez', 'keywords_cs' => null, 'status' => 'active', 'view_count' => 20, 'license' => 'cc_by_nc', 'author_name' => 'petr'],
            ['id' => 13, 'title' => 'Hidden thing', 'slug' => 'hidden-thing', 'description' => null, 'category_id' => null, 'collection_id' => null, 'collection_order' => null, 'source' => 'cults3d', 'nsfw' => 0, 'external_url' => null, 'thumbnail' => null, 'tags' => null, 'keywords_cs' => null, 'status' => 'hidden', 'view_count' => 0, 'license' => 'free_personal', 'author_name' => null],
            ['id' => 14, 'title' => 'Adult thing', 'slug' => 'adult-thing', 'description' => null, 'category_id' => null, 'collection_id' => null, 'collection_order' => null, 'source' => 'cults3d', 'nsfw' => 1, 'external_url' => null, 'thumbnail' => null, 'tags' => null, 'keywords_cs' => null, 'status' => 'active', 'view_count' => 0, 'license' => 'free_personal', 'author_name' => null],
            ['id' => 15, 'title' => 'Cookie cutter', 'slug' => 'cookie-cutter', 'description' => 'A cutter for cookies.', 'category_id' => 6, 'collection_id' => 3, 'collection_order' => 0, 'source' => 'makeronline', 'nsfw' => 0, 'external_url' => 'https://makeronline.com/model/9', 'thumbnail' => null, 'tags' => null, 'keywords_cs' => null, 'status' => 'active', 'view_count' => 5, 'license' => 'cc0', 'author_name' => null],
        ]);
        $db->table('model_images')->insert([['model_id' => 11, 'path' => '9e/stand.jpg', 'is_main' => 1, 'sort_order' => 0], ['model_id' => 11, 'path' => '9e/stand-2.jpg', 'is_main' => 0, 'sort_order' => 1]]);

        // the old site's thumbnails on the disk
        $thumbs = sys_get_temp_dir().'/mp_thumbs_'.uniqid();
        mkdir($thumbs.'/9e', 0777, true);
        imagejpeg(imagecreatetruecolor(40, 30), $thumbs.'/9e/stand.jpg');
        imagejpeg(imagecreatetruecolor(40, 30), $thumbs.'/9e/stand-2.jpg');

        return $thumbs;
    }

    public function test_the_old_catalogue_is_imported_with_its_addresses_and_can_be_imported_again(): void
    {
        $thumbs = $this->legacy();
        $run = fn (array $options = []) => $this->artisan('matplace:import-catalog', ['--connection' => 'legacy_test', '--thumbs' => $thumbs] + $options)->assertSuccessful();

        $run(['--dry-run' => true]);
        $this->assertSame([0, 0, 0], [CatalogModel::count(), CatalogCategory::count(), Collection::count()], 'a dry run writes nothing');

        $run();
        // three of five: the hidden one and the adult one stay behind
        $this->assertSame(['cookie-cutter', 'drzak-na-houbicku', 'phone-stand'], CatalogModel::orderBy('slug')->pluck('slug')->all());
        $stand = CatalogModel::where('slug', 'phone-stand')->firstOrFail();
        $this->assertSame(['en', false, 'cc_by', 300, 'jana', '100', ['phone', 'desk']], [$stand->source_locale, $stand->license_restricted, $stand->license, $stand->view_count, $stand->author_name, $stand->external_id, $stand->tags]);
        $this->assertSame(['en' => 'A simple stand for the phone. It prints without supports and holds the phone well.'], $stand->description);
        $this->assertSame(['catalog/9e/stand.jpg', ['catalog/9e/stand-2.jpg']], [$stand->thumbnail_path, $stand->images]);
        Storage::disk('public')->assertExists(['catalog/9e/stand.jpg', 'catalog/9e/stand-2.jpg']);
        $holder = CatalogModel::where('slug', 'drzak-na-houbicku')->firstOrFail();
        $this->assertSame(['cs', true, 'https://cdn.example.com/x.jpg', null], [$holder->source_locale, $holder->license_restricted, $holder->preview_url, $holder->thumbnail_path]);
        // categories keep their tree and both names
        $kitchen = CatalogCategory::where('slug', 'kuchyne')->firstOrFail();
        $this->assertSame(['Kuchyně', 'Kitchen', 'domacnost'], [$kitchen->name['cs'], $kitchen->name['en'], $kitchen->parent->slug]);
        $this->assertSame($kitchen->id, $holder->category_id);
        // the collection with its two models in the old order
        $collection = Collection::where('slug', 'do-kuchyne')->firstOrFail();
        $this->assertSame(['cookie-cutter', 'drzak-na-houbicku'], $collection->items->map(fn ($i) => $i->catalogModel->slug)->all());

        // translations made here survive the next import; nothing is doubled; what the old site hid goes away here too
        $stand->update(['description' => $stand->description + ['cs' => 'Jednoduchý stojánek.'], 'view_count' => 999]);
        DB::connection('legacy_test')->table('models')->where('id', 15)->update(['status' => 'hidden']);
        DB::connection('legacy_test')->table('models')->where('id', 11)->update(['title' => 'Phone stand v2']);
        $run();
        $this->assertSame([3, 2, 1, 2], [CatalogModel::count(), CatalogCategory::count(), Collection::count(), CatalogModel::where('visible', true)->count()]);
        $stand->refresh();
        $this->assertSame(['Phone stand v2', 'Jednoduchý stojánek.', 999], [$stand->title, $stand->description['cs'], $stand->view_count]);
        $this->assertSame(['drzak-na-houbicku'], $collection->refresh()->items->map(fn ($i) => $i->catalogModel->slug)->all());

        // the pages, at the old addresses
        $this->get('/katalog')->assertStatus(301)->assertRedirect('/model');
        $this->get('/model')->assertOk()->assertSee('Phone stand v2')->assertSee('Držák na houbičku')->assertDontSee('Cookie cutter')->assertSee('Domácnost');
        $this->get('/model/kategorie/kuchyne')->assertOk()->assertSee('Držák na houbičku')->assertDontSee('Phone stand v2');
        $this->get('/model/kategorie/domacnost')->assertOk()->assertSee('Držák na houbičku')->assertSee('Phone stand v2');
        $this->get('/model?q=desk')->assertOk()->assertSee('Phone stand v2')->assertDontSee('Držák na houbičku');
        $this->get('/model/cookie-cutter')->assertNotFound();
        $this->get('/model/hidden-thing')->assertNotFound();
    }

    public function test_a_model_page_calls_to_print_only_where_the_licence_allows_it(): void
    {
        $category = CatalogCategory::create(['slug' => 'domacnost', 'name' => ['cs' => 'Domácnost']]);
        $free = CatalogModel::create(['slug' => 'phone-stand', 'title' => 'Phone stand', 'description' => ['en' => 'A simple stand.', 'cs' => 'Jednoduchý stojánek.'], 'source_locale' => 'en', 'source' => 'printables', 'external_id' => '100', 'external_url' => 'https://www.printables.com/model/100-phone-stand', 'license' => 'cc_by', 'license_restricted' => false, 'author_name' => 'jana', 'category_id' => $category->id, 'tags' => ['phone']]);
        $closed = CatalogModel::create(['slug' => 'dragon', 'title' => 'Dragon', 'description' => ['cs' => 'Drak.'], 'source_locale' => 'cs', 'source' => 'makerworld', 'external_url' => 'https://makerworld.com/en/models/5-dragon', 'license' => 'cc_by_nc', 'license_restricted' => true, 'category_id' => $category->id]);

        $page = $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/140')->get('/model/phone-stand')->assertOk();
        $page->assertSee('Phone stand')->assertSee('Jednoduchý stojánek.')->assertSee('data-cta="print"', false)
            ->assertSee(route('farm.start', ['source' => $free->id]), false)->assertSee(__('models.inspiration.attribution'))
            ->assertSee('href="https://www.printables.com/model/100-phone-stand" rel="nofollow noopener"', false)
            ->assertSee(__('models.inspiration.similar'))->assertSee('Dragon')
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/model/phone-stand').'">', false);
        $this->assertStringNotContainsString('hreflang="es"', str_replace('hreflang="es" lang="es"', '', $page->getContent()), 'no Spanish text: no Spanish alternate');
        $this->assertSame(1, $free->refresh()->view_count);
        $this->flushHeaders();

        // non-commercial: no call to print, a link to what can be printed instead
        $nc = $this->get('/model/dragon')->assertOk();
        $nc->assertSee('data-cta="none"', false)->assertSee(__('models.inspiration.no_print'))->assertDontSee(__('models.inspiration.upload_print'))->assertSee(route('models.index'), false);
        $this->assertStringNotContainsString('<link rel="alternate"', $nc->getContent(), 'Czech only: no hreflang');

        // a page exists only in the languages it has a text in; the others point to the Czech one
        $this->get('/en/model/phone-stand')->assertOk()->assertSee('A simple stand.');
        $this->get('/en/model/dragon')->assertNotFound()->assertSee('id="czech-version" href="'.url('/model/dragon').'"', false);
        $this->get('/es/model/phone-stand')->assertNotFound();

        // the customer brings the file: the order's note names the model, its author, address and licence
        $this->actingAs($this->customer)->get('/farm?source='.$free->id)->assertOk()->assertSee($free->attribution())->assertSee('name="catalog_model" value="'.$free->id.'"', false);
        $this->actingAs($this->customer)->get('/farm?source='.$closed->id)->assertOk()->assertDontSee('name="catalog_model"', false);
        $path = sys_get_temp_dir().'/mp_src_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 30);
        $uuid = $this->actingAs($this->customer)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'stand.stl', null, null, true)])->json('file.uuid');
        $url = $this->actingAs($this->customer)->postJson('/farm/orders', ['file' => $uuid, 'catalog_model' => $free->id])->assertCreated()->json('url');
        $order = FarmOrder::where('token', basename($url))->firstOrFail();
        $this->assertSame([$free->id, 'Model: Phone stand, jana, https://www.printables.com/model/100-phone-stand, CC BY'], [$order->catalog_model_id, $order->note]);
        // … and the customer's own note is added to it, not written over it
        $this->credit($this->customer, 1000);
        $state = $this->actingAs($this->customer)->getJson("/farm/orders/{$order->token}/status")->json();
        $this->actingAs($this->customer)->postJson("/farm/orders/{$order->token}/pay", ['slot' => $state['colors'][0]['slot'], 'delivery' => 'pickup', 'terms' => true, 'expected_total' => $state['colors'][0]['total'], 'note' => 'Prosím černě.'])->assertOk();
        $this->assertSame("Model: Phone stand, jana, https://www.printables.com/model/100-phone-stand, CC BY\nProsím černě.", $order->refresh()->note);
        // a restricted model cannot be slipped in by hand
        $bad = $this->actingAs($this->customer)->postJson('/farm/orders', ['file' => $uuid, 'catalog_model' => $closed->id])->assertCreated()->json('url');
        $this->assertNull(FarmOrder::where('token', basename($bad))->firstOrFail()->catalog_model_id);

        // the author brings the model to matplace: the page offers their card instead
        $card = $this->card('Phone stand', 40, ['source' => 'printables', 'external_id' => '100', 'external_url' => 'https://www.printables.com/model/100-phone-stand', 'catalog_model_id' => $free->id]);
        $claimed = $this->get('/model/phone-stand')->assertOk();
        $claimed->assertSee('data-cta="author"', false)->assertSee($card->publicUrl(), false)->assertSee(__('models.inspiration.author_here'))->assertSee($this->designer->publicUrl(), false)
            ->assertDontSee(__('models.inspiration.upload_print'));
    }

    public function test_search_puts_what_the_farm_prints_first_and_marks_the_rest_as_inspiration(): void
    {
        $card = $this->card('Phone stand deluxe', 40);
        CatalogModel::create(['slug' => 'phone-stand', 'title' => 'Phone stand', 'description' => ['en' => 'A simple stand.'], 'source' => 'printables', 'external_url' => 'https://www.printables.com/model/100-phone-stand', 'license' => 'cc_by']);
        CatalogModel::create(['title' => 'Old index row phone stand', 'source' => 'makerworld', 'external_url' => 'https://makerworld.com/en/models/9']);

        $results = $this->postJson('/api/search', ['q' => 'phone stand'])->assertOk()->json('results');
        $local = array_values(array_filter($results, fn ($r) => $r['source'] === 'local'));
        $this->assertSame(['matplace', 'inspiration', 'makerworld'], array_column($local, 'origin'));
        $this->assertSame([$card->publicUrl(), route('catalog.show', 'phone-stand'), 'https://makerworld.com/en/models/9'], array_column($local, 'externalUrl'));
        $this->assertSame('Jana Nováková', $local[0]['authorName']);
        // the calculator page knows the words for both kinds of cards
        $this->get('/')->assertOk()->assertSee('models.search.label_inspiration', false);
    }

    public function test_translations_are_queued_only_for_printable_and_most_visited_models(): void
    {
        config(['catalog.translate_top' => 1]);
        $printable = CatalogModel::create(['slug' => 'a', 'title' => 'A', 'description' => ['en' => 'Free to print.'], 'source_locale' => 'en', 'license' => 'cc0', 'license_restricted' => false, 'view_count' => 1]);
        $popular = CatalogModel::create(['slug' => 'b', 'title' => 'B', 'description' => ['en' => 'Popular one.'], 'source_locale' => 'en', 'license' => 'cc_by_nc', 'license_restricted' => true, 'view_count' => 900]);
        $rest = CatalogModel::create(['slug' => 'c', 'title' => 'C', 'description' => ['en' => 'Nobody opens this.'], 'source_locale' => 'en', 'license' => 'cc_by_nc', 'license_restricted' => true, 'view_count' => 2]);
        $complete = CatalogModel::create(['slug' => 'd', 'title' => 'D', 'description' => ['cs' => 'a', 'en' => 'b', 'es' => 'c'], 'source_locale' => 'en', 'license' => 'cc0', 'license_restricted' => false]);

        $this->artisan('matplace:translate-catalog', ['--dry-run' => true])->expectsOutputToContain('Would queue 2 models')->assertSuccessful();
        $this->assertSame([], FakeTranslator::$calls);
        $this->artisan('matplace:translate-catalog')->assertSuccessful();

        $this->assertSame(['cs', 'en', 'es'], $printable->refresh()->locales());
        $this->assertSame('[es] Free to print.', $printable->description['es']);
        $this->assertSame(['cs', 'en', 'es'], $popular->refresh()->locales());
        $this->assertSame(['cs', 'en'], $rest->refresh()->locales(), 'Czech exists by its address, with the text of the source; nothing was translated');
        $this->assertSame(['en'], array_keys($rest->description));
        // one call per model, both missing languages at once, in the catalogue style, booked
        $this->assertCount(2, FakeTranslator::$calls);
        $this->assertSame([['cs', 'es'], 'en', Translator::STYLE_CATALOG], [FakeTranslator::$calls[0]['to'], FakeTranslator::$calls[0]['from'], FakeTranslator::$calls[0]['style']]);
        $this->assertSame(2, AiCall::where('kind', 'translate')->where('subject_type', 'catalog_model')->count());
        $this->assertSame(['cs' => 'a', 'en' => 'b', 'es' => 'c'], $complete->refresh()->description);
        // now the Spanish page of the printable model exists
        $this->get('/es/model/a')->assertOk()->assertSee('[es] Free to print.');
    }

    public function test_the_language_of_an_old_description_is_guessed_from_the_text(): void
    {
        $this->assertSame('cs', LanguageGuess::of('Držák na houbičku, který se přilepí na dřez.'));
        $this->assertSame('en', LanguageGuess::of('A simple stand for the phone that prints without supports.'));
        $this->assertSame('es', LanguageGuess::of('Un soporte para el teléfono que se imprime sin soportes.'));
        $this->assertSame('en', LanguageGuess::of('STL'));
    }
}
