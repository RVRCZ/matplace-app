@php
    $integer = fn (array $f) => $f[3] === 1;
    $unit = fn (string $k) => in_array($k, ['colors_n', 'bg_strength'], true) ? '' : (in_array($k, ['contrast', 'brightness', 'saturation'], true) ? '%' : 'mm');
    $i18n = collect(['param.working', 'param.failed', 'param.too_fast', 'param.estimate', 'param.creating', 'param.artwork.failed', 'toolpage.color.out'])->mapWithKeys(fn ($k) => [$k => __($k)])
        ->merge(collect(['art.part.body', 'art.part.frame', 'art.part.plate', 'art.part.color', 'art.found', 'art.print.layered', 'art.print.stack', 'art.print.stack1', 'art.print.one', 'art.print.farm', 'art.depth', 'art.posts',
            'art.guide', 'art.guide.hint', 'art.guide.back', 'art.guide.front', 'art.guide.posts', 'art.guide.flat', 'art.guide.frame', 'art.guide.led', 'art.guide.glue', 'art.frame.size',
            'colors.share', 'colors.up', 'colors.down', 'colors.merge', 'colors.merge.into', 'colors.split', 'colors.picture',
            'warn.outlines_ignored', 'warn.pieces_tied', 'warn.thin_merged', 'warn.small_plate_flat', 'warn.thin_lines', 'download.plates'])->mapWithKeys(fn ($k) => ['edit.'.$k => \App\Support\NextStep::text('edit.'.$k)]))->all();
    $sections = ['input' => __('toolpage.section.input'), 'size' => __('toolpage.section.size'), 'colors' => __('toolpage.section.colors'), 'print' => \App\Support\NextStep::text('param.step.inquiry')];
    $warnAt = ['outlines_ignored' => 'input', 'pieces_tied' => 'input', 'thin_lines' => 'input', 'thin_merged' => 'colors', 'small_plate_flat' => 'size'];
@endphp

@extends('tools.page', ['tool' => 'filament_art', 'module' => 'art', 'lead' => __('edit.art.lead'), 'sections' => $sections, 'available' => $available, 'goLabel' => \App\Support\NextStep::text('param.go')])

@push('head')
<script>
    window.MP_ART = {
        preview: @json(route('api.tools.art.preview')),
        create: @json(route('api.tools.art')),
        zip: @json(route('api.tools.art.zip')),
        home: @json(route('home')),
        files: @json(url('/api/files')),
        parts: @json(url('/api/tools/edit')),
        from: @json($from),
        sample: @json($sample),
        config: {{ \Illuminate\Support\Js::from(\Illuminate\Support\Arr::except($config, ['colors'])) }},
        warnAt: {{ \Illuminate\Support\Js::from($warnAt) }},
        i18n: {{ \Illuminate\Support\Js::from($i18n) }},
    };
</script>
@endpush

@section('price-note'){{ \App\Support\NextStep::text('param.estimate.note') }} {{ \App\Support\NextStep::text('param.go.hint') }}@endsection

@section('stage')
    {{-- the guide of a layered picture: the plates back to front, each drawn, with its filament and its spacers --}}
    <div id="art-guide" class="card hidden p-4 text-sm"></div>
    <p class="text-xs text-muted">{{ \App\Support\NextStep::text('edit.art.tip') }}</p>
@endsection

