<?php

namespace App\Support;

/**
 * Which of our three languages a text is most likely written in, without asking anybody: letters only one of
 * them uses, and its most common short words. Good enough to file thousands of old catalogue descriptions under
 * the right language; a text that gives no sign counts as English (what the sources mostly write).
 */
final class LanguageGuess
{
    private const CZECH_LETTERS = 'ěščřžýůťďň';

    private const WORDS = [
        'cs' => [' je ', ' na ', ' se ', ' pro ', ' nebo ', ' který ', ' která ', ' jako ', ' tisk ', ' bez ', ' model ', ' že ', ' při '],
        'es' => [' el ', ' la ', ' los ', ' las ', ' para ', ' con ', ' una ', ' del ', ' que ', ' por ', ' es ', ' sin ', ' este '],
        'en' => [' the ', ' and ', ' for ', ' with ', ' this ', ' you ', ' is ', ' of ', ' to ', ' it ', ' are ', ' can '],
    ];

    public static function of(string $text): string
    {
        $text = ' '.mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text))).' ';
        if (mb_strlen($text) < 4) {
            return 'en';
        }
        $score = ['cs' => 0.0, 'es' => 0.0, 'en' => 0.0];
        foreach (mb_str_split(self::CZECH_LETTERS) as $letter) {
            $score['cs'] += 2 * mb_substr_count($text, $letter);
        }
        $score['es'] += 3 * (mb_substr_count($text, 'ñ') + mb_substr_count($text, '¿') + mb_substr_count($text, '¡'));
        foreach (self::WORDS as $locale => $words) {
            foreach ($words as $word) {
                $score[$locale] += mb_substr_count($text, $word);
            }
        }
        arsort($score);
        $best = array_key_first($score);

        return $score[$best] > 0 ? $best : 'en';
    }
}
