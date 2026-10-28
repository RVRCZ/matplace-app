<?php

namespace Tests\Feature;

use App\Models\Calculation;
use App\Models\FarmVideo;
use App\Models\ModelFile;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** docs/AP.md §7.1: a blog article embeds a published farm video and a shared calculation through lines of its own. */
class BlogEmbedsTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $body): Post
    {
        return Post::create(['slug' => 'clanek-s-vlozkami', 'title' => ['cs' => 'Článek s vložkami'], 'excerpt' => ['cs' => str_repeat('Úvod článku. ', 12)],
            'body' => ['cs' => $body], 'format' => Post::FORMAT_MARKDOWN, 'published_at' => now()->subDay(), 'author_name' => 'matplace']);
    }

    public function test_a_video_line_shows_nothing_until_the_video_is_published_and_never_loads_youtube_before_the_click(): void
    {
        $this->article("Odstavec před.\n\n[video: abc123XYZ_-]\n\nOdstavec po.\n\n[video: latest]\n");
        $html = (string) $this->get('/blog/clanek-s-vlozkami')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-blog-video', $html, 'no published video: nothing');
        $this->assertStringNotContainsString('MPEMBED', $html, 'the placeholder never shows');
        $this->assertStringNotContainsString('[video:', $html);
        $this->assertStringContainsString('<p>Odstavec po.</p>', $html);

        FarmVideo::factory()->published()->create(['youtube_id' => 'abc123XYZ_-', 'title' => 'Perníček za 20 minut']);
        $html = (string) $this->get('/blog/clanek-s-vlozkami')->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'data-blog-video="abc123XYZ_-"'), 'the video by its id, and as the latest one');
        $this->assertStringContainsString('Perníček za 20 minut', $html);
        $this->assertStringContainsString('youtube-nocookie.com/embed/', $html, 'the player is built by the click handler');
        $this->assertStringNotContainsString('<iframe', $html, 'nothing of YouTube before the click');
    }

    public function test_a_calc_line_is_a_link_while_the_calculation_is_away_and_a_card_once_it_is_here(): void
    {
        $this->article("Kočka ze SVG:\n\n[calc: lfa9iggn3hls]\n\nA dál text.\n");
        $html = (string) $this->get('/blog/clanek-s-vlozkami')->assertOk()->getContent();
        $this->assertStringContainsString('<p data-blog-calc="lfa9iggn3hls">', $html, 'just the link');
        $this->assertStringContainsString('href="'.route('calc.share', 'lfa9iggn3hls').'"', $html);
        $this->assertStringNotContainsString('<aside class="my-6 not-prose card', $html);

        $file = ModelFile::factory()->create(['original_name' => 'kocka.stl', 'bbox' => ['x' => 80, 'y' => 81.09, 'z' => 6]]);
        Calculation::create(['token' => 'lfa9iggn3hls', 'model_file_id' => $file->id, 'params' => ['material' => 'PLA', 'quantity' => 1], 'params_hash' => 'x',
            'status' => Calculation::STATUS_DONE, 'slicer' => ['grams' => 9.6, 'minutes' => 17], 'pricing_context' => ['farm' => true],
            'prices' => [['profile' => 'farm', 'label' => 'Tisk u nás (PLA)', 'quantity' => 1, 'total' => 99, 'minutes' => 17]]]);
        $html = (string) $this->get('/blog/clanek-s-vlozkami')->assertOk()->getContent();
        $this->assertStringContainsString('<aside class="my-6 not-prose card flex flex-col gap-1 p-4" data-blog-calc="lfa9iggn3hls">', $html);
        $this->assertStringContainsString('kocka.stl', $html);
        $this->assertStringContainsString('80 × 81.1 × 6 mm', $html);
        $this->assertStringContainsString('9,6 g', $html);
        $this->assertStringContainsString('17 min', $html);
        $this->assertStringContainsString('99', $html);
        $this->assertStringContainsString('href="'.route('calc.share', 'lfa9iggn3hls').'"', $html);
        $this->assertStringContainsString('<p>A dál text.</p>', $html);
    }

    public function test_raw_html_in_an_article_stays_stripped(): void
    {
        $this->article("Text <iframe src=\"https://www.youtube.com/embed/x\"></iframe> a <script>alert(1)</script>konec.\n");
        $html = (string) $this->get('/blog/clanek-s-vlozkami')->assertOk()->getContent();
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html, 'the tag is stripped, its words stay as text');
    }
}
