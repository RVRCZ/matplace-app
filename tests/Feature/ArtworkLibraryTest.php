<?php

namespace Tests\Feature;

use App\Domain\Tools\Artwork;
use App\Domain\Tools\ParametricGenerator;
use App\Engines\Repair\PythonTool;
use App\Models\User;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** The picture window of the tools: a library of CC0 silhouettes with a named source each, and "my pictures". */
class ArtworkLibraryTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> "category/slug" of every silhouette on the disk */
    private function files(): array
    {
        return array_map(fn ($p) => basename(dirname($p)).'/'.pathinfo($p, PATHINFO_FILENAME), File::glob(Artwork::libraryDir().'/*/*.svg'));
    }

    public function test_every_silhouette_has_a_source_with_a_free_licence(): void
    {
        $files = $this->files();
        $this->assertGreaterThanOrEqual(100, count($files), 'the library holds at least a hundred silhouettes');
        $sources = (string) file_get_contents(Artwork::libraryDir().'/SOURCES.md');
        $rows = [];
        foreach (preg_split('/\R/', $sources) as $line) {
            $cells = array_map('trim', explode('|', trim($line, " |\t")));
            if (count($cells) >= 4 && preg_match('#^`?([a-z-]+/[a-z0-9-]+)\.svg`?$#', $cells[0], $m)) {
                $rows[$m[1]] = $cells;
            }
        }
        foreach ($files as $file) {
            $this->assertArrayHasKey($file, $rows, "{$file}.svg has no row in SOURCES.md: it must not be in the repository");
            [, , $source, $licence] = $rows[$file];
            $this->assertMatchesRegularExpression('/CC0|public domain/i', $licence, "{$file}: only CC0 or public domain");
            $this->assertMatchesRegularExpression('#https?://\S+|own drawing#i', $source, "{$file}: the source is a page address or our own drawing");
            $this->assertContains(explode('/', $file)[0], Artwork::CATEGORIES, $file);
        }
        $this->assertSame([], array_values(array_diff(array_keys($rows), $files)), 'SOURCES.md lists pictures that are not there');
        // slugs are unique across the categories: the names of the pictures are keyed by them
        $slugs = array_map(fn ($f) => explode('/', $f)[1], $files);
        $this->assertSame(count($slugs), count(array_unique($slugs)));
    }

    public function test_silhouettes_are_plain_shapes_with_names_in_three_languages(): void
    {
        foreach ($this->files() as $file) {
            $path = Artwork::libraryDir().'/'.$file.'.svg';
            $svg = (string) file_get_contents($path);
            $this->assertLessThan(40 * 1024, strlen($svg), $file);
            foreach (['<image', '<script', '<text', '<foreignObject', 'data:', '<!ENTITY', 'javascript:'] as $banned) {
                $this->assertStringNotContainsStringIgnoringCase($banned, $svg, "{$file} carries {$banned}");
            }
            $this->assertDoesNotMatchRegularExpression('/(href|src)\s*=\s*["\']https?:/i', $svg, "{$file} points outside");
            $slug = explode('/', $file)[1];
            foreach (['cs', 'en', 'es'] as $locale) {
                $this->assertNotSame('artwork.items.'.$slug, __('artwork.items.'.$slug, [], $locale), "{$slug} has no name in {$locale}");
            }
        }
        foreach (Artwork::CATEGORIES as $category) {
            foreach (['cs', 'en', 'es'] as $locale) {
                $this->assertNotSame('artwork.cat.'.$category, __('artwork.cat.'.$category, [], $locale));
            }
        }
    }

    public function test_every_silhouette_loads_through_the_tools_own_loader(): void
    {
        $python = app(PythonTool::class);
        if (! $python->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        $r = $python->runScript('artwork_check.py', [Artwork::libraryDir()], 300);
        $this->assertSame([], $r['failed'] ?? ['no answer'], 'silhouettes the tools cannot read');
        $this->assertSame(count($this->files()), $r['count']);
    }

    public function test_the_library_is_searched_in_the_visitors_language(): void
    {
        $all = $this->getJson('/api/artwork/library')->assertOk()->json();
        $this->assertCount(count($this->files()), $all['items']);
        $this->assertSame(count($all['items']), array_sum(array_column($all['cats'], 'count')));
        $first = $all['items'][0];
        $this->assertStringStartsWith('lib:', $first['ref']);
        $this->assertSame(route('api.artwork.item', ['category' => $first['cat'], 'slug' => explode('/', $first['ref'])[1]]), $first['url']);

        $animals = $this->getJson('/api/artwork/library?cat=animals')->assertOk()->json('items');
        $this->assertNotEmpty($animals);
        $this->assertSame(['animals'], array_values(array_unique(array_column($animals, 'cat'))));
        // by the name of a picture, with or without accents, in each language
        $one = $animals[0];
        $slug = explode('/', $one['ref'])[1];
        foreach (['cs' => '', 'en' => '/en', 'es' => '/es'] as $locale => $prefix) {
            $name = __('artwork.items.'.$slug, [], $locale);
            $found = $this->withHeaders(['Referer' => url($prefix.'/tools/logo')])->getJson('/api/artwork/library?q='.urlencode(mb_strtoupper($name)))->assertOk()->json('items');
            $this->assertContains($one['ref'], array_column($found, 'ref'), "{$name} ({$locale})");
        }
        $this->assertSame([], $this->getJson('/api/artwork/library?q=zzzqqq')->assertOk()->json('items'));
        $this->getJson('/api/artwork/library?cat=weapons')->assertStatus(422);

        // the picture itself is served as an image that cannot run anything
        $svg = $this->get($one['url'])->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $svg->headers->get('Content-Type'));
        $this->assertStringContainsString("default-src 'none'", (string) $svg->headers->get('Content-Security-Policy'));
        $this->get('/api/artwork/library/animals/no-such-animal.svg')->assertNotFound();
    }

    public function test_a_silhouette_of_the_library_makes_a_model(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('Python with manifold3d is not installed.');
        }
        $ref = $this->getJson('/api/artwork/library?cat=hearts-stars')->assertOk()->json('items.0.ref');
        $r = $this->postJson('/api/tools/param/preview', ['kind' => 'logo', 'params' => ['artwork' => $ref, 'line1' => '', 'width' => 60]])->assertOk();
        $meta = PreviewMeta::whole($r->headers->get('X-Model-Meta'));
        $this->assertGreaterThan(100, $meta['volume_mm3']);
        // a reference is a name inside the library, never a path
        $this->postJson('/api/tools/param/preview', ['kind' => 'logo', 'params' => ['artwork' => 'lib:../../.env', 'line1' => '']])->assertStatus(422);
        $this->postJson('/api/tools/param/preview', ['kind' => 'logo', 'params' => ['artwork' => 'lib:animals/no-such-animal', 'line1' => '']])->assertStatus(422);
        $this->assertNull(Artwork::path('lib:animals/../SOURCES'));
    }

    public function test_my_pictures_are_only_mine(): void
    {
        $ann = User::factory()->create();
        $bob = User::factory()->create();
        $svg = fn () => UploadedFile::fake()->createWithContent('znak.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');
        $id = $this->actingAs($ann)->post('/api/tools/artwork', ['file' => $svg()], ['Accept' => 'application/json'])->assertCreated()->json('artwork');
        try {
            $mine = $this->actingAs($ann)->getJson('/api/artwork/mine')->assertOk()->json();
            $this->assertSame(Artwork::KEEP_DAYS, $mine['keep_days']);
            $this->assertSame([$id], array_column($mine['items'], 'ref'));
            $this->assertSame('znak.svg', $mine['items'][0]['name']);
            $this->actingAs($ann)->get($mine['items'][0]['url'])->assertOk();
            // somebody else neither sees it in the list nor opens it by its address
            $this->assertSame([], $this->actingAs($bob)->getJson('/api/artwork/mine')->assertOk()->json('items'));
            $this->actingAs($bob)->get('/api/artwork/file/'.$id)->assertNotFound();
            // the upload still works as artwork of a tool
            $this->assertNotNull(Artwork::path($id));
            // after 30 days it leaves the list and the disk
            $path = (string) Artwork::ownPath('u'.$ann->id, $id);
            touch($path, time() - (Artwork::KEEP_DAYS + 1) * 86400);
            $this->assertSame([], $this->actingAs($ann)->getJson('/api/artwork/mine')->json('items'));
            $this->assertGreaterThanOrEqual(1, Artwork::prune());
            $this->assertFileDoesNotExist($path);
        } finally {
            File::deleteDirectory(storage_path('app/artwork/u'.$ann->id));
        }
    }
}
