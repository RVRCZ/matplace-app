@extends('layouts.app', ['title' => ($event->exists ? $event->name : 'Nová akce').' · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<p class="mt-2 text-sm"><a href="{{ route('admin.events.index') }}" class="underline">← Akce</a></p>

<form method="post" action="{{ $event->exists ? route('admin.events.update', $event->id) : route('admin.events.store') }}" class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
    @if($event->exists)@method('put')@endif
    <div class="grid gap-3 sm:grid-cols-2">
        <label class="lbl sm:col-span-2">Název<input name="name" required minlength="3" maxlength="160" value="{{ old('name', $event->name) }}" class="field"></label>
        <label class="lbl">Typ<select name="type" class="field">@foreach(\App\Models\MarketEvent::TYPES as $t)<option value="{{ $t }}" @selected(old('type', $event->type) === $t)>{{ __('sell.vendors.type.'.$t) }}</option>@endforeach</select></label>
        <label class="lbl">Stav<select name="status" class="field">@foreach(['verified' => 'ověřená', 'verify' => 'k ověření', 'suggested' => 'navržená'] as $s => $label)<option value="{{ $s }}" @selected(old('status', $event->status) === $s)>{{ $label }}</option>@endforeach</select></label>
        <label class="lbl">Město<input name="city" required minlength="2" maxlength="80" value="{{ old('city', $event->city) }}" class="field"></label>
        <label class="lbl">Země<select name="country" class="field">@foreach(\App\Models\MarketEvent::COUNTRIES as $c)<option value="{{ $c }}" @selected(old('country', $event->country) === $c)>{{ $c }}</option>@endforeach</select></label>
        <label class="lbl sm:col-span-2">Adresa / místo<input name="address" maxlength="160" value="{{ old('address', $event->address) }}" class="field"></label>
        <label class="lbl">Zem. šířka<input name="lat" type="number" step="0.000001" min="-90" max="90" value="{{ old('lat', $event->lat) }}" class="field"><span class="block text-xs font-normal text-slate-500">Prázdné = doplní se z města (Nominatim).</span></label>
        <label class="lbl">Zem. délka<input name="lng" type="number" step="0.000001" min="-180" max="180" value="{{ old('lng', $event->lng) }}" class="field"></label>
        <label class="lbl">Od<input name="starts_on" type="date" value="{{ old('starts_on', $event->starts_on?->toDateString()) }}" class="field"></label>
        <label class="lbl">Do<input name="ends_on" type="date" value="{{ old('ends_on', $event->ends_on?->toDateString()) }}" class="field"></label>
        <label class="lbl sm:col-span-2">Web<input name="url" type="url" maxlength="300" value="{{ old('url', $event->url) }}" class="field"></label>
        <label class="lbl">Poplatek za stánek<input name="stall_fee" maxlength="120" value="{{ old('stall_fee', $event->stall_fee) }}" class="field" placeholder="1 500 Kč / den"></label>
        <label class="lbl">Zdroj<input name="source" maxlength="300" value="{{ old('source', $event->source) }}" class="field"></label>
        <label class="lbl sm:col-span-2">Poznámka<textarea name="note" rows="3" maxlength="1000" class="field">{{ old('note', $event->note) }}</textarea></label>
    </div>
    @if($event->suggested_by)<p class="mt-2 text-xs text-slate-500">Navrhl: {{ $event->suggested_by }}</p>@endif
    <div class="mt-4 flex flex-wrap gap-2">
        <button class="btn-primary text-sm">Uložit</button>
        @if($event->exists && $event->status !== 'verified')<button formaction="{{ route('admin.events.verify', $event->id) }}" formmethod="post" name="_method" value="POST" class="btn-secondary text-sm">Uložit a ověřit</button>@endif
    </div>
</form>
@endsection
