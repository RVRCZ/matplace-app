<?php

namespace App\Domain\Intake;

use App\Domain\Farm\Palette;
use App\Domain\Tools\ParametricGenerator;
use App\Support\Locales;

/**
 * What a tool's page opens with after one press of "Vytvořit" (docs/AN.md §24): the wish's own words read once more
 * for what a tool can take (a name or an inscription, a second line, a number, a size, a count, colours, a shape, a
 * material), and those general parameters turned into the values of the chosen tool's own form (`values`, keys of
 * ParametricGenerator: the page applies them like a stored design). `understood` is the short list the page shows on
 * its one line "the assistant understood: …": only what was really put into the form.
 *
 * Nothing here asks a model: the router's answer (when there is one) comes first, the words fill in what it left out.
 */
final class IntakePrefill
{
    /** The tools of the router that live on the parametric page → the generator's kind (the key ring opens its quick form). */
    public const KIND = [
        'charm' => 'charm', 'ornament' => 'ornament', 'keychain' => 'keychain', 'medallion' => 'medallion', 'topper' => 'topper', 'coaster' => 'coaster',
        'earrings' => 'earrings', 'papel' => 'papel', 'sign' => 'sign', 'text' => 'sign', 'cutter' => 'cutter',
    ];

    /** The field the main size of a parametric tool goes to (the rest take `width`). */
    private const SIZE_FIELD = ['sign' => 'text_height', 'text' => 'text_height'];

    /** The choice that holds the outline of a tool, and what a shape named in words is called there. */
    private const SHAPE_CHOICE = [
        'charm' => 'body', 'keychain' => 'body', 'earrings' => 'body', 'ornament' => 'body', 'coaster' => 'body', 'medallion' => 'body', 'topper' => 'template', 'sign' => 'shape',
    ];

    private const SHAPE_ALIASES = ['hex' => ['hex', 'hexagon'], 'rect' => ['rect', 'rounded'], 'square' => ['square', 'rect'], 'circle' => ['circle'], 'oval' => ['oval', 'circle']];

    /** Shapes a wish may name (cs, en, es; with and without diacritics). The first family found wins. */
    private const SHAPES = [
        'heart' => '/srd[cí]e|srd[ií][čc]k|\bheart|coraz[óo]n/u',
        'star' => '/hv[ěe]zd|\bstar\b|\bstars\b|estrella/u',
        'hex' => '/[šs]esti[úu]heln|hexagon|hex[áa]gon/u',
        'circle' => '/kulat|\bkruh|kole[čc]ko\b|\bround\b|\bcircle|circular|redond|c[íi]rculo/u',
        'square' => '/[čc]tverc|[čc]tverec|\bsquare|cuadrad/u',
        'rect' => '/obd[ée]ln[íi]k|rectang/u',
        'oval' => '/ov[áa]l|\boval/u',
        'shield' => '/\b[šs]t[íi]t|\bshield|\bescudo/u',
        'cloud' => '/\bmrak|obl[áa][čc]|\bcloud|\bnube/u',
        'bone' => '/\bkost\b|kosti[čc]k|\bbone\b|\bhueso/u',
        'house' => '/dome[čc]|\bdomek|\bhouse\b|\bcasita/u',
    ];

