<?php

namespace Tests\Feature;

use App\Models\GenerationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The consent to process a photo (24 Nov 2026): a signed-in customer gives it once and the page shows "given on … · change"
 * instead of the box; a visitor ticks it himself every time and the box is never ticked for him.
 */
class PhotoConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['engines.generator' => 'fake', 'ai.anthropic.api_key' => 'k', 'ai.daily_limits.generate_guest' => 20, 'ai.daily_limits.generate_user' => 20]);
        Storage::fake('models');
        Storage::fake('local');
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'subject' => 'person', 'reason' => ''])]]])]);
    }

    private function photo(array $extra = []): array
    {
        return array_replace(['image' => UploadedFile::fake()->image('me.jpg', 600, 800), 'kind' => 'bust', 'pedestal' => 'none'], $extra);
    }

    private function send(array $data)
    {
        return $this->post('/api/generate', $data, ['Accept' => 'application/json']);
    }

    public function test_a_visitor_must_tick_the_box_every_time_and_nothing_is_remembered(): void
    {
        $this->send($this->photo())->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->send($this->photo(['consent' => 1]))->assertCreated();
        $this->send($this->photo())->assertStatus(422);
        $this->assertSame(1, GenerationRequest::count());
    }

    public function test_a_signed_in_customer_ticks_once_and_the_server_remembers_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->assertNull($user->photo_consent_at);

        $this->send($this->photo())->assertStatus(422);
        $this->assertNull($user->fresh()->photo_consent_at);                       // a refused request remembers nothing

        $this->send($this->photo(['consent' => 1]))->assertCreated();
        $this->assertNotNull($user->fresh()->photo_consent_at);

        $this->send($this->photo())->assertCreated();                              // no box needed any more
        $this->assertSame(2, GenerationRequest::count());
    }

    public function test_the_consent_for_a_person_in_the_photo_is_a_separate_one(): void
    {
        $user = User::factory()->create(['photo_consent_at' => now()]);
        $this->actingAs($user);
        $pet = ['kind' => 'pet', 'pedestal' => 'plaque', 'style' => 'realistic'];

        $this->send($this->photo($pet + ['with_person' => '1']))->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->send($this->photo($pet))->assertCreated();                           // the animal alone: the remembered consent holds

        // ticking the box for a person is not remembered as the general one
        $other = User::factory()->create();
        $this->actingAs($other);
        $this->send($this->photo($pet + ['with_person' => '1', 'consent' => 1]))->assertCreated();
        $this->assertNull($other->fresh()->photo_consent_at);
    }

    public function test_change_withdraws_the_consent_on_the_account(): void
    {
        $user = User::factory()->create(['photo_consent_at' => now()]);
        $this->postJson('/api/photo-consent/withdraw')->assertUnauthorized();
        $this->actingAs($user)->postJson('/api/photo-consent/withdraw')->assertOk();
        $this->assertNull($user->fresh()->photo_consent_at);
        $this->send($this->photo())->assertStatus(422);
    }

    public function test_the_pages_show_the_box_empty_to_a_visitor_and_the_given_line_to_a_customer_with_consent(): void
    {
        foreach (['/tools/figure', '/tools/pet-figurine'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('data-consent-row', $html);
            $this->assertMatchesRegularExpression('/<input id="figure-consent" type="checkbox" name="consent" value="1" required\s+class=/', $html);   // not ticked in advance
            $this->assertMatchesRegularExpression('/<input id="figure-drop-consent" type="checkbox" required\s+class=/', $html);
        }

        $user = User::factory()->create(['photo_consent_at' => '2026-11-20 10:00:00']);
        foreach (['/tools/figure', '/tools/pet-figurine'] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('Souhlas s fotkou udělen 20. 11. 2026', $html);
            $this->assertStringContainsString('data-consent-row', $html);
            $this->assertStringContainsString('data-consent-change', $html);
            $this->assertStringContainsString('required checked class=', $html);
        }
    }

    public function test_the_consent_text_is_short_and_names_no_company(): void
    {
        foreach (['cs', 'en', 'es'] as $lang) {
            app()->setLocale($lang);
            foreach (['figure.consent', 'pet.consent', 'pet.consent.owner'] as $key) {
                $text = __($key);
                $this->assertNotSame($key, $text);
                $this->assertStringNotContainsString('Tripo', $text);
                $this->assertStringNotContainsString('Meshy', $text);
                $this->assertLessThan(140, mb_strlen($text));
            }
        }
        app()->setLocale('cs');
        $this->assertSame('Fotka je moje nebo ji smím použít a souhlasím, že ji zpracujeme k vytvoření modelu.', __('figure.consent'));
    }

    public function test_the_privacy_page_names_the_generators_and_the_deletion_times(): void
    {
        foreach (['/privacy' => ['tripo3d.ai', 'meshy.ai', 'mimo EU', '24 hodin', '14 dní'], '/en/privacy' => ['tripo3d.ai', 'meshy.ai', 'outside the EU', '24 hours', '14 days']] as $url => $needles) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $html, $url.' '.$needle);
            }
        }
    }
}
