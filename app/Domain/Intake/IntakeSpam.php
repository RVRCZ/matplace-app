<?php

namespace App\Domain\Intake;

use Illuminate\Http\Request;

/**
 * Advertising kept away from the paid model (docs/AN.md §23.2): the first screen's field draws robots that fill every
 * form they find. Before anything is asked of the assistant, a wish goes through rules that cost nothing: the unseen
 * field, the time since the page was made, and a few rules over the words (links and markup, phrases only an
 * advertisement says, a script or a language the page does not speak). All of it is set in config/home.php
 * `intake.spam`. What is held gets the status `spam`, the rules that held it stay in `answer.spam`, and the admin can
 * give it back.
 *
 * A question about the price that names nothing to make is not advertising, but the model has nothing to decide
 * either: it is held under the rule `price_only` and the visitor gets the honest answer about prices instead.
 */
final class IntakeSpam
{
    /** The hidden field carrying the stamp of the page. */
    public const STAMP_FIELD = 'started';

    public const RULE_HONEYPOT = 'honeypot';

    public const RULE_FAST = 'too_fast';

    public const RULE_NO_STAMP = 'no_stamp';

    public const RULE_PRICE = 'price_only';

    public const RULE_MANUAL = 'manual';

    /** What the admin reads next to a held wish. */
    public const LABELS = [
        'honeypot' => 'vyplněné neviditelné pole', 'too_fast' => 'odesláno dřív, než to stihne člověk', 'no_stamp' => 'odesláno bez stránky (holý POST)',
        'markup' => 'HTML nebo odkaz v závorkách', 'links' => 'více odkazů', 'link' => 'odkaz', 'strong' => 'reklamní fráze', 'weak' => 'slova reklam',
        'script' => 'cizí písmo bez fotky', 'language' => 'neznámý jazyk bez fotky', 'long' => 'dlouhý text bez fotky', 'price_only' => 'jen dotaz na cenu',
        'manual' => 'označil správce',
    ];

    /** @return array<string, mixed> */
    private static function cfg(): array
    {
        return (array) config('home.intake.spam');
    }

    public static function honeypot(): string
    {
        return (string) (self::cfg()['honeypot'] ?? 'topic');
    }

    /** The stamp the page carries: when it was made, signed so that nobody types a time of their own. */
    public static function stamp(?int $at = null): string
    {
        $at ??= now()->timestamp;

        return $at.'.'.self::sign($at);
    }

    private static function sign(int $at): string
    {
        return substr(hash_hmac('sha256', 'intake:'.$at, (string) config('app.key')), 0, 20);
    }

    /** Seconds since the page with this stamp was made; null for a stamp that is missing or not ours. */
    public static function age(?string $stamp): ?int
    {
        if (! is_string($stamp) || ! preg_match('/^(\d{9,11})\.([0-9a-f]{20})$/', $stamp, $m) || ! hash_equals(self::sign((int) $m[1]), $m[2])) {
            return null;
        }

        return now()->timestamp - (int) $m[1];
    }

    /**
     * The verdict on what a visitor sent: null lets it through; otherwise the rules that held it, their points and
     * which sentence the visitor gets (`held` = the neutral one, `price` = the answer about prices). A 3D file is not
     * read for words: its text is a note for the calculator, no model is asked.
     *
     * @return array{rules: list<string>, points: int, reply: string}|null
     */
    public static function check(Request $request, string $text, bool $hasPhoto, bool $hasModel): ?array
    {
        $cfg = self::cfg();
        if (empty($cfg['enabled'])) {
            return null;
        }
        if (trim((string) $request->input(self::honeypot())) !== '') {
            return ['rules' => [self::RULE_HONEYPOT], 'points' => 99, 'reply' => 'held'];
        }
        $age = self::age($request->input(self::STAMP_FIELD));
        if ($age === null && ! empty($cfg['require_stamp'])) {
            return ['rules' => [self::RULE_NO_STAMP], 'points' => 99, 'reply' => 'held'];
        }
        if ($age !== null && $age < (int) ($cfg['min_seconds'] ?? 0)) {
            return ['rules' => [self::RULE_FAST], 'points' => 99, 'reply' => 'held'];
        }
        if ($hasModel || $text === '') {
            return null;
        }
        $score = self::score($text, $hasPhoto);
        if ($score['points'] >= (int) ($cfg['threshold'] ?? 3)) {
            return $score + ['reply' => 'held'];
        }
        if (! $hasPhoto && self::priceOnly($text)) {
            return ['rules' => [self::RULE_PRICE], 'points' => 0, 'reply' => 'price'];
        }

        return null;
    }