    /** Colour words → the basic colours of the colour window (Palette::BASIC). In the order they are tried. */
    private const COLORS = [
        'white' => '/\bb[íi]l[áéouýaeěy]|\bwhite\b|\bblanc[oa]s?\b/u',
        'black' => '/\b[čc]ern[áéouýaeěy]|\bblack\b|\bnegr[oa]s?\b/u',
        'grey' => '/\b[šs]ed[áéouýaeěy]|\bgr[ae]y\b|\bgris\b/u',
        'red' => '/\b[čc]erven[áéouýaeěy]|\bred\b|\broj[oa]s?\b/u',
        'orange' => '/\boran[žz]ov|\borange\b|\bnaranja/u',
        'yellow' => '/\b[žz]lut[áéouýaeěy]|\byellow\b|\bamarill[oa]/u',
        'green' => '/\bzelen[áéouýaeěy]\b|\bgreen\b|\bverdes?\b/u',
        'turquoise' => '/\btyrkys|\bturquoise\b|\bteal\b|\bturquesa/u',
        'blue' => '/\bmodr[áéouýaeěy]|\bblue\b|\bazul/u',
        'violet' => '/\bfialov|\bviolet\b|\bpurple\b|\bvioleta|\bmorad[oa]/u',
        'pink' => '/\br[ůu][žz]ov|\bpink\b|\brosa\b/u',
        'brown' => '/\bhn[ěe]d[áéouýaeěy]|\bbrown\b|\bmarr[óo]n/u',
        'beige' => '/\bb[ée][žz]ov|\bbeige\b/u',
        'gold' => '/\bzlat[áéouýaeěy]|\bgold(en)?\b|\bdorad[oa]/u',
        'silver' => '/\bst[řr][íi]brn|\bsilver\b|\bplatead[oa]/u',
        'copper' => '/\bbronzov|\bm[ěe]d[ěe]n|\bbronze\b|\bcopper\b|\bbronce\b|\bcobre\b/u',
    ];

    /** Words in front of a name or an inscription. What follows them is taken up to the end of the capitals or the quotes. */
    private const BEFORE_TEXT = 'se jm[ée]nem|se jm[ée]ny|jm[ée]no|s n[áa]pisem|n[áa]pis|s textem|\btext|with the names?|\bnamed|\bname|that says|\bsaying|with the text|with the words?|con el nombre|con los nombres|\bnombre|con el texto|que diga|que dice';

    /** Capitalised words that are no name: the days a gift is for. */
    private const NO_NAME = ['vánoce', 'vánocům', 'vánoc', 'mikuláš', 'mikuláše', 'silvestr', 'silvestra', 'valentýn', 'valentýna', 'velikonoce', 'velikonocům', 'nový', 'rok',
        'christmas', 'easter', 'valentine', 'halloween', 'new', 'year', 'navidad', 'reyes', 'pascua', 'pla', 'petg', 'tpu', 'abs', 'asa', 'stl', 'led', 'usb'];

    private const MATERIALS = ['PLA', 'PETG', 'TPU', 'ABS', 'ASA'];

    /**
     * What plain words say that a tool can take. Keys of the router's parameters: size_mm, copies, text, text2, number,
     * colors (basic colour keys), colors_n, shape, hang, material. Only what the words really say.
     *
     * @return array<string, mixed>
     */
    public static function fromWords(?string $text): array
    {
        $raw = trim((string) $text);
        if ($raw === '') {
            return [];
        }
        $t = mb_strtolower($raw);
        $out = [];

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*cm\b/u', $t, $m)) {
            $out['size_mm'] = (int) round((float) str_replace(',', '.', $m[1]) * 10);
        } elseif (preg_match('/(\d+)\s*mm\b/u', $t, $m)) {
            $out['size_mm'] = (int) $m[1];
        }
        if (preg_match('/(\d+)\s*(?:ks|kus\w*|pieces?|pcs|piezas?|unidades?)\b/u', $t, $m)) {
            $out['copies'] = (int) $m[1];
        } elseif (preg_match('/\b(dva|dvě|two|dos)\s+(?:kusy|pieces|piezas)/u', $t)) {
            $out['copies'] = 2;
        }

        // a name or an inscription: what stands in quotes, else what follows "se jménem", "nápis", "with the name", "con el nombre"
        if (preg_match('/[„“"«]([^„“”"«»]{1,60})[“”"»]/u', $raw, $m)) {
            $out['text'] = trim($m[1]);
        } elseif (preg_match('/(?i:'.self::BEFORE_TEXT.')\s*:?\s+((?:[\p{Lu}\d][\p{L}\p{N}\'’.-]*)(?:\s+(?:(?:a|and|y|&|\+)\s+)?[\p{Lu}\d][\p{L}\p{N}\'’.-]*){0,3})/u', $raw, $m)) {
            $name = self::names($m[1]);
            if ($name !== '') {
                $out['text'] = $name;
            }
        }

