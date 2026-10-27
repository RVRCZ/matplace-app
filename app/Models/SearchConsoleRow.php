<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One day of one page in Search Console (docs/AN.md §14), for one country, with or without the query: rows without a
 * query are the page's own totals (Search Console leaves the anonymised queries out of the per-query rows, so the two
 * kinds never add up to each other). Written by `searchconsole:pull`, read by App\Domain\Stats\SearchConsole.
 */
class SearchConsoleRow extends Model
{
    protected $table = 'search_console_rows';

    protected $fillable = ['row_key', 'date', 'page', 'query', 'country', 'clicks', 'impressions', 'ctr', 'position'];

    protected $casts = ['date' => 'date', 'clicks' => 'int', 'impressions' => 'int', 'ctr' => 'float', 'position' => 'float'];

    public static function keyFor(string $date, string $page, ?string $query, ?string $country): string
    {
        return sha1($date.'|'.$page.'|'.($query ?? '').'|'.($country ?? ''));
    }
}
