<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** The shared design tokens have to stay readable: WCAG 2.1 AA contrast (4.5:1 for text) wherever they meet. */
class DesignTokensTest extends TestCase
{
    /** @return array<string, string> */
    private function tokens(): array
    {
        preg_match_all('/--color-([a-z-]+):\s*(#[0-9A-Fa-f]{6})/', (string) file_get_contents(__DIR__.'/../../resources/css/app.css'), $m);

        return array_combine($m[1], $m[2]);
    }

    private function luminance(string $hex): float
    {
        $c = array_map(fn ($h) => hexdec($h) / 255, str_split(ltrim($hex, '#'), 2));
        $c = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private function ratio(string $a, string $b): float
    {
        [$hi, $lo] = [max($this->luminance($a), $this->luminance($b)), min($this->luminance($a), $this->luminance($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    public function test_tokens_are_the_agreed_ones(): void
    {
        $t = $this->tokens();
        $this->assertSame(['#F7F8FA', '#FFFFFF', '#172B4D', '#C94714', '#526176', '#18794E'], array_map('strtoupper', [$t['page'], $t['card'], $t['ink'], $t['action'], $t['muted'], $t['ok']]));
        $this->assertSame(['#7A4A00', '#FFF4D6', '#B42318', '#FEECEB', '#F3EEE6'], array_map('strtoupper', [$t['warn'], $t['warn-soft'], $t['danger'], $t['danger-soft'], $t['studio']]));
    }

    /** One typeface from our own server (no font CDN), headings 600, cards 12 px. */
    public function test_typeface_and_shape_are_the_agreed_ones(): void
    {
        $css = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');
        $this->assertStringContainsString("url('/fonts/InterVariable.woff2')", $css);
        $this->assertStringContainsString('font-display: swap', $css);
        $this->assertStringNotContainsString('fonts.googleapis.com', $css);
        $this->assertFileExists(__DIR__.'/../../public/fonts/InterVariable.woff2');
        $this->assertFileExists(__DIR__.'/../../public/fonts/Inter-LICENSE.txt');
        $this->assertMatchesRegularExpression('/--font-weight-extrabold:\s*600/', $css);
        $this->assertMatchesRegularExpression('/--radius-2xl:\s*0\.75rem/', $css);
    }

    public function test_text_pairs_meet_aa_contrast(): void
    {
        $t = $this->tokens();
        $pairs = [
            'ink on page' => [$t['ink'], $t['page']], 'ink on card' => [$t['ink'], $t['card']],
            'muted on page' => [$t['muted'], $t['page']], 'muted on card' => [$t['muted'], $t['card']],
            'white on action' => ['#FFFFFF', $t['action']], 'white on action-dark' => ['#FFFFFF', $t['action-dark']],
            'action-dark on card' => [$t['action-dark'], $t['card']], 'action-dark on action-soft' => [$t['action-dark'], $t['action-soft']],
            'action on card' => [$t['action'], $t['card']],
            'ok on card' => [$t['ok'], $t['card']], 'ok on ok-soft' => [$t['ok'], $t['ok-soft']],
            'warn on warn-soft' => [$t['warn'], $t['warn-soft']], 'warn on card' => [$t['warn'], $t['card']],
            'danger on danger-soft' => [$t['danger'], $t['danger-soft']], 'danger on card' => [$t['danger'], $t['card']],
            'ink on studio' => [$t['ink'], $t['studio']], 'muted on studio' => [$t['muted'], $t['studio']],
            'white on ink' => ['#FFFFFF', $t['ink']],
        ];
        foreach ($pairs as $name => [$fg, $bg]) {
            $this->assertGreaterThanOrEqual(4.5, $this->ratio($fg, $bg), $name.' = '.round($this->ratio($fg, $bg), 2));
        }
    }
}
