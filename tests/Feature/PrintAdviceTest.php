<?php

namespace Tests\Feature;

use App\Domain\Tools\PrintAdvisor;
use App\Models\GenerationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MeshFixtures;
use Tests\TestCase;

/**
 * "How do I print this?": the geometry is measured on the server (plate contact, overhangs, walls, best orientation,
 * four pictures), the AI writes a few plain tips from it; the same question never costs twice and has a daily limit.
 */
class PrintAdviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('models');
        config(['ai.anthropic.api_key' => 'test-key']);
    }

    private function upload(): string
    {
        $path = sys_get_temp_dir().'/mp_advice_'.uniqid().'.stl';
        MeshFixtures::cubeStl($path, 20);

        return $this->postJson('/api/uploads', ['file' => new UploadedFile($path, 'cube.stl', null, null, true)])->json('file.uuid');
    }

    private function answer(): array
    {
        $advice = ['summary' => 'Kostka se vytiskne bez potíží.', 'items' => [
            ['topic' => 'orientation', 'level' => 'fine', 'title' => 'Leží na celé ploše', 'text' => 'Spodní stěna 400 mm² drží na podložce.'],
            ['topic' => 'nonsense', 'level' => 'tip', 'title' => 'x', 'text' => 'dropped: unknown topic'],
        ]];

        return ['id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5', 'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => json_encode($advice, JSON_UNESCAPED_UNICODE)]], 'usage' => ['input_tokens' => 5000, 'output_tokens' => 700]];
    }

    public function test_the_geometry_is_measured_and_the_tips_come_back_once_per_question(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->answer())]);
        $uuid = $this->upload();

        $r = $this->postJson("/api/files/{$uuid}/advice")->assertStatus(202)->json();
        $this->assertSame('done', $r['status']);   // the queue runs inline in tests
        $this->assertSame('Kostka se vytiskne bez potíží.', $r['advice']['summary']);
        $this->assertCount(1, $r['advice']['items'], 'an item outside the schema is dropped');
        $this->getJson("/api/advice/{$r['token']}")->assertOk()->assertJsonPath('advice.items.0.level', 'fine');

        Http::assertSent(function ($req) {
            $content = $req['messages'][0]['content'];
            $facts = end($content)['text'];

            return $req['model'] === 'claude-opus-5' && $req['output_config']['format']['type'] === 'json_schema'
                && count(array_filter($content, fn ($b) => $b['type'] === 'image')) === 4
                && str_contains($facts, '"plate_contact_mm2": 400') && str_contains($facts, '"best_orientation"');
        });
        Storage::disk('models')->assertExists("files/{$uuid}/advice/facts.json");

        // asked again: the same answer, nothing sent
        $this->postJson("/api/files/{$uuid}/advice")->assertOk()->assertJsonPath('token', $r['token']);
        Http::assertSentCount(1);
    }

    public function test_the_daily_limit_holds_and_a_missing_key_says_so(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->answer())]);
        config(['ai.daily_limits.advise' => 1]);
        $a = $this->upload();
        $b = $this->upload();
        $this->postJson("/api/files/{$a}/advice")->assertStatus(202);
        GenerationRequest::query()->update(['prompt' => 'advice:other']);   // a different question about the same visitor's files
        $this->postJson("/api/files/{$b}/advice")->assertStatus(429)->assertJsonPath('error', 'daily_limit');

        config(['ai.anthropic.api_key' => '']);
        $this->app->forgetInstance(PrintAdvisor::class);   // the advisor reads its key when it is built
        $this->postJson("/api/files/{$b}/advice")->assertStatus(503);
    }
}
