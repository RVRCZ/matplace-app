<?php

namespace App\Domain\Intake;

/**
 * The place of a map as a wish says it (docs/AN.md §25): "3D mapa Olomouce", "mapa města Plzeň", "a 3D map of Bath",
 * "un mapa de Toledo", "mapa okolí Náměstí Míru 5, Praha". The words are the visitor's own, so Czech names come in the
 * case the sentence put them in: `tries()` gives the names to look up, the most likely first (the name as a map calls
 * it, then what the rules of the Czech second case make of it, then the words as written). App\Domain\Tools\MapData
 * walks them until a settlement of that very name answers, so "Prahy" opens Prague and not a hamlet called Prahy.
 *
 * Nothing here asks a service: reading only.
 */
final class IntakePlace
{
    /** The word a map is called by in the wish (cs, en, es; with and without diacritics). */
    private const MAP = 'map[auyě]?|mapou|mapk[auy]|pl[áa]n(?:ek|ku)?|maps?|mapas?|plano';

    /**
     * Words between "a map" and the name that are no part of it. What follows the last of them is the place.
     * (A capitalised word of this list is a name: "Nové Město", "Villa del Río".)
     */
    private const FILLER = ['3d', 'na', 'ze', 'z', 'od', 'pro', 'v', 've', 'k', 'ke', 'm[ěe]sta', 'm[ěe]ste[čc]ka', 'm[ěe]sto', 'obce', 'obec', 'vesnice', 'vesni[čc]ky', 'vsi', 'm[ěe]styse', '[čc]tvrti', '[čc]tvr[ťt]', 'centra', 'centrum',
        'okol[íi]', 'kolem', 'okolo', 'ulice', 'n[áa]m[ěe]st[íi]', 'krajiny', 'krajina', 'hory', 'hora', 'hor', 'poho[řr][íi]', 'oblasti', 'oblast', 'na[šs]eho', 'na[šs][íi]', 'na[šs]ich', 'm[ée]ho', 'moj[íi]', 'm[ée]', 'm[ůu]j', 'rodn[ée]ho', 'rodn[ée]', 'rodn[áa]',
        'of', 'the', 'our', 'my', 'a', 'an', 'town', 'city', 'village', 'around', 'area', 'centre', 'center', 'old', 'home', 'street', 'for',
        'de', 'del', 'la', 'el', 'los', 'las', 'nuestra', 'nuestro', 'mi', 'ciudad', 'pueblo', 'aldea', 'barrio', 'centro', 'calle', 'alrededores', 'casco', 'antiguo', 'para', 'tu', 'su'];

    /** Small words a name holds between its capitalised ones: "Ústí nad Labem", "Stratford upon Avon", "Villanueva de la Serena". */
    private const JOINERS = 'nad|pod|u|na|v|ve|pri|při|upon|on|in|le|de|del|de la|de los|la|am|an der|im';

    /** Capitalised words after a map that are no place: the days a gift is for, the months, the tool's own words. */
    private const NO_PLACE = ['vánoce', 'vánoc', 'vánocům', 'mikuláš', 'mikuláše', 'valentýn', 'valentýna', 'velikonoce', 'narozeniny', 'christmas', 'easter', 'valentine', 'navidad', 'reyes', 'pascua',
        'pla', 'petg', 'tpu', 'abs', 'asa', 'stl', 'led', 'osm', 'openstreetmap'];

