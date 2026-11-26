<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\Mail\MailboxWatch;
use App\Domain\Mail\MailText;
use App\Engines\Ai\FakeAssistant;
use App\Engines\Mail\FakeMailbox;
use App\Mail\NewMailAlert;
use App\Models\FarmMaterial;
use App\Models\FarmOrder;
use App\Models\MailboxAlert;
use App\Models\ModelFile;
use App\Models\OutgoingEmail;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The mailbox watch (docs/AU.md): people's mails reach the owner once, our own notifications and reports leave the
 * inbox, robots are folded away in the admin, a mail still unread a day later is mentioned once a day.
 */
class WatchInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FakeMailbox::reset();
        FakeAssistant::reset();
        Mail::fake();
        Cache::forget(MailboxWatch::CACHE);
    }

    public function test_an_old_mail_seen_for_the_first_time_is_not_reminded_at_once(): void
    {
        // 26 Nov 2026: five mails weeks old were announced at 02:01 and "still unread" came at 02:05
        app(FarmSettings::class)->set('alert_email', 'owner@example.com');
        FakeMailbox::put('h1', ['from' => 'katerina@example.com', 'subject' => 'Poptávka', 'date' => now()->subDays(28)->toRfc2822String()]);
        $this->artisan('matplace:watch-inbox')->assertExitCode(0);
        $this->artisan('matplace:watch-inbox')->assertExitCode(0);
        Mail::assertQueuedCount(1);
        Mail::assertQueued(NewMailAlert::class, fn (NewMailAlert $m) => ! $m->reminder);
        // a day after the owner heard: now
        MailboxAlert::where('message_id', 'h1')->update(['alerted_at' => now()->subHours(25)]);
        $this->artisan('matplace:watch-inbox')->assertExitCode(0);
        Mail::assertQueued(NewMailAlert::class, fn (NewMailAlert $m) => $m->reminder);
    }

    public function test_a_new_mail_gets_a_draft_reply_that_saw_the_photos_and_the_order(): void
    {
        app(FarmSettings::class)->set('alert_email', 'owner@example.com');
        $this->seed(FarmSeeder::class);
        $customer = User::factory()->create(['email' => 'mila@example.com']);
        $file = ModelFile::create(['uuid' => (string) Str::uuid(), 'original_name' => 'pet-figurine.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => 1,
            'sha256' => str_repeat('a', 64), 'storage_path' => 'models/x/model.stl', 'status' => 'ready', 'owner_user_id' => $customer->id]);
        $order = FarmOrder::forceCreate(['token' => 'tok-1', 'number' => 'F26-000194', 'user_id' => $customer->id, 'model_file_id' => $file->id, 'status' => FarmOrder::STATUS_HANDED_OVER, 'quality' => 'standard', 'strength' => 'standard',
            'farm_material_id' => FarmMaterial::query()->value('id'), 'price_total' => 359, 'currency' => 'CZK', 'paid_at' => now()->subDays(6), 'packeta_barcode' => 'Z3701584914']);
        $jpeg = (string) base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        FakeMailbox::put('h1', ['from' => 'mila@example.com', 'from_name' => 'Mila Hurtová', 'subject' => 'Re: Zakázka F26-000194 je na cestě',
            'text' => "Dobrý den objednala jsem si u vás vytisknout psa (fenu)\na přišel mi pes, posílám moji fotku a co mi přišlo\n---------- Původní e-mail ----------\nOd: matplace <info@matplace.cz>\nKomu: mila@example.com\n\nZakázka F26-000194 je na cestě",
            'files' => ['a1' => ['fena.jpg', 'image/jpeg', $jpeg], 'a2' => ['vytisk.jpg', 'image/jpeg', $jpeg]]]);
        FakeAssistant::$answers['email'] = ['subject' => 'Re: Zakázka F26-000194 je na cestě', 'body' => "Dobrý den,\n\nomlouváme se, na fotkách vidíme rozdíl.\n\nmatplace"];

        $this->artisan('matplace:watch-inbox')->assertExitCode(0);

        $draft = OutgoingEmail::where('inbox_message_id', 'h1')->firstOrFail();
        $this->assertSame(['draft', 'mila@example.com', 'cs', 't-h1'], [$draft->status, $draft->to, $draft->locale, $draft->inbox_thread_id]);
        $call = FakeAssistant::$calls[0];
        $this->assertCount(2, $call['images'], 'both photos were shown to the assistant');
        $this->assertStringContainsString("Message:\nDobrý den objednala jsem si u vás vytisknout psa (fenu)\na přišel mi pes", $call['user']);
        $this->assertStringContainsString("Attachments:\n- fena.jpg (image/jpeg)\n- vytisk.jpg (image/jpeg)", $call['user']);
        $this->assertStringContainsString('F26-000194 · Vydáno', $call['user']);
        $this->assertStringContainsString('359 CZK', $call['user']);
        $this->assertStringContainsString('zásilka Z3701584914', $call['user']);
        $this->assertStringContainsString("Quoted:\n---------- Původní e-mail", $call['user']);
        $this->assertStringContainsString('never say they did not', $call['system']);
        foreach ($call['images'] as $path) {
            $this->assertFileDoesNotExist($path, 'temporary pictures are deleted');
        }
        Mail::assertQueued(NewMailAlert::class, fn (NewMailAlert $m) => ($m->messages[0]['draft_url'] ?? null) === route('admin.emails.show', $draft->id)
            && str_contains($m->render(), 'Koncept odpovědi ke schválení'));

        // a second pass writes no second draft
        $this->artisan('matplace:watch-inbox')->assertExitCode(0);
        $this->assertSame(1, OutgoingEmail::where('inbox_message_id', 'h1')->count());

        // the admin: the person's words big, photos shown, the quote folded, the order linked, the draft one click away
        $admin = User::factory()->create();
        UserRole::create(['user_id' => $admin->id, 'role' => 'admin']);
        $this->actingAs($admin)->get('/admin/emails/inbox/h1')->assertOk()
            ->assertSee('Koncept odpovědi je připravený')->assertSee(route('admin.emails.show', $draft->id), false)
            ->assertSee('a přišel mi pes')->assertSee(route('admin.emails.inbox.file', ['h1', 'a1']), false)->assertSee('fena.jpg')
            ->assertSee('Citovaný e-mail pod zprávou')->assertSee('Objednávky tohoto člověka')->assertSee(route('admin.farm.orders.show', $order), false);
        $this->actingAs($admin)->get('/admin/emails/inbox/h1/file/a1')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($admin)->get('/admin/emails/inbox/h1/file/nope')->assertNotFound();
        $this->actingAs($admin)->get('/admin/emails/'.$draft->id)->assertOk()->assertSee('a přišel mi pes')->assertSee(route('admin.emails.inbox.file', ['h1', 'a2']), false);
        $this->actingAs($customer)->get('/admin/emails/inbox/h1/file/a1')->assertRedirect();
    }

    public function test_the_quote_is_split_off_and_the_language_guessed(): void
    {
        $this->assertSame(['fresh' => 'Díky, dorazilo.', 'quoted' => "Dne 21. 11. 2026 v 13:29 matplace <info@matplace.cz> napsal(a):\n> Zakázka je na cestě"],
            MailText::split("Díky, dorazilo.\n\nDne 21. 11. 2026 v 13:29 matplace <info@matplace.cz> napsal(a):\n> Zakázka je na cestě"));
        $this->assertSame('Hi', MailText::split("Hi\n\nOn Mon, Nov 23, 2026 at 10:00 AM matplace wrote:\n> text")['fresh']);
        $this->assertSame('Ahoj', MailText::split("Ahoj\n-----Original Message-----\nFrom: x")['fresh']);
        $this->assertSame(['fresh' => 'Jen text bez citace.', 'quoted' => ''], MailText::split('Jen text bez citace.'));
        $this->assertSame('cs', MailText::locale('Dobrý den, přišel mi pes'));
        $this->assertSame('es', MailText::locale('Hola, quiero saber el precio de mi pedido'));
        $this->assertSame('en', MailText::locale('Hello, could you print my model please?'));
        $this->assertSame('cs', MailText::locale('ok'));
    }

    public function test_the_four_kinds(): void
    {
        $own = ['info@matplace.com', 'info@matplace.cz'];
        $this->assertSame(MailboxWatch::HUMAN, MailboxWatch::kind('katerina.bartosova@saunia.cz', 'Poptávka 3D tisku – 50 ks klíčenek', $own));
        $this->assertSame(MailboxWatch::HUMAN, MailboxWatch::kind('hello@small-company.cz', 'Dotaz', $own), 'a plain address with a plain subject is a person');
        $this->assertSame(MailboxWatch::OWN, MailboxWatch::kind('info@matplace.cz', '[farm] Nová zakázka F26-000208: zaplaceno', $own));
        $this->assertSame(MailboxWatch::OWN, MailboxWatch::kind('Info@Matplace.com ', 'Zakázka je vytištěná', $own));
        $this->assertSame(MailboxWatch::OWN, MailboxWatch::kind('somebody@example.com', '[farm] forwarded', $own));
        $this->assertSame(MailboxWatch::REPORT, MailboxWatch::kind('mailer-daemon@googlemail.com', 'Delivery Status Notification (Failure)', $own));
        $this->assertSame(MailboxWatch::REPORT, MailboxWatch::kind('noreply-dmarc-support@google.com', 'Report domain: matplace.cz Submitter: google.com', $own));
        $this->assertSame(MailboxWatch::REPORT, MailboxWatch::kind('abuse@seznam.cz', 'Report Domain: matplace.cz Submitter: seznam.cz', $own));
        $this->assertSame(MailboxWatch::REPORT, MailboxWatch::kind('dmarcreport@microsoft.com', '[Preview] Report Domain: matplace.cz', $own));
        $this->assertSame(MailboxWatch::ROBOT, MailboxWatch::kind('follow-suggestions@mail.instagram.com', 'Mrkněte na pravoslavny.tuklaty', $own));
        $this->assertSame(MailboxWatch::ROBOT, MailboxWatch::kind('No-Reply@accounts.google.com', 'Bezpečnostní upozornění', $own));
        $this->assertSame(MailboxWatch::ROBOT, MailboxWatch::kind('notifications@stripe.com', 'All secret API key values will be hidden', $own));
    }

    public function test_people_reach_the_owner_once_and_the_noise_leaves_the_inbox(): void
    {
        app(FarmSettings::class)->set('alert_email', 'owner@example.com');
        FakeMailbox::put('h1', ['from' => 'katerina@example.com', 'from_name' => 'Kateřina Bartošová', 'subject' => 'Poptávka 50 ks klíčenek', 'text' => 'Dobrý den, ráda bych požádala o kalkulaci.', 'date' => now()->subMinutes(3)->toRfc2822String()]);
        FakeMailbox::put('o1', ['from' => 'info@matplace.cz', 'from_name' => 'matplace', 'subject' => '[farm] Nová zakázka F26-000208: zaplaceno', 'text' => 'Zákazník…']);
        FakeMailbox::put('r1', ['from' => 'mailer-daemon@googlemail.com', 'subject' => 'Delivery Status Notification (Failure)', 'text' => '550']);
        FakeMailbox::put('b1', ['from' => 'noreply@newsletter.example.com', 'subject' => 'Save 60 %', 'text' => 'Buy']);
        FakeMailbox::put('h0', ['from' => 'old@example.com', 'subject' => 'Already read', 'text' => '…', 'unread' => false]);

        $this->artisan('matplace:watch-inbox')->assertExitCode(0);

        Mail::assertQueued(NewMailAlert::class, function (NewMailAlert $m) {
            return count($m->messages) === 1 && $m->messages[0]['id'] === 'h1' && ! $m->reminder && $m->hasTo('owner@example.com')
                && str_contains((string) $m->envelope()->subject, 'Kateřina Bartošová') && str_contains((string) $m->envelope()->subject, 'Poptávka 50 ks');
        });
        Mail::assertQueuedCount(1);
        $row = MailboxAlert::where('message_id', 'h1')->firstOrFail();
        $this->assertNotNull($row->alerted_at);
        $this->assertSame('Kateřina Bartošová', $row->from_name);
        $this->assertNotNull($row->received_at);
        // our own notification and the bounce left the inbox; the robot and the person stayed, unread
        $this->assertTrue(FakeMailbox::$messages['o1']['archived'] ?? false);
        $this->assertTrue(FakeMailbox::$messages['r1']['archived'] ?? false);
        $this->assertArrayNotHasKey('archived', FakeMailbox::$messages['b1']);
        $this->assertTrue(FakeMailbox::$messages['h1']['unread'], 'the watch reads nothing on the owner\'s behalf');
        $this->assertSame(1, Cache::get(MailboxWatch::CACHE)['unread_human']);

        // a second pass: nothing new, nobody is told twice
        $this->artisan('matplace:watch-inbox')->assertExitCode(0);
        Mail::assertQueuedCount(1);
        $this->assertSame(1, MailboxAlert::count());

        // the mail itself: who, what, and the link to the message in the admin
        $html = (new NewMailAlert([FakeMailbox::$messages['h1'] + ['snippet' => 'Dobrý den, ráda bych požádala o kalkulaci.']], 'info@matplace.com'))->render();
        $this->assertStringContainsString('Kateřina Bartošová', $html);
        $this->assertStringContainsString('ráda bych požádala', $html);
        $this->assertStringContainsString(route('admin.emails.inbox.show', 'h1'), $html);
    }

    public function test_without_an_address_the_mail_is_remembered_but_nobody_is_told(): void
    {
        FakeMailbox::put('h1', ['from' => 'jana@example.com', 'subject' => 'Dotaz']);
        $this->artisan('matplace:watch-inbox')->assertExitCode(0);
        Mail::assertNothingQueued();
        $this->assertNull(MailboxAlert::where('message_id', 'h1')->firstOrFail()->alerted_at);
    }

    public function test_a_mail_still_unread_a_day_later_is_mentioned_once_a_day(): void
    {
        app(FarmSettings::class)->set('alert_email', 'owner@example.com');
        FakeMailbox::put('h1', ['from' => 'jana@example.com', 'from_name' => 'Jana', 'subject' => 'Dotaz', 'date' => now()->subHours(30)->toRfc2822String()]);
        MailboxAlert::create(['message_id' => 'h1', 'from' => 'jana@example.com', 'subject' => 'Dotaz', 'received_at' => now()->subHours(30), 'alerted_at' => now()->subHours(30)]);

        $this->artisan('matplace:watch-inbox')->assertExitCode(0);
        Mail::assertQueued(NewMailAlert::class, fn (NewMailAlert $m) => $m->reminder && $m->messages[0]['id'] === 'h1' && str_contains((string) $m->envelope()->subject, 'Stále nepřečtený'));
        $this->assertNotNull(MailboxAlert::where('message_id', 'h1')->first()->reminded_at);

        $this->artisan('matplace:watch-inbox')->assertExitCode(0);
        Mail::assertQueuedCount(1);   // no second reminder the same day
    }

    public function test_the_admin_inbox_folds_robots_away_and_the_badge_counts_people(): void
    {
        $admin = User::factory()->create();
        UserRole::create(['user_id' => $admin->id, 'role' => 'admin']);
        FakeMailbox::put('h1', ['from' => 'jana@example.com', 'from_name' => 'Jana', 'subject' => 'Dotaz k zakázce']);
        FakeMailbox::put('b1', ['from' => 'noreply@newsletter.example.com', 'subject' => 'Save sixty percent']);
        Cache::put(MailboxWatch::CACHE, ['unread_human' => 7, 'checked_at' => now()->toIso8601String()], 600);

        $this->actingAs($admin)->get('/admin/emails/inbox')->assertOk()
            ->assertSee('Dotaz k zakázce')->assertDontSee('Save sixty percent')->assertSee('Automatické (1)')
            ->assertSee('nepřečtené od lidí', false)->assertSee('>7<', false);
        $this->actingAs($admin)->get('/admin/emails/inbox?auto=1')->assertOk()->assertSee('Save sixty percent')->assertSee('Skrýt automatické');
    }
}
