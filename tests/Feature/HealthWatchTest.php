<?php

namespace Tests\Feature;

use App\Domain\Farm\FarmSettings;
use App\Domain\Mail\MailboxWatch;
use App\Domain\Ops\HealthChecks;
use App\Mail\HealthAlert;
use App\Models\HealthCheck;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The server checks (docs/AU.md): the owner hears when a check flips, once a day while it stays bad, and sees it all in the admin. */
class HealthWatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        HealthChecks::$fake = null;
        config(['watch.backup_marker' => '']);
        Cache::forget(MailboxWatch::CACHE);
    }

    protected function tearDown(): void
    {
        HealthChecks::$fake = null;
        parent::tearDown();
    }

    public function test_a_problem_is_told_once_then_daily_while_it_lasts_then_its_end(): void
    {
        app(FarmSettings::class)->set('alert_email', 'owner@example.com');
        HealthChecks::$fake = [['key' => 'disk', 'ok' => true, 'message' => 'systém 48 %'], ['key' => 'failed_jobs', 'ok' => false, 'message' => '1 za posledních 60 min – GenerateModel: boom']];
        $this->artisan('matplace:health')->assertExitCode(1);
        Mail::assertQueued(HealthAlert::class, fn (HealthAlert $m) => $m->hasTo('owner@example.com') && $m->envelope()->subject === '[hlídač] Problém: Spadlé úlohy' && $m->changes[0]['what'] === 'problem');
        Mail::assertQueuedCount(1);
        $this->assertSame(['disk' => true, 'failed_jobs' => false], HealthCheck::orderBy('key')->pluck('ok', 'key')->all());
        $this->assertNotNull(HealthCheck::where('key', 'failed_jobs')->first()->alerted_at);
        $this->assertNull(HealthCheck::where('key', 'disk')->first()->alerted_at, 'an ok check on the first run says nothing');

        // the same again: silence
        $this->artisan('matplace:health')->assertExitCode(1);
        Mail::assertQueuedCount(1);

        // a day later still bad: a reminder
        HealthCheck::where('key', 'failed_jobs')->update(['alerted_at' => now()->subHours(25)]);
        $this->artisan('matplace:health')->assertExitCode(1);
        Mail::assertQueuedCount(2);
        Mail::assertQueued(HealthAlert::class, fn (HealthAlert $m) => $m->changes[0]['what'] === 'still');

        // solved: told once
        HealthChecks::$fake = [['key' => 'disk', 'ok' => true, 'message' => 'systém 48 %'], ['key' => 'failed_jobs', 'ok' => true, 'message' => 'žádná']];
        $this->artisan('matplace:health')->assertExitCode(0);
        Mail::assertQueuedCount(3);
        Mail::assertQueued(HealthAlert::class, fn (HealthAlert $m) => $m->envelope()->subject === '[hlídač] Vyřešeno: Spadlé úlohy' && $m->changes[0]['what'] === 'solved');
        $this->artisan('matplace:health')->assertExitCode(0);
        Mail::assertQueuedCount(3);

        // the mail renders with the change and the whole picture
        $html = (new HealthAlert([['key' => 'disk', 'ok' => false, 'message' => 'KRITICKÉ: systém 95 %', 'what' => 'problem']], HealthChecks::$fake))->render();
        $this->assertStringContainsString('Problém – Disk', $html);
        $this->assertStringContainsString('OK – Spadlé úlohy', $html);
        $this->assertStringContainsString(route('admin.health'), $html);
    }

    public function test_without_an_address_the_problem_waits_for_one(): void
    {
        HealthChecks::$fake = [['key' => 'queue', 'ok' => false, 'message' => 'úloha čeká 20 min']];
        $this->artisan('matplace:health')->assertExitCode(1);
        Mail::assertNothingQueued();
        app(FarmSettings::class)->set('alert_email', 'owner@example.com');
        $this->artisan('matplace:health')->assertExitCode(1);
        Mail::assertQueued(HealthAlert::class, fn (HealthAlert $m) => $m->changes[0]['what'] === 'problem');
    }

    public function test_the_real_checks_answer_in_one_shape_and_see_a_failed_job_and_a_stale_watch(): void
    {
        $rows = app(HealthChecks::class)->run();
        $keys = array_column($rows, 'key');
        foreach (['disk', 'models', 'failed_jobs', 'queue', 'log', 'mailbox'] as $k) {
            $this->assertContains($k, $keys);
        }
        $this->assertNotContains('backup', $keys, 'no marker configured = no backup check');
        foreach ($rows as $r) {
            $this->assertIsBool($r['ok']);
            $this->assertNotSame('', $r['message']);
        }
        $this->assertTrue(collect($rows)->firstWhere('key', 'failed_jobs')['ok']);
        $this->assertTrue(collect($rows)->firstWhere('key', 'queue')['ok']);
        $this->assertSame('zatím neběžel', collect($rows)->firstWhere('key', 'mailbox')['message']);

        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Jobs\\GenerateModel']), 'exception' => "RuntimeException: boom\n  at x", 'failed_at' => now()]);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(40)->getTimestamp(), 'created_at' => now()->subMinutes(40)->getTimestamp()]);
        Cache::put(MailboxWatch::CACHE, ['unread_human' => 2, 'checked_at' => now()->subMinutes(45)->toIso8601String()], 600);
        $rows = collect(app(HealthChecks::class)->run());
        $this->assertFalse($rows->firstWhere('key', 'failed_jobs')['ok']);
        $this->assertStringContainsString('GenerateModel: RuntimeException: boom at x', $rows->firstWhere('key', 'failed_jobs')['message']);
        $this->assertFalse($rows->firstWhere('key', 'queue')['ok']);
        $this->assertStringContainsString('čeká 40 min', $rows->firstWhere('key', 'queue')['message']);
        $this->assertFalse($rows->firstWhere('key', 'mailbox')['ok']);
        $this->assertStringContainsString('45 min', $rows->firstWhere('key', 'mailbox')['message']);

        // --print remembers nothing
        $this->artisan('matplace:health', ['--print' => true])->run();
        $this->assertSame(0, HealthCheck::count());
    }

    public function test_the_admin_page_shows_the_checks_and_runs_them(): void
    {
        $admin = User::factory()->create();
        UserRole::create(['user_id' => $admin->id, 'role' => 'admin']);
        HealthChecks::$fake = [['key' => 'disk', 'ok' => false, 'message' => 'KRITICKÉ: systém 95 % (3 GB volných)']];
        $this->get('/admin/health')->assertRedirect();
        $this->actingAs($admin)->get('/admin/health')->assertOk()->assertSee('Ještě neproběhla žádná kontrola')->assertSee('nikam');
        $this->actingAs($admin)->post('/admin/health/run')->assertRedirect(route('admin.health'));
        // "check now" ran both: the check is on the page, and the inbox pass left its count
        $this->actingAs($admin)->get('/admin/health')->assertOk()->assertSee('KRITICKÉ: systém 95 %')->assertSee('Problém')->assertSee('nepřečtených od lidí')->assertDontSee('zatím neběžel');
    }
}