    /**
     * The second case of the larger Czech (and a few neighbouring) towns → the name on the map. The rules below would
     * guess most of them; these are certain, and the ones the rules get wrong (Plzně, Chebu, Písku, Varů).
     */
    private const KNOWN = [
        'prahy' => 'Praha', 'brna' => 'Brno', 'ostravy' => 'Ostrava', 'plzně' => 'Plzeň', 'liberce' => 'Liberec', 'olomouce' => 'Olomouc', 'českých budějovic' => 'České Budějovice',
        'hradce králové' => 'Hradec Králové', 'pardubic' => 'Pardubice', 'zlína' => 'Zlín', 'havířova' => 'Havířov', 'kladna' => 'Kladno', 'mostu' => 'Most', 'opavy' => 'Opava',
        'frýdku-místku' => 'Frýdek-Místek', 'jihlavy' => 'Jihlava', 'karviné' => 'Karviná', 'teplic' => 'Teplice', 'děčína' => 'Děčín', 'chomutova' => 'Chomutov', 'karlových varů' => 'Karlovy Vary',
        'jablonce nad nisou' => 'Jablonec nad Nisou', 'prostějova' => 'Prostějov', 'mladé boleslavi' => 'Mladá Boleslav', 'přerova' => 'Přerov', 'české lípy' => 'Česká Lípa', 'třebíče' => 'Třebíč',
        'třince' => 'Třinec', 'tábora' => 'Tábor', 'znojma' => 'Znojmo', 'kolína' => 'Kolín', 'příbrami' => 'Příbram', 'příbrame' => 'Příbram', 'chebu' => 'Cheb', 'písku' => 'Písek', 'kroměříže' => 'Kroměříž',
        'trutnova' => 'Trutnov', 'vsetína' => 'Vsetín', 'šumperka' => 'Šumperk', 'šumperku' => 'Šumperk', 'uherského hradiště' => 'Uherské Hradiště', 'břeclavi' => 'Břeclav', 'hodonína' => 'Hodonín',
        'českého krumlova' => 'Český Krumlov', 'kutné hory' => 'Kutná Hora', 'telče' => 'Telč', 'stodu' => 'Stod', 'domažlic' => 'Domažlice', 'klatov' => 'Klatovy', 'rokycan' => 'Rokycany',
        'berouna' => 'Beroun', 'mělníka' => 'Mělník', 'litoměřic' => 'Litoměřice', 'ústí nad labem' => 'Ústí nad Labem', 'mariánských lázní' => 'Mariánské Lázně', 'strakonic' => 'Strakonice',
        'jindřichova hradce' => 'Jindřichův Hradec', 'žďáru nad sázavou' => 'Žďár nad Sázavou', 'nového jičína' => 'Nový Jičín', 'valašského meziříčí' => 'Valašské Meziříčí', 'litomyšle' => 'Litomyšl',
        'bratislavy' => 'Bratislava', 'košic' => 'Košice', 'vídně' => 'Vídeň', 'paříže' => 'Paříž', 'londýna' => 'Londýn', 'říma' => 'Řím', 'berlína' => 'Berlín', 'drážďan' => 'Drážďany',
        'mnichova' => 'Mnichov', 'krakova' => 'Krakov', 'varšavy' => 'Varšava', 'budapešti' => 'Budapešť', 'benátek' => 'Benátky', 'new yorku' => 'New York', 'barcelony' => 'Barcelona', 'madridu' => 'Madrid',
    ];

    /**
     * The place a wish names, as it is written there; null when it names none ("3D mapa našeho města").
     */
    public static function fromWords(?string $text): ?string
    {
        $raw = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
        if ($raw === '') {
            return null;
        }
        // in quotes after the map: taken as it stands
        if (preg_match('/\b(?i:'.self::MAP.')\b[^„“"«]{0,40}[„“"«]([^„“”"«»]{2,60})[“”"»]/u', $raw, $m)) {
            return self::tidy($m[1]);
        }
        $name = '(?:\p{Lu}[\p{L}\'’.-]*|\d{1,4}[a-z]?(?=\s*(?:,|$|\s\p{Lu})))';
        $filler = '(?:'.implode('|', self::FILLER).')';       // small letters only: "Náměstí Míru" is a name
        // "mapa [města] Olomouce", "a 3D map of Bath", "un mapa 3D de Toledo"; an address goes on over one comma ("Dlouhá 12, Plzeň")
        $pattern = '/\b(?i:'.self::MAP.')\b(?:\s+(?:3[dD]\b|'.$filler.'\b))*\s*:?\s+('.$name.'(?:\s+(?:(?:'.self::JOINERS.')\s+)?'.$name.'){0,5}(?:,\s+\p{Lu}[\p{L}\'’.-]*(?:\s+(?:(?:'.self::JOINERS.')\s+)?\p{Lu}[\p{L}\'’.-]*){0,3})?)/u';
        if (preg_match($pattern, $raw, $m)) {
            $place = self::tidy($m[1]);
            if ($place !== null) {
                return $place;
            }
        }
        // the place named before the map or apart from it: "Plzeň jako 3D mapa", "our town Bath as a map", "město Plzeň"
        if (preg_match('/\b(?i:m[ěe]st[oa]|m[ěe]ste[čc]k[oa]|obec|obce|vesnic[ei]|vesni[čc]k[auy]|town|city|village|ciudad|pueblo)\s+('.$name.'(?:\s+(?:(?:'.self::JOINERS.')\s+)?\p{Lu}[\p{L}\'’.-]*){0,3})/u', $raw, $m)) {
            return self::tidy($m[1]);
        }
        if (preg_match('/\b(?i:'.self::MAP.')\b/u', $raw) && preg_match('/^((?:\p{Lu}[\p{L}\'’.-]*)(?:\s+(?:(?:'.self::JOINERS.')\s+)?\p{Lu}[\p{L}\'’.-]*){0,3})\s+(?i:jako|as|como|na|v)\b/u', $raw, $m)
            && ! preg_match('/^(?i:'.self::MAP.'|3d|chci|cht[ěe]l|pot[řr]ebuj|pros[íi]m|i want|i need|a|an|un|una|quiero|necesito)\b/u', $m[1])) {
            return self::tidy($m[1]);
        }

        return null;
    }

