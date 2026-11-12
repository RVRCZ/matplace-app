<?php

namespace Tests\Feature;

use App\Models\FarmAgent;
use App\Models\FarmCommand;
use App\Models\FarmPrinter;
use App\Models\User;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The agent's note on a command in the operator's words (docs/AM.md §12j): a tool changer's API reports no spool
 * positions, so the agent starts without the check and says so slot by slot in English; the dashboard shows one Czech
 * sentence naming the positions, and leaves any other note as the agent wrote it.
 */
class FarmCommandResultTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_unchecked_positions_become_one_sentence_and_other_notes_stay(): void
    {
        app()->setLocale('cs');
        $cmd = new FarmCommand(['result' => 'slot 1 not checked, the printer does not report its slots; slot 2 not checked, the printer does not report its slots; slot 5 not checked, the printer does not report its slots']);
        $this->assertSame('Pozice 1, 2, 5 agent nekontroloval, tiskárna své pozice nehlásí (filament hlídá firmware při startu).', $cmd->resultText());
        $this->assertSame('slot 3 is empty: load a spool into it and confirm the plate again', (new FarmCommand(['result' => 'slot 3 is empty: load a spool into it and confirm the plate again']))->resultText(), 'an unknown note as written');
        $this->assertNull((new FarmCommand(['result' => null]))->resultText());
        $this->assertNull((new FarmCommand(['result' => '  ']))->resultText());
        app()->setLocale('en');
        $this->assertSame('Positions 1 were not checked by the agent, the printer does not report its positions (the firmware checks the filament at the start).', (new FarmCommand(['result' => 'slot 1 not checked, the printer does not report its slots']))->resultText());
    }

    public function test_the_dashboard_shows_the_sentence_under_the_last_command(): void
    {
        $this->seed(FarmSeeder::class);
        app()->setLocale('cs');
        $admin = User::factory()->create();
        $admin->setRole(User::ROLE_ADMIN, true);
        [$agent] = FarmAgent::issue('test agent');
        $printer = FarmPrinter::where('key', 'kobra-s1-01')->firstOrFail();
        $printer->update(['mode' => FarmPrinter::MODE_AGENT, 'farm_agent_id' => $agent->id, 'last_seen_at' => now(), 'state' => FarmPrinter::STATE_IDLE]);
        FarmCommand::create(['farm_printer_id' => $printer->id, 'type' => FarmCommand::TYPE_START, 'payload' => ['job_id' => 1], 'status' => 'done', 'result' => 'slot 1 not checked, the printer does not report its slots; slot 2 not checked, the printer does not report its slots', 'finished_at' => now()]);
        $this->actingAs($admin)->get(route('admin.farm.printers.show', $printer))->assertOk()
            ->assertSee('Pozice 1, 2 agent nekontroloval')
            ->assertDontSee('not checked');
    }
}
