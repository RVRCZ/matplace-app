<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every tool of the catalogue has the picture of its card. Since 13 Oct 2026 the cards are studio photographs in one
 * style (scripts/card_images.py: the tool's own render taken into a photo by Gemini, reviewed by a person before it is
 * published); until then the generators' cards were their plain renders on a beige backdrop.
 */
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

    public function test_the_tools_kept_out_of_the_catalogue_have_their_cards_ready(): void
    {
        // a generator whose print is still to be tried is shown to an admin only; its card waits for the day it goes public
        foreach (['straw', 'opener', 'insert'] as $key) {
            if (! is_file(public_path('img/tools/'.$key.'-800.jpg'))) {
                $this->markTestSkipped("The card of {$key} is not made yet (scripts/card_images.py).");
            }
            foreach (['-800.jpg', '-800.webp', '-480.webp'] as $suffix) {
                $this->assertFileExists(public_path('img/tools/'.$key.$suffix), $key);
            }
            [$w, $h] = getimagesize(public_path('img/tools/'.$key.'-800.jpg'));
            $this->assertSame([800, 533], [$w, $h], $key);
        }
        $this->assertNotEmpty(config('tools.gifts.card'));      // the gifts page borrows the sign generator for its examples
    }
}
