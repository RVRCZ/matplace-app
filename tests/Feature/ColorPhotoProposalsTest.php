<?php

namespace Tests\Feature;

use App\Domain\Farm\ColorPhotoProposals;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\PrintPhotos;
use App\Engines\Photo\FakeBackgroundRemover;
use App\Models\FarmColor;
use App\Models\FarmColorPhotoProposal;
use App\Models\FarmOrder;
use App\Models\FarmPrinter;
use App\Models\FarmPrinterSlot;
use App\Models\ModelFile;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * Photos of our prints as the pictures of their colours (TODO #71 D, docs/AL.md §21.7): a photographed order proposes
 * one per colour, the admin says yes or no in the materials list, and a customer sees our photos only – never the
 * e-shop's (FarmColor::customerPhoto), while a row without a source (the catalogue before #71) still counts as ours.
 */
class ColorPhotoProposalsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private FarmPrinter $s1;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        Storage::fake('farm');
        Storage::fake('public');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        app()->setLocale('cs');
        FakeBackgroundRemover::reset();
        $this->admin = User::factory()->create();
        $this->admin->setRole(User::ROLE_ADMIN, true);
        $this->user = User::factory()->create();
        $this->s1 = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $this->tmp = sys_get_temp_dir().'/mp_proposals_'.uniqid();
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    /** A web render of a print as PrintPhotos makes it: 1200 × 800, a coloured piece on a light backdrop. */
    private function webPhoto(array $rgb = [40, 90, 160]): string
    {
        $im = imagecreatetruecolor(1200, 800);
        imagefill($im, 0, 0, imagecolorallocate($im, 236, 236, 232));
        imagefilledellipse($im, 600, 450, 500, 400, imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]));
        ob_start();
        imagejpeg($im, null, 88);

        return (string) ob_get_clean();
    }

    /** A photo of a piece as the photo box takes it, on disk, for PrintPhotos::attach. */
    private function piecePhoto(): string
    {
        $path = $this->tmp.'/piece.jpg';
        file_put_contents($path, $this->webPhoto([160, 60, 40]));

        return $path;
    }

    private function colour(string $name, string $hex, array $extra = []): FarmColor
    {
        $white = $this->s1->slots()->where('slot', 2)->firstOrFail()->color;

        return FarmColor::create(['farm_material_id' => $white->farm_material_id, 'code' => 'T_'.Str::slug($name), 'name' => $name, 'name_en' => $name, 'hex' => $hex, 'enabled' => true, 'in_stock' => true] + $extra);
    }

    private function photoFile(string $rel): string
    {
        Storage::disk('public')->put($rel, $this->webPhoto());

        return $rel;
    }

    private function load(int $slot, FarmColor $colour): FarmPrinterSlot
    {
        return tap($this->s1->slots()->where('slot', $slot)->firstOrFail())->update(['farm_color_id' => $colour->id, 'remaining_g' => 800, 'enabled' => true]);
    }

    private function orderForCube(): FarmOrder
    {
        $path = $this->tmp.'/cube.stl';
        MeshFixtures::cubeStl($path, 20.0);
        $uuid = $this->actingAs($this->user)->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->assertCreated()->json('file.uuid');
        $file = ModelFile::where('uuid', $uuid)->firstOrFail();
        $r = $this->actingAs($this->user)->postJson('/farm/orders', ['file' => $file->uuid])->assertCreated();

        return FarmOrder::where('token', basename($r->json('url')))->firstOrFail();
    }

    public function test_a_photographed_order_proposes_a_print_photo_for_each_of_its_colours(): void
    {
        $coral = $this->colour('korálová', '#ff7f50');
        $teal = $this->colour('tyrkysová', '#30d5c8');
        $done = $this->colour('vyfocená', '#333333', ['photo_path' => $this->photoFile('farm/colors/done.jpg'), 'photo_source' => FarmColor::PHOTO_PRINT]);
        $order = FarmOrder::factory()->paid()->done()->create(['farm_color_id' => $coral->id, 'second_color_id' => $teal->id, 'color_changes' => [['z' => 3.0, 'slot_id' => 1, 'color_id' => $teal->id]]]);
        $order->forceFill(['photo_path' => $this->photoFile('prints/'.$order->token.'/photo-web.jpg')])->save();

        $made = app(ColorPhotoProposals::class)->proposeFor($order);
        $this->assertSame([$coral->id, $teal->id], array_map(fn ($p) => $p->farm_color_id, $made), 'the first colour and the colour of the change, once each');
        foreach ($made as $p) {
            $this->assertStringStartsWith('farm/colors/proposals/'.$order->token.'-', $p->path);
            Storage::disk('public')->assertExists($p->path);
            $this->assertSame(FarmColorPhotoProposal::STATUS_PENDING, $p->status);
            $this->assertSame($order->id, $p->farm_order_id);
            $this->assertSame('zakázka '.$order->number, $p->note);
        }
        // the same photo again (a redo) proposes nothing new while the first proposals wait
        $this->assertSame([], app(ColorPhotoProposals::class)->proposeFor($order));
        $this->assertSame(2, FarmColorPhotoProposal::count());
        // a colour photographed already keeps its photo: no proposal
        $other = FarmOrder::factory()->paid()->done()->create(['farm_color_id' => $done->id]);
        $other->forceFill(['photo_path' => $this->photoFile('prints/'.$other->token.'/photo-web.jpg')])->save();
        $this->assertSame([], app(ColorPhotoProposals::class)->proposeFor($other));

        // the order's photo folder goes (a redo, the clean-up of order files): the copies stay for the admin
        app(PrintPhotos::class)->remove($order);
        Storage::disk('public')->assertMissing('prints/'.$order->token.'/photo-web.jpg');
        Storage::disk('public')->assertExists($made[0]->path);
        // a rejected proposal lets the next photo of the same order propose the colour again
        app(ColorPhotoProposals::class)->reject($made[0], $this->admin->id);
        Storage::disk('public')->assertMissing($made[0]->path);
        $order->forceFill(['photo_path' => $this->photoFile('prints/'.$order->token.'/photo-web.jpg')])->save();
        $again = app(ColorPhotoProposals::class)->proposeFor($order);
        $this->assertSame([$coral->id], array_map(fn ($p) => $p->farm_color_id, $again));

        // through the photo box's path: the proposals are made as the photo lands on the order
        $third = FarmOrder::factory()->paid()->done()->create(['farm_color_id' => $teal->id]);
        app(PrintPhotos::class)->attach($third, $this->piecePhoto());
        $this->assertNotNull($third->fresh()->photo_path);
        $proposal = FarmColorPhotoProposal::where('farm_order_id', $third->id)->firstOrFail();
        $this->assertSame($teal->id, $proposal->farm_color_id);
        $this->assertSame([1200, 800], array_slice(getimagesize(Storage::disk('public')->path($proposal->path)), 0, 2), 'the web render, copied');
    }

    public function test_the_admin_approves_or_rejects_a_proposal_in_the_materials_list(): void
    {
        $shop = $this->colour('fialová', '#8800ff', ['photo_path' => $this->photoFile('farm/colors/eshop.jpg'), 'photo_source' => FarmColor::PHOTO_ESHOP, 'source_meta' => ['host' => 'www.prusa3d.com', 'weight_g' => 1000]]);
        $bare = $this->colour('písková', '#d2b48c');
        $order = FarmOrder::factory()->paid()->done()->create(['farm_color_id' => $shop->id]);
        $p1 = FarmColorPhotoProposal::create(['farm_color_id' => $shop->id, 'farm_order_id' => $order->id, 'path' => $this->photoFile('farm/colors/proposals/p1.jpg')]);
        $p2 = FarmColorPhotoProposal::create(['farm_color_id' => $bare->id, 'path' => $this->photoFile('farm/colors/proposals/p2.jpg')]);

        $page = $this->actingAs($this->admin)->get('/admin/farm/materials')->assertOk();
        $page->assertSee(__('farm.admin.shop.proposals.header', ['n' => 2]))->assertSee('data-pending-photos="2"', false)
            ->assertSee('data-photo-proposals="'.$shop->id.'"', false)->assertSee('data-photo-proposals="'.$bare->id.'"', false)
            ->assertSee(route('admin.farm.colors.proposals.approve', $p1), false)->assertSee(__('farm.admin.shop.proposals.from_order', ['number' => $order->number]))
            ->assertSee('farm/colors/eshop.jpg')->assertSee(__('farm.admin.shop.photo_source.eshop'));   // the admin sees the shop photo and knows what it is

        // "Použít": the print becomes the colour's photo, the shop's photo is remembered, its file stays
        $this->actingAs($this->admin)->post(route('admin.farm.colors.proposals.approve', $p1))->assertRedirectContains('/admin/farm/materials')->assertSessionHas('status', __('farm.admin.shop.proposals.approved', ['color' => 'fialová']));
        $shop->refresh();
        $this->assertStringStartsWith('farm/colors/', $shop->photo_path);
        $this->assertStringNotContainsString('proposals', $shop->photo_path);
        $this->assertNotSame('farm/colors/eshop.jpg', $shop->photo_path);
        Storage::disk('public')->assertExists($shop->photo_path);
        $this->assertLessThanOrEqual(800, max(array_slice(getimagesize(Storage::disk('public')->path($shop->photo_path)), 0, 2)), '800 px like every colour photo');
        $this->assertSame(FarmColor::PHOTO_PRINT, $shop->photo_source);
        $this->assertSame('farm/colors/eshop.jpg', $shop->source_meta['eshop_photo']);
        $this->assertSame('www.prusa3d.com', $shop->source_meta['host'], 'the record of the shop page stays');
        Storage::disk('public')->assertExists('farm/colors/eshop.jpg');
        $p1->refresh();
        $this->assertSame(FarmColorPhotoProposal::STATUS_APPROVED, $p1->status);
        $this->assertSame($this->admin->id, $p1->decided_by);
        $this->assertNotNull($p1->decided_at);
        Storage::disk('public')->assertMissing('farm/colors/proposals/p1.jpg');
        // customers now see the print's photo of that colour
        $this->assertStringContainsString($shop->photo_path, (string) $shop->customerPhoto());

        // "Zamítnout": the copy goes, the colour stays as it was
        $this->actingAs($this->admin)->post(route('admin.farm.colors.proposals.reject', $p2))->assertRedirectContains('/admin/farm/materials')->assertSessionHas('status', __('farm.admin.shop.proposals.rejected'));
        $this->assertSame(FarmColorPhotoProposal::STATUS_REJECTED, $p2->fresh()->status);
        Storage::disk('public')->assertMissing('farm/colors/proposals/p2.jpg');
        $this->assertNull($bare->fresh()->photo_path);
        $this->actingAs($this->admin)->get('/admin/farm/materials')->assertOk()->assertDontSee('data-pending-photos', false)->assertDontSee('data-photo-proposals', false);
        // a decided proposal cannot be decided again
        $this->actingAs($this->admin)->post(route('admin.farm.colors.proposals.approve', $p1))->assertRedirect()->assertSessionHas('error', __('farm.admin.shop.proposals.decided'));
        // nobody else
        $this->actingAs($this->user)->post(route('admin.farm.colors.proposals.reject', $p1))->assertRedirect();
        $this->assertSame(FarmColorPhotoProposal::STATUS_APPROVED, $p1->fresh()->status);
    }

    public function test_customers_never_see_a_shop_photo_while_ours_they_do(): void
    {
        $legacy = $this->colour('starší', '#112233', ['photo_path' => $this->photoFile('farm/colors/legacy.jpg')]);   // photo_source NULL: the catalogue before #71
        $shop = $this->colour('e-shopová', '#445566', ['photo_path' => $this->photoFile('farm/colors/eshop.jpg'), 'photo_source' => FarmColor::PHOTO_ESHOP]);
        $print = $this->colour('vyfocená', '#778899', ['photo_path' => $this->photoFile('farm/colors/print.jpg'), 'photo_source' => FarmColor::PHOTO_PRINT]);
        $this->load(0, $legacy);
        $this->load(1, $shop);
        $this->load(3, $print);
        $this->assertStringContainsString('legacy.jpg', (string) $legacy->customerPhoto());
        // since 9 Nov 2026 the shop's photo shows to customers too (farm setting eshop_photos_public, on by default)
        $this->assertStringContainsString('eshop.jpg', (string) $shop->customerPhoto());
        $this->assertStringContainsString('print.jpg', (string) $print->customerPhoto());
        app(FarmSettings::class)->set('eshop_photos_public', false);
        $this->assertNull($shop->fresh()->customerPhoto(), 'switched off: admin only, as before');
        $this->assertStringContainsString('eshop.jpg', (string) $shop->photoUrl(), 'the admin\'s view still has it');
        $this->assertStringContainsString('legacy.jpg', (string) $legacy->fresh()->customerPhoto());
        app(FarmSettings::class)->set('eshop_photos_public', true);

        // the colour tiles of an order (#69): photo per colour
        $order = $this->orderForCube();
        $state = $this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $tiles = collect($state['colors'])->keyBy('color_id');
        $this->assertStringContainsString('legacy.jpg', (string) $tiles[$legacy->id]['photo']);
        $this->assertStringContainsString('eshop.jpg', (string) $tiles[$shop->id]['photo'], 'since 9 Nov 2026 the shop photo is shown while the switch is on');
        $this->assertStringContainsString('print.jpg', (string) $tiles[$print->id]['photo']);
        $html = (string) $this->actingAs($this->user)->get("/farm/orders/{$order->token}")->assertOk()->getContent();
        $this->assertStringContainsString('eshop.jpg', $html);
        $this->assertStringContainsString('legacy.jpg', $html);
        // the admin's view of the same order carries the same colours and the same photos
        $his = $this->actingAs($this->admin)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json();
        $this->assertStringContainsString('eshop.jpg', (string) collect($his['colors'])->keyBy('color_id')[$shop->id]['photo']);

        // the calculator's and the catalogue's palette (/api/config)
        $items = collect($this->getJson('/api/config')->assertOk()->json('colors.items'))->keyBy('code');
        $this->assertStringContainsString('legacy.jpg', (string) $items[$legacy->code]['photo']);
        $this->assertStringContainsString('eshop.jpg', (string) $items[$shop->code]['photo']);
        // switched off: the shop photo leaves the palette and the tiles, ours stay
        app(FarmSettings::class)->set('eshop_photos_public', false);
        $off = collect($this->getJson('/api/config')->assertOk()->json('colors.items'))->keyBy('code');
        $this->assertNull($off[$shop->code]['photo']);
        $this->assertStringContainsString('print.jpg', (string) $off[$print->code]['photo']);
        $this->assertNull(collect($this->actingAs($this->user)->getJson("/farm/orders/{$order->token}/status")->assertOk()->json('colors'))->keyBy('color_id')[$shop->id]['photo']);
        app(FarmSettings::class)->set('eshop_photos_public', true);
        $this->assertStringContainsString('print.jpg', (string) $items[$print->code]['photo']);

        // the admin's materials list shows every photo, the shop's marked
        $this->actingAs($this->admin)->get('/admin/farm/materials')->assertOk()->assertSee('farm/colors/eshop.jpg')->assertSee('farm/colors/legacy.jpg')->assertSee(__('farm.admin.shop.photo_source.eshop'))->assertSee(__('farm.admin.shop.photo_source.print'));
    }
}
