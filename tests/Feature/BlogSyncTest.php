<?php

namespace Tests\Feature;

use App\Console\Commands\BlogSync;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The Czech blog articles in resources/blog/cs: written into the posts by blog:sync, about today's site, ending with "Co dál". */
class BlogSyncTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_ADDRESSES = ['/tiskarny', '/tiskar/', '/katalog', 'legacy.'];

    public function test_the_dry_run_writes_nothing(): void
    {
        $this->artisan('blog:sync', ['--dry-run' => true])->expectsOutputToContain('Would change '.count(BlogSync::files()).' article(s).')->assertSuccessful();
        $this->assertSame(0, Post::count());
    }

    public function test_the_sync_creates_and_updates_by_slug_and_a_second_run_changes_nothing(): void
    {
        // an article from the old site: HTML, Czech only, its own date and cover
        $old = Post::create(['slug' => 'kolik-stoji-3d-tisk-na-zakazku-v-roce-2026', 'title' => ['cs' => 'Starý název'], 'excerpt' => ['cs' => 'Starý úvod'],
            'body' => ['cs' => '<p>Poptat 3D tisk <a href="/tiskarny">Zobrazit tiskárny</a></p>'], 'format' => Post::FORMAT_HTML,
            'cover_path' => 'blog/old.jpg', 'published_at' => '2026-04-22 22:34:00']);

        $this->artisan('blog:sync')->expectsOutputToContain('kolik-stoji-3d-tisk-na-zakazku-v-roce-2026: update title, excerpt, body, format')->assertSuccessful();
        $this->assertSame(count(BlogSync::files()), Post::count(), 'one post per file');

        $old->refresh();
        $this->assertSame([Post::FORMAT_MARKDOWN, 'Kolik stojí 3D tisk na zakázku v roce 2026?', '2026-04-22 22:34:00', 'blog/old.jpg'],
            [$old->format, $old->titleIn('cs'), $old->published_at->format('Y-m-d H:i:s'), $old->cover_path], 'the date stays, an empty cover in the file keeps the old one');
        $this->assertSame('2026-10-18 20:00:00', $old->updated_at->format('Y-m-d H:i:s'), 'dateModified from the file');
        $this->assertStringNotContainsString('/tiskarny', $old->html('cs'));
        $this->assertStringContainsString('<h2>Co dál</h2>', $old->html('cs'));

        // a new one takes its date from the file; an empty date is a draft (not public)
        $new = Post::where('slug', 'generovani-3d-modelu-pomoci-ai')->firstOrFail();
        $this->assertSame(['2026-05-23 13:01:42', 'blog/20260523_130435_e0f041a2.png', 'matplace'], [$new->published_at->format('Y-m-d H:i:s'), $new->cover_path, $new->author_name]);
        $draft = Post::where('slug', 'printpal-io-a-ai-nastroje-pro-3d-tisk-navod')->firstOrFail();
        $this->assertNull($draft->published_at);
        $this->get('/blog/printpal-io-a-ai-nastroje-pro-3d-tisk-navod')->assertNotFound();

        $this->artisan('blog:sync')->expectsOutputToContain('Changed 0 article(s).')->assertSuccessful();
        $this->assertSame('2026-10-18 20:00:00', $old->refresh()->updated_at->format('Y-m-d H:i:s'));
    }

    public function test_an_article_with_an_html_translation_is_left_alone(): void
    {
        $post = Post::create(['slug' => 'pla-vs-petg-vs-asa-jaky-material-vybrat', 'title' => ['cs' => 'PLA', 'en' => 'PLA'],
            'body' => ['cs' => '<p>česky</p>', 'en' => '<p>in English</p>'], 'format' => Post::FORMAT_HTML, 'published_at' => now()->subYear()]);

        $this->artisan('blog:sync')->expectsOutputToContain('pla-vs-petg-vs-asa-jaky-material-vybrat: skipped, its en version is HTML')->assertSuccessful();
        $this->assertSame([Post::FORMAT_HTML, '<p>česky</p>'], [$post->refresh()->format, $post->body['cs']]);
    }

    public function test_the_other_languages_of_a_markdown_article_stay(): void
    {
        $post = Post::create(['slug' => 'jak-pripravit-stl-soubor-pro-3d-tisk', 'title' => ['cs' => 'STL', 'en' => 'Preparing an STL'],
            'body' => ['cs' => 'Starý text.', 'en' => 'English text.'], 'format' => Post::FORMAT_MARKDOWN, 'published_at' => now()->subYear()]);

        $this->artisan('blog:sync')->assertSuccessful();
        $post->refresh();
        $this->assertSame(['Preparing an STL', 'English text.'], [$post->title['en'], $post->body['en']]);
        $this->assertStringStartsWith('Špatně připravený soubor', $post->body['cs']);
    }

    public function test_no_article_points_to_the_old_marketplace(): void
    {
        $this->assertGreaterThanOrEqual(6, count(BlogSync::files()));
        foreach (BlogSync::files() as $slug => $path) {
            $text = (string) file_get_contents($path);
            foreach (self::OLD_ADDRESSES as $old) {
                $this->assertStringNotContainsString($old, $text, "{$slug} still points to {$old}");
            }
            foreach (['poptávk', 'tiskař', 'do 24 hodin', 'během pár hodin'] as $gone) {
                $this->assertStringNotContainsStringIgnoringCase($gone, $text, "{$slug} still describes the old marketplace ({$gone})");
            }
        }
    }

    public function test_every_article_ends_with_co_dal_and_every_internal_link_exists(): void
    {
        $slugs = array_keys(BlogSync::files());
        $known = array_merge($slugs, ['jak-vznika-busta-z-fotky']);   // the bust article lives in the database, written from docs/blog
        foreach (BlogSync::files() as $slug => $path) {
            $article = BlogSync::parse((string) file_get_contents($path));
            $this->assertNotSame('', $article['title'], $slug);
            $this->assertGreaterThanOrEqual(110, mb_strlen($article['excerpt']), "{$slug}: excerpt too short");

            // "Co dál" is the last section, with one to three links
            $this->assertMatchesRegularExpression('/\n## Co dál\n/', $article['body'], "{$slug} has no Co dál");
            $tail = substr($article['body'], strrpos($article['body'], "\n## Co dál\n"));
            $this->assertStringNotContainsString("\n## ", substr($tail, 1), "{$slug}: Co dál is not the last section");
            preg_match_all('/\]\(([^)]+)\)/', $tail, $next);
            $this->assertGreaterThanOrEqual(1, count($next[1]), $slug);
            $this->assertLessThanOrEqual(3, count($next[1]), $slug);

            preg_match_all('/\]\((\/[^)\s]*)\)/', $article['body'], $links);
            foreach ($links[1] as $link) {
                $path = (string) parse_url($link, PHP_URL_PATH);
                if (str_starts_with($path, '/blog/')) {
                    $this->assertContains(substr($path, 6), $known, "{$slug} links to a missing article {$link}");

                    continue;
                }
                $route = Route::getRoutes()->match(Request::create($path));
                $this->assertNotNull($route->getName(), "{$slug} links to {$link}, which is no page");
            }
        }
    }

    public function test_a_tool_and_its_article_link_each_other(): void
    {
        $files = BlogSync::files();
        $tools = array_filter(config('tools'), fn ($t) => isset($t['article']));
        $this->assertGreaterThanOrEqual(5, count($tools));
        foreach ($tools as $key => $tool) {
            $slug = $tool['article'];
            if ($slug === 'jak-vznika-busta-z-fotky') {
                continue;   // in the database, written from docs/blog; it starts with a link to the figure tool
            }
            $this->assertArrayHasKey($slug, $files, "{$key}: no article {$slug}");
            $path = route($tool['route'], [], false);
            $this->assertStringContainsString('('.$path.')', (string) file_get_contents($files[$slug]), "{$slug} does not link back to {$key} ({$path})");
        }
    }

    public function test_the_photo_places_are_not_shown_until_the_photos_come(): void
    {
        $places = 0;
        foreach (BlogSync::files() as $slug => $path) {
            $article = BlogSync::parse((string) file_get_contents($path));
            $places += substr_count($article['body'], '<!-- foto:');
            $html = (new Post(['body' => ['cs' => $article['body']], 'format' => Post::FORMAT_MARKDOWN]))->html('cs');
            $this->assertStringNotContainsString('foto:', $html, "{$slug} shows a photo place");
        }
        $this->assertGreaterThan(0, $places);
    }

    public function test_the_article_page_shows_co_dal_with_a_working_link(): void
    {
        $this->artisan('blog:sync')->assertSuccessful();
        $page = $this->get('/blog/jak-pripravit-stl-soubor-pro-3d-tisk')->assertOk();
        $page->assertSee('<h2>Co dál</h2>', false)->assertSee('href="/tools/check"', false)->assertSee('href="/tools/repair"', false);
        $this->get('/tools/check')->assertOk();
        $this->get('/blog')->assertOk()->assertSee('Jak připravit STL soubor pro 3D tisk')->assertDontSee('PrintPal.io a 4 další');
    }
}
