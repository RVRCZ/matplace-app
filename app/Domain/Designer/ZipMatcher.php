<?php

namespace App\Domain\Designer;

use App\Models\DesignerModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * "One zip for the whole portfolio": which file belongs to which card? Names are compared after normalising
 * (lower case, no accents, no extension, no "_v2" / "-final" / trailing numbers) with Jaro-Winkler:
 *   ≥ 0.90  sure   — paired automatically
 *   ≥ 0.75  maybe  — offered, the designer confirms
 *   below   none   — left for the designer to pair by hand
 * A card is compared by its title and by the file names it has at the source (Printables lists them).
 * Every card gets at most one file and every file at most one card: the best pairs win.
 */
final class ZipMatcher
{
    public const SURE = 0.90;

    public const MAYBE = 0.75;

    /**
     * @param  list<string>  $files  names of model files in the zip
     * @param  Collection<int, DesignerModel>  $cards  cards that can take a file
     * @return array<string, array{card: ?int, score: float, level: string}> by file name
     */
    public function match(array $files, Collection $cards): array
    {
        $pairs = [];
        foreach ($files as $file) {
            $name = self::normalize($file);
            foreach ($cards as $card) {
                $best = 0.0;
                foreach (array_merge([$card->title], (array) $card->source_files) as $candidate) {
                    $best = max($best, self::jaroWinkler($name, self::normalize((string) $candidate)));
                }
                if ($best >= self::MAYBE) {
                    $pairs[] = [$best, $file, $card->id];
                }
            }
        }
        usort($pairs, fn ($a, $b) => $b[0] <=> $a[0]);

        $out = array_fill_keys($files, ['card' => null, 'score' => 0.0, 'level' => 'none']);
        $taken = [];
        foreach ($pairs as [$score, $file, $cardId]) {
            if ($out[$file]['card'] !== null || isset($taken[$cardId])) {
                continue;
            }
            $out[$file] = ['card' => $cardId, 'score' => round($score, 3), 'level' => $score >= self::SURE ? 'sure' : 'maybe'];
            $taken[$cardId] = true;
        }

        return $out;
    }

    public static function normalize(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/\.(stl|3mf|obj|step|stp)$/i', '', $name);
        $name = Str::lower(Str::ascii($name));
        $name = (string) preg_replace('/[^a-z0-9]+/', ' ', $name);
        // version and "final" markers, then numbers hanging at the end
        do {
            $before = $name;
            $name = (string) preg_replace('/\s+(v\d+|ver\d+|rev\d+|final|fixed|fix|new|copy|kopie)$/', '', trim($name));
            $name = (string) preg_replace('/\s+\d+$/', '', $name);
        } while ($name !== $before);

        return trim($name);
    }

    /** Jaro-Winkler similarity of two strings, 0..1 (1 = identical). */
    public static function jaroWinkler(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        $la = strlen($a);
        $lb = strlen($b);
        $window = max(0, intdiv(max($la, $lb), 2) - 1);
        $matchedA = array_fill(0, $la, false);
        $matchedB = array_fill(0, $lb, false);
        $matches = 0;
        for ($i = 0; $i < $la; $i++) {
            $from = max(0, $i - $window);
            $to = min($lb - 1, $i + $window);
            for ($j = $from; $j <= $to; $j++) {
                if (! $matchedB[$j] && $a[$i] === $b[$j]) {
                    $matchedA[$i] = $matchedB[$j] = true;
                    $matches++;
                    break;
                }
            }
        }
        if ($matches === 0) {
            return 0.0;
        }
        $transpositions = 0;
        $k = 0;
        for ($i = 0; $i < $la; $i++) {
            if (! $matchedA[$i]) {
                continue;
            }
            while (! $matchedB[$k]) {
                $k++;
            }
            if ($a[$i] !== $b[$k]) {
                $transpositions++;
            }
            $k++;
        }
        $jaro = ($matches / $la + $matches / $lb + ($matches - $transpositions / 2) / $matches) / 3;
        $prefix = 0;
        while ($prefix < min(4, $la, $lb) && $a[$prefix] === $b[$prefix]) {
            $prefix++;
        }

        return $jaro + $prefix * 0.1 * (1 - $jaro);
    }
}
