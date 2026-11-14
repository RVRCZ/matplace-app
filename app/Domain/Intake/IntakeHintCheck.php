<?php

namespace App\Domain\Intake;

use App\Models\Intake;
use App\Support\ToolSeo;

/**
 * The rule of the first screen's examples (docs/AN.md §25): a hint ends on a design that matches its sentence. For
 * every example (config/home.php, in the admin's own words where the admin changed them) this says where the wish
 * goes, what its sentence names (a name, a number, a size, colours, a count, a shape, a place), what the tool's page
 * opens with, and what of the sentence did not get there (`lost`). An example that loses something, or that would open
 * a tool's own sample, does not pass: the test (IntakeHintsMatchTest) and the walk in a browser (scripts/hints_walk.mjs,
 * fed by `php artisan matplace:intake-hints --json`) both read this.
 *
 * The decision is the keyword router's (free, the weakest reader): the model's own answer is completed by the same
 * words (IntakePrefill::complete), so what the words carry here they carry there too.
 */
final class IntakeHintCheck
{
    /**
     * Every example of every event in the languages it is offered in.
     *
     * @param  list<string>|null  $locales
     * @return list<array<string, mixed>>
     */
    public static function all(?array $locales = null, int $year = 0): array
    {
        $out = [];
        foreach (IntakeHints::events() as $key => $event) {
            foreach (IntakeHints::locales($key) as $locale) {
                if ($locales !== null && ! in_array($locale, $locales, true)) {
                    continue;
                }
                foreach (IntakeHints::examples($key, $locale, ($year ?: (int) now()->year) + (int) ($event['year_offset'] ?? 0)) as $i => $example) {
                    $out[] = self::one($key.'.'.($i + 1), $locale, $example['label'], $example['fill'], $event['examples'][$i] ?? null);
                }
            }
        }

        return $out;
    }

    /**
     * One example: the decision, the page's values, what is asked for, what the browser must show, what was lost.
     *
     * @return array<string, mixed>
     */
    public static function one(string $code, string $locale, string $label, string $fill, ?string $wanted = null): array
    {
        $decision = IntakeRouter::guess($fill, false, $locale);
        $tool = $decision['route'] === Intake::ROUTE_TOOL ? $decision['tool_key'] : null;
        $said = IntakePrefill::fromWords($fill);
        $params = $values = [];
        $ask = null;
        $understood = [];
        if ($tool !== null) {
            $params = IntakeRouter::params(IntakePrefill::complete($decision['params'], $fill, $tool), $tool);
            $values = IntakePrefill::values($tool, $params, $fill, false);
            $ask = IntakePrefill::ask($tool, $params, $values, false, $locale);
            $understood = IntakePrefill::understood($tool, $params, $values, false, $locale);
        }
        $lost = self::lost($tool, $said, $params, $values, $ask);
        if ($wanted !== null && $wanted !== 'human' && $tool !== $wanted) {
            array_unshift($lost, 'tool:'.($tool ?? $decision['route']).'>'.$wanted);
        }

        return [
            'code' => $code, 'locale' => $locale, 'label' => $label, 'fill' => $fill, 'tool' => $tool, 'wanted' => $wanted,
            'said' => $said, 'params' => $params, 'values' => $values, 'ask' => $ask['kind'] ?? null, 'understood' => $understood, 'lost' => $lost,
            'expect' => $tool === null ? null : self::expect($tool, $params, $values, $ask, $locale),
        ];
    }

    /** What a thing lost on the way is called: for the command's table (en) and for the admin's page (cs). */
    private const SAY = [
        'tool' => ['goes to :a, config/home.php says :b', 'vede na :a, má vést na :b'],
        'text' => ['the text „:a“ is not in the form', 'text „:a“ se do návrhu nedostane'],
        'number' => ['the number :a is not in the form', 'číslo :a se do návrhu nedostane'],
        'size' => ['the size is not in the form', 'velikost se do návrhu nedostane'],
        'colors' => ['the colours (:a) are not in the design', 'barvy (:a) se do návrhu nedostanou'],
        'no_colors' => ['the sentence names colours (:a), this tool has none to open with', 'věta jmenuje barvy (:a), ale tenhle nástroj barvy při otevření nemá: vynechte je'],
        'pieces' => ['the count (:a) is not in the form', 'počet kusů (:a) se do návrhu nedostane'],
        'place' => ['the place is not handed over', 'místo se do mapy nedostane'],
        'sample' => ['the page would open with its own sample', 'stránka by otevřela vlastní ukázku nástroje'],
        'ask_photo' => ['the page does not ask for the photo', 'stránka si neřekne o fotku'],
        'ask_place' => ['the page does not ask for the place', 'stránka se nezeptá na místo'],
    ];

    /** One lost thing ("colors:red, white") as a sentence, in English or in Czech. */
    public static function say(string $lost, string $locale = 'en'): string
    {
        [$code, $rest] = explode(':', $lost, 2) + [1 => ''];
        [$a, $b] = explode('>', $rest, 2) + [1 => ''];
        $text = self::SAY[$code][$locale === 'cs' ? 1 : 0] ?? $lost;

        return str_replace([':a', ':b'], [$a, $b], $text);
    }