    /**
     * The points of the rules over the words, and which rules gave them.
     *
     * @return array{rules: list<string>, points: int}
     */
    public static function score(string $text, bool $hasPhoto): array
    {
        $cfg = self::cfg();
        $pts = (array) ($cfg['points'] ?? []);
        $t = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($text)));
        $rules = [];
        $points = 0;
        $add = function (string $rule, ?int $n = null) use (&$rules, &$points, $pts): void {
            $rules[] = $rule;
            $points += $n ?? (int) ($pts[$rule] ?? 0);
        };

        if (preg_match('~<\s*/?\s*[a-z][^>]*>|\[/?(url|link|img)\b|href\s*=~iu', $t)) {
            $add('markup');
        }
        $hosts = self::hosts($t);
        $foreign = array_filter($hosts, fn (string $h) => ! self::allowedHost($h));
        if (count($hosts) >= 2 && $foreign) {
            $add('links');
        } elseif ($foreign) {
            $add('link');
        }
        if (self::phrases($t, (array) ($cfg['strong'] ?? []))) {
            $add('strong');
        }
        if ($weak = self::phrases($t, (array) ($cfg['weak'] ?? []))) {
            $add('weak', min(2, $weak) * (int) ($pts['weak'] ?? 1));
        }
        if (! $hasPhoto) {
            if (self::foreignScript($t)) {
                $add('script');
            } elseif (self::unknownLanguage($t)) {
                $add('language');
            }
            if (mb_strlen($t) > (int) ($cfg['long_chars'] ?? 600)) {
                $add('long');
            }
        }

        return ['rules' => $rules, 'points' => $points];
    }

    /**
     * The hosts of the addresses in a text: http(s)://…, www.…, and a bare name with a common ending (shop.example.com).
     *
     * @return list<string>
     */
    public static function hosts(string $t): array
    {
        preg_match_all('~(?:https?://|www\.)([a-z0-9][a-z0-9.\-]*\.[a-z]{2,})|(?<![\p{L}\d@.])([a-z0-9][a-z0-9\-]*(?:\.[a-z0-9\-]+)*\.(?:com|net|org|info|biz|ru|cn|xyz|io|eu|cz|sk|pl|uk))(?![\p{L}\d])~iu', $t, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $hit) {
            $out[] = strtolower((string) preg_replace('/^www\./', '', ($hit[1] ?? '') !== '' ? $hit[1] : ($hit[2] ?? '')));
        }

        return array_values(array_unique(array_filter($out)));
    }

    private static function allowedHost(string $host): bool
    {
        foreach ((array) (self::cfg()['link_hosts'] ?? []) as $ok) {
            if ($host === $ok || str_ends_with($host, '.'.$ok)) {
                return true;
            }
        }

        return false;
    }

    /**
     * How many of the phrases a text says, as whole words.
     *
     * @param  list<string>  $phrases
     */
    private static function phrases(string $t, array $phrases): int
    {
        $n = 0;
        foreach ($phrases as $phrase) {
            $p = preg_quote(mb_strtolower(trim((string) $phrase)), '~');
            if ($p !== '' && preg_match('~(?<![\p{L}\d])'.$p.'(?![\p{L}\d])~u', $t)) {
                $n++;
            }
        }

        return $n;
    }

    /** Most letters are of a script the page's languages do not use (Arabic, Cyrillic, Chinese…). */
    private static function foreignScript(string $t): bool
    {
        $letters = preg_match_all('/\p{L}/u', $t);
        if ($letters < 4) {
            return false;
        }
        $own = 0;
        foreach ((array) (self::cfg()['scripts'] ?? ['Latin']) as $script) {
            if (preg_match('/^[A-Za-z_]+$/', (string) $script)) {
                $own += preg_match_all('/\p{'.$script.'}/u', $t);
            }
        }

        return ($letters - $own) / $letters > (float) (self::cfg()['script_share'] ?? 0.5);
    }

    /**
     * Four words and more, no number, and not one word of a language we answer in (the small words of
     * config/home.php and the words the router itself knows): "Hæ, ég vildi vita verð þitt."
     */
    private static function unknownLanguage(string $t): bool
    {
        preg_match_all('/\p{L}+/u', $t, $m);
        $words = $m[0];
        if (count($words) < (int) (self::cfg()['language_min_words'] ?? 4) || preg_match('/\d/', $t)) {
            return false;
        }
        if (array_intersect($words, (array) (self::cfg()['known_words'] ?? []))) {
            return false;
        }

        return ! IntakeRouter::knows($t);
    }

    /** A short question about the price that names nothing to make. */
    public static function priceOnly(string $text): bool
    {
        $cfg = self::cfg();
        $t = mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($text)));
        preg_match_all('/\p{L}+/u', $t, $m);
        if ($t === '' || count($m[0]) > (int) ($cfg['price_max_words'] ?? 12) || preg_match('/\d/', $t)) {
            return false;
        }

        return self::phrases($t, (array) ($cfg['price_words'] ?? [])) > 0 && ! IntakeRouter::knows($t);
    }
}
