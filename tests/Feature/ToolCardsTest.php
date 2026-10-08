<?php

namespace Tests\Feature;

use App\Domain\Tools\ParametricGenerator;
use Tests\TestCase;

/** Every tool of the catalogue has the picture of its card; the generators' cards are renders of their own output. */
class ToolCardsTest extends TestCase
{
    public function test_every_listed_tool_has_a_card_picture_in_three_files(): void
    {
        foreach (config('tools') as $key => $tool) {
            if (! $tool['available'] && $key !== 'spare') {
                continue;
            }
            $base = public_path('img/tools/'.$key);
            foreach (['-800.jpg', '-800.webp', '-480.webp'] as $suffix) {
                $this->assertFileExists($base.$suffix, $key);
            }
            [$w, $h] = getimagesize($base.'-800.jpg');
            $this->assertSame(800, $w, $key);
            $this->assertEqualsWithDelta(533, $h, 1, $key);                    // 3:2
            $this->assertLessThan(60 * 1024, filesize($base.'-800.webp'), $key);
            $this->assertLessThan(120 * 1024, filesize($base.'-800.jpg'), $key);
        }
    }

    public function test_cards_of_the_generators_are_renders_on_the_studio_backdrop(): void
    {
        if (! function_exists('imagecreatefromjpeg')) {
            $this->markTestSkipped('PHP GD is not installed.');
        }
        // every generator that is in the catalogue: one kept out until its print was tried has no card yet
        $drawn = array_values(array_filter(array_keys(ParametricGenerator::FIELDS), fn ($kind) => config('tools.'.$kind.'.available') !== false));
        $drawn[] = 'gifts';                                                   // borrows the sign generator (config/tools.php `card`)
        foreach ($drawn as $key) {
            $im = imagecreatefromjpeg(public_path('img/tools/'.$key.'-800.jpg'));
            // the corners are the backdrop #F3EEE6, a touch darker towards the edges: no room, no desk, no photo studio props
            foreach ([[6, 6], [793, 6], [6, 526], [793, 526]] as [$x, $y]) {
                $c = imagecolorat($im, $x, $y);
                $rgb = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
                foreach ([243, 238, 230] as $i => $want) {
                    $this->assertEqualsWithDelta($want, $rgb[$i], 30, "{$key} at {$x},{$y}");
                }
                $this->assertGreaterThanOrEqual($rgb[2], $rgb[0], $key);        // warm, not grey
            }
        }
        $this->assertNotEmpty(config('tools.gifts.card'));
    }
}
