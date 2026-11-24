<?php

namespace Tests\Feature;

use App\Mail\ModelLink;
use App\Models\AnonymousSession;
use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\ModelLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Send me my bust" (Roman, 24 Nov 2026): a visitor from the ads made a bust, left, and nothing of him stayed. At the
 * finished model he leaves an e-mail: the link goes out at once (never the file), the address is kept, one reminder
 * only when ticked, an unsubscribe link, and the model is not pruned meanwhile.
 */
class ModelLeadTest extends TestCase
{
    use RefreshDatabase;

    private function bust(?AnonymousSession $session, array $extra = []): ModelFile
    {
        return ModelFile::create($extra + ['uuid' => (string) Str::uuid(), 'original_name' => 'bust.stl', 'ext' => 'stl', 'mime' => 'model/stl', 'size_bytes' => 1,
            'sha256' => str_repeat('a', 64), 'storage_path' => 'files/x/original.stl', 'origin' => 'generated', 'origin_ref' => Str::random(16),
            'status' => ModelFile::STATUS_READY, 'anonymous_session_id' => $session?->id]);
    }

    public function test_the_visitor_keeps_his_bust_by_e_mail_and_gets_one_reminder_only_when_he_ticked_it(): void
    {
        Mail::fake();
        $session = AnonymousSession::start('127.0.0.1', 'test');
        $mine = $this->bust($session);
        $as = fn () => $this->withCredentials()->withUnencryptedCookie(AnonymousSession::COOKIE, $session->token);

        // the page offers the box on his own model from a photo, not on somebody else's
        $as()->getJson(route('api.files.show', $mine->uuid))->assertOk()->assertJsonPath('file.keep_url', route('api.files.keep', $mine->uuid));
        $other = $this->bust(AnonymousSession::start('127.0.0.2', 'test'));
        $as()->getJson(route('api.files.show', $other->uuid))->assertOk()->assertJsonPath('file.keep_url', null);
        $as()->postJson(route('api.files.keep', $other->uuid), ['email' => 'x@example.com'])->assertNotFound();

        // the link goes out at once, the address is kept; no reminder without the tick
        $as()->postJson(route('api.files.keep', $mine->uuid), ['email' => 'eva@example.com'])->assertOk();
        $lead = ModelLead::firstOrFail();
        $this->assertSame([$mine->id, false, null], [$lead->model_file_id, $lead->remind, $lead->consent_at]);
        Mail::assertQueued(ModelLink::class, fn (ModelLink $m) => $m->hasTo('eva@example.com') && ! $m->reminder);
        $html = (new ModelLink($lead))->render();
        $this->assertStringContainsString(route('home', ['open' => $mine->uuid]), $html, 'the link, never the file');
        $this->assertStringNotContainsString('model.stl', $html);

        $this->travel(3)->days();
        $this->artisan('matplace:lead-reminders')->assertSuccessful();
        Mail::assertQueuedCount(1);

        // ticked: one reminder after two days, once
        $as()->postJson(route('api.files.keep', $mine->uuid), ['email' => 'eva@example.com', 'remind' => true])->assertOk();
        $this->assertSame(1, ModelLead::count());
        $this->assertNotNull($lead->refresh()->consent_at);
        $this->artisan('matplace:lead-reminders')->assertSuccessful();
        Mail::assertQueuedCount(2);                                                  // not yet
        $this->travel(2)->days();
        $this->artisan('matplace:lead-reminders')->assertSuccessful();
        Mail::assertQueued(ModelLink::class, fn (ModelLink $m) => $m->reminder);
        $this->artisan('matplace:lead-reminders')->assertSuccessful();
        Mail::assertQueuedCount(3);
        $this->assertStringContainsString(route('keep.unsubscribe', $lead->token), (new ModelLink($lead->refresh(), true))->render());

        // the unsubscribe link: nothing more
        $this->get(route('keep.unsubscribe', $lead->token))->assertOk()->assertSee(__('keep.off.title'));
        $this->assertNotNull($lead->refresh()->unsubscribed_at);
        $this->assertFalse($lead->remind);
    }

    public function test_no_reminder_once_the_bust_is_paid_and_the_kept_model_is_not_pruned(): void
    {
        Mail::fake();
        $session = AnonymousSession::start('127.0.0.1', 'test');
        $kept = $this->bust($session);
        $loose = $this->bust($session);
        ModelFile::whereIn('id', [$kept->id, $loose->id])->update(['created_at' => now()->subDays(45)]);
        $this->withCredentials()->withUnencryptedCookie(AnonymousSession::COOKIE, $session->token)->postJson(route('api.files.keep', $kept->uuid), ['email' => 'eva@example.com', 'remind' => 1])->assertOk();
        $unordered = app(\App\Domain\Account\ModelLibrary::class)->unordered()->pluck('id')->all();
        $this->assertNotContains($kept->id, $unordered, 'a model kept by e-mail stays');
        $this->assertContains($loose->id, $unordered);

        $this->seed(\Database\Seeders\FarmSeeder::class);
        $color = \App\Models\FarmColor::firstOrFail();
        FarmOrder::forceCreate(['farm_material_id' => $color->farm_material_id, 'farm_color_id' => $color->id, 'farm_printer_id' => \App\Models\FarmPrinter::firstOrFail()->id, 'token' => Str::random(32), 'model_file_id' => $kept->id, 'status' => FarmOrder::STATUS_QUEUED, 'paid_at' => now(), 'quality' => 'standard', 'strength' => 'standard']);
        $this->travel(3)->days();
        $this->artisan('matplace:lead-reminders')->assertSuccessful();
        Mail::assertQueuedCount(1);                                                  // the link only
        $this->assertNotNull(ModelLead::firstOrFail()->reminded_at);
    }

    public function test_a_signed_in_customer_has_his_models_in_the_account_and_no_box(): void
    {
        $user = User::factory()->create();
        $file = $this->bust(null, ['owner_user_id' => $user->id]);
        $this->actingAs($user)->getJson(route('api.files.show', $file->uuid))->assertOk()->assertJsonPath('file.keep_url', null);
    }
}
