<?php

namespace App\Domain\Intake;

use App\Domain\Farm\Palette;
use App\Domain\Tools\ParametricGenerator;
use App\Support\Locales;

/**
 * What a tool's page opens with after one press of "Vytvořit" (docs/AN.md §24, §25): the wish's own words read once
 * more for what a tool can take (a name or an inscription, a second line, a number, a size and what it is the size of,
 * a count, colours, a shape, a material, the place of a map, an age), and those general parameters turned into the
 * values of the chosen tool's own form (`values`, keys of ParametricGenerator: the page applies them like a stored
 * design). `understood` is the short list the page shows on its one line "the assistant understood: …": only what was
 * really put into the form. `ask` is what the tool still needs and only the visitor can give (a photo, a picture, a
 * place): the page then asks for it instead of showing its own sample, which has nothing to do with the wish.
 *
 * The rule of §25: a page opened from a wish never shows the tool's default example as if it were the answer.
 *
 * Nothing here asks a model: the router's answer (when there is one) comes first, the words fill in what it left out.
 */
final class IntakePrefill
{
    /** The tools of the router that live on the parametric page → the generator's kind (the key ring opens its quick form). */
    public const KIND = [
        'charm' => 'charm', 'ornament' => 'ornament', 'keychain' => 'keychain', 'medallion' => 'medallion', 'topper' => 'topper', 'coaster' => 'coaster',
        'earrings' => 'earrings', 'papel' => 'papel', 'sign' => 'sign', 'text' => 'sign', 'cutter' => 'cutter', 'headband' => 'headband', 'beads' => 'beads',
    ];

    /** The field the main size of a parametric tool goes to (the rest take `width`). */
    private const SIZE_FIELD = ['sign' => 'text_height', 'text' => 'text_height', 'headband' => 'head', 'beads' => 'size'];

    /** The choice that holds the outline of a tool, and what a shape named in words is called there. */
    private const SHAPE_CHOICE = [
        'charm' => 'body', 'keychain' => 'body', 'earrings' => 'body', 'ornament' => 'body', 'coaster' => 'body', 'medallion' => 'body', 'topper' => 'template', 'sign' => 'shape', 'beads' => 'shape',
    ];

    private const SHAPE_ALIASES = ['hex' => ['hex', 'hexagon'], 'rect' => ['rect', 'rounded'], 'square' => ['square', 'rect'], 'circle' => ['circle', 'ball'], 'oval' => ['oval', 'circle']];

    /** Tools that make a thing of a picture: without one (and without words to stand in for it) the page asks for it. */
    public const PICTURE_TOOLS = ['charm', 'earrings', 'cutter', 'coaster', 'ornament', 'keychain', 'medallion'];

    /** Tools that are nothing without the visitor's photo. */
    public const PHOTO_TOOLS = ['figure', 'pet', 'relief', 'filament_art', 'papel'];

