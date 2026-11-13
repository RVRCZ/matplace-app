<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What Google said of one address on one day (`searchconsole:inspect`, docs/AP.md §14). */
class SearchConsoleInspection extends Model
{
    public const INDEXED = 'indexed';        // in the index

    public const DISCOVERED = 'discovered';  // Google knows the address and has not fetched it yet

    public const CRAWLED = 'crawled';        // fetched and not indexed

    public const UNKNOWN = 'unknown';        // Google does not know the address

    public const EXCLUDED = 'excluded';      // a redirect, a noindex, a duplicate, a 404 …

    public const ERROR = 'error';            // the question itself failed

    /** In the order a summary prints them. Each fits the column `state` (varchar 20). */
    public const STATES = [self::INDEXED, self::DISCOVERED, self::CRAWLED, self::UNKNOWN, self::EXCLUDED, self::ERROR];

    protected $table = 'search_console_inspections';

    protected $fillable = ['url_key', 'checked_on', 'url', 'state', 'verdict', 'coverage', 'robots_state', 'indexing_state', 'fetch_state',
        'google_canonical', 'user_canonical', 'last_crawl_at', 'important', 'error'];

    protected $casts = ['checked_on' => 'date', 'last_crawl_at' => 'datetime', 'important' => 'bool'];

    public static function keyOf(string $url): string
    {
        return sha1($url);
    }

    /**
     * Our short word for Google's answer (`indexStatusResult` of the URL Inspection API, asked in English).
     *
     * @param  array<string, mixed>  $status
     */
    public static function stateOf(array $status): string
    {
        $coverage = strtolower((string) ($status['coverageState'] ?? ''));
        if (($status['verdict'] ?? '') === 'PASS' || str_contains($coverage, 'submitted and indexed') || str_starts_with($coverage, 'indexed')) {
            return self::INDEXED;
        }
        if (str_contains($coverage, 'unknown to google')) {
            return self::UNKNOWN;
        }
        if (str_starts_with($coverage, 'discovered')) {
            return self::DISCOVERED;
        }
        if (str_starts_with($coverage, 'crawled')) {
            return self::CRAWLED;
        }

        return self::EXCLUDED;
    }
}