    /**
     * What the sentence names and the page does not open with, as short codes (`say()` makes sentences of them).
     * Empty = the design matches the sentence.
     *
     * @param  array<string, mixed>  $said  IntakePrefill::fromWords of the sentence
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $values
     * @param  array{kind: string}|null  $ask
     * @return list<string>
     */
    public static function lost(?string $tool, array $said, array $params, array $values, ?array $ask): array
    {
        if ($tool === null) {
            return [];                                    // a person's work (a spare part): nothing is opened, nothing can be lost
        }
        $lost = [];
        $parametric = isset(IntakePrefill::KIND[$tool]);
        $pictured = in_array($tool, IntakePrefill::PICTURE_TOOLS, true);
        $waits = $ask !== null;                           // the page asks for the picture or the photo: the words about it are kept for then
        $lines = array_filter([$values['line1'] ?? '', $values['line2'] ?? '', $values['line3'] ?? '', $values['number'] ?? ''], fn ($v) => $v !== '');

        if (isset($said['text'])) {
            $there = $parametric ? (bool) array_filter($lines, fn ($l) => str_contains(mb_strtolower((string) $said['text']), mb_strtolower((string) $l)) || str_contains(mb_strtolower((string) $l), mb_strtolower((string) $said['text'])))
                : isset($params['text']);
            if (! $there && ! in_array($tool, ['map', 'relief', 'filament_art'], true)) {
                $lost[] = 'text:'.$said['text'];
            }
        }
        if (isset($said['number']) && $parametric && ! in_array($tool, ['headband', 'sign', 'text', 'beads', 'cutter', 'charm', 'keychain', 'coaster', 'ornament', 'earrings', 'papel'], true)) {
            if (! in_array((string) $said['number'], array_map('strval', $lines), true) && ! isset($values['preset'])) {
                $lost[] = 'number:'.$said['number'];
            }
        }
        if (isset($said['size_mm']) || isset($said['head_mm']) || ($tool === 'headband' && isset($said['age']))) {
            $there = $parametric
                ? (bool) array_intersect(array_keys($values), ['width', 'text_height', 'head', 'size'])
                : isset($params['size_mm']);
            if (! $there) {
                $lost[] = 'size';
            }
        }
        if (isset($said['colors'])) {
            if (! $parametric) {
                // a bust and a pet figurine get their one colour in the calculator, a map has its own three: a sentence
                // that names colours for them promises what the page cannot open with
                $lost[] = 'no_colors:'.implode(', ', (array) $said['colors']);
            } elseif (! isset($values['part_colors']) && ! isset($values['preset']) && ! isset($values['colors_n'])) {
                $lost[] = 'colors:'.implode(', ', (array) $said['colors']);
            }
        }
        if (isset($said['copies']) && (int) $said['copies'] > 1 && ! isset($values['_qty'])) {
            $lost[] = 'pieces:'.$said['copies'];
        }
        if (isset($said['place']) && $tool === 'map' && ! isset($params['place'])) {
            $lost[] = 'place';
        }
        // never the tool's own sample: a tool of a picture opens with words, with a picture of the library, or asking
        if ($pictured && $tool !== 'medallion' && ! array_key_exists('artwork', $values)) {
            $lost[] = 'sample';
        }
        if (in_array($tool, IntakePrefill::PHOTO_TOOLS, true) && ! $waits) {
            $lost[] = 'ask_photo';
        }
        if ($tool === 'map' && ! isset($params['place']) && ! $waits) {
            $lost[] = 'ask_place';
        }

        return $lost;
    }

    /**
     * What a browser must show after the hint was clicked and sent (scripts/hints_walk.mjs): the page's address, the
     * form's fields by their names on the page, the colour of every part, the picture, the place, what is asked for.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $values
     * @param  array{kind: string}|null  $ask
     * @return array<string, mixed>
     */
    public static function expect(string $tool, array $params, array $values, ?array $ask, string $locale): array
    {
        $form = [];
        foreach ($values as $key => $value) {
            if (in_array($key, ['preset', 'part_colors', 'artwork', '_plain'], true)) {
                continue;
            }
            $form[['_qty' => '#param-qty', '_material' => '#param-material'][$key] ?? $key] = is_string($value) || is_bool($value) ? $value : (float) $value;
        }
        if (! isset(IntakePrefill::KIND[$tool]) && isset($params['size_mm'])) {
            $field = ['figure' => '#figure-size', 'pet' => '#figure-size', 'relief' => '#relief-w', 'map' => 'size', 'filament_art' => 'width'][$tool] ?? null;
            if ($field !== null) {
                $form[$field] = (float) $params['size_mm'];
            }
        }
        $out = ['tool' => $tool, 'path' => (string) parse_url(ToolSeo::url($tool, $locale), PHP_URL_PATH), 'form' => $form];
        if (isset($values['part_colors'])) {
            $out['colors'] = array_map(fn ($hex) => strtolower((string) $hex), (array) $values['part_colors']);
        }
        if (array_key_exists('artwork', $values)) {
            $out['artwork'] = (string) $values['artwork'];
        }
        if ($ask !== null) {
            $out['ask'] = $ask['kind'];
        }
        if ($tool === 'map' && isset($params['place'])) {
            $out['place'] = (string) $params['place'];
        }

        return $out;
    }
}
