@extends('tools.sell', ['tool' => 'listing', 'lead' => __('sell.listing.lead'), 'payload' => $payload, 'unavailable' => $unavailable,
    'sections' => ['product' => __('sell.listing.sec.product'), 'where' => __('sell.listing.sec.where'), 'write' => __('sell.listing.sec.write')]])

@section('panel')
<form id="sell-form" novalidate enctype="multipart/form-data">
    <x-tool-section id="product" :title="__('sell.listing.sec.product')">
        @if($from)<p class="hint !text-xs">{{ __('sell.listing.from_model') }} <a href="{{ $from['url'] }}" class="underline">{{ $from['name'] }}</a></p>@endif
        <label class="block text-sm font-medium text-ink">{{ __('sell.listing.f.what') }}<textarea id="listing-what" name="what" rows="4" maxlength="400" class="field" placeholder="{{ __('sell.listing.f.what.placeholder') }}" required>{{ $from['what'] ?? '' }}</textarea></label>
        <fieldset>
            <legend class="lbl">{{ __('sell.listing.f.materials') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach($payload['materials'] as $m)<label class="tool-choice"><input type="checkbox" name="materials" value="{{ $m }}" class="sr-only" @checked($m === 'pla')>{{ __('sell.listing.material.'.$m) }}</label>@endforeach
            </div>
        </fieldset>
        <div class="grid grid-cols-2 gap-2">
            <label class="block text-sm font-medium text-ink">{{ __('sell.listing.f.size') }}<input name="size" type="text" maxlength="80" class="field" placeholder="{{ __('sell.listing.f.size.placeholder') }}"></label>
            <label class="block text-sm font-medium text-ink">{{ __('sell.listing.f.colours') }}<input name="colours" type="text" maxlength="120" class="field" placeholder="{{ __('sell.listing.f.colours.placeholder') }}"></label>
        </div>
        <label class="block text-sm font-medium text-ink">{{ __('sell.listing.f.audience') }}<input name="audience" type="text" maxlength="160" class="field" placeholder="{{ __('sell.listing.f.audience.placeholder') }}"></label>
        <label class="block text-sm font-medium text-ink">{{ __('sell.listing.f.photo') }}<input id="listing-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="field !py-1.5 text-sm"><span class="block text-xs font-normal text-muted">{{ __('sell.listing.f.photo.hint') }}</span></label>
    </x-tool-section>
    <x-tool-section id="where" :title="__('sell.listing.sec.where')">
        <fieldset>
            <legend class="lbl">{{ __('sell.listing.f.platform') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach($payload['platforms'] as $p)<label class="tool-choice"><input type="radio" name="platform" value="{{ $p }}" class="sr-only" @checked($loop->first)>{{ __('sell.platform.'.$p) }}</label>@endforeach
            </div>
        </fieldset>
        <div class="grid grid-cols-2 gap-2">
            <fieldset>
                <legend class="lbl">{{ __('sell.listing.f.language') }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach($payload['languages'] as $l)<label class="tool-choice"><input type="radio" name="language" value="{{ $l }}" class="sr-only" @checked($l === app()->getLocale())>{{ __('sell.listing.lang.'.$l) }}</label>@endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend class="lbl">{{ __('sell.listing.f.tone') }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach($payload['tones'] as $tone)<label class="tool-choice"><input type="radio" name="tone" value="{{ $tone }}" class="sr-only" @checked($loop->first)>{{ __('sell.listing.tone.'.$tone) }}</label>@endforeach
                </div>
            </fieldset>
        </div>
    </x-tool-section>
    <x-tool-section id="write" :title="__('sell.listing.sec.write')">
        <button type="submit" id="listing-go" class="btn-primary w-full gap-1.5" @disabled(!$available)><x-icon name="sparkles" class="h-4 w-4" />{{ __('sell.listing.go') }}</button>
        <p id="listing-left" class="hint !text-xs" aria-live="polite"></p>
        <p id="listing-msg" class="hint !text-xs" aria-live="polite"></p>
        <p class="hint !text-xs">{{ __('sell.listing.limit.note', ['n' => (int) config('ai.daily_limits.listing', 5)]) }}</p>
    </x-tool-section>
</form>
@endsection

@section('stage')
<div class="card p-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <div class="text-xs font-medium uppercase tracking-wide text-muted">{{ __('sell.listing.result') }}</div>
        <button type="button" id="listing-copy-all" class="hidden text-sm underline">{{ __('sell.listing.copy_all') }}</button>
    </div>
    <div id="listing-empty" class="mt-2 text-sm text-muted">{{ __('sell.listing.empty') }}</div>
    <div id="listing-result" class="mt-2 hidden space-y-4 text-sm"></div>
</div>
@endsection
