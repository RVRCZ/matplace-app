<?php

namespace Tests\Feature;

use App\Domain\Designer\DesignerProfiles;
use App\Domain\Designer\DesignerStats;
use App\Domain\Designer\PortfolioImporter;
use App\Domain\Designer\ZipMatcher;
use App\Engines\Import\FakeSource;
use App\Engines\Import\ImportedModel;
use App\Engines\Import\SourceProfile;
use App\Engines\Translate\FakeTranslator;
use App\Models\AiCall;
use App\Models\CatalogModel;
use App\Models\DesignerImport;
use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use App\Models\Event;
use App\Models\ModelFile;
use App\Models\User;
use App\Support\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * A designer without a printer: a public portfolio, cards imported from Printables / MakerWorld after proving
 * the account, files uploaded for the farm, links to hand out and numbers of what they bring.
 */
class DesignerTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('public');
        FakeSource::reset();
        FakeTranslator::reset();
        $picture = imagecreatetruecolor(320, 240);
        ob_start();
        imagepng($picture);
        Http::fake(['media.printables.com/*' => Http::response((string) ob_get_clean(), 200, ['Content-Type' => 'image/png'])]);
        $this->user = User::factory()->create(['name' => 'Jana Nováková']);
    }

    private function designer(?User $user = null, array $attributes = []): DesignerProfile
    {
        $profile = app(DesignerProfiles::class)->enable($user ?? $this->user);
        $profile->forceFill($attributes)->save();

        return $profile->refresh();
    }

    private function printablesModel(string $id, string $title, array $with = []): ImportedModel
    {
        $model = new ImportedModel(
            source: 'printables', id: $id, url: "https://www.printables.com/model/{$id}-".str($title)->slug(), title: $title,
            descriptionHtml: $with['html'] ?? '<p>A handy <b>thing</b> for the desk.</p><ul><li>prints without supports</li></ul>',
            images: $with['images'] ?? ["https://media.printables.com/media/prints/{$id}/images/a.jpg", "https://media.printables.com/media/prints/{$id}/images/b.jpg"],
            tags: ['desk'], license: 'Creative Commons — Attribution', isRemix: $with['remix'] ?? false,
            remixSourceUrl: ($with['remix'] ?? false) ? 'https://www.printables.com/model/1-original' : null,
            authorId: $with['author'] ?? '4242', authorName: 'jana', files: $with['files'] ?? [],
        );
        FakeSource::putModel($model);

        return $model;
    }

    private function verified(): DesignerProfile
    {
        return $this->designer(null, ['printables_user_id' => '4242', 'printables_username' => 'jana_4242', 'printables_verified_at' => now()]);
    }

    private function stl(float $mm, string $name = 'model.stl'): UploadedFile
    {
        $path = sys_get_temp_dir().'/mp_des_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, $mm);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function card(DesignerProfile $profile, array $attributes = []): DesignerModel
    {
        $title = $attributes['title'] ?? 'Stojánek na telefon';

        return DesignerModel::create($attributes + ['designer_profile_id' => $profile->id, 'title' => $title, 'slug' => DesignerModel::makeSlug($title), 'description' => ['cs' => 'Popis.']]);
    }

    // ── switching the profile on ─────────────────────────────────────────────

    public function test_anybody_with_a_verified_e_mail_switches_the_profile_on(): void
    {
        $this->actingAs($this->user)->get('/account')->assertOk()->assertSee(__('designer.enable'))->assertSee(__('designer.pitch'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->from('/account')->post('/account/designer/enable')->assertRedirect('/account')->assertSessionHas('error', __('user.verify.needed'));
        $this->assertSame(0, DesignerProfile::count());
        $this->actingAs($unverified)->get('/account/designer')->assertRedirect(route('account'));

        $this->actingAs($this->user)->post('/account/designer/enable')->assertRedirect(route('designer.dashboard'))->assertSessionHas('status');
        $profile = DesignerProfile::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame(['Jana Nováková', 'jana-novakova', false], [$profile->display_name, $profile->slug, $profile->visible]);
        $this->assertTrue($this->user->fresh()->isDesigner());
        $this->assertSame(25.0, $profile->default_royalty_czk);
        $this->assertSame(1, Event::where('type', 'designer_enabled')->count());

        // a second namesake gets another address; switching on twice changes nothing
        $this->actingAs($this->user)->post('/account/designer/enable')->assertRedirect(route('designer.dashboard'));
        $this->assertSame('jana-novakova-2', app(DesignerProfiles::class)->enable(User::factory()->create(['name' => 'Jana Nováková']))->slug);
        $this->assertSame(2, DesignerProfile::count());

        foreach (['cs', 'en', 'es'] as $locale) {
            foreach (['/account/designer', '/account/designer/profile', '/account/designer/models/new', '/account/designer/upload', '/account'] as $url) {
                $page = $this->actingAs($this->user)->get($this->localized($url, $locale))->assertOk();
                $this->assertDoesNotMatchRegularExpression('/\bdesigner\.[a-z_]+\.[a-z_.]+/', strip_tags($page->getContent()), "untranslated key on {$url} ({$locale})");
            }
        }
    }

    // ── proving the account elsewhere ────────────────────────────────────────

    public function test_a_printables_account_is_proven_by_the_token_in_its_bio(): void
    {
        $profile = $this->designer();
        $page = $this->actingAs($this->user)->get('/account/designer/verify/printables')->assertOk();
        $token = $profile->refresh()->tokenFor('printables');
        $this->assertMatchesRegularExpression('/^matplace-[0-9a-f]{16}$/', $token);
        $page->assertSee($token);
        // not verified: the import page sends the designer here first
        $this->actingAs($this->user)->get('/account/designer/import/printables')->assertRedirect(route('designer.verify', 'printables'));

        $check = fn (string $url) => $this->actingAs($this->user)->from('/account/designer/verify/printables')->post('/account/designer/verify/printables', ['url' => $url]);
        FakeSource::putProfile('printables', new SourceProfile('4242', 'jana_4242', 'Jana', 'I design desk things.', 'https://www.printables.com/@jana_4242'));
        $check('https://www.printables.com/@jana_4242')->assertSessionHas('error', __('designer.verify.error.token_missing'));
        $check('https://www.printables.com/@nobody_1')->assertSessionHas('error', __('designer.verify.error.not_found'));
        $check('https://example.com/@jana_4242')->assertSessionHas('error', __('designer.verify.error.bad_url'));
        $this->assertFalse($profile->refresh()->verifiedOn('printables'));

        FakeSource::putProfile('printables', new SourceProfile('4242', 'jana_4242', 'Jana', "I design desk things.\n{$token}", 'https://www.printables.com/@jana_4242'));
        $check('https://www.printables.com/@jana_4242')->assertRedirect(route('designer.import', 'printables'))->assertSessionHas('status');
        $profile->refresh();
        $this->assertTrue($profile->verifiedOn('printables'));
        $this->assertSame(['4242', 'jana_4242', 'https://www.printables.com/@jana_4242'], [$profile->printables_user_id, $profile->printables_username, $profile->links['printables']]);

        // the same Printables account cannot serve a second designer
        $other = User::factory()->create();
        $second = $this->designer($other);
        FakeSource::putProfile('printables', new SourceProfile('4242', 'jana_4242', 'Jana', $second->tokenFor('printables'), 'https://www.printables.com/@jana_4242'));
        $this->actingAs($other)->post('/account/designer/verify/printables', ['url' => 'https://www.printables.com/@jana_4242'])->assertSessionHas('error', __('designer.verify.error.taken'));
    }

    public function test_a_makerworld_account_is_proven_by_the_token_in_a_model_description(): void
    {
        $profile = $this->designer();
        $token = $profile->tokenFor('makerworld');
        $model = fn (string $html) => FakeSource::putModel(new ImportedModel('makerworld', '777', 'https://makerworld.com/en/models/777-hook', 'Hook', $html, [], [], 'CC BY', false, null, '99001', 'jana3d'));
        $check = fn (string $url) => $this->actingAs($this->user)->post('/account/designer/verify/makerworld', ['url' => $url]);

        // MakerWorld shows us no profiles: a profile address is answered with "use a model"
        $check('https://makerworld.com/en/@jana3d')->assertSessionHas('error', __('designer.verify.error.use_model'));
        $model('<p>A hook.</p>');
        $check('https://makerworld.com/en/models/777-hook')->assertSessionHas('error', __('designer.verify.error.token_missing'));
        $model("<p>A hook. {$token}</p>");
        $check('https://makerworld.com/en/models/777-hook#profileId-1')->assertRedirect(route('designer.import', 'makerworld'));
        $profile->refresh();
        $this->assertSame(['99001', 'jana3d'], [$profile->makerworld_uid, $profile->makerworld_handle]);

        // no list there: the import page offers the field for links
        $this->actingAs($this->user)->get('/account/designer/import/makerworld')->assertOk()->assertSee(__('designer.import.links'))->assertDontSee(__('designer.import.select_new'));
    }

    // ── import ───────────────────────────────────────────────────────────────

    public function test_import_brings_cards_with_pictures_and_translations_and_skips_what_is_there(): void
    {
        config(['engines.import.image_hosts' => ['printables.com']]);
        $profile = $this->verified();
        $this->printablesModel('100', 'Phone stand');
        $this->printablesModel('101', 'Cable clip', ['remix' => true]);
        $this->printablesModel('102', 'Somebody else\'s vase', ['author' => '999']);
        CatalogModel::create(['title' => 'Phone stand', 'source' => 'printables', 'external_id' => '100', 'external_url' => 'https://www.printables.com/model/100']);

        // the list of the author's own models, the foreign one is not in it
        $page = $this->actingAs($this->user)->get('/account/designer/import/printables')->assertOk();
        $page->assertSee('Phone stand')->assertSee('Cable clip')->assertDontSee('vase');

        // "I am the author" is required once for the whole import
        $this->actingAs($this->user)->post('/account/designer/import/printables', ['ids' => ['100']])->assertSessionHasErrors('author');
        $this->actingAs($this->user)->post('/account/designer/import/printables', ['author' => 1])->assertSessionHas('error', __('designer.import.nothing'));

        $start = $this->actingAs($this->user)->post('/account/designer/import/printables', [
            'author' => 1, 'ids' => ['100', '101'],
            'links' => "https://www.printables.com/model/102-somebody-elses-vase\nhttps://example.com/not-ours\nhttps://www.printables.com/model/100-phone-stand",
        ]);
        $import = DesignerImport::firstOrFail();
        $start->assertRedirect(route('designer.imports.show', $import->id));

        // the queue is synchronous in tests: everything is done
        $this->assertSame(['done', 3, 2, 1], [$import->status, $import->total, $import->done, $import->failed]);
        $this->assertSame(['imported', 'imported', 'failed'], array_column($import->items, 'state'));
        $this->assertSame('not_yours', $import->items[2]['note']);

        $stand = DesignerModel::where('external_id', '100')->firstOrFail();
        $this->assertSame(['printables', 'Phone stand', 'phone-stand', 'Creative Commons — Attribution', false, true], [$stand->source, $stand->title, $stand->slug, $stand->license_source, $stand->is_remix, $stand->visible]);
        $this->assertSame('en', $stand->source_locale);
        $this->assertSame("A handy thing for the desk.\n\n• prints without supports", $stand->description['en']);
        $this->assertStringStartsWith('[cs] A handy thing', $stand->description['cs']);
        $this->assertStringStartsWith('[es] A handy thing', $stand->description['es']);
        $this->assertNull($stand->model_file_id);
        $this->assertSame(25.0, $stand->royalty_czk);
        // two pictures, re-encoded, the first one is the cover
        $this->assertSame(2, $stand->images()->count());
        $cover = $stand->cover();
        $this->assertTrue($cover->is_cover);
        Storage::disk('public')->assertExists([$cover->path, $cover->smallPath()]);
        // the same model already sits in the inspiration catalogue: the card is tied to it
        $this->assertSame(CatalogModel::first()->id, $stand->catalog_model_id);
        // the remix is marked and knows its parent
        $clip = DesignerModel::where('external_id', '101')->firstOrFail();
        $this->assertSame([true, 'https://www.printables.com/model/1-original'], [$clip->is_remix, $clip->remix_source_url]);
        // the first card made the profile public
        $this->assertTrue($profile->refresh()->visible);
        // translations are booked
        $this->assertSame(2, AiCall::where('kind', 'translate')->count());

        // progress for the page that polls
        $this->actingAs($this->user)->getJson("/account/designer/imports/{$import->id}/status")->assertOk()->assertJsonPath('status', 'done')->assertJsonPath('items.0.title', 'Phone stand');
        $this->actingAs($this->user)->get("/account/designer/imports/{$import->id}")->assertOk()->assertSee(__('designer.import.finished'));
        $this->actingAs(User::factory()->create())->get("/account/designer/imports/{$import->id}")->assertRedirect(route('account'));

        // a second import of the same model is a skip, not a second card
        $this->actingAs($this->user)->post('/account/designer/import/printables', ['author' => 1, 'ids' => ['100']]);
        $again = DesignerImport::latest('id')->firstOrFail();
        $this->assertSame(['skipped', 'duplicate'], [$again->items[0]['state'], $again->items[0]['note']]);
        $this->assertSame(1, DesignerModel::where('external_id', '100')->count());
        $this->actingAs($this->user)->get('/account/designer/import/printables')->assertSee(__('designer.import.already'));
    }

    public function test_only_the_missing_languages_are_translated(): void
    {
        $importer = app(PortfolioImporter::class);

        // nothing known: one call detects the language and returns the other two
        FakeTranslator::$detects = 'cs';
        [$from, $texts] = $importer->describe('Držák na kabely.');
        $this->assertSame('cs', $from);
        $this->assertSame(['cs', 'en', 'es'], array_keys(collect($texts)->sortKeys()->all()));
        $this->assertSame('Držák na kabely.', $texts['cs']);
        $this->assertSame(['cs', 'en', 'es'], FakeTranslator::$calls[0]['to']);

        // Czech and English written by the designer: only Spanish is asked for
        FakeTranslator::reset();
        [, $texts] = $importer->describe('', [], ['cs' => 'Držák.', 'en' => 'A holder.'], 'cs');
        $this->assertSame([['to' => ['es'], 'from' => 'cs', 'style' => 'faithful', 'text' => 'Držák.']], FakeTranslator::$calls);
        $this->assertSame(['cs' => 'Držák.', 'en' => 'A holder.', 'es' => '[es] Držák.'], $texts);

        // everything written: no call at all
        FakeTranslator::reset();
        $importer->describe('', [], ['cs' => 'a', 'en' => 'b', 'es' => 'c']);
        $this->assertSame([], FakeTranslator::$calls);

        // a text in a language the site does not have: three translations, no original kept under a wrong language
        FakeTranslator::reset();
        FakeTranslator::$detects = 'other';
        [$from, $texts] = $importer->describe('Ein Kabelhalter.');
        $this->assertSame('other', $from);
        $this->assertSame(['[cs] Ein Kabelhalter.', '[en] Ein Kabelhalter.', '[es] Ein Kabelhalter.'], [$texts['cs'], $texts['en'], $texts['es']]);
    }

    public function test_a_card_made_by_hand_and_the_form_of_a_card(): void
    {
        $profile = $this->designer();
        $this->actingAs($this->user)->post('/account/designer/models', ['title' => 'Váza Vlna', 'description' => ['cs' => 'Váza s vlnitým povrchem.'], 'royalty_czk' => 40, 'translate' => 1])->assertRedirect();
        $card = DesignerModel::firstOrFail();
        $this->assertSame(['manual', 'vaza-vlna', 40.0, 'cs'], [$card->source, $card->slug, $card->royalty_czk, $card->source_locale]);
        $this->assertSame(['cs' => 'Váza s vlnitým povrchem.', 'en' => '[en] Váza s vlnitým povrchem.', 'es' => '[es] Váza s vlnitým povrchem.'], $card->description);
        $this->assertTrue($profile->refresh()->visible, 'the first visible card publishes the profile');

        $this->actingAs($this->user)->get("/account/designer/models/{$card->id}")->assertOk()->assertSee('Váza Vlna')->assertSee(__('designer.file.drop'))->assertSee($profile->slug.'?ref='.$profile->slug, false);

        // a free download needs a licence; the reward has a ceiling
        $save = fn (array $data) => $this->actingAs($this->user)->post("/account/designer/models/{$card->id}", $data + ['title' => 'Váza Vlna', 'royalty_czk' => 0, 'visible' => 1]);
        $save(['download_allowed' => 1])->assertSessionHasErrors('download_license');
        $save(['royalty_czk' => 999999])->assertSessionHasErrors('royalty_czk');
        $save(['download_allowed' => 1, 'download_license' => 'cc_by_nc', 'description' => ['cs' => 'Nový popis.', 'en' => 'New text.']])->assertSessionHas('status');
        $card->refresh();
        $this->assertSame([0.0, true, 'cc_by_nc', ['cs' => 'Nový popis.', 'en' => 'New text.']], [$card->royalty_czk, $card->download_allowed, $card->download_license, $card->description]);

        // pictures: added, another one made the cover, removed
        $png = sys_get_temp_dir().'/mp_pic_'.uniqid().'.png';
        imagepng(imagecreatetruecolor(800, 600), $png);
        $this->actingAs($this->user)->post("/account/designer/models/{$card->id}/images", ['images' => [new UploadedFile($png, 'a.png', 'image/png', null, true), new UploadedFile($png, 'b.png', 'image/png', null, true)]])->assertSessionHas('status');
        [$first, $second] = $card->images()->get()->all();
        $this->assertTrue($first->is_cover);
        $this->actingAs($this->user)->post("/account/designer/models/{$card->id}/images/{$second->id}/cover")->assertSessionHas('status');
        $this->assertTrue($second->fresh()->is_cover);
        $this->actingAs($this->user)->post("/account/designer/models/{$card->id}/images/{$second->id}/delete")->assertSessionHas('status');
        $this->assertTrue($first->fresh()->is_cover, 'the remaining picture becomes the cover');

        // somebody else's card is not there
        $other = User::factory()->create();
        $this->designer($other);
        $this->actingAs($other)->get("/account/designer/models/{$card->id}")->assertNotFound();
        $this->actingAs($other)->post("/account/designer/models/{$card->id}/delete")->assertNotFound();

        $this->actingAs($this->user)->post("/account/designer/models/{$card->id}/delete")->assertRedirect(route('designer.dashboard'));
        $this->assertSame(0, DesignerModel::count());
    }

    // ── files ────────────────────────────────────────────────────────────────

    public function test_an_uploaded_file_is_checked_sliced_and_makes_the_card_printable(): void
    {
        $profile = $this->designer(null, ['visible' => true, 'published_at' => now()]);
        $card = $this->card($profile);
        $this->assertFalse($card->isPrintable());

        // the author's word is part of the upload
        $this->actingAs($this->user)->post("/account/designer/models/{$card->id}/file", ['file' => $this->stl(30)])->assertSessionHasErrors('author');
        $this->actingAs($this->user)->post("/account/designer/models/{$card->id}/file", ['file' => UploadedFile::fake()->create('notes.txt', 4), 'author' => 1])->assertSessionHas('error', __('designer.file.error.format'));

        $this->actingAs($this->user)->post("/account/designer/models/{$card->id}/file", ['file' => $this->stl(30, 'stojanek.stl'), 'author' => 1])->assertSessionHas('status');
        $card->refresh();
        $this->assertSame(DesignerModel::FILE_READY, $card->file_status);
        $this->assertNotNull($card->model_file_id);
        $this->assertNotNull($card->author_confirmed_at);
        $this->assertSame('portfolio', $card->modelFile->origin);
        $this->assertEqualsWithDelta(30.0, $card->slice_summary['dims']['x'], 0.5);
        $this->assertGreaterThan(0, $card->slice_summary['grams']);
        $this->assertGreaterThan(0, $card->slice_summary['minutes']);
        $this->assertTrue($card->isPrintable());
        $this->assertSame(1, DesignerModel::printable()->count());
        $this->assertSame(1, Event::where('type', 'designer_file_uploaded')->count());
        $this->actingAs($this->user)->get("/account/designer/models/{$card->id}")->assertOk()->assertSee(__('designer.file.ready', ['name' => 'stojanek.stl']));
        // the model behind a card cannot be deleted from "My models"
        $this->actingAs($this->user)->post("/account/models/{$card->modelFile->uuid}/delete")->assertSessionHas('error', __('user.models.locked_card'));

        // a file the check refuses (half a millimetre: wrong units) is not used; the good one stays
        $this->actingAs($this->user)->post("/account/designer/models/{$card->id}/file", ['file' => $this->stl(0.5, 'drobek.stl'), 'author' => 1]);
        $card->refresh();
        $this->assertSame('stojanek.stl', $card->modelFile->original_name);
        $this->assertSame('units_tiny', $card->file_check['rejected'][0]['code']);
        $this->actingAs($this->user)->get("/account/designer/models/{$card->id}")->assertSee(__('designer.file.rejected_kept'));

        // a card without a file that gets a bad one is marked and stays out of the catalogue
        $bad = $this->card($profile, ['title' => 'Drobek']);
        $this->actingAs($this->user)->post("/account/designer/models/{$bad->id}/file", ['file' => $this->stl(0.5), 'author' => 1]);
        $bad->refresh();
        $this->assertSame([DesignerModel::FILE_FAILED, null, null], [$bad->file_status, $bad->model_file_id, $bad->author_confirmed_at]);
        $this->assertFalse($bad->isPrintable());

        // taking the file away: the card stays, printable no more
        $this->actingAs($this->user)->post("/account/designer/models/{$card->id}/file/remove")->assertSessionHas('status');
        $this->assertFalse($card->refresh()->isPrintable());
        // a hidden profile hides its cards from the catalogue too
        $this->assertSame(0, DesignerModel::printable()->count());
    }

    public function test_a_remix_takes_no_file_until_the_licence_of_its_original_is_confirmed(): void
    {
        $profile = $this->designer();
        $remix = $this->card($profile, ['is_remix' => true, 'remix_source_url' => 'https://www.printables.com/model/1-original']);

        $this->actingAs($this->user)->get("/account/designer/models/{$remix->id}")->assertOk()->assertSee(__('designer.remix.confirm'))->assertSee(__('designer.file.error.remix'))->assertDontSee(__('designer.file.drop'));
        $this->actingAs($this->user)->post("/account/designer/models/{$remix->id}/file", ['file' => $this->stl(30), 'author' => 1])->assertSessionHas('error', __('designer.file.error.remix'));
        $this->assertNull($remix->refresh()->model_file_id);
        $this->assertSame(DesignerModel::FILE_NONE, $remix->file_status);

        $this->actingAs($this->user)->post("/account/designer/models/{$remix->id}", ['title' => $remix->title, 'royalty_czk' => 25, 'visible' => 1, 'remix_confirmed' => 1])->assertSessionHas('status');
        $this->assertNotNull($remix->refresh()->remix_confirmed_at);
        $this->actingAs($this->user)->post("/account/designer/models/{$remix->id}/file", ['file' => $this->stl(30), 'author' => 1])->assertSessionHas('status');
        $this->assertNotNull($remix->refresh()->model_file_id);
    }

    public function test_file_names_are_paired_with_cards_surely_maybe_or_not_at_all(): void
    {
        $this->assertSame('phone stand', ZipMatcher::normalize('models/Phone_Stand_v2-FINAL (3).STL'));
        $this->assertSame('drzak na kabely', ZipMatcher::normalize('Držák na kabely 02.3mf'));
        $this->assertSame(1.0, ZipMatcher::jaroWinkler('vase', 'vase'));
        $this->assertEqualsWithDelta(0.961, ZipMatcher::jaroWinkler('martha', 'marhta'), 0.001);
        $this->assertSame(0.0, ZipMatcher::jaroWinkler('abc', 'xyz'));

        $profile = $this->designer();
        $stand = $this->card($profile, ['title' => 'Phone stand']);
        $clip = $this->card($profile, ['title' => 'Cable clip']);
        $vase = $this->card($profile, ['title' => 'Vase', 'source_files' => ['wavy_vase_body.stl']]);
        $cards = collect([$stand, $clip, $vase]);

        $matches = app(ZipMatcher::class)->match(['Phone_Stand_v2.stl', 'kabel-klip.stl', 'wavy vase body final.3mf', 'benchy.stl'], $cards);
        $this->assertSame([$stand->id, 'sure'], [$matches['Phone_Stand_v2.stl']['card'], $matches['Phone_Stand_v2.stl']['level']]);
        $this->assertSame([$clip->id, 'maybe'], [$matches['kabel-klip.stl']['card'], $matches['kabel-klip.stl']['level']], 'a similar name has to be confirmed by the designer');
        // the card is called "Vase", but Printables knows its file name: an exact hit
        $this->assertSame([$vase->id, 'sure'], [$matches['wavy vase body final.3mf']['card'], $matches['wavy vase body final.3mf']['level']]);
        $this->assertSame([null, 'none'], [$matches['benchy.stl']['card'], $matches['benchy.stl']['level']]);

        // two files for one card: the better one gets it
        $two = app(ZipMatcher::class)->match(['phone stand.stl', 'phone stan.stl'], collect([$stand]));
        $this->assertSame($stand->id, $two['phone stand.stl']['card']);
        $this->assertNull($two['phone stan.stl']['card']);
    }

    public function test_one_zip_fills_many_cards(): void
    {
        $profile = $this->designer();
        $stand = $this->card($profile, ['title' => 'Phone stand']);
        $clip = $this->card($profile, ['title' => 'Cable clip']);
        $remix = $this->card($profile, ['title' => 'Remixed hook', 'is_remix' => true]);

        $zipPath = sys_get_temp_dir().'/mp_zip_'.uniqid().'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE);
        foreach (['Phone_Stand_v2.stl' => 30, 'my models/klip.stl' => 20, '__MACOSX/._Phone_Stand_v2.stl' => 10, 'remixed hook.stl' => 25] as $name => $mm) {
            $stl = $this->stl($mm);
            $zip->addFromString($name, (string) file_get_contents($stl->getRealPath()));
        }
        $zip->addFromString('readme.txt', 'hello');
        $zip->close();

        $this->actingAs($this->user)->get('/account/designer/upload')->assertOk()->assertSee(trans_choice('designer.bulk.waiting', 2, ['n' => 2]));
        $upload = $this->actingAs($this->user)->post('/account/designer/upload', ['zip' => new UploadedFile($zipPath, 'modely.zip', 'application/zip', null, true)]);
        $upload->assertRedirect();
        $matchUrl = $upload->headers->get('Location');

        $page = $this->actingAs($this->user)->get($matchUrl)->assertOk();
        // three model files (the Mac shadow file and the text file are not models), the remix is not offered as a target
        $page->assertSee('Phone_Stand_v2.stl')->assertSee('klip.stl')->assertSee('remixed hook.stl')->assertDontSee('._Phone')->assertDontSee('readme.txt');
        $page->assertSee(trans_choice('designer.bulk.remixes', 1, ['n' => 1]));
        $this->assertSame(1, substr_count($page->getContent(), 'data-match-level="sure"'));
        $this->assertSame(2, substr_count($page->getContent(), 'data-match-level="none"'));

        // the designer pairs the unpaired file by hand and confirms authorship once for the whole zip
        $this->actingAs($this->user)->post($matchUrl, ['pairs' => [0 => $stand->id, 1 => $clip->id, 2 => $remix->id]])->assertSessionHasErrors('author');
        $this->actingAs($this->user)->post($matchUrl, ['author' => 1, 'pairs' => [0 => $stand->id, 1 => $clip->id, 2 => $remix->id]])
            ->assertRedirect(route('designer.dashboard', ['show' => 'file']))->assertSessionHas('status');
        $this->assertSame('Phone_Stand_v2.stl', $stand->refresh()->modelFile->original_name);
        $this->assertSame('klip.stl', $clip->refresh()->modelFile->original_name);
        $this->assertNull($remix->refresh()->model_file_id, 'a remix without the confirmation takes no file, whatever is posted');
        $this->assertNotNull($stand->author_confirmed_at);
        // the zip is gone afterwards
        $this->actingAs($this->user)->get($matchUrl)->assertNotFound();

        // a zip uploaded to one card: its first model is used, the rest is named
        $single = $this->card($profile, ['title' => 'Krabička']);
        $zip2 = sys_get_temp_dir().'/mp_zip_'.uniqid().'.zip';
        $z = new \ZipArchive;
        $z->open($zip2, \ZipArchive::CREATE);
        $z->addFromString('telo.stl', (string) file_get_contents($this->stl(40)->getRealPath()));
        $z->addFromString('vicko.stl', (string) file_get_contents($this->stl(20)->getRealPath()));
        $z->close();
        $this->actingAs($this->user)->post("/account/designer/models/{$single->id}/file", ['file' => new UploadedFile($zip2, 'krabicka.zip', 'application/zip', null, true), 'author' => 1])
            ->assertSessionHas('status', __('designer.file.received').' '.__('designer.file.zip_others', ['files' => 'vicko.stl']));
        $this->assertSame('telo.stl', $single->refresh()->modelFile->original_name);
    }

    // ── the public portfolio ─────────────────────────────────────────────────

    public function test_a_hidden_portfolio_does_not_exist_for_the_public(): void
    {
        $profile = $this->designer(null, ['bio' => 'Navrhuji věci na stůl.', 'links' => ['printables' => 'https://www.printables.com/@jana_4242']]);
        $this->card($profile, ['title' => 'Skrytá věc', 'visible' => false]);
        $linked = $this->card($profile, ['title' => 'Stojánek', 'source' => 'printables', 'external_url' => 'https://www.printables.com/model/100-stojanek']);

        $this->get('/d/jana-novakova')->assertNotFound();
        $this->get('/en/d/jana-novakova')->assertNotFound();
        // the owner sees a preview, marked as such and kept out of search engines
        $this->actingAs($this->user)->get('/d/jana-novakova')->assertOk()->assertSee(__('designer.public.preview'))->assertSee('noindex', false);
        $this->post('/logout');

        $profile->forceFill(['visible' => true, 'published_at' => now()])->save();
        $page = $this->withHeader('User-Agent', self::BROWSER)->get('/d/jana-novakova')->assertOk();
        $page->assertSee('Jana Nováková')->assertSee('Navrhuji věci na stůl.')->assertSee('Stojánek')->assertDontSee('Skrytá věc')
            // a card without a file leads to its source, marked for search engines
            ->assertSee('href="https://www.printables.com/model/100-stojanek"', false)->assertSee('rel="nofollow noopener" target="_blank"', false)
            ->assertSee(__('designer.badge.at', ['source' => 'Printables']))
            ->assertSee('"@type":"Person"', false)->assertSee('"sameAs":["https://www.printables.com/@jana_4242"]', false)
            ->assertSee('<meta property="og:image" content="'.url('/og/designer/jana-novakova.png').'">', false)
            ->assertSee('<link rel="alternate" hreflang="es" href="'.url('/es/d/jana-novakova').'">', false);
        $this->flushHeaders();   // the test client now looks like a script again: its visit is not counted
        $this->get('/es/d/jana-novakova')->assertOk()->assertSee(__('designer.public.role', [], 'es'));
        $this->assertSame(1, Event::where('type', Event::VIEW)->where('subject_type', 'designer')->where('subject_id', $profile->id)->count(), 'one view per visitor, robots and repeats do not count');
        $this->assertSame($linked->id, $profile->models()->where('visible', true)->value('id'));

        // the preview picture: 1200 × 630
        $og = $this->get('/og/designer/jana-novakova.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $size = getimagesize($og->baseResponse->getFile()->getPathname());
        $this->assertSame([1200, 630], [$size[0], $size[1]]);
        $this->get('/og/es/designer/jana-novakova.png')->assertOk();
        $this->get('/og/designer/nobody.png')->assertNotFound();
    }

    public function test_a_designers_link_is_remembered_and_counted(): void
    {
        $profile = $this->designer(null, ['visible' => true, 'published_at' => now()]);
        $browser = ['User-Agent' => self::BROWSER];

        $landing = $this->withHeaders($browser)->get('/d/jana-novakova?ref=jana-novakova')->assertOk();
        $landing->assertCookie(Track::REF_COOKIE, 'jana-novakova');
        $visit = Event::where('type', Event::REF_VISIT)->firstOrFail();
        $this->assertSame(['jana-novakova', 'designer', 'designer', $profile->id, 'cs'], [$visit->ref_slug, $visit->source, $visit->subject_type, $visit->subject_id, $visit->locale]);
        // the page view of that visit is attributed to the link as well
        $this->assertSame('jana-novakova', Event::where('type', Event::VIEW)->value('ref_slug'));

        // the link works on any page of the site; an unknown slug and a robot leave no trace
        $this->withHeaders($browser)->get('/en/tools?ref=jana-novakova')->assertOk()->assertCookie(Track::REF_COOKIE);
        $this->withHeaders($browser)->get('/tools?ref=nobody-here')->assertOk()->assertCookieMissing(Track::REF_COOKIE);
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get('/tools?ref=jana-novakova')->assertOk()->assertCookieMissing(Track::REF_COOKIE);
        $this->assertSame(2, Event::where('type', Event::REF_VISIT)->count());

        // what the designer sees
        $stats = app(DesignerStats::class);
        $this->assertSame(['visits' => 1, 'via_ref' => 1, 'ref_visits' => 2], $stats->visits($profile, 30));
        $this->actingAs($this->user)->get('/account/designer')->assertOk()->assertSee(__('designer.stats.via_ref', ['n' => 1]))->assertSee(__('designer.stats.arrivals', ['n' => 2]))
            ->assertSee(url('/d/jana-novakova').'?ref=jana-novakova', false);

        // where a visit came from
        $this->assertSame('google', Track::classify('', 'https://www.google.com/search?q=x', 'matplace.com'));
        $this->assertSame('seznam', Track::classify('', 'https://search.seznam.cz/', 'matplace.com'));
        $this->assertSame('facebook', Track::classify('facebook', '', 'matplace.com'));
        $this->assertSame('instagram', Track::classify('', 'https://l.instagram.com/', 'matplace.com'));
        $this->assertSame('direct', Track::classify('', '', 'matplace.com'));
        $this->assertSame('direct', Track::classify('', 'https://matplace.com/tools', 'matplace.com'));
        $this->assertSame('other', Track::classify('', 'https://forum.example.org/t/1', 'matplace.com'));
    }

    public function test_deleting_the_account_takes_the_portfolio_down(): void
    {
        $this->user->forceFill(['password' => 'secret123'])->save();
        $profile = $this->designer(null, ['visible' => true, 'published_at' => now(), 'bio' => 'O mně.']);
        $card = $this->card($profile);
        $this->actingAs($this->user)->post("/account/designer/models/{$card->id}/file", ['file' => $this->stl(30), 'author' => 1]);
        $fileId = $card->refresh()->model_file_id;
        Event::create(['type' => 'view', 'user_id' => $this->user->id]);

        $this->actingAs($this->user)->post('/account/delete', ['understand' => 1, 'password' => 'secret123'])->assertRedirect(route('home'));
        $this->get('/d/jana-novakova')->assertNotFound();
        $profile->refresh();
        $this->assertSame([false, null, 'Smazaný uživatel'], [$profile->visible, $profile->bio, $profile->display_name]);
        $card->refresh();
        $this->assertSame([false, null], [$card->visible, $card->model_file_id]);
        $this->assertNull(ModelFile::find($fileId), 'the file of the card goes with the account');
        $this->assertNull(Event::where('type', 'view')->value('user_id'), 'statistics stay, without the person');
    }
}
