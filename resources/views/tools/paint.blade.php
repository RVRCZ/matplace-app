@php
    // colouring a model in the browser (docs/AS.md): a fill, colours by height, a brush; the script is resources/js/calc/paint.ts
    $maxMb = (int) $config['max_upload_mb'];
    $formats = $config['formats'];
    $keys = ['pick', 'source', 'uploading', 'processing', 'loading', 'preparing', 'model_failed', 'bad_format', 'too_big', 'error.too_heavy', 'brush.off', 'brush.full',
        'mode.fill.hint', 'mode.brush.hint', 'mode.erase.hint', 'mode.turn.hint', 'colors.base', 'colors.base.hint', 'colors.left.one', 'colors.left.few', 'colors.left.other', 'colors.left.none',
        'colors.remove', 'colors.change', 'colors.window', 'colors.window.farm', 'colors.window.basic', 'colors.loaded', 'colors.taken', 'colors.close', 'colors.share',
        'height.from', 'height.color', 'height.remove', 'height.need_model', 'go.need', 'saving', 'building', 'saved.specks.one', 'saved.specks.few', 'saved.specks.other', 'saved.lost',
        'error.one_color', 'error.failed', 'error.source_changed', 'error.too_many_colors', 'download.3mf', 'download.3mf.hint', 'opened.painted', 'color.now'];
    $i18n = collect($keys)->mapWithKeys(fn ($k) => ['paint.'.$k => __('paint.'.$k)])->all();
    $sections = ['model' => __('paint.step.model'), 'colors' => __('paint.step.colors'), 'height' => __('paint.step.height')];
    $modes = ['fill' => 'paint-bucket', 'brush' => 'brush', 'erase' => 'eraser', 'turn' => 'move-3d'];
@endphp

@extends('tools.page', ['fileInput' => 'paint-file', 'tool' => 'paint', 'module' => 'paint', 'lead' => __('paint.lead'), 'available' => $available, 'unavailable' => __('edit.unavailable'), 'sections' => $sections])

@push('head')
<script>
    window.MP_PAINT = {{ \Illuminate\Support\Js::from([
        'upload' => route('api.uploads.store'), 'files' => url('/api/files'), 'download' => url('/api/tools/paint'), 'home' => route('home'), 'from' => $from,
        'formats' => $formats, 'maxMb' => $maxMb, 'limits' => $limits, 'tiles' => $tiles, 'fromFarm' => $fromFarm, 'i18n' => $i18n,
    ]) }};
</script>
@endpush

@section('viewer-empty'){{ __('paint.pick') }}@endsection

