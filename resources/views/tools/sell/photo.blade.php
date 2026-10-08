@extends('tools.sell', ['tool' => 'photo', 'lead' => __('sell.photo.lead'), 'payload' => $payload, 'unavailable' => $unavailable,
    'sections' => ['photos' => __('sell.photo.sec.photos'), 'backdrop' => __('sell.photo.sec.backdrop'), 'export' => __('sell.photo.sec.export')]])

@section('panel')
<form id="sell-form" novalidate>
    <x-tool-section id="photos" :title="__('sell.photo.sec.photos')">
        <label id="photo-drop" class="block cursor-pointer rounded-xl border-2 border-dashed border-line bg-white p-4 text-center text-sm text-muted transition hover:border-ink">
            <input id="photo-input" type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple class="sr-only" @disabled(!$available)>
            <span class="block font-medium text-ink">{{ __('sell.photo.drop') }}</span>
            <span class="mt-1 block text-xs">{{ __('sell.photo.drop.hint', ['n' => $payload['max_photos'], 'mb' => $payload['max_mb']]) }}</span>
        </label>
        <p id="photo-status" class="hint !text-xs" aria-live="polite"></p>
        <p id="photo-msg" class="hint !text-xs" aria-live="polite"></p>
        <p class="hint !text-xs">{{ __('sell.photo.keep') }}</p>
    </x-tool-section>
    <x-tool-section id="backdrop" :title="__('sell.photo.sec.backdrop')">
        <fieldset>
            <legend class="lbl">{{ __('sell.photo.f.backdrop') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach($payload['backdrops'] as $b)<label class="tool-choice"><input type="radio" name="backdrop" value="{{ $b }}" class="sr-only" @checked($loop->first)>{{ __('sell.photo.bg.'.$b) }}</label>@endforeach
            </div>
        </fieldset>
        <fieldset>
            <legend class="lbl">{{ __('sell.photo.f.shadow') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach(['none', 'soft', 'strong'] as $s)<label class="tool-choice"><input type="radio" name="shadow" value="{{ $s }}" class="sr-only" @checked($s === 'soft')>{{ __('sell.photo.shadow.'.$s) }}</label>@endforeach
            </div>
        </fieldset>
        <label class="block text-sm font-medium text-ink">{{ __('sell.photo.f.fill') }} <span id="photo-fill-v" class="num font-normal text-muted">80 %</span><input id="photo-fill" name="fill" type="range" min="50" max="98" step="1" value="80" class="mt-1 w-full"></label>
        <fieldset>
            <legend class="lbl">{{ __('sell.photo.f.position') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach(['centre', 'bottom'] as $pos)<label class="tool-choice"><input type="radio" name="position" value="{{ $pos }}" class="sr-only" @checked($loop->first)>{{ __('sell.photo.pos.'.$pos) }}</label>@endforeach
            </div>
        </fieldset>
    </x-tool-section>
    <x-tool-section id="export" :title="__('sell.photo.sec.export')">
        <div class="grid grid-cols-2 gap-2">
            <fieldset>
                <legend class="lbl">{{ __('sell.photo.f.out_size') }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach([1000, 1500, 2000] as $px)<label class="tool-choice"><input type="radio" name="out_size" value="{{ $px }}" class="sr-only" @checked($px === 2000)>{{ $px }} px</label>@endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend class="lbl">{{ __('sell.photo.f.format') }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach(['jpg', 'png'] as $f)<label class="tool-choice"><input type="radio" name="format" value="{{ $f }}" class="sr-only" @checked($loop->first)>{{ __('sell.photo.format.'.$f) }}</label>@endforeach
                </div>
            </fieldset>
        </div>
        <button type="button" id="photo-download" class="btn-primary w-full gap-1.5" disabled><x-icon name="download" class="h-4 w-4" />{{ __('sell.photo.download') }}</button>
        <button type="button" id="photo-download-all" class="btn-secondary w-full text-sm" disabled>{{ __('sell.photo.download_all') }}</button>
    </x-tool-section>
</form>
@endsection

@section('stage')
<div class="card p-4">
    <div class="text-xs font-medium uppercase tracking-wide text-muted">{{ __('sell.photo.result') }}</div>
    <div id="photo-stage" class="mt-2 flex aspect-square items-center justify-center overflow-hidden rounded-xl border border-dashed border-line bg-white text-center text-sm text-muted">
        <span id="photo-empty" class="p-4">{{ __('sell.photo.empty') }}</span>
        <canvas id="photo-canvas" class="hidden h-full w-full" width="1000" height="1000"></canvas>
    </div>
    <ul id="photo-thumbs" class="mt-3 hidden grid-cols-6 gap-2"></ul>
</div>
@endsection
