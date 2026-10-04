<?php

namespace App\Models;

use App\Domain\Stats\Humans;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One line of our own statistics (App\Support\Track). Written once, never changed. */
class Event extends Model
{
    public const UPDATED_AT = null;

    public const VIEW = 'view';

    public const REF_VISIT = 'ref_visit';

    public const VISIT = 'visit';   // the first page of a browser session (App\Support\Track::visit)

    public const PAGE = 'page';     // a page a browser really showed: its script said so (Track::seen)

    public const CRAWL = 'crawl';   // a page a robot fetched; `source` holds the robot's family (Track::crawl)

    protected $fillable = ['session_id', 'user_id', 'locale', 'type', 'subject_type', 'subject_id', 'source', 'ref_slug', 'utm', 'meta'];

    protected $casts = ['utm' => 'array', 'meta' => 'array', 'created_at' => 'datetime'];

    /**
     * Visits of people (docs/O.md): not marked as a robot's or the staff's, and — since the day visits are confirmed
     * by the browser — confirmed by the page's script (`js`) or by something only a person does (`act`).
     * Older visits could not be confirmed; they count unless matplace:events-bots marked them.
     */
    public function scopePeople(Builder $query): Builder
    {
        $since = Humans::since();

        return $query->where('type', self::VISIT)->whereNull('meta->bot')->whereNull('meta->staff')
            ->when($since, fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('created_at', '<', $since)->orWhere('meta->js', true)->orWhere('meta->act', true)));
    }
}