@section('stage')
    {{-- the tools, the colours to paint with and the one setting of the chosen tool: the script puts this bar right under the model --}}
    <div id="paint-bar" class="hidden border-t border-line px-3 py-2">
        <div class="tool-bar w-fit max-w-full" role="group" aria-label="{{ __('paint.mode') }}">
            @foreach($modes as $mode => $icon)
                <button type="button" data-mode="{{ $mode }}" class="tool-bar-btn !min-h-11 lg:!min-h-9" aria-pressed="{{ $mode === 'fill' ? 'true' : 'false' }}"><x-icon :name="$icon" class="h-4 w-4" />{{ __('paint.mode.'.$mode) }}</button>
            @endforeach
        </div>
        {{-- the colours in hand and, beside them, a step back and forth: within the thumb's reach on a phone --}}
        <div class="mt-2 flex items-center gap-2">
            <div id="paint-swatches" class="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto px-1 py-1.5" role="radiogroup" aria-label="{{ __('paint.color.now') }}"></div>
            <div class="flex shrink-0 gap-1" role="group" aria-label="{{ __('toolpage.history') }}">
                <button type="button" id="paint-undo" class="tool-icon-btn" title="{{ __('toolpage.undo') }} (Ctrl+Z)" aria-label="{{ __('toolpage.undo') }}" disabled><x-icon name="undo-2" class="h-4 w-4" /></button>
                <button type="button" id="paint-redo" class="tool-icon-btn" title="{{ __('toolpage.redo') }} (Ctrl+Shift+Z)" aria-label="{{ __('toolpage.redo') }}" disabled><x-icon name="redo-2" class="h-4 w-4" /></button>
            </div>
        </div>
        <div id="paint-opt-fill" class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink">
            <label for="paint-angle" class="font-medium">{{ __('paint.fill.angle') }}</label>
            <input id="paint-angle" type="range" min="5" max="90" step="5" value="30" class="min-w-24 flex-1 accent-ink" title="{{ __('paint.fill.angle.hint') }}">
            <span id="paint-angle-val" class="num w-10 text-right text-muted">30°</span>
            <label class="inline-flex min-h-11 items-center gap-2 lg:min-h-0"><input id="paint-whole" type="checkbox" class="h-5 w-5 accent-ink">{{ __('paint.fill.whole') }}</label>
        </div>
        <div id="paint-opt-brush" class="mt-1 hidden flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink">
            <label for="paint-size" class="font-medium">{{ __('paint.brush.size') }}</label>
            <input id="paint-size" type="range" min="0.5" max="10" step="0.5" value="2" class="min-w-24 flex-1 accent-ink">
            <span id="paint-size-val" class="num w-16 text-right text-muted">2 mm</span>
        </div>
        <p id="paint-hint" class="mt-1 text-xs text-muted">{{ __('paint.mode.fill.hint') }}</p>
    </div>

    <p id="paint-result" class="note-warn hidden text-sm" role="status" aria-live="polite"></p>
    {{-- what a print in several colours costs in time and plastic, said before the price is --}}
    <div id="paint-honest" class="card p-4 text-sm text-ink">
        <h2 class="font-semibold">{{ __('paint.honest.title') }}</h2>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-muted">
            @foreach(['time', 'waste', 'height', 'detail'] as $k)<li>{{ __('paint.honest.'.$k) }}</li>@endforeach
        </ul>
        @if(\App\Support\NextStep::mode() === \App\Support\NextStep::FARM)<p class="mt-2 text-muted">{{ __('paint.tip') }}</p>@endif
    </div>
@endsection

@section('panel')
<form id="paint-form" novalidate>
    {{-- 1 · the model: a file, or the one the page was opened with (?from=<uuid>) --}}
    <x-tool-section id="model" :title="__('paint.step.model')">
        <label id="paint-drop" class="block cursor-pointer rounded-xl border-2 border-dashed border-line p-6 text-center hover:border-ink">
            <input id="paint-file" type="file" class="sr-only" accept="{{ collect($formats)->map(fn ($f) => '.'.$f)->join(',') }}">
            <x-icon name="upload" class="mx-auto h-7 w-7 text-muted" />
            <span class="mt-1 block font-medium text-ink">{{ __('paint.pick') }}</span>
            <span class="block text-sm text-muted">{{ strtoupper(implode(', ', $formats)) }} · {{ __('check.page.max', ['max' => $maxMb]) }}</span>
            <span id="paint-source" class="mt-2 hidden text-sm font-medium text-ink"></span>
        </label>
        <p id="paint-state" class="note-warn hidden text-sm" role="status" aria-live="polite"></p>
        <p id="paint-note" class="hidden text-sm text-ink" aria-live="polite"></p>
    </x-tool-section>

    {{-- 2 · the colours: the model's own and those painted with, from the farm's filaments; as many as one print takes --}}
    <x-tool-section id="colors" :title="__('paint.step.colors')">
        <div id="paint-colors" class="space-y-1"></div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" id="paint-add" class="btn-quiet !min-h-10 gap-1.5 !px-3 !py-2 text-sm"><x-icon name="plus" class="h-4 w-4" />{{ __('paint.colors.add') }}</button>
            <button type="button" id="paint-clear" class="btn-quiet !min-h-10 gap-1.5 !px-3 !py-2 text-sm" disabled><x-icon name="trash-2" class="h-4 w-4" />{{ __('paint.colors.clear') }}</button>
        </div>
        <p id="paint-left" class="hint !text-xs" aria-live="polite">{{ __('paint.colors.limit', ['max' => $limits['max_colors']]) }}</p>
    </x-tool-section>

    {{-- 3 · by height: from a height up another colour, for what is not painted by hand --}}
    <x-tool-section id="height" :title="__('paint.step.height')">
        <p class="hint !text-xs">{{ __('paint.height.intro') }}</p>
        <div id="paint-bands" class="space-y-3"></div>
        <button type="button" id="paint-band-add" class="btn-quiet !min-h-10 gap-1.5 !px-3 !py-2 text-sm"><x-icon name="layers" class="h-4 w-4" />{{ __('paint.height.add') }}</button>
    </x-tool-section>
</form>
@endsection
