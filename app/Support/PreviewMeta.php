<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * What a preview says about its model (size, volume, notes, pieces) travels in the header X-Model-Meta beside the STL.
 * A web server hands all the headers of a response one small buffer (nginx: 4 kB unless told otherwise) and answers
 * 502 when they do not fit; the guide of a layered picture alone is 9 kB of outlines. So the header is kept small:
 * when the whole would not fit, the biggest notes wait in the cache and the header only says where (`more`). The page
 * fetches them with one more request (GET /api/tools/preview/{key}/meta) and puts them back where they belong.
 */
final class PreviewMeta
{
    /** The most a header value may weigh, in bytes: well under 4 kB, the other headers of the response need room too. */
    public const LIMIT = 3000;

    /** How long the rest of a preview waits for its page, in seconds. */
    private const KEEP = 900;

    /** The value of X-Model-Meta: all of the meta when it is small, else what fits and the key of the rest. */
    public static function header(array $meta): string
    {
        $json = (string) json_encode($meta);
        if (strlen($json) <= self::LIMIT) {
            return $json;
        }
        $key = Str::random(32);
        $rest = [];
        // what weighs most goes first (the guide, a long outline, a long list of pieces), until what is left fits
        $weights = array_map(fn ($value) => strlen((string) json_encode($value)), (array) ($meta['notes'] ?? []));
        $weights = array_combine(array_map(fn ($note) => 'notes.'.$note, array_keys($weights)), $weights);
        if (isset($meta['parts'])) {
            $weights['parts'] = strlen((string) json_encode($meta['parts']));
        }
        arsort($weights);
        $meta['more'] = $key;
        foreach (array_keys($weights) as $heavy) {
            if (strlen((string) json_encode($meta)) <= self::LIMIT) {
                break;
            }
            if ($heavy === 'parts') {
                $rest['parts'] = $meta['parts'];
                unset($meta['parts']);
            } else {
                $note = substr($heavy, 6);
                $rest['notes'][$note] = $meta['notes'][$note];
                unset($meta['notes'][$note]);
            }
        }
        Cache::put('preview_meta:'.$key, $rest, self::KEEP);

        return (string) json_encode($meta);
    }

    /** @return array{notes?: array<string, mixed>, parts?: list<mixed>}|null what did not fit the header; null when the key is unknown or too old */
    public static function rest(string $key): ?array
    {
        $rest = preg_match('/^[A-Za-z0-9]{32}$/', $key) ? Cache::get('preview_meta:'.$key) : null;

        return is_array($rest) ? $rest : null;
    }

    /** The whole meta again from a header value (what the page does; tests read a preview the same way). */
    public static function whole(?string $header): ?array
    {
        $meta = json_decode((string) $header, true);
        if (! is_array($meta) || ! isset($meta['more'])) {
            return is_array($meta) ? $meta : null;
        }
        $rest = self::rest((string) $meta['more']) ?? [];
        unset($meta['more']);
        $meta['notes'] = (array) ($rest['notes'] ?? []) + (array) ($meta['notes'] ?? []);

        return $meta + (isset($rest['parts']) ? ['parts' => $rest['parts']] : []);
    }
}