        // a number of its own: "číslem 2", "number 5", an age, a place of a race; a year is the second line
        if (preg_match('/(?:[čc][íi]sl\w*|number|n[úu]mero)\s+(\d{1,3})\b/u', $t, $m) || preg_match('/\b(\d{1,3})\s*(?:\.\s*narozenin|let\b|rok[ůuy]\b|years?\b|th birthday|a[ñn]os\b)/u', $t, $m)
            || preg_match('/\b([1-3])\s*\.\s*m[íi]st|\b([1-3])(?:st|nd|rd) place/u', $t, $m)) {
            $out['number'] = (string) (int) (array_values(array_filter(array_slice($m, 1), fn ($v) => $v !== ''))[0] ?? 0);
        }
        if (preg_match('/\b(20[2-4]\d)\b/u', $t, $m) && ($out['text'] ?? null) !== $m[1]) {
            $out['text2'] = $m[1];
        }

        $colors = [];
        foreach (self::COLORS as $key => $pattern) {
            if (preg_match($pattern, $t, $m, PREG_OFFSET_CAPTURE)) {
                $colors[$key] = $m[0][1];
            }
        }
        asort($colors);
        if ($colors) {
            $out['colors'] = array_slice(array_keys($colors), 0, 6);
        }
        if (preg_match('/\b(\d|dv[ěe]|t[řr]i|[čc]ty[řr]i|two|three|four|dos|tres|cuatro)\s+(?:barv|colou?rs?|colores)/u', $t, $m)) {
            $n = ['dvě' => 2, 'dve' => 2, 'two' => 2, 'dos' => 2, 'tři' => 3, 'tri' => 3, 'three' => 3, 'tres' => 3, 'čtyři' => 4, 'ctyri' => 4, 'čtyri' => 4, 'ctyři' => 4, 'four' => 4, 'cuatro' => 4][$m[1]] ?? (int) $m[1];
            if ($n >= 1 && $n <= 8) {
                $out['colors_n'] = $n;
            }
        }

        foreach (self::SHAPES as $shape => $pattern) {
            if (preg_match($pattern, $t)) {
                $out['shape'] = $shape;
                break;
            }
        }
        if (preg_match('/stu[žz][kc]|\bribbon|\bcinta/u', $t)) {
            $out['hang'] = 'ribbon';
        } elseif (preg_match('/[řr]et[íi]z|\bchain\b|\bcadena/u', $t)) {
            $out['hang'] = 'chain';
        }
        if (preg_match('/\b('.implode('|', self::MATERIALS).')\b/u', $raw, $m)) {
            $out['material'] = $m[1];
        }

