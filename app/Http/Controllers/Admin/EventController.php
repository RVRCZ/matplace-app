<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Geo\Geocoder;
use App\Http\Controllers\Controller;
use App\Models\MarketEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /admin/events: the markets and fairs of /tools/vendors. The seeded ones wait as `verify`, visitors' suggestions
 * as `suggested`; the admin checks the date, the place and the fee, fills in what is missing and marks them
 * `verified` (or deletes them). An event without coordinates is geocoded from its town when saved.
 */
class EventController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $events = MarketEvent::query()->when(in_array($status, MarketEvent::STATUSES, true), fn ($q) => $q->where('status', $status))->orderByRaw("case status when 'suggested' then 0 when 'verify' then 1 else 2 end")->orderBy('starts_on')->get();
        $counts = MarketEvent::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();

        return view('admin.events.index', ['events' => $events, 'counts' => $counts, 'status' => $status]);
    }

    public function create(): View
    {
        return view('admin.events.edit', ['event' => new MarketEvent(['country' => 'CZ', 'type' => 'craft', 'status' => 'verified'])]);
    }

    public function edit(int $event): View
    {
        return view('admin.events.edit', ['event' => MarketEvent::findOrFail($event)]);
    }

    public function store(Request $request, Geocoder $geocoder): RedirectResponse
    {
        $event = new MarketEvent;
        $this->fill($event, $request, $geocoder);

        return redirect()->route('admin.events.index')->with('status', 'Akce založena.');
    }

    public function update(Request $request, int $event, Geocoder $geocoder): RedirectResponse
    {
        $this->fill(MarketEvent::findOrFail($event), $request, $geocoder);

        return redirect()->route('admin.events.index')->with('status', 'Akce uložena.');
    }

    /** POST /admin/events/{event}/verify: checked, shown as verified */
    public function verify(int $event, Geocoder $geocoder): RedirectResponse
    {
        $e = MarketEvent::findOrFail($event);
        if ($e->lat === null && ($place = $geocoder->resolve(null, $e->city, $e->country))) {
            $e->lat = $place['lat'];
            $e->lng = $place['lng'];
        }
        $e->status = 'verified';
        $e->save();

        return back()->with('status', 'Akce „'.$e->name.'“ je ověřená.');
    }

    public function destroy(int $event): RedirectResponse
    {
        MarketEvent::where('id', $event)->delete();

        return redirect()->route('admin.events.index')->with('status', 'Akce smazána.');
    }

    private function fill(MarketEvent $event, Request $request, Geocoder $geocoder): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'], 'type' => ['required', 'in:'.implode(',', MarketEvent::TYPES)], 'city' => ['required', 'string', 'min:2', 'max:80'], 'address' => ['nullable', 'string', 'max:160'],
            'country' => ['required', 'in:'.implode(',', MarketEvent::COUNTRIES)], 'lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'url' => ['nullable', 'url', 'max:300'], 'stall_fee' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'], 'status' => ['required', 'in:'.implode(',', MarketEvent::STATUSES)], 'source' => ['nullable', 'string', 'max:300'],
        ]);
        $event->fill($data);
        if (($data['lat'] ?? null) === null || ($data['lng'] ?? null) === null) {
            $place = $geocoder->resolve(null, $data['city'], $data['country']);
            $event->lat = $place['lat'] ?? null;
            $event->lng = $place['lng'] ?? null;
        }
        $event->save();
    }
}
