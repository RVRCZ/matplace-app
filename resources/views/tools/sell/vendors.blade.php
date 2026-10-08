@extends('tools.sell', ['tool' => 'vendors', 'lead' => __('sell.vendors.lead'), 'payload' => $payload,
    'sections' => ['where' => __('sell.vendors.sec.where'), 'fit' => __('sell.vendors.sec.fit'), 'suggest' => __('sell.vendors.sec.suggest'), 'channels' => __('sell.vendors.sec.channels')]])

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="" defer></script>
@endpush

@section('panel')
@include('partials.flash')
<form id="sell-form" novalidate>
    <x-tool-section id="where" :title="__('sell.vendors.sec.where')">
        <div class="grid grid-cols-[1fr_auto] gap-2">
            <label class="block text-sm font-medium text-ink">{{ __('sell.vendors.f.city') }}<input id="vendors-city" type="text" maxlength="80" class="field" placeholder="{{ __('sell.vendors.f.city.placeholder') }}" autocomplete="address-level2"></label>
            <label class="block text-sm font-medium text-ink">{{ __('sell.vendors.f.country') }}<select id="vendors-country" class="field">@foreach(\App\Models\MarketEvent::COUNTRIES as $c)<option value="{{ $c }}">{{ __('sell.vendors.country.'.$c) }}</option>@endforeach</select></label>
        </div>
        <fieldset>
            <legend class="lbl">{{ __('sell.vendors.f.radius') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach($payload['radii'] as $r)<label class="tool-choice"><input type="radio" name="radius" value="{{ $r }}" class="sr-only" @checked($r === 50)>{{ $r }} km</label>@endforeach
            </div>
        </fieldset>
        <fieldset>
            <legend class="lbl">{{ __('sell.vendors.f.days') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach($payload['periods'] as $d)<label class="tool-choice"><input type="radio" name="days" value="{{ $d }}" class="sr-only" @checked($d === 90)>{{ __('sell.vendors.days.'.$d) }}</label>@endforeach
            </div>
        </fieldset>
        <fieldset>
            <legend class="lbl">{{ __('sell.vendors.f.types') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach($payload['types'] as $t)<label class="tool-choice"><input type="checkbox" name="types" value="{{ $t }}" class="sr-only" checked>{{ __('sell.vendors.type.'.$t) }}</label>@endforeach
            </div>
        </fieldset>
        <button type="submit" id="vendors-go" class="btn-primary w-full gap-1.5"><x-icon name="search" class="h-4 w-4" />{{ __('sell.vendors.go') }}</button>
        <p id="vendors-msg" class="hint !text-xs" aria-live="polite"></p>
    </x-tool-section>
    <x-tool-section id="fit" :title="__('sell.vendors.sec.fit')">
        <label class="block text-sm font-medium text-ink">{{ __('sell.vendors.f.make') }}<textarea id="vendors-make" rows="3" maxlength="400" class="field" placeholder="{{ __('sell.vendors.f.make.placeholder') }}"></textarea></label>
        <button type="button" id="vendors-fit" class="btn-secondary w-full text-sm" disabled>{{ __('sell.vendors.fit.go') }}</button>
        <p class="hint !text-xs">{{ __('sell.vendors.fit.hint', ['n' => (int) config('ai.daily_limits.vendors_fit', 10)]) }}</p>
        <p id="vendors-fit-msg" class="hint !text-xs" aria-live="polite"></p>
    </x-tool-section>
</form>
<x-tool-section id="suggest" :title="__('sell.vendors.sec.suggest')">
    <p class="hint !text-xs">{{ __('sell.vendors.suggest.hint') }}</p>
    <form method="post" action="{{ route('tools.vendors.suggest') }}" class="grid gap-2 text-sm">@csrf
        <input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
        <label class="lbl">{{ __('sell.vendors.s.name') }}<input name="name" required minlength="3" maxlength="160" value="{{ old('name') }}" class="field"></label>
        <div class="grid grid-cols-2 gap-2">
            <label class="lbl">{{ __('sell.vendors.s.type') }}<select name="type" class="field">@foreach(\App\Models\MarketEvent::TYPES as $t)<option value="{{ $t }}" @selected(old('type') === $t)>{{ __('sell.vendors.type.'.$t) }}</option>@endforeach</select></label>
            <label class="lbl">{{ __('sell.vendors.f.country') }}<select name="country" class="field">@foreach(\App\Models\MarketEvent::COUNTRIES as $c)<option value="{{ $c }}" @selected(old('country') === $c)>{{ __('sell.vendors.country.'.$c) }}</option>@endforeach</select></label>
        </div>
        <label class="lbl">{{ __('sell.vendors.f.city') }}<input name="city" required minlength="2" maxlength="80" value="{{ old('city') }}" class="field"></label>
        <div class="grid grid-cols-2 gap-2">
            <label class="lbl">{{ __('sell.vendors.s.from') }}<input name="starts_on" type="date" value="{{ old('starts_on') }}" class="field"></label>
            <label class="lbl">{{ __('sell.vendors.s.to') }}<input name="ends_on" type="date" value="{{ old('ends_on') }}" class="field"></label>
        </div>
        <label class="lbl">{{ __('sell.vendors.s.url') }}<input name="url" type="url" maxlength="300" value="{{ old('url') }}" class="field" placeholder="https://"></label>
        <label class="lbl">{{ __('sell.vendors.s.fee') }}<input name="stall_fee" maxlength="120" value="{{ old('stall_fee') }}" class="field" placeholder="{{ __('sell.vendors.s.fee.placeholder') }}"></label>
        <label class="lbl">{{ __('sell.vendors.s.note') }}<textarea name="note" rows="2" maxlength="1000" class="field">{{ old('note') }}</textarea></label>
        <label class="lbl">{{ __('sell.vendors.s.email') }}<input name="email" type="email" maxlength="160" value="{{ old('email') }}" class="field"><span class="block text-xs font-normal text-muted">{{ __('sell.vendors.s.email.hint') }}</span></label>
        <button class="btn-secondary w-full text-sm">{{ __('sell.vendors.suggest.go') }}</button>
    </form>
</x-tool-section>
<x-tool-section id="channels" :title="__('sell.vendors.sec.channels')">
    <p class="hint !text-xs">{{ __('sell.vendors.channels.hint') }}</p>
    <table class="w-full text-sm">
        <tbody class="divide-y divide-line">
            @foreach($channels as $ch)
                <tr>
                    <td class="py-1.5 pr-2 align-top">@if($ch['url'])<a href="{{ $ch['url'] }}" rel="noopener" target="_blank" class="font-medium text-ink underline">{{ __('sell.channel.'.$ch['key']) }}</a>@else<span class="font-medium text-ink">{{ __('sell.channel.'.$ch['key']) }}</span>@endif<span class="block text-xs text-muted">{{ __('sell.channel.'.$ch['key'].'.hint') }}</span></td>
                    <td class="num py-1.5 text-right align-top text-ink">{{ $ch['fee'] }}<span class="block text-xs text-muted">{{ __('sell.as_of', ['date' => \Illuminate\Support\Carbon::parse($ch['as_of'])->isoFormat('L')]) }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-tool-section>
@endsection

@section('stage')
<div class="card overflow-hidden">
    <div id="vendors-map" class="h-[380px] w-full bg-page" role="img" aria-label="{{ __('sell.vendors.map') }}"></div>
    <p class="px-4 py-2 text-xs text-muted">{{ __('sell.vendors.map.note') }}</p>
</div>
<div class="card p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="font-semibold text-ink" id="vendors-title">{{ __('sell.vendors.list.empty') }}</div>
        <div class="flex gap-1.5" role="group">
            <button type="button" class="chip chip-on !py-1 text-sm" data-tab="found" aria-pressed="true">{{ __('sell.vendors.tab.found') }}</button>
            <button type="button" class="chip !py-1 text-sm" data-tab="saved" aria-pressed="false">{{ __('sell.vendors.tab.saved') }} <span id="vendors-saved-count" class="num">{{ count($payload['saved']) }}</span></button>
        </div>
    </div>
    <div id="vendors-picks" class="mt-3 hidden rounded-lg bg-page p-3 text-sm"></div>
    <ul id="vendors-list" class="mt-3 divide-y divide-line text-sm"></ul>
    @if($payload['saved_ics'])
        <p class="mt-3 text-xs text-muted"><a href="{{ $payload['saved_ics'] }}" class="underline">{{ __('sell.vendors.saved.ics') }}</a></p>
    @else
        <p class="mt-3 text-xs text-muted">{{ __('sell.vendors.saved.login') }}</p>
    @endif
</div>
@endsection
