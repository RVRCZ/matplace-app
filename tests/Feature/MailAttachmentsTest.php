<?php

namespace Tests\Feature;

use App\Domain\Farm\OrderFiles;
use App\Domain\Farm\OrderPictures;
use App\Domain\Mail\Outbox;
use App\Engines\Ai\FakeAssistant;
use App\Engines\Mail\FakeMailbox;
use App\Engines\Mail\GmailMailbox;
use App\Mail\PlainMessage;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\OutgoingEmail;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** docs/AU.md §8: pictures of an order kept small and for good, attached to a reply, a link to the order, the assistant sees our model. */
class MailAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private FarmOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('farm');
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('models');
        FakeMailbox::reset();
        FakeAssistant::reset();
        Mail::fake();
        $this->seed(FarmSeeder::class);
        $this->admin = User::factory()->create();
        UserRole::create(['user_id' => $this->admin->id, 'role' => 'admin']);
        $this->customer = User::factory()->create(['email' => 'mila@example.com']);
        $file = ModelFile::create(['uuid' => (string) Str::uuid(), 'original_name' => 'pet-figurine.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => 1,
            'sha256' => str_repeat('a', 64), 'storage_path' => 'files/x/original.stl', 'status' => 'ready', 'owner_user_id' => $this->customer->id]);
        $this->order = FarmOrder::forceCreate(['token' => 'tokABC', 'number' => 'F26-000194', 'user_id' => $this->customer->id, 'model_file_id' => $file->id,
            'status' => FarmOrder::STATUS_HANDED_OVER, 'quality' => 'standard', 'strength' => 'standard', 'farm_material_id' => FarmMaterial::query()->value('id'),
            'price_total' => 359, 'currency' => 'CZK', 'paid_at' => now()->subDays(6)]);
    }

    private function png(int $w = 1600, int $h = 1200): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 120, 40));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    public function test_the_pictures_are_kept_small_and_survive_the_print_files(): void
    {
        $farm = Storage::disk('farm');
        $farm->put('orders/tokABC/source.png', $this->png());
        $farm->put('orders/tokABC/snapshot.jpg', $this->png(640, 480));
        $farm->put('orders/tokABC/print.stl', 'solid x');

        $kept = app(OrderPictures::class)->all($this->order, render: false);
        $this->assertSame(['source', 'snapshot'], array_keys($kept));
        [$w, $h] = getimagesize($kept['source']);
        $this->assertSame([900, 675], [$w, $h], 'the longer side shrinks to 900 px');
        $this->assertSame('image/jpeg', getimagesize($kept['snapshot'])['mime']);

        // the prune of the order's files leaves keep/ alone, and the kept copies still answer
        $this->order->forceFill(['status' => FarmOrder::STATUS_HANDED_OVER])->save();
        app(OrderFiles::class)->prune($this->order);
        $this->assertFalse($farm->exists('orders/tokABC/print.stl'));
        $this->assertFalse($farm->exists('orders/tokABC/source.png'), 'the full source photo goes with the frames');
        $this->assertSame(['source', 'snapshot'], array_keys(app(OrderPictures::class)->all($this->order, render: false)));
    }

    public function test_a_draft_gets_order_pictures_and_own_files_and_they_leave_with_the_reply(): void
    {
        Storage::disk('farm')->put('orders/tokABC/keep/model.jpg', $this->png(900, 600));
        FakeMailbox::put('h1', ['from' => 'mila@example.com', 'subject' => 'Re: Zakázka F26-000194 je na cestě', 'text' => 'Přišel mi pes místo fenky.']);
        $draft = OutgoingEmail::create(['to' => 'mila@example.com', 'subject' => 'Re: Zakázka F26-000194', 'body' => "Dobrý den,\n\nomlouváme se.\n\nmatplace", 'locale' => 'cs',
            'status' => 'draft', 'generated_by_ai' => true, 'inbox_message_id' => 'h1', 'inbox_thread_id' => 't-h1', 'in_reply_to' => '<h1@example.com>']);

        $this->actingAs($this->admin)->get('/admin/emails/'.$draft->id)->assertOk()
            ->assertSee('Vložit odkaz na zakázku')->assertSee(route('farm.orders.show', $this->order), false)
            ->assertSee('Náhled modelu, podle kterého jsme tiskli')->assertSee(route('admin.emails.order_picture', [$this->order, 'model']), false)
            ->assertSee('enctype="multipart/form-data"', false);
        $this->actingAs($this->admin)->get(route('admin.emails.order_picture', [$this->order, 'model']))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($this->admin)->post('/admin/emails/'.$draft->id, ['to' => 'mila@example.com', 'subject' => $draft->subject, 'body' => $draft->body, 'action' => 'save',
            'pictures' => [$this->order->id.':model'], 'files' => [UploadedFile::fake()->createWithContent('Fenka nová.png', $this->png(300, 300))]])->assertRedirect();
        $draft->refresh();
        $this->assertSame(['F26-000194-model.jpg', 'Fenka-nova.png'], array_column($draft->attachments, 'name'));
        $this->actingAs($this->admin)->get('/admin/emails/'.$draft->id)->assertOk()->assertSee('Přílohy, které odejdou')->assertSee(route('admin.emails.file', [$draft->id, 'F26-000194-model.jpg']), false);
        $this->actingAs($this->admin)->get(route('admin.emails.file', [$draft->id, 'F26-000194-model.jpg']))->assertOk();
        $this->actingAs($this->customer)->get(route('admin.emails.file', [$draft->id, 'F26-000194-model.jpg']))->assertRedirect();

        // one taken off, then approved: the reply leaves in the thread with the rest
        $this->actingAs($this->admin)->post('/admin/emails/'.$draft->id, ['to' => 'mila@example.com', 'subject' => $draft->subject, 'body' => $draft->body, 'action' => 'approve', 'remove' => ['Fenka-nova.png']])->assertRedirect();
        $this->assertSame('sent', $draft->fresh()->status);
        $this->assertSame(['F26-000194-model.jpg'], FakeMailbox::$replies[0]['files']);

        // a mail of our own (not a reply) carries its files too
        $own = OutgoingEmail::create(['to' => 'mila@example.com', 'subject' => 'Nová figurka', 'body' => 'Text', 'locale' => 'cs', 'status' => 'draft', 'generated_by_ai' => true]);
        $this->actingAs($this->admin)->post('/admin/emails/'.$own->id, ['to' => 'mila@example.com', 'subject' => 'Nová figurka', 'body' => 'Text', 'action' => 'approve',
            'files' => [UploadedFile::fake()->createWithContent('foto.png', $this->png(200, 200))]])->assertRedirect();
        Mail::assertSent(PlainMessage::class, fn (PlainMessage $m) => count($m->attachments()) === 1);
    }

    public function test_the_gmail_reply_with_files_is_multipart(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($path, 'JPEGDATA');
        $raw = GmailMailbox::raw('info@matplace.com', 'mila@example.com', 'Dotaz', "Dobrý den,\n\nposíláme.", '<x@y>', [['name' => 'F26-000194-model.jpg', 'mime' => 'image/jpeg', 'path' => $path]]);
        @unlink($path);
        $this->assertMatchesRegularExpression('/Content-Type: multipart\/mixed; boundary="(mp-[0-9a-f]+)"/', $raw);
        preg_match('/boundary="([^"]+)"/', $raw, $m);
        $this->assertStringContainsString("--{$m[1]}\r\nContent-Type: text/plain; charset=UTF-8", $raw);
        $this->assertStringContainsString('Content-Disposition: attachment; filename="=?UTF-8?B?'.base64_encode('F26-000194-model.jpg').'?="', $raw);
        $this->assertStringContainsString(base64_encode('JPEGDATA'), $raw);
        $this->assertStringEndsWith("--{$m[1]}--\r\n", $raw);
        $this->assertStringNotContainsString('multipart', GmailMailbox::raw('a@b', 'c@d', 'x', 'y', ''), 'without files it stays plain text');
    }

    public function test_the_assistant_sees_our_model_next_to_the_customer_photos(): void
    {
        Storage::disk('farm')->put('orders/tokABC/keep/model.jpg', $this->png(900, 600));
        Storage::disk('farm')->put('orders/tokABC/keep/source.jpg', $this->png(600, 600));
        app(Outbox::class)->reply(['id' => 'h9', 'thread_id' => 't9', 'from' => 'mila@example.com', 'from_name' => 'Mila', 'subject' => 'Re: Zakázka F26-000194',
            'text' => 'Objednala jsem fenu, přišel pes.', 'fresh' => 'Objednala jsem fenu, přišel pes.', 'quoted' => '', 'message_id' => '<h9@x>', 'attachments' => []]);
        $call = FakeAssistant::$calls[0];
        $this->assertCount(2, $call['images']);
        $this->assertStringEndsWith('keep/model.jpg', str_replace('\\', '/', $call['images'][0]));
        $this->assertStringContainsString('none attached by the customer; then F26-000194: our picture of the model we printed; F26-000194: the photo the customer gave us to make the model from', $call['user']);
        $this->assertFileExists($call['images'][0], 'kept pictures are not deleted after the call');
    }
}
