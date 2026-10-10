<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use App\Support\PreviewMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The typefaces of the text tools: the registry, the files behind it, the picker on the page, the models. */
class FontsTest extends TestCase
{
    use RefreshDatabase;

    private function preview(string $kind, array $params)
    {
        return $this->postJson('/api/tools/param/preview', ['kind' => $kind, 'params' => $params]);
    }

    public function test_every_typeface_has_its_file_its_licence_and_its_picture(): void
    {
        $fonts = ParametricGenerator::FONTS;
        $this->assertGreaterThanOrEqual(24, count($fonts));
        $this->assertSame(['sans', 'serif', 'mono', 'script'], array_slice(array_keys($fonts), 0, 4), 'designs made before carry these four keys');
        $this->assertCount(count($fonts), array_unique(array_column($fonts, 1)));
        foreach ($fonts as $key => [$file, $name, $group]) {
            $this->assertFileExists(base_path($file), $key);
            $this->assertContains($group, ['plain', 'serif', 'hand', 'fun', 'tech'], $key);
            foreach (['cs', 'en', 'es'] as $locale) {
                $this->assertNotSame('param.fonts.'.$group, __('param.fonts.'.$group, [], $locale));
            }
            if (str_starts_with($file, 'engines/fonts/')) {
                // what we ship ourselves is under the Open Font License, the licence lies next to the file
                $licence = base_path('engines/fonts/'.explode('-', basename($file))[0].'-OFL.txt');
                $this->assertFileExists($licence, $key);
                $this->assertStringContainsString('SIL Open Font License', (string) file_get_contents($licence), $key);
            }
            $picture = public_path('img/fonts/'.$key.'.svg');
            $this->assertFileExists($picture, $key.': run php artisan matplace:font-previews');
            $this->assertLessThan(25 * 1024, filesize($picture), $key);
            $this->assertStringStartsWith('<svg', (string) file_get_contents($picture));
        }
    }

    public function test_a_tool_offers_every_typeface_its_own_first(): void
    {
        $all = array_keys(ParametricGenerator::FONTS);
        foreach (ParametricGenerator::CHOICES as $kind => $choices) {
            if (! isset($choices['typeface'])) {
                continue;
            }
            $offered = ParametricGenerator::choicesOf($kind)['typeface'];
            // the face the tool opens with stays the first one
            $this->assertSame($choices['typeface'], array_slice($offered, 0, count($choices['typeface'])), $kind);
            in_array($kind, ParametricGenerator::OWN_FACES, true)
                ? $this->assertSame($choices['typeface'], $offered, $kind)
                : $this->assertEqualsCanonicalizing($all, $offered, $kind);
        }
        // the picker: a tile for every face with its picture, grouped, the tool's own face chosen
        $page = $this->get('/tools/sign')->assertOk();
        $html = $page->getContent();
        $this->assertSame(count($all), preg_match_all('/data-choice="typeface"/', $html));
        $this->assertSame(1, preg_match('/data-choice="typeface" value="([a-z_]+)"[^>]* checked/', $html, $m));
        $this->assertSame(ParametricGenerator::CHOICES['sign']['typeface'][0], $m[1]);
        $page->assertSee('img/fonts/lobster.svg', false)->assertSee(__('param.fonts.hand'))->assertSee(__('param.fonts.fun'));
        // a bead keeps to its three plain faces and its plain buttons
        $beads = $this->get('/tools/letter-beads')->assertOk()->getContent();
        $this->assertSame(3, preg_match_all('/data-choice="typeface"/', $beads));
        $this->assertStringNotContainsString('data-font-picker', $beads);
    }

    public function test_every_typeface_sets_czech_and_spanish_and_makes_a_model_of_its_own(): void
    {
        if (! app(ParametricGenerator::class)->available()) {
            $this->markTestSkipped('The model generator needs Python with manifold3d.');
        }
        $seen = [];
        foreach (array_keys(ParametricGenerator::FONTS) as $key) {
            $r = $this->preview('sign', ['line1' => 'Žluťoučký kůň', 'line2' => '¿Señor Ďáblík?', 'typeface' => $key, 'text_height' => 12])->assertOk();
            $meta = PreviewMeta::whole($r->headers->get('X-Model-Meta'));
            $this->assertSame([], $meta['notes']['missing_chars'] ?? [], $key);
            $seen[$key] = round($meta['volume_mm3']);
        }
        // no two faces draw the same letters
        $this->assertCount(count($seen), array_unique($seen));
        // a face the registry does not know is refused; the cutter takes the new ones like every text tool
        $this->preview('sign', ['line1' => 'Jana', 'typeface' => 'comic_sans'])->assertStatus(422);
        $this->preview('cutter', ['line1' => 'Ela', 'typeface' => 'lobster', 'width' => 70])->assertOk();
        $this->preview('beads', ['line1' => 'JANA', 'typeface' => 'lobster'])->assertStatus(422);
    }
}