        return $out;
    }

    /** A run of capitalised words as a name: the days of the year and the names of plastics are no names. */
    private static function names(string $run): string
    {
        $words = preg_split('/\s+/u', trim($run)) ?: [];
        $kept = [];
        foreach ($words as $word) {
            $bare = mb_strtolower(trim($word, ".,;:!?'’-"));
            if ($bare !== '' && in_array($bare, self::NO_NAME, true)) {
                break;
            }
            $kept[] = $word;
        }

        return mb_substr(trim(implode(' ', $kept), ' .,;:!?-'), 0, 60);
    }

    /**
     * The router's parameters with what the words add: the model's answer (or nothing, when keywords decided) stays,
     * the words fill only what is missing.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function complete(array $params, ?string $text): array
    {
        $said = self::fromWords($text);
        // colour names of the model are English words: keep those that are basic colours, the words' own otherwise
        if (isset($params['colors'])) {
            $params['colors'] = array_values(array_unique(array_map(fn ($c) => ['gray' => 'grey', 'purple' => 'violet', 'bronze' => 'copper', 'teal' => 'turquoise', 'golden' => 'gold'][$c] ?? $c, (array) $params['colors'])));
            if (! array_intersect($params['colors'], array_keys(Palette::BASIC)) && isset($said['colors'])) {
                $params['colors'] = $said['colors'];
            }
        }

        return $params + $said;
    }

    /**
     * The values of a parametric tool's form (keys of ParametricGenerator FIELDS, TEXTS, CHOICES, FLAGS; `preset`,
     * `part_colors`, `_qty`, `_material` as a stored design has them), every one inside what the tool takes.
     * A tool that is not parametric (the bust, the pet, the map, the relief, the picture for the wall) gets the count
     * and the material alone: its page reads the general parameters itself (resources/js/calc/intake_preload.ts).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function values(string $tool, array $params): array
    {
        $out = [];
        if (isset($params['copies']) && (int) $params['copies'] > 1) {
            $out['_qty'] = (string) min(1000, (int) $params['copies']);
        }
        if (isset($params['material']) && in_array($params['material'], self::MATERIALS, true)) {
            $out['_material'] = $params['material'];
        }
        $kind = self::KIND[$tool] ?? null;
        if ($kind === null) {
            return $out;
        }
        $fields = ParametricGenerator::FIELDS[$kind] ?? [];
        $texts = ParametricGenerator::TEXTS[$kind] ?? [];
        $choices = ParametricGenerator::CHOICES[$kind] ?? [];
        $flags = ParametricGenerator::FLAGS[$kind] ?? [];

        $sizeField = self::SIZE_FIELD[$tool] ?? 'width';
        if (isset($params['size_mm'], $fields[$sizeField])) {
            [$lo, $hi] = $fields[$sizeField];
            $out[$sizeField] = max($lo, min($hi, (int) $params['size_mm']));
        }

        $text = isset($params['text']) ? trim((string) $params['text']) : '';
        $second = isset($params['text2']) ? trim((string) $params['text2']) : '';
        $number = isset($params['number']) ? trim((string) $params['number']) : '';
        $colors = array_values(array_intersect((array) ($params['colors'] ?? []), array_keys(Palette::BASIC)));
        $fit = fn (string $key, string $value): string => mb_substr($value, 0, (int) ($texts[$key][0] ?? 24));

        if ($tool === 'medallion') {
            // a place of a race is the medal's own preset: the number on the front, gold, silver or bronze
            $place = in_array($number, ['1', '2', '3'], true) ? (int) $number
                : (count($colors) === 1 ? (['gold' => 1, 'silver' => 2, 'copper' => 3][$colors[0]] ?? null) : null);
            if ($place !== null) {
                $out['preset'] = 'place'.$place;
                $out['line1'] = (string) $place;
                if ($text !== '') {
                    $out['line2'] = $fit('line2', $text);
                }
            } elseif ($text !== '') {
                $out['line1'] = $fit('line1', $text);
                if ($second !== '') {
                    $out['line2'] = $fit('line2', $second);
                }
            }
        } elseif ($tool === 'sign' || $tool === 'text') {
            // up to three lines: "Jana / Novák", a line break; the standing letters take the first one
            $lines = array_values(array_filter(array_map('trim', preg_split('/\s*(?:\/|\||\R)\s*/u', $text) ?: []), fn ($l) => $l !== ''));
            if ($second !== '' && count($lines) === 1) {
                $lines[] = $second;
            }
            foreach (array_slice($lines, 0, $tool === 'text' ? 1 : 3) as $i => $line) {
                $out['line'.($i + 1)] = $fit('line'.($i + 1), $line);
            }
        } else {
            if ($text !== '' && isset($texts['line1'])) {
                $out['line1'] = $fit('line1', $text);
            }
            if ($second !== '' && isset($texts['line2']) && $text !== '') {
                $out['line2'] = $fit('line2', $second);
            }
            if ($number !== '' && isset($texts['number'])) {
                $out['number'] = $fit('number', $number);
            }
        }

        // the outline: only a shape this tool really has
        $choice = self::SHAPE_CHOICE[$tool] ?? null;
        if ($choice !== null && isset($params['shape'], $choices[$choice])) {
            foreach (self::SHAPE_ALIASES[$params['shape']] ?? [(string) $params['shape']] as $name) {
                if (in_array($name, $choices[$choice], true)) {
                    $out[$choice] = $name;
                    break;
                }
            }
        }
        if ($tool === 'topper' && isset($out['number']) && ! isset($out['template']) && in_array('number', $choices['template'] ?? [], true)) {
            $out['template'] = 'number';
        }
        if ($tool === 'medallion' && isset($params['hang']) && in_array($params['hang'], $choices['hang'] ?? [], true)) {
            $out['hang'] = $params['hang'];
        }

        // colours: a sign of two colours is a plate and its letters; a thing of words alone is one body; a picture keeps
        // its own colours and takes only how many of them there should be
        if ($kind === 'sign' && $colors) {
            if (count($colors) >= 2 && $tool === 'sign' && in_array('two_color', $flags, true)) {
                $out['two_color'] = true;
                $out['part_colors'] = ['plate' => $colors[0], 'text' => $colors[1]];
            } else {
                $out['part_colors'] = ['body' => $colors[0]];
            }
        } elseif (isset($fields['colors_n']) && ! isset($out['preset'])) {
            $n = isset($params['colors_n']) ? (int) $params['colors_n'] : (count($colors) >= 2 ? count($colors) : 0);
            if ($n >= 1) {
                [$lo, $hi] = $fields['colors_n'];
                $out['colors_n'] = max($lo, min($hi, $n));
            }
        }

        return $out;
    }

    /**
     * The short list for the tool page's one line: what the assistant understood and the page really opens with, in
     * the visitor's language. Empty when nothing was read from the wish.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    public static function understood(string $tool, array $params, array $values, bool $hasPhoto, string $locale): array
    {
        $locale = Locales::supported($locale) ? $locale : Locales::DEFAULT;
        $say = fn (string $key, array $replace = []): string => (string) __('router.got.'.$key, $replace, $locale);
        $out = [];
        $parametric = isset(self::KIND[$tool]);

        $lines = array_values(array_filter([$values['line1'] ?? null, $values['line2'] ?? null, $values['line3'] ?? null], fn ($v) => is_string($v) && $v !== ''));
        if ($parametric && $lines && ! (isset($values['preset']) && count($lines) === 1)) {
            $out[] = $say('text', ['text' => implode(' / ', isset($values['preset']) ? array_slice($lines, 1) : $lines)]);
        } elseif (! $parametric && isset($params['text']) && in_array($tool, ['figure', 'pet'], true)) {
            $out[] = $say('name', ['text' => (string) $params['text']]);
        }
        if (isset($values['preset']) && preg_match('/^place([1-3])$/', (string) $values['preset'], $m)) {
            $out[] = $say('place', ['n' => $m[1]]);
        }
        if (isset($values['number'])) {
            $out[] = $say('number', ['n' => (string) $values['number']]);
        }
        if (! $parametric && isset($params['place']) && $tool === 'map') {
            $out[] = $say('where', ['place' => (string) $params['place']]);
        }
        $size = $parametric ? ($values[self::SIZE_FIELD[$tool] ?? 'width'] ?? null) : ($params['size_mm'] ?? null);
        if ($size !== null) {
            $out[] = $say(in_array($tool, ['figure', 'pet'], true) ? 'height' : (isset(self::SIZE_FIELD[$tool]) ? 'letters' : 'width'), ['mm' => (int) $size]);
        }
        $choice = self::SHAPE_CHOICE[$tool] ?? null;
        if ($choice !== null && isset($values[$choice]) && $values[$choice] !== 'number') {
            $out[] = $say('shape', ['shape' => $say('shape.'.(['hexagon' => 'hex', 'rounded' => 'rect'][$values[$choice]] ?? $values[$choice]))]);
        }
        if (isset($values['hang'])) {
            $out[] = $say('hang.'.$values['hang']);
        }
        if (isset($values['part_colors'])) {
            $out[] = $say('colors', ['colors' => implode(', ', array_map(fn ($c) => (string) __('toolpage.color.basic.'.$c, [], $locale), array_values((array) $values['part_colors'])))]);
        } elseif (isset($values['colors_n'])) {
            $out[] = $say('colors_n', ['n' => (int) $values['colors_n']]);
        }
        if (isset($values['_qty'])) {
            $out[] = $say('copies', ['n' => (int) $values['_qty']]);
        }
        if (isset($values['_material'])) {
            $out[] = $say('material', ['material' => (string) $values['_material']]);
        }
        if ($hasPhoto && (IntakeRouter::TOOLS[$tool]['input'] ?? 'photo') !== 'text') {
            $out[] = $say(($params['decor'] ?? null) === 'photo' ? 'photo_relief' : 'photo');
        }

        return $out;
    }
}