    /** A name without what trails it; null when what was caught is a day of the year or a plastic. */
    private static function tidy(string $name): ?string
    {
        $words = preg_split('/\s+/u', trim($name, " \t.,;:!?-")) ?: [];
        $kept = [];
        foreach ($words as $word) {
            if (in_array(mb_strtolower(trim($word, '.,;:!?')), self::NO_PLACE, true)) {
                break;
            }
            $kept[] = $word;
        }
        // a joiner left hanging at the end ("Ústí nad") belongs to nothing
        while ($kept && preg_match('/^(?:'.self::JOINERS.')$/u', (string) end($kept))) {
            array_pop($kept);
        }
        $out = mb_substr(trim(implode(' ', $kept), ' .,;:!?-'), 0, 80);

        return mb_strlen($out) >= 2 ? $out : null;
    }

    /**
     * The names to look a place up by, the most likely first: the name the map knows for a Czech second case we are
     * sure of, else what the rules of the second case make of the last word (and of the adjectives before it), and the
     * words as they were written. At most four: every one is a question to a public service.
     *
     * @return list<string>
     */
    public static function tries(string $place, string $locale = 'cs'): array
    {
        $place = trim((string) preg_replace('/\s+/u', ' ', $place));
        if ($place === '') {
            return [];
        }
        $low = mb_strtolower($place);
        if (isset(self::KNOWN[$low])) {
            return [self::KNOWN[$low]];
        }
        if (in_array($low, array_map('mb_strtolower', self::KNOWN), true)) {
            return [$place];
        }
        // an address, coordinates, or a language whose names do not change: as written
        if ($locale !== 'cs' || preg_match('/\d|,/u', $place)) {
            return [$place];
        }
        $words = explode(' ', $place);
        $last = array_pop($words);
        $before = array_map(fn (string $w) => self::adjective($w), $words);
        $out = [];
        foreach (self::nouns($last) as $noun) {
            // the adjectives agree with the noun: tried in their first case with a noun that changed, as written otherwise
            foreach ($words === [] ? [[]] : array_values(array_unique([$before, $words], SORT_REGULAR)) as $head) {
                $out[] = trim(implode(' ', $head).' '.$noun);
            }
        }
        $out[] = $place;

        // the words as written come second: a first case typed ("mapa Lhota") is found by them when the rules guessed wrong
        $out = array_values(array_unique($out));
        $first = array_shift($out);
        $rest = array_values(array_diff($out, [$place]));

        return array_slice(array_values(array_unique(array_filter([$first, $place, ...$rest]))), 0, 4);
    }

    /** The name a map knows for a Czech second case we are sure of ("Olomouce" → "Olomouc"); the words as written otherwise. */
    public static function named(string $place): string
    {
        return self::KNOWN[mb_strtolower(trim($place))] ?? $place;
    }

    /** An adjective of a name in its first case: "Českých" → "České", "Nového" → "Nový", "Mladé" → "Mladá". */
    private static function adjective(string $word): string
    {
        foreach (['ých' => 'é', 'ého' => 'ý', 'é' => 'á', 'ova' => 'ův'] as $from => $to) {
            if (mb_strlen($word) > mb_strlen($from) + 2 && str_ends_with($word, $from)) {
                return mb_substr($word, 0, -mb_strlen($from)).$to;
            }
        }

        return $word;
    }

    /**
     * What a place name in the second case may be in the first: "Olomouce" → Olomouc, "Liberce" → Liberec, "Ostravy" →
     * Ostrava, "Útušic" → Útušice, "Zlína" → Zlín, "Brna" → Brno, "Mostu" → Most. The likelier first; nothing when the
     * word has no ending of the second case.
     *
     * @return list<string>
     */
    private static function nouns(string $word): array
    {
        $cut = fn (int $n, string $add = ''): string => mb_substr($word, 0, -$n).$add;
        $ends = fn (string $s): bool => mb_strlen($word) > mb_strlen($s) + 1 && str_ends_with(mb_strtolower($word), $s);

        return match (true) {
            $ends('ice') => [],                                           // Pardubice, Útušice: a first case already
            $ends('ic') => [$word.'e'],                                   // Pardubic → Pardubice
            $ends('ov') || $ends('an') => [$word.'y'],                    // Klatov → Klatovy, Rokycan → Rokycany
            $ends('ů') => [$cut(1, 'y')],                                 // Varů → Vary
            $ends('ně') => [$cut(2, 'ň')],                                // Plzně → Plzeň
            $ends('ce') => [$cut(1), $cut(2, 'ec')],                      // Olomouce → Olomouc, Liberce → Liberec
            $ends('če') || $ends('že') || $ends('še') || $ends('ře') || $ends('je') || $ends('le') => [$cut(1)],     // Třebíče → Třebíč, Kroměříže → Kroměříž
            $ends('y') => [$cut(1, 'a')],                                 // Prahy → Praha
            $ends('ku') => [$cut(2, 'ek'), $cut(1)],                      // Písku → Písek, Šumperku → Šumperk
            $ends('u') => [$cut(1)],                                      // Mostu → Most
            $ends('i') => [$cut(1)],                                      // Břeclavi → Břeclav
            $ends('é') => [$cut(1, 'á')],                                 // Karviné → Karviná
            $ends('a') => [$cut(1), $cut(1, 'o')],                        // Zlína → Zlín, Brna → Brno
            default => [],
        };
    }
}