    /**
     * How wide a line comes out of the tool, in letter heights: [per character, on top], measured on the tool itself.
     * A sign (a plate round a name in small letters): "Novákovi" at 20 mm is 153 mm, "Adam" at 14 mm 74 mm, "Tomáš" at
     * 11 mm 69 mm wide, with its 2 × 5 mm margin. Standing letters (capitals and digits): "MAMINKA" at 30 mm is 241 mm,
     * "2027" at 80 mm 296 mm. Good to about a tenth: the page shows the real size the moment it opens.
     */
    private const LINE_WIDTH = ['sign' => [0.643, 2.0], 'text' => [0.95, 0.3]];

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
        'pink' => '/\br[ůu][žz]ov|\bpink\b|\brosas?\b/u',
        'brown' => '/\bhn[ěe]d[áéouýaeěy]|\bbrown\b|\bmarr[óo]n/u',
        'beige' => '/\bb[ée][žz]ov|\bbeige\b/u',
        'gold' => '/\bzlat[áéouýaeěy]|\bgold(en)?\b|\bdorad[oa]/u',
        'silver' => '/\bst[řr][íi]brn|\bsilver\b|\bplatead[oa]/u',
        'copper' => '/\bbronzov|\bm[ěe]d[ěe]n|\bbronze\b|\bcopper\b|\bbronce\b|\bcobre\b/u',
    ];

    /** Words in front of a name: what follows them is taken up to the end of the capitals. */
    private const BEFORE_NAME = 'se jm[ée]nem|se jm[ée]ny|jm[ée]no|s n[áa]pisem|n[áa]pis|standing letters|with the names?|\bnamed|\bname|\bletters|con el nombre|con los nombres|\bnombre|letras de pie|\bletras';

    /** Words in front of an inscription of several words: what follows them is taken up to the next comma. */
    private const BEFORE_PHRASE = 's textem|\btext|that says|\bsaying|with the text|with the words?|con el texto|que diga|que dice';

    /** Capitalised words that are no name: the days a gift is for. */
    private const NO_NAME = ['vánoce', 'vánocům', 'vánoc', 'mikuláš', 'mikuláše', 'silvestr', 'silvestra', 'valentýn', 'valentýna', 'velikonoce', 'velikonocům', 'nový', 'rok',
        'christmas', 'easter', 'valentine', 'halloween', 'new', 'year', 'navidad', 'reyes', 'pascua', 'pla', 'petg', 'tpu', 'abs', 'asa', 'stl', 'led', 'usb'];

    private const MATERIALS = ['PLA', 'PETG', 'TPU', 'ABS', 'ASA'];

    /** What a size is the size of, by the words of its own clause: the letters, the whole thing across, the head. */
    private const SIZE_ROLES = [
        'head' => '/obvod|hlav|\bhead|circumference|contorno|cabeza/u',
        'letters' => '/vysok|v[ýy][šs]k|p[íi]sm|[čc][íi]slic|\btall\b|\bhigh\b|height|\bletters?\b|\bdigits?\b|de alto|de altura|\balt[oa]s?\b|\bletras?\b|\bcifras?\b/u',
        'width' => '/[šs]irok|[šs][íi][řr]k|dlouh|d[ée]lk|\bwide\b|width|\blong\b|de ancho|de largo|\banch[oa]/u',
    ];

    /** An age said in a word: "osmileté dítě", "an eight-year-old", "de ocho años". */
    private const AGE_WORDS = [
        3 => 't[řr][íi]|three|tres', 4 => '[čc]ty[řr]|four|cuatro', 5 => 'p[ěe]ti|five|cinco', 6 => '[šs]esti|six|seis', 7 => 'sedmi|seven|siete', 8 => 'osmi|eight|ocho', 9 => 'dev[íi]ti|nine|nueve',
        10 => 'deseti|ten|diez', 11 => 'jeden[áa]cti|eleven|once', 12 => 'dvan[áa]cti|twelve|doce', 13 => 't[řr]in[áa]cti|thirteen|trece', 14 => '[čc]trn[áa]cti|fourteen|catorce', 15 => 'patn[áa]cti|fifteen|quince',
    ];

    /** The decoration the words of a headband name (IntakeRouter::KINDS['headband']). */
    private const HEADBAND = ['unicorn' => '/jednoro[žz]|unicorn/u', 'cat' => '/ko[čc]i[čc]|\bcat\b|\bgato\b/u', 'bunny' => '/zaj[íe][čc]|kr[áa]l[íi][čc]|bunny|rabbit|conej/u', 'antlers' => '/paroh|\bsob|antler|reindeer|\basta|\breno\b/u',
        'crown' => '/korun|princez|crown|tiara|princess|corona|princesa/u', 'halo' => '/svatoz|and[ěe]l|\bhalo\b|angel|aureola|[áa]ngel/u', 'horns' => '/\broh|r[ůu][žz]k|[čc]ert|horn|devil|cuerno|diablo/u'];

    /**
     * What plain words say that a tool can take. Keys of the router's parameters: size_mm (and size_is: letters | width),
     * head_mm, age, copies, text, text2, number, colors (basic colour keys), colors_n, shape, hang, material, place.
     * Only what the words really say.
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

        // every size with its unit, and what its own clause says it is the size of
        if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*(cm|mm)\b/u', $t, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($all as $m) {
                $mm = (int) round((float) str_replace(',', '.', $m[1][0]) * ($m[2][0] === 'cm' ? 10 : 1));
                // (offsets are bytes: the clause is cut out of the bytes too)
                $start = strrpos(substr($t, 0, $m[0][1]), ',');
                $end = strpos($t, ',', $m[0][1]);
                $clause = substr($t, $start === false ? 0 : $start + 1, ($end === false ? strlen($t) : $end) - ($start === false ? 0 : $start + 1));
                // the word of the clause that stands nearest to the number says what the number is the size of
                $role = null;
                $near = PHP_INT_MAX;
                $at = $m[0][1] - ($start === false ? 0 : $start + 1);
                foreach (self::SIZE_ROLES as $name => $pattern) {
                    if (preg_match_all($pattern, $clause, $hits, PREG_OFFSET_CAPTURE)) {
                        foreach ($hits[0] as $hit) {
                            if (abs($hit[1] - $at) < $near) {
                                [$role, $near] = [$name, abs($hit[1] - $at)];
                            }
                        }
                    }
                }
                if ($role === 'head') {
                    $out['head_mm'] ??= $mm;
                } elseif (! isset($out['size_mm'])) {
                    $out['size_mm'] = $mm;
                    if ($role !== null) {
                        $out['size_is'] = $role;
                    }
                }
            }
        }
        if (preg_match('/(\d+)\s*(?:ks|kus\w*|pieces?|pcs|piezas?|unidades?)\b/u', $t, $m) || preg_match('/\b(?:sada|sadu|set of|a set of|juego de|un juego de)\s+(\d+)\b/u', $t, $m)) {
            $out['copies'] = (int) $m[1];
        } elseif (preg_match('/\b(dva|dvě|two|dos)\s+(?:kusy|pieces|piezas)/u', $t)) {
            $out['copies'] = 2;
        }

        // a name or an inscription: what stands in quotes, else what follows "s textem" up to the comma, else the capitals after "se jménem", "nápis"…
        if (preg_match('/[„“"«]([^„“”"«»]{1,60})[“”"»]/u', $raw, $m)) {
            $out['text'] = trim($m[1]);
        } elseif (preg_match('/(?i:'.self::BEFORE_PHRASE.')\s*:?\s+([^,;.!?]{1,60}?)(?=\s*(?:[,;.!?]|$))/u', $raw, $m) && trim($m[1]) !== '') {
            $out['text'] = trim($m[1]);
        } elseif (preg_match('/(?i:'.self::BEFORE_NAME.')\s*:?\s+((?:[\p{Lu}\d][\p{L}\p{N}\'’.-]*)(?:\s+(?:(?:a|and|y|&|\+)\s+)?[\p{Lu}\d][\p{L}\p{N}\'’.-]*){0,3})/u', $raw, $m)) {
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
        if (preg_match('/\b(20[2-4]\d)\b/u', $t, $m) && ! str_contains((string) ($out['text'] ?? ''), $m[1])) {
            $out['text2'] = $m[1];
        }
        // how old the one is it is for: a headband takes the head's size from it
        if (preg_match('/\b(\d{1,2})\s*(?:-\s*)?(?:let[ýáéouí]\w*|let\b|rok[ůuy]\b|-?\s*years?[- ]old|a[ñn]os\b)/u', $t, $m)) {
            $out['age'] = (int) $m[1];
        } else {
            foreach (self::AGE_WORDS as $age => $words) {
                if (preg_match('/\b(?:'.$words.')(?:let[ýáéouí]\w*|[- ]years?[- ]old| a[ñn]os\b)/u', $t)) {
                    $out['age'] = $age;
                    break;
                }
            }
        }

        // (a name is no colour and no shape: "Mrs Brown", "Hvězda")
        if (isset($out['text'])) {
            $t = str_replace(mb_strtolower($out['text']), ' ', $t);
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
        if (preg_match('/\b(\d|jedn[aéu]|dv[ěe]|t[řr]i|[čc]ty[řr]i|one|two|three|four|un|una|dos|tres|cuatro)\s+(?:barv|colou?rs?\b|color(?:es)?\b)/u', $t, $m)) {
            $n = ['jedna' => 1, 'jedné' => 1, 'jednu' => 1, 'one' => 1, 'un' => 1, 'una' => 1, 'dvě' => 2, 'dve' => 2, 'two' => 2, 'dos' => 2, 'tři' => 3, 'tri' => 3, 'three' => 3, 'tres' => 3,
                'čtyři' => 4, 'ctyri' => 4, 'čtyri' => 4, 'ctyři' => 4, 'four' => 4, 'cuatro' => 4][$m[1]] ?? (int) $m[1];
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
        if (($place = IntakePlace::fromWords($raw)) !== null) {
            $out['place'] = IntakePlace::named($place);      // "mapa Olomouce" is a map of Olomouc
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

    /** The decoration of a headband as the words name it; null when they name none (the tool then opens with its horns). */
    public static function headbandKind(?string $text): ?string
    {
        $t = mb_strtolower((string) $text);
        foreach (self::HEADBAND as $kind => $pattern) {
            if (preg_match($pattern, $t)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * The router's parameters with what the words add: the model's answer (or nothing, when keywords decided) stays,
     * the words fill only what is missing. Two things the words decide whatever the model said, because the model is
     * told one meaning of `size_mm` per tool and people say another: a sign "15 cm wide" is 15 cm across, not letters
     * 15 cm tall (`width_mm`), and a headband's size is the head, said in centimetres or by an age.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function complete(array $params, ?string $text, ?string $tool = null): array
    {
        $said = self::fromWords($text);
        // colour names of the model are English words: keep those that are basic colours, the words' own otherwise
        if (isset($params['colors'])) {
            $params['colors'] = array_values(array_unique(array_map(fn ($c) => ['gray' => 'grey', 'purple' => 'violet', 'bronze' => 'copper', 'teal' => 'turquoise', 'golden' => 'gold'][$c] ?? $c, (array) $params['colors'])));
            if (! array_intersect($params['colors'], array_keys(Palette::BASIC)) && isset($said['colors'])) {
                $params['colors'] = $said['colors'];
            }
        }
        $own = $said;
        unset($own['size_is'], $own['head_mm']);
        $out = $params + $own;

        if ($tool === 'sign' || $tool === 'text') {
            if (isset($said['size_mm'])) {
                // a sign's size is how wide it is unless the words speak of the letters; standing letters over 8 cm are a width too
                $role = $said['size_is'] ?? ($tool === 'sign' || $said['size_mm'] > 80 ? 'width' : 'letters');
                if ($role === 'width') {
                    $out['width_mm'] = $said['size_mm'];
                    unset($out['size_mm']);
                } else {
                    $out['size_mm'] = $said['size_mm'];
                    unset($out['width_mm']);
                }
            }
        } elseif ($tool === 'headband') {
            $head = $said['head_mm'] ?? (isset($said['size_mm']) && $said['size_mm'] >= 440 && $said['size_mm'] <= 640 ? $said['size_mm'] : null);
            if ($head !== null) {
                $out['size_mm'] = $head;
                unset($out['age']);
            } elseif (! isset($params['size_mm'])) {
                unset($out['size_mm']);                  // "a 12 cm crown" is no head
            }
            if (! isset($out['kind']) && ($kind = self::headbandKind($text)) !== null) {
                $out['kind'] = $kind;
            }
        } elseif ($tool !== null) {
            unset($out['width_mm']);
        }
        if ($tool !== null && $tool !== 'headband') {
            unset($out['age']);
        }
        if ($tool !== null && $tool !== 'map') {
            unset($out['place']);
        }

        return $out;
    }

    /** The colour a basic colour's key stands for on a tool's page: the colour itself (docs/U.md), the same sixteen the colour window offers. */
    private static function hex(string $key): string
    {
        return Palette::BASIC[$key];
    }

    /** Black or white, whichever reads on a colour. */
    private static function contrast(string $key): string
    {
        return Palette::lab(self::hex($key))[0] > 60 ? self::hex('black') : self::hex('white');
    }

    /**
     * The values of a parametric tool's form (keys of ParametricGenerator FIELDS, TEXTS, CHOICES, FLAGS; `preset`,
     * `part_colors`, `artwork`, `_qty`, `_material` as a stored design has them), every one inside what the tool takes.
     * `_plain` tells the page to leave out the sample it would open with (our logo on the charm, the golfer on the
     * coaster): the wish says what the thing is. A tool that is not parametric (the bust, the pet, the map, the
     * relief, the picture for the wall) gets the count and the material alone: its page reads the general parameters
     * itself (resources/js/calc/intake_preload.ts).
     *
     * @param  array<string, mixed>  $params
     * @param  string|null  $words  the wish itself: a picture it names is looked up in our library (IntakeMotif)
     * @return array<string, mixed>
     */
    public static function values(string $tool, array $params, ?string $words = null, bool $hasPhoto = false): array
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

        $text = isset($params['text']) ? trim((string) $params['text']) : '';
        $second = isset($params['text2']) ? trim((string) $params['text2']) : '';
        $number = isset($params['number']) ? trim((string) $params['number']) : '';
        $colors = array_values(array_intersect((array) ($params['colors'] ?? []), array_keys(Palette::BASIC)));
        $fit = fn (string $key, string $value): string => mb_substr($value, 0, (int) ($texts[$key][0] ?? 24));

        if ($tool === 'headband') {
            return $out + self::headband($params, $colors, $fields, $choices);
        }

        $sizeField = self::SIZE_FIELD[$tool] ?? 'width';
        if (isset($params['size_mm'], $fields[$sizeField])) {
            [$lo, $hi] = $fields[$sizeField];
            $out[$sizeField] = max($lo, min($hi, (int) $params['size_mm']));
        }

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
            // how wide the whole sign should be → the letter height that gives it (the longest line decides)
            if (isset($params['width_mm'], $fields['text_height']) && ! isset($out['text_height'])) {
                $longest = max(array_map('mb_strlen', array_slice($lines, 0, $tool === 'text' ? 1 : 3) ?: [(string) ($texts['line1'][2] ?? 'Jana')]));
                [$lo, $hi] = $fields['text_height'];
                $margin = $tool === 'text' ? 0 : 2 * (float) ($fields['margin'][2] ?? 5);
                [$perChar, $onTop] = self::LINE_WIDTH[$tool];
                $out['text_height'] = (int) max($lo, min($hi, round(((int) $params['width_mm'] - $margin) / ($perChar * max(1, $longest) + $onTop))));
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
        if ($tool === 'topper') {
            // the big number behind the name only when the wish says a number; a shape when it names one; else the words alone
            if (isset($out['number']) && ! isset($out['template'])) {
                $out['template'] = 'number';
            } elseif (! isset($out['template']) && isset($out['line1']) && in_array('none', $choices['template'] ?? [], true)) {
                $out['template'] = 'none';
            }
        }
        if ($tool === 'medallion' && isset($params['hang']) && in_array($params['hang'], $choices['hang'] ?? [], true)) {
            $out['hang'] = $params['hang'];
        }

        // a picture: the visitor's own (handed over by the page), else one of our library the words name, else the page asks for it
        $pictured = in_array($tool, self::PICTURE_TOOLS, true);
        $motif = null;
        $written = isset($out['line1']) || isset($out['preset']);
        if ($pictured && ! $hasPhoto) {
            // a name on a shape ("a heart charm with the names…") is the thing itself: no picture stands in its way
            $motif = $written ? null : IntakeMotif::fromWords($words, $tool);
            if ($motif !== null) {
                $out['artwork'] = $motif['ref'];
                $out['_plain'] = true;
                foreach (array_keys($texts) as $key) {
                    $out[$key] = '';                       // the tool's sample name ("Ela" on the cutter) is not part of the wish
                }
                if (in_array('image', $choices['body'] ?? [], true)) {
                    $out['body'] = 'image';                // the thing follows the outline of the picture
                }
            } elseif ($written && $tool !== 'medallion') {
                $out['artwork'] = '';
                $out['_plain'] = true;
                // a name alone on an ornament is a disc with the name on it, not loose letters
                if ($tool === 'ornament' && ! isset($out['body'])) {
                    $out['body'] = 'circle';
                }
            } elseif (! $written && $tool !== 'medallion' && ($params['decor'] ?? null) !== 'photo') {
                $out['artwork'] = '';
                $out['_plain'] = true;
                foreach (array_keys($texts) as $key) {
                    $out[$key] = '';
                }
            }
        }
        if ($hasPhoto && $pictured && $tool !== 'medallion') {
            $out['_plain'] = true;                         // the visitor's photo is on its way: nothing of ours is shown meanwhile
        }

        // colours: the colours of the colour window, by what each part of the tool is
        $one = $colors[0] ?? null;
        $two = $colors[1] ?? null;
        if ($kind === 'sign' && $one !== null) {
            if ($two !== null && $tool === 'sign' && in_array('two_color', $flags, true)) {
                $out['two_color'] = true;
                $out['part_colors'] = ['plate' => self::hex($one), 'text' => self::hex($two)];
            } else {
                if ($tool === 'sign' && in_array('two_color', $flags, true)) {
                    $out['two_color'] = false;
                }
                $out['part_colors'] = ['body' => self::hex($one)];
            }
        } elseif ($kind === 'sign' && $tool === 'sign' && (int) ($params['colors_n'] ?? 0) === 2 && in_array('two_color', $flags, true)) {
            $out['two_color'] = true;
        } elseif ($tool === 'beads' && $one !== null) {
            $out['style'] = 'raised';                      // the letters stand out in their own colour
            $out['part_colors'] = ['body' => self::hex($one), 'text' => $two !== null ? self::hex($two) : self::contrast($one)];
        } elseif ($tool === 'cutter' && $one !== null) {
            $out['part_colors'] = ['body' => self::hex($one)];
        } elseif (isset($fields['colors_n']) && ! isset($out['preset'])) {
            if ($one !== null && ! $hasPhoto && ($motif !== null || $written)) {
                $multi = $motif !== null && str_starts_with($motif['ref'], 'lib:colour/');       // a picture of ours that has its own colours keeps them
                if ($motif !== null && ! $multi) {
                    // a silhouette: one colour makes the whole thing, two are the picture and the plate under it
                    $out['part_colors'] = ['body' => self::hex($two ?? $one), 'color_1' => self::hex($one)];
                } elseif ($motif === null) {
                    // words on a plate: the plate, then the letters (black or white when only the plate was named)
                    $out['part_colors'] = ['body' => self::hex($one), 'color_1' => $two !== null ? self::hex($two) : self::contrast($one)];
                }
            }
            $n = isset($params['colors_n']) ? (int) $params['colors_n'] : (count($colors) >= 2 ? count($colors) : 0);
            if ($n >= 1 && ! isset($out['part_colors'])) {
                [$lo, $hi] = $fields['colors_n'];
                $out['colors_n'] = max($lo, min($hi, $n));
            }
        }

        return $out;
    }

    /**
     * A headband: the costume (the tool's own preset: the decoration and its two colours), the head, the colours said.
     *
     * @param  array<string, mixed>  $params
     * @param  list<string>  $colors
     * @return array<string, mixed>
     */
    private static function headband(array $params, array $colors, array $fields, array $choices): array
    {
        $out = [];
        $kind = isset($params['kind']) && in_array($params['kind'], $choices['decoration'] ?? [], true) ? (string) $params['kind'] : null;
        if ($kind !== null && isset(ParametricGenerator::PRESETS['headband']['hb_'.$kind])) {
            $out['preset'] = 'hb_'.$kind;
            $out['decoration'] = $kind;
        }
        [$lo, $hi] = $fields['head'];
        if (isset($params['size_mm']) && (int) $params['size_mm'] >= 440) {
            $head = max($lo, min($hi, round((int) $params['size_mm'] / 5) / 2));      // the field's own step: half a centimetre
            $out['head'] = $head == (int) $head ? (int) $head : $head;
            $out['head_size'] = 'custom';
        } elseif (isset($params['age']) && (int) $params['age'] >= 2) {
            // the tool's own three sizes: a child of 3 to 6, a schoolchild, a grown-up
            $size = (int) $params['age'] <= 6 ? 'child' : ((int) $params['age'] <= 14 ? 'kid' : 'adult');
            $out['head_size'] = $size;
            $out['head'] = ParametricGenerator::FILLS['headband']['head_size'][$size]['head'];
        }
        if ($colors) {
            // one colour is the whole thing; two are the band and the decoration, unless they are the costume's own two
            // (a devil "red and black" has a black band and red horns, whichever was said first)
            $own = self::presetColors($out['preset'] ?? null);
            $said = [self::hex($colors[0]), self::hex($colors[1] ?? $colors[0])];
            if ($own !== null && count($colors) >= 2 && self::sameColors($own, $said)) {
                $out['part_colors'] = ['body' => self::nearestOf($own[0], $said), 'details' => self::nearestOf($own[1], $said)];
            } else {
                $out['part_colors'] = ['body' => $said[0], 'details' => $said[1]];
            }
        }

        return $out;
    }

    /** @return array{0: string, 1: string}|null the band's and the decoration's colour of a costume, as colours */
    private static function presetColors(?string $preset): ?array
    {
        if ($preset === null) {
            return null;
        }
        $palette = app(Palette::class);
        $colors = ParametricGenerator::presets('headband')[$preset]['part_colors'] ?? [];
        $body = $palette->hex($colors['body'] ?? null);
        $details = $palette->hex($colors['details'] ?? null);

        return $body !== null && $details !== null ? [$body, $details] : null;
    }

    /** True when two pairs of colours are the same two colours, in either order (as the eye sees them: the basic colour each is nearest to). */
    private static function sameColors(array $a, array $b): bool
    {
        $names = fn (array $pair): array => array_values(array_unique(array_map(fn (string $hex) => self::basicOf($hex), $pair)));
        [$x, $y] = [$names($a), $names($b)];
        sort($x);
        sort($y);

        return count($x) === 2 && $x === $y;
    }

    /** The one of the said colours that a costume's colour is. */
    private static function nearestOf(string $hex, array $said): string
    {
        usort($said, fn (string $a, string $b) => Palette::distance($hex, $a) <=> Palette::distance($hex, $b));

        return $said[0];
    }

    /** The basic colour a colour is nearest to (its key). */
    public static function basicOf(?string $hex): ?string
    {
        if (! is_string($hex) || ! preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
            return is_string($hex) && isset(Palette::BASIC[$hex]) ? $hex : null;
        }
        $best = null;
        $bestD = INF;
        foreach (Palette::BASIC as $key => $basic) {
            $d = Palette::distance($hex, $basic);
            if ($d < $bestD) {
                [$best, $bestD] = [$key, $d];
            }
        }

        return $best;
    }

    /**
     * What the tool still needs and only the visitor can give: `photo` (a bust, a pet, a relief, a picture for the wall
     * are made of their photo), `picture` (a charm, a cutter, a coaster… of a drawing, a logo or a photo, when the wish
     * names no picture of our library and no words to put on the thing), `place` (a map of "our town"). Null when the
     * page can open with a design. The page shows the sentence and leaves its own sample out.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $values
     * @return array{kind: string, text: string, button: ?string}|null
     */
    public static function ask(string $tool, array $params, array $values, bool $hasPhoto, string $locale): ?array
    {
        $locale = Locales::supported($locale) ? $locale : Locales::DEFAULT;
        $say = fn (string $kind, bool $button = true): array => ['kind' => $kind, 'text' => (string) __('router.ask.'.$kind, [], $locale), 'button' => $button ? (string) __('router.ask.'.$kind.'.button', [], $locale) : null];
        if ($tool === 'map') {
            return isset($params['place']) && trim((string) $params['place']) !== '' ? null : $say('place', false);
        }
        if ($hasPhoto) {
            return null;
        }
        if (in_array($tool, self::PHOTO_TOOLS, true)) {
            return $say('photo');
        }
        if (in_array($tool, self::PICTURE_TOOLS, true) && array_key_exists('artwork', $values) && $values['artwork'] === '' && ($values['line1'] ?? '') === '') {
            return $say('picture');
        }

        return null;
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

        if ($tool === 'headband' && isset($values['decoration'])) {
            $out[] = $say('deco', ['deco' => mb_strtolower((string) __('param.o.headband.'.$values['decoration'], [], $locale))]);
        }
        if (isset($values['artwork']) && $values['artwork'] !== '') {
            $slug = IntakeMotif::slug((string) $values['artwork']);
            $out[] = $say('motif', ['motif' => mb_strtolower((string) __('artwork.items.'.$slug, [], $locale))]);
        }
        $lines = array_values(array_filter([$values['line1'] ?? null, $values['line2'] ?? null, $values['line3'] ?? null], fn ($v) => is_string($v) && $v !== ''));
        if ($parametric && $lines && ! (isset($values['preset']) && count($lines) === 1)) {
            $out[] = $say('text', ['text' => implode(' / ', isset($values['preset']) ? array_slice($lines, 1) : $lines)]);
        } elseif (! $parametric && isset($params['text']) && in_array($tool, ['figure', 'pet'], true)) {
            $out[] = $say('name', ['text' => (string) $params['text']]);
        }
        if (isset($values['preset']) && preg_match('/^place([1-3])$/', (string) $values['preset'], $m)) {
            $out[] = $say('place', ['n' => $m[1]]);
        }
        if (isset($values['number']) && $values['number'] !== '') {
            $out[] = $say('number', ['n' => (string) $values['number']]);
        }
        if (! $parametric && isset($params['place']) && $tool === 'map') {
            $out[] = $say('where', ['place' => (string) $params['place']]);
        }
        if ($tool === 'headband') {
            if (isset($values['head'])) {
                $cm = rtrim(rtrim(number_format((float) $values['head'], 1, $locale === 'en' ? '.' : ',', ''), '0'), ',.');
                $out[] = ($values['head_size'] ?? 'custom') === 'custom' ? $say('head', ['cm' => $cm])
                    : $say('head_for', ['who' => mb_strtolower((string) __('param.o.headband.'.$values['head_size'], [], $locale)), 'cm' => $cm]);
            }
        } elseif (($tool === 'sign' || $tool === 'text') && isset($params['width_mm'], $values['text_height'])) {
            $out[] = $say('width_about', ['mm' => (int) $params['width_mm']]);
        } else {
            $size = $parametric ? ($values[self::SIZE_FIELD[$tool] ?? 'width'] ?? null) : ($params['size_mm'] ?? null);
            if ($size !== null) {
                $out[] = $say(in_array($tool, ['figure', 'pet'], true) ? 'height' : ($tool === 'beads' ? 'bead' : (isset(self::SIZE_FIELD[$tool]) ? 'letters' : 'width')), ['mm' => (int) $size]);
            }
        }
        $choice = self::SHAPE_CHOICE[$tool] ?? null;
        if ($choice !== null && isset($values[$choice]) && ! in_array($values[$choice], ['number', 'none', 'image'], true)) {
            $shape = ['hexagon' => 'hex', 'rounded' => 'rect', 'ball' => 'circle'][$values[$choice]] ?? $values[$choice];
            if (isset(self::SHAPES[$shape])) {
                $out[] = $say('shape', ['shape' => $say('shape.'.$shape)]);
            }
        }
        if (isset($values['hang'])) {
            $out[] = $say('hang.'.$values['hang']);
        }
        if (isset($values['part_colors'])) {
            $names = [];
            foreach (array_values((array) $values['part_colors']) as $hex) {
                $key = self::basicOf((string) $hex);
                if ($key !== null) {
                    $names[] = (string) __('toolpage.color.basic.'.$key, [], $locale);
                }
            }
            if ($names = array_values(array_unique($names))) {
                $out[] = $say('colors', ['colors' => implode(', ', $names)]);
            }
        } elseif (isset($values['two_color']) && $values['two_color'] === true) {
            $out[] = $say('colors_n', ['n' => 2]);
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
