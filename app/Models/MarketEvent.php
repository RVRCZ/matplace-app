<?php

namespace App\Models;

use App\Domain\Geo\Geocoder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A market, fair or convention to sell at (/tools/vendors, /admin/events). Table `market_events` (`events` is the traffic log). */
class MarketEvent extends Model
{
    public const TYPES = ['craft', 'christmas', 'farmers', 'maker', 'comic', 'fair', 'swap', 'design'];

    public const STATUSES = ['verified', 'verify', 'suggested'];

    public const COUNTRIES = ['CZ', 'SK'];

    protected $table = 'market_events';

    protected $fillable = ['name', 'type', 'city', 'address', 'country', 'lat', 'lng', 'starts_on', 'ends_on', 'url', 'stall_fee', 'note', 'status', 'source', 'suggested_by'];

    protected $casts = ['lat' => 'float', 'lng' => 'float', 'starts_on' => 'date', 'ends_on' => 'date'];

    public function saves(): HasMany
    {
        return $this->hasMany(EventSave::class, 'event_id');
    }

    /** What the public page shows: checked by the admin or waiting for the check, never a bare suggestion. */
    public function scopePublic(Builder $q): Builder
    {
        return $q->whereIn('status', ['verified', 'verify'])->whereNotNull('lat')->whereNotNull('lng');
    }

    /** Still ahead (or running), and starting within the days asked. */
    public function scopeUpcoming(Builder $q, int $days): Builder
    {
        $today = now()->startOfDay();

        return $q->where(fn ($w) => $w->where('ends_on', '>=', $today)->orWhere(fn ($x) => $x->whereNull('ends_on')->where('starts_on', '>=', $today)))
            ->where(fn ($w) => $w->whereNull('starts_on')->orWhere('starts_on', '<=', $today->copy()->addDays($days)));
    }

    public function distanceKmFrom(float $lat, float $lng): float
    {
        return Geocoder::distanceKm($lat, $lng, (float) $this->lat, (float) $this->lng);
    }

    /** The event as the page and the calendar take it. */
    public function toPayload(?float $lat = null, ?float $lng = null, bool $saved = false): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'type' => $this->type, 'city' => $this->city, 'address' => $this->address, 'country' => $this->country,
            'lat' => $this->lat, 'lng' => $this->lng, 'starts_on' => $this->starts_on?->toDateString(), 'ends_on' => $this->ends_on?->toDateString(),
            'url' => $this->url, 'stall_fee' => $this->stall_fee, 'note' => $this->note, 'status' => $this->status,
            'distance_km' => $lat !== null && $lng !== null && $this->lat !== null ? round($this->distanceKmFrom($lat, $lng), 1) : null,
            'saved' => $saved, 'ics' => route('tools.vendors.ics', $this->id),
        ];
    }

    /** One VEVENT of an iCalendar file. */
    public function toIcs(): string
    {
        $esc = fn (?string $s) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], (string) $s);
        $start = ($this->starts_on ?? now())->format('Ymd');
        $end = ($this->ends_on ?? $this->starts_on ?? now())->copy()->addDay()->format('Ymd');
        $lines = ['BEGIN:VEVENT', 'UID:event-'.$this->id.'@matplace.com', 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'), 'DTSTART;VALUE=DATE:'.$start, 'DTEND;VALUE=DATE:'.$end,
            'SUMMARY:'.$esc($this->name), 'LOCATION:'.$esc(trim($this->address.', '.$this->city, ', ')), 'DESCRIPTION:'.$esc(trim(($this->stall_fee ? $this->stall_fee.' · ' : '').(string) $this->note))];
        if ($this->url) {
            $lines[] = 'URL:'.$this->url;
        }
        if ($this->lat !== null) {
            $lines[] = 'GEO:'.$this->lat.';'.$this->lng;
        }
        $lines[] = 'END:VEVENT';

        return implode("\r\n", $lines);
    }

    /** A whole calendar file of events. */
    public static function calendar(iterable $events): string
    {
        $body = [];
        foreach ($events as $e) {
            $body[] = $e->toIcs();
        }

        return implode("\r\n", ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//matplace//vendors//CS', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', ...$body, 'END:VCALENDAR'])."\r\n";
    }
}
