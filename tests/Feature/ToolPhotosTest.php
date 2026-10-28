<?php

namespace Tests\Feature;

use App\Domain\Tools\ToolPhotos;
use App\Models\FarmOrder;
use Database\Seeders\FarmSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Takhle to vytiskli" on a tool's page (docs/AN.md §15): the farm's photographed prints of that tool, only with the
 * customer's consent unless the tool takes sizes alone, the material and the date, never the price; the example
 * cards take the newest photo of their tool when Roman picked none by hand.
 */
final class ToolPhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('farm');
        $this->seed(FarmSeeder::class);
    }

    /** A paid, finished order of a print made with the tool, photographed; the tool's kind on its file. */
    private function printed(string $tool, bool $consent, string $day, string $number): FarmOrder
    {
        $order = FarmOrder::factory()->paid()->done(now()->parse($day))->create(['video_consent' => $consent, 'number' => $number]);
        $order->modelFile->forceFill($tool === 'figure' ? ['origin' => 'generated', 'origin_ref' => 'abc'] : ['origin' => 'tool', 'origin_ref' => $tool])->save();
        Storage::disk('public')->put("farm/photos/{$number}.jpg", 'jpg');
        Storage::disk('public')->put("farm/photos/{$number}.webp", 'webp');
        $order->forceFill(['photo_path' => "farm/photos/{$number}.jpg", 'photo_meta' => ['files' => ['web' => "farm/photos/{$number}.jpg", 'webp' => "farm/photos/{$number}.webp"]]])->save();
        ToolPhotos::forget($tool);

        return $order;
    }

    public function test_a_tool_page_shows_the_prints_made_with_it_with_consent_or_for_an_impersonal_tool(): void
    {
        // nothing photographed: no block
        $this->get('/tools/box')->assertOk()->assertDontSee('id="tool-photos"', false);

        // a box carries nothing of its owner: shown without consent; a sign carries a text: consent needed
        $box = $this->printed('box', false, '2026-10-20', 'F26-000201');
        $this->printed('sign', false, '2026-10-21', 'F26-000202');
        $agreed = $this->printed('sign', true, '2026-10-22', 'F26-000203');
        $this->assertTrue(ToolPhotos::impersonal('box'));
        $this->assertFalse(ToolPhotos::impersonal('sign'));

        $page = $this->get('/tools/box')->assertOk();
        $page->assertSee('id="tool-photos"', false)->assertSee(__('tools.photos.title'))->assertSee('F26-000201.webp')->assertSee('loading="lazy"', false);
        $html = (string) $page->getContent();
        $this->assertSame(1, preg_match_all('/data-tool-photo/', $html));
        $this->assertStringContainsString('20. 10. 2026', $html);
        $this->assertStringContainsString($box->material->label(), $html);
        $this->assertStringNotContainsString('F26-000202', $html);
        $this->assertStringNotContainsString((string) $box->total()?->format(), substr($html, (int) strpos($html, 'id="tool-photos"'), 3000), 'the customer\'s price stays off the block');

        $sign = (string) $this->get('/tools/sign')->assertOk()->getContent();
        $this->assertStringContainsString('F26-000203.webp', $sign);
        $this->assertStringNotContainsString('F26-000202', $sign, 'no consent, a personal print: not shown');
        $this->assertSame(1, preg_match_all('/data-tool-photo/', $sign));

        // at most four, the newest first; a bust from a photo is the figure tool
        foreach (range(1, 5) as $i) {
            $this->printed('box', false, '2026-10-0'.$i, 'F26-00030'.$i);
        }
        $photos = ToolPhotos::for('box');
        $this->assertCount(4, $photos);
        $this->assertSame('F26-000201', $photos[0]['number']);
        $this->assertSame('2026-10-20', $photos[0]['at']);
        $this->printed('figure', true, '2026-10-23', 'F26-000401');
        $this->assertSame('F26-000401', ToolPhotos::latest('figure')['number']);
        $this->assertSame('cs', app()->getLocale());
        $this->get('/en/tools/box')->assertOk()->assertSee('How it came out')->assertSee('20 Oct 2026');
    }

    public function test_an_example_card_without_a_hand_picked_photo_takes_the_newest_print_of_its_tool(): void
    {
        config(['site.examples' => true]);
        $html = (string) $this->get('/tools/box')->assertOk()->getContent();
        $this->assertStringContainsString('id="landing-result"', $html);
        $this->assertStringContainsString(__('site.examples.preview'), $html, 'no photo yet: the card says it is a preview');

        $this->printed('box', false, '2026-10-20', 'F26-000201');
        $html = (string) $this->get('/tools/box')->assertOk()->getContent();
        $result = substr($html, (int) strpos($html, 'id="landing-result"'), (int) strpos($html, 'id="tool-photos"') - (int) strpos($html, 'id="landing-result"'));
        $this->assertStringContainsString('F26-000201.webp', $result, 'the real print replaces the preview');
        $this->assertStringNotContainsString(__('site.examples.preview'), $result);
        $this->assertStringContainsString(__('site.examples.item.box', ['mm' => 84, 'size' => '84 × 54 × 32 mm']), $result);
    }
}
