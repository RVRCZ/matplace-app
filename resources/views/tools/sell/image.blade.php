@extends('tools.sell', ['tool' => 'image', 'lead' => __('sell.image.lead'), 'payload' => $payload, 'unavailable' => $unavailable,
    'sections' => ['describe' => __('sell.image.sec.describe'), 'style' => __('sell.image.sec.style'), 'make' => __('sell.image.sec.make')]])

@section('panel')
<form id="sell-form" novalidate>
    <x-tool-section id="describe" :title="__('sell.image.sec.describe')">
        <label class="block text-sm font-medium text-ink">{{ __('sell.image.f.prompt') }}<textarea id="image-prompt" rows="3" maxlength="300" class="field" placeholder="{{ __('sell.image.f.prompt.placeholder') }}" required></textarea></label>
        <p class="hint !text-xs">{{ __('sell.image.f.prompt.hint') }}</p>
    </x-tool-section>
    <x-tool-section id="style" :title="__('sell.image.sec.style')">
        <fieldset>
            <legend class="lbl">{{ __('sell.image.f.style') }}</legend>
            <div class="mt-2 grid gap-1.5" role="radiogroup">
                @foreach($payload['styles'] as $s)
                    <label class="tool-choice flex items-baseline gap-2"><input type="radio" name="style" value="{{ $s }}" class="sr-only" @checked($loop->first)><span>{{ __('sell.image.style.'.$s) }}</span><span class="text-xs font-normal opacity-80">{{ __('sell.image.style.'.$s.'.hint') }}</span></label>
                @endforeach
            </div>
        </fieldset>
        <fieldset>
            <legend class="lbl">{{ __('sell.image.f.size') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach($payload['sizes'] as $s)<label class="tool-choice"><input type="radio" name="size" value="{{ $s }}" class="sr-only" @checked($loop->first)>{{ __('sell.image.size.'.$s) }}</label>@endforeach
            </div>
        </fieldset>
    </x-tool-section>
    <x-tool-section id="make" :title="__('sell.image.sec.make')">
        <button type="submit" id="image-go" class="btn-primary w-full gap-1.5" @disabled(!$available)><x-icon name="sparkles" class="h-4 w-4" />{{ __('sell.image.go') }}</button>
        <p id="image-left" class="hint !text-xs" aria-live="polite"></p>
        <p id="image-msg" class="hint !text-xs" aria-live="polite"></p>
        <p class="hint !text-xs">{{ __('sell.image.limit.note', ['guest' => (int) config('ai.daily_limits.image_guest', 2), 'user' => (int) config('ai.daily_limits.image_user', 10)]) }}</p>
    </x-tool-section>
</form>
@endsection

@section('stage')
<div class="card p-4">
    <div class="text-xs font-medium uppercase tracking-wide text-muted">{{ __('sell.image.result') }}</div>
    <div id="image-stage" class="mt-2 flex aspect-square items-center justify-center rounded-xl border border-dashed border-line bg-white p-2 text-center text-sm text-muted sm:aspect-[4/3]">{{ __('sell.image.empty') }}</div>
    <div id="image-actions" class="mt-3 hidden flex-wrap items-center gap-x-4 gap-y-1 text-sm">
        <a id="image-download" href="#" download class="underline">{{ __('sell.image.download') }}</a>
        <button type="button" id="image-again" class="underline">{{ __('sell.image.again') }}</button>
    </div>
</div>
<div id="image-use" class="card hidden p-4">
    <div class="text-xs font-medium uppercase tracking-wide text-muted">{{ __('sell.image.use') }}</div>
    <p class="mt-1 text-sm text-muted">{{ __('sell.image.use.hint') }}</p>
    <div class="mt-2 flex flex-wrap gap-1.5">
        @foreach($payload['tools'] as $key => $tool)<a href="{{ $tool['url'] }}" class="btn-secondary text-xs">{{ $tool['title'] }}</a>@endforeach
    </div>
</div>
<div id="image-history" class="card hidden p-4">
    <div class="text-xs font-medium uppercase tracking-wide text-muted">{{ __('sell.image.history') }}</div>
    <div id="image-history-grid" class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-6"></div>
</div>
@endsection
