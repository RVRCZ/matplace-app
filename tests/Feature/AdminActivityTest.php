<?php

namespace Tests\Feature;

use App\Models\AnonymousSession;
use App\Models\Event;
use App\Models\FarmOrder;
use App\Models\FarmOrderEvent;
use App\Models\FarmPrinter;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** /admin/farm/activity (Roman, 28 Nov 2026): what happened on the web, newest first (App\Domain\Stats\ActivityStream). */
class AdminActivityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin;
    }

    private function visitorSession(bool $person): int
    {
        $id = AnonymousSession::create(['token' => Str::random(40)])->id;
        Event::create(['session_id' => $id, 'type' => Event::VISIT, 'source' => 'facebook', 'utm' => ['source' => 'facebook', 'content' => 'video-real'],
            'meta' => array_filter(['path' => '/tools/figure', 'js' => $person ?: null])]);

        return $id;
    }

    private function event(int $session, string $type, array $meta = [], ?ModelFile $file = null, ?\DateTimeInterface $at = null): Event
    {
        $e = Event::create(['session_id' => $session, 'type' => $type, 'source' => 'facebook', 'utm' => ['source' => 'facebook', 'content' => 'video-real'],
            'subject_type' => $file ? 'model_file' : null, 'subject_id' => $file?->id, 'meta' => $meta ?: null]);
        if ($at) {
            $e->forceFill(['created_at' => $at])->save();
        }

        return $e;
    }

    public function test_the_stream_shows_models_the_way_to_an_order_and_the_prints(): void
    {
        $person = $this->visitorSession(true);
        $file = ModelFile::factory()->create(['original_name' => 'busta-barvy.3mf']);
        $order = FarmOrder::factory()->create(['number' => 'F26-000999', 'model_file_id' => $file->id, 'anonymous_session_id' => $person,
            'farm_printer_id' => FarmPrinter::create(['name' => 'Kobra S1 #9', 'model' => 'Kobra S1', 'key' => 'b91', 'mode' => 'manual', 'enabled' => true, 'bed_x' => 250, 'bed_y' => 250, 'bed_z' => 250,
                'nozzle_mm' => 0.4, 'machine_profile' => 'machine.json', 'process_profiles' => ['standard' => 'process_standard.json'], 'time_factor' => 1, 'weight_factor' => 1])->id]);
        $this->event($person, 'upload', ['kind' => 'stl'], $file);
        $this->event($person, 'generate', ['kind' => 'image', 'tool' => 'figure']);
        $this->event($person, 'order_started', ['kind' => 'file'], $file);
        $this->event($person, 'order_created', ['order' => $order->id], $file);
        $this->event($person, 'tool_step', ['tool' => 'order', 'step' => 'o_slice']);
        $this->event($person, 'tool_step', ['tool' => 'figure', 'step' => 'wait_left', 'ms' => 41000]);
        $this->event($person, 'tool_step', ['tool' => 'figure', 'step' => 'scroll']);
        FarmOrderEvent::create(['farm_order_id' => $order->id, 'from' => 'queued', 'to' => 'printing', 'actor' => 'agent']);
        FarmOrderEvent::create(['farm_order_id' => $order->id, 'from' => 'printing', 'to' => 'stopped', 'actor' => 'admin', 'note' => 'špageta']);

        // robots open the order page by the thousand: a session no browser confirmed shows no page loads
        $robot = $this->visitorSession(false);
        $this->event($robot, 'order_started', ['kind' => 'inspiration']);
        // what the staff did (marked when an admin was recognised in that browser) never shows
        $this->event($person, 'download', ['staff' => true], $file);
        // older than the window
        $this->event($person, 'calculation', [], $file, now()->subHours(30));

        $admin = $this->admin();
        $page = $this->actingAs($admin)->get('/admin/farm/activity')->assertOk()
            ->assertSee('Aktivita na webu')
            ->assertSee('Nahrál model')->assertSee('busta-barvy.3mf')
            ->assertSee('Spustil generování modelu')->assertSee('z fotky')
            ->assertSee('Založil zakázku (návrh tisku)')->assertSee('F26-000999')
            ->assertSee('Přepočet tisku')
            ->assertSee('Odešel během čekání na model')->assertSee('po 41 s')
            ->assertSee('Tisk spuštěn')->assertSee('Kobra S1 #9')
            ->assertSee('Tisk zastaven')->assertSee('špageta')
            ->assertSee('Facebook · video-real')
            ->assertSee('host #'.$person)
            ->assertDontSee('Posunul stránku nástroje')
            ->assertDontSee('host #'.$robot)
            ->assertDontSee('Stáhl model')
            ->assertDontSee('Spočítal cenu v kalkulačce');
        $html = $page->getContent();
        $this->assertSame(1, substr_count($html, 'Otevřel návrh tisku'), 'only the person opened the order page');
        // newest first: the stop came after the print started
        $this->assertLessThan(strpos($html, 'Tisk spuštěn'), strpos($html, 'Tisk zastaven'));

        // the detailed view shows the small steps too; the longer window the older events
        $this->actingAs($admin)->get('/admin/farm/activity?detail=1')->assertOk()->assertSee('Posunul stránku nástroje')->assertSee('Přišel na web');
        $this->actingAs($admin)->get('/admin/farm/activity?window=3d')->assertOk()->assertSee('Spočítal cenu v kalkulačce');

        // one group only
        $this->actingAs($admin)->get('/admin/farm/activity?group=print')->assertOk()->assertSee('Tisk spuštěn')->assertDontSee('Nahrál model');

        // one visitor's whole way, every step, the robot's not among them
        $this->actingAs($admin)->get('/admin/farm/activity?session='.$person)->assertOk()
            ->assertSee('Cesta návštěvníka')->assertSee('Posunul stránku nástroje')->assertSee('Tisk spuštěn')->assertSee('Spočítal cenu v kalkulačce');
        $this->actingAs($admin)->get('/admin/farm/activity?session='.$robot)->assertOk()->assertDontSee('Nahrál model');
    }

    public function test_the_router_requests_show_with_their_text_and_the_page_is_admin_only(): void
    {
        $person = $this->visitorSession(true);
        $intake = \App\Models\Intake::create(['token' => Str::random(32), 'anonymous_session_id' => $person, 'kind' => 'text', 'text' => 'Potřebuji náhradní kolečko ke kočárku', 'locale' => 'cs', 'status' => 'human']);
        Event::create(['session_id' => $person, 'type' => 'intake_human_created', 'subject_type' => 'intake', 'subject_id' => $intake->id]);

        $this->actingAs($this->admin())->get('/admin/farm/activity')->assertOk()
            ->assertSee('Dotaz pro nás (odpovídá člověk)')->assertSee('Potřebuji náhradní kolečko ke kočárku');

        $this->actingAs(User::factory()->create())->get('/admin/farm/activity')->assertRedirect()->assertDontSee('kolečko');
        $this->actingAs($this->admin2())->get('/admin/farm')->assertOk()->assertSee('Aktivita na webu');
    }

    private function admin2(): User
    {
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);

        return $admin;
    }
}
