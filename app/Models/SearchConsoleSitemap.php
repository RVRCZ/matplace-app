<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A sitemap the Search Console account lists, as it stood on one day (`searchconsole:inspect`, docs/AP.md §14). */
class SearchConsoleSitemap extends Model
{
    protected $table = 'search_console_sitemaps';

    protected $fillable = ['checked_on', 'path', 'is_index', 'is_pending', 'last_submitted_at', 'last_downloaded_at', 'warnings', 'errors', 'submitted', 'indexed'];

    protected $casts = ['checked_on' => 'date', 'is_index' => 'bool', 'is_pending' => 'bool', 'last_submitted_at' => 'datetime', 'last_downloaded_at' => 'datetime',
        'warnings' => 'int', 'errors' => 'int', 'submitted' => 'int', 'indexed' => 'int'];
}
