<?php

namespace Tests\Feature;

use App\Domain\Farm\AgentService;
use App\Mail\FarmAdminAlert;
use App\Models\FarmPrinter;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A printer that keeps dropping off Wi‑Fi tells the admin once in a while, not at every drop. */
class FarmOfflineAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_flapping_printer_mails_the_admin_once_in_six_hours(): void
    {
        Storage::fake('farm');
        Mail::fake();
        $this->seed(FarmSeeder::class);
        config(['farm.settings.admin_email' => 'roman@example.com']);
        $printer = FarmPrinter::where('mode', FarmPrinter::MODE_AGENT)->where('enabled', true)->firstOrFail();
        // as a heartbeat does: seen (ten minutes ago, so the watch finds it silent), the "offline" mark cleared
        $silent = fn () => $printer->refresh()->forceFill(['last_seen_at' => now()->subMinutes(10), 'offline_notified_at' => null, 'state' => FarmPrinter::STATE_IDLE])->save();
        $watch = app(AgentService::class);

        // the first drop: one mail
        $silent();
        $this->assertSame(1, $watch->watch());
        Mail::assertQueued(FarmAdminAlert::class, 1);
        $this->assertNotNull($printer->fresh()->offline_alerted_at);

        // back for a moment and gone again, three times within the hour: no more mails, the state still follows
        foreach (range(1, 3) as $i) {
            $this->travel(15)->minutes();
            $silent();   // a heartbeat clears offline_notified_at; the next watch finds it silent again
            $this->assertSame(0, $watch->watch(), "drop {$i}");
            $this->assertSame(FarmPrinter::STATE_UNKNOWN, $printer->fresh()->state);
        }
        Mail::assertQueued(FarmAdminAlert::class, 1);

        // six hours later it is news again
        $this->travel(6)->hours();
        $silent();
        $this->assertSame(1, $watch->watch());
        Mail::assertQueued(FarmAdminAlert::class, 2);

        // a disabled printer is nobody's concern
        $this->travel(7)->hours();
        $silent();
        $printer->forceFill(['enabled' => false])->save();
        $this->assertSame(0, $watch->watch());
        Mail::assertQueued(FarmAdminAlert::class, 2);
    }
}