@section('panel')
<form id="art-form" novalidate>
    {{-- 1 · the picture: upload, the library, my pictures; the background; the photo's own sliders --}}
    <x-tool-section id="input" :title="__('toolpage.section.input')">
        <fieldset>
            <legend class="lbl">{{ __('edit.art.picture') }}</legend>
            <div class="mt-2 flex items-start gap-3">
                <span id="art-artwork-thumb" class="hidden h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-white p-1.5"></span>
                <div class="min-w-0">
                    <button type="button" id="art-artwork-open" class="btn-quiet !min-h-10 gap-1.5 !px-3 !py-2 text-sm"><x-icon name="image" class="h-4 w-4" />{{ __('toolpage.artwork.choose') }}</button>
                    <p id="art-artwork-state" class="mt-1 text-sm text-muted" aria-live="polite"></p>
                </div>
            </div>
            <p class="hint mt-1 !text-xs">{{ __('edit.art.picture.hint') }}</p>
        </fieldset>
        <label class="flex items-start gap-3 text-sm text-ink">
            <input data-flag="remove_bg" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" checked>
            <span><span class="font-medium">{{ __('edit.flag.remove_bg') }}</span><br><span class="text-muted">{{ __('edit.flag.remove_bg.hint') }}</span></span>
        </label>
        <div class="grid gap-3">
            @foreach(['bg_strength', 'smooth'] as $key)
                @include('tools._num', ['key' => $key, 'f' => $fields[$key], 'label' => __('edit.f.'.$key), 'unit' => $unit($key), 'when' => $key === 'bg_strength' ? 'data-when="remove_bg=on"' : ''])
            @endforeach
        </div>
        <details class="text-sm" id="art-adjust">
            <summary class="cursor-pointer font-medium text-ink underline decoration-line underline-offset-4">{{ __('edit.adjust') }}</summary>
            <div class="mt-3 grid gap-3">
                @foreach($folded as $key)
                    @include('tools._num', ['key' => $key, 'f' => $fields[$key], 'label' => __('edit.f.'.$key), 'unit' => $unit($key), 'when' => ''])
                @endforeach
            </div>
        </details>
    </x-tool-section>

    {{-- 2 · how it is made and how big: one print or plates, the body, the frame, the sizes --}}
    <x-tool-section id="size" :title="__('toolpage.section.size')">
        <fieldset>
            <legend class="lbl">{{ __('edit.c.mode') }}</legend>
            <div class="mt-2 grid gap-2" role="radiogroup">
                @foreach($choices['mode'] as $i => $o)
                    <label class="cursor-pointer rounded-lg border border-slate-300 bg-white p-3 text-sm has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink">
                        <input type="radio" name="c-mode" data-choice="mode" value="{{ $o }}" class="sr-only" @checked($i === 0)>
                        <span class="block font-medium text-ink">{{ __('edit.o.mode.'.$o) }}</span>
                        <span class="block text-xs text-muted">{{ \App\Support\NextStep::text('edit.o.mode.'.$o.'.hint') }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <fieldset data-when="mode=layered">
            <legend class="lbl">{{ __('edit.c.frame') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach($choices['frame'] as $i => $o)
                    <label class="tool-choice"><input type="radio" name="c-frame" data-choice="frame" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('edit.o.frame.'.$o) }}</label>
                @endforeach
            </div>
        </fieldset>
        <fieldset data-when="frame=none,square">
            <legend class="lbl">{{ __('edit.c.shape') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach($choices['shape'] as $i => $o)
                    <label class="tool-choice"><input type="radio" name="c-shape" data-choice="shape" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('edit.o.shape.'.$o) }}</label>
                @endforeach
            </div>
        </fieldset>
        <div class="grid gap-3">
            @include('tools._num', ['key' => 'width', 'f' => $fields['width'], 'label' => __('edit.f.width'), 'unit' => 'mm', 'when' => ''])
            @include('tools._num', ['key' => 'height', 'f' => $fields['height'], 'label' => __('edit.f.height'), 'unit' => 'mm', 'when' => 'data-when="frame=none,square"'])
            @include('tools._num', ['key' => 'margin', 'f' => $fields['margin'], 'label' => __('edit.f.margin'), 'unit' => 'mm', 'when' => 'data-when="shape=rect,circle"'])
            @include('tools._num', ['key' => 'frame_w', 'f' => $fields['frame_w'], 'label' => __('edit.f.frame_w'), 'unit' => 'mm', 'when' => 'data-when="frame=round,square"'])
        </div>
        <div data-when="mode=layered" class="space-y-4">
            <div class="grid gap-3">
                @include('tools._num', ['key' => 'plate', 'f' => $fields['plate'], 'label' => __('edit.f.plate'), 'unit' => 'mm', 'when' => ''])
                @include('tools._num', ['key' => 'gap', 'f' => $fields['gap'], 'label' => __('edit.f.gap'), 'unit' => 'mm', 'when' => 'data-when="flat=off"'])
            </div>
            <label class="flex items-start gap-3 text-sm text-ink">
                <input data-flag="flat" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink">
                <span><span class="font-medium">{{ __('edit.flag.flat') }}</span><br><span class="text-muted">{{ __('edit.flag.flat.hint') }}</span></span>
            </label>
            <label class="flex items-start gap-3 text-sm text-ink" data-when="frame=round,square">
                <input data-flag="led" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink">
                <span><span class="font-medium">{{ __('edit.flag.led') }}</span><br><span class="text-muted">{{ __('edit.flag.led.hint') }}</span></span>
            </label>
        </div>
        <div data-when="mode=stack" class="grid gap-3">
            @include('tools._num', ['key' => 'base', 'f' => $fields['base'], 'label' => __('edit.f.base'), 'unit' => 'mm', 'when' => ''])
            @include('tools._num', ['key' => 'step', 'f' => $fields['step'], 'label' => __('edit.f.step'), 'unit' => 'mm', 'when' => ''])
        </div>
        <dl id="art-dims" class="hidden grid-cols-1 gap-x-4 gap-y-1 text-sm sm:grid-cols-2"></dl>
    </x-tool-section>

    {{-- 3 · colours: how many, each one a filament of the farm, listed from the front plate to the back one --}}
    <x-tool-section id="colors" :title="__('toolpage.section.colors')">
        <div class="grid gap-3">
            @include('tools._num', ['key' => 'colors_n', 'f' => $fields['colors_n'], 'label' => __('edit.f.colors_n'), 'unit' => '', 'when' => ''])
        </div>
        <p id="art-found" class="hint !text-xs" aria-live="polite"></p>
        <div id="tool-parts" class="space-y-2"></div>
        <p id="art-print" class="text-sm text-ink" aria-live="polite"></p>
    </x-tool-section>

    {{-- 4 · the print: material and how many --}}
    <x-tool-section id="print" :title="\App\Support\NextStep::text('param.step.inquiry')">
        <div class="grid grid-cols-2 gap-3">
            <label class="lbl">{{ __('calc.material') }}
                <select id="art-material" class="field">
                    @foreach($config['materials'] as $m)<option value="{{ $m['code'] }}" @selected($m['code'] === $config['default_material'])>{{ $m['label'] }} ({{ $m['code'] }})</option>@endforeach
                </select>
            </label>
            <label class="lbl">{{ __('calc.quantity') }}
                <input id="art-qty" type="number" inputmode="numeric" min="1" max="100" value="1" class="field">
            </label>
        </div>
    </x-tool-section>
</form>
@endsection
