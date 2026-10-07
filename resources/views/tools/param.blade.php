@php
    $family = $family ?? null;
    $place = $place ?? [];
    $integer = fn (array $f) => $f[3] === 1;
    $unit = fn (string $k) => in_array($k, ['rows', 'cols', 'count', 'ribs', 'colors_n', 'bg_strength'], true) ? '' : (in_array($k, ['angle', 'twist'], true) ? '°' : (in_array($k, ['flute', 'contrast', 'brightness', 'saturation', 'eye_pos'], true) ? '%' : 'mm'));
    $i18n = collect(['param.working', 'param.failed', 'param.too_fast', 'param.text_required', 'param.estimate', 'param.outer', 'param.inner', 'param.cell', 'param.slot', 'param.hole', 'param.hole.remove', 'param.creating', 'param.too_many_holes',
        'param.warn.stand_angle_45', 'param.warn.stand_angle_55', 'param.warn.stand_angle_70', 'param.wall.front', 'param.wall.back', 'param.wall.left', 'param.wall.right', 'param.shape.circle', 'param.shape.rect', 'param.hole.w', 'param.hole.d', 'param.hole.h', 'param.hole.x', 'param.hole.z',
        'param.part.body', 'param.part.lid', 'param.part.all', 'param.part.saucer', 'param.part.handle', 'param.part.stand', 'param.part.imprint', 'param.part.cut', 'param.part.body.logo', 'param.part.stand.logo', 'param.part.body.vase', 'param.part.body.stamp', 'param.part.body.qr', 'param.part.body.lightbox', 'param.warn.floating_pieces', 'param.need.glue_optional', 'param.part.tray', 'param.part.bin', 'param.bom', 'param.bom.line', 'param.unit', 'param.bins.free', 'param.bins.pick_end', 'param.bins.taken', 'param.bins.bin', 'param.bins.empty',
        'color.white', 'color.black', 'color.grey', 'color.brown', 'color.red', 'color.blue', 'color.green', 'color.yellow', 'color.orange', 'param.part.face', 'param.part.diffuser', 'param.part.back', 'param.part.plate', 'param.part.text', 'param.part.stamp', 'param.bridges', 'param.lightbox.led', 'param.need.led_strip8', 'param.need.led_strip10', 'param.need.led_module', 'param.need.usb_power', 'param.need.tape', 'param.view', 'param.artwork.uploading', 'param.artwork.failed', 'param.artwork.remove',
        'param.warn.thread_try', 'param.warn.seal_try', 'param.need.liner', 'param.fits', 'param.warn.thin_lines', 'param.warn.outlines_ignored', 'param.warn.missing_chars', 'param.warn.separate_pieces', 'param.need.glue', 'param.needs', 'param.qr.facts', 'param.warn.qr_one_color', 'param.warn.qr_low_contrast', 'param.warn.qr_inverted', 'param.vase.facts', 'param.saucer'])->mapWithKeys(fn ($k) => [$k => __($k)])->all();
    if ($family === 'shape') {
        // the picture in colours: its list of colours, the notes on how it prints, the eyelet
        $i18n += collect(['part.body', 'part.rim', 'part.color', 'colors.share', 'colors.up', 'colors.down', 'colors.merge', 'colors.merge.into', 'colors.split', 'colors.found', 'colors.picture', 'print.one', 'print.swap1', 'print.swap', 'print.multi',
            'eyelet.drag', 'eyelet.top', 'each', 'pair', 'warn.pieces_tied', 'warn.magnet_no_room', 'warn.magnet_shows', 'warn.name_small', 'warn.name_no_room', 'part.icing', 'thickened', 'magnet.fact',
            // a part may be called by what it is in this tool (the plate of a gingerbread is "the gingerbread")
            ...array_filter(['part.body.'.$kind, 'part.color_1.'.$kind], fn ($k) => \Illuminate\Support\Facades\Lang::has('param.shape.'.$k))])->mapWithKeys(fn ($k) => ['shape.'.$k => \App\Support\NextStep::text('param.shape.'.$k)])->all();
    }
    if ($kind === 'cookie') {
        $i18n += collect(['draw', 'draw.on', 'count', 'hint', 'limit'])->mapWithKeys(fn ($k) => ['cookie.'.$k => __('param.cookie.'.$k)])->all();
    }
    // a text may be written for one tool, for its family (pendant, earrings… are all "shape") or for every tool
    $tr = function (string $prefix, string $k) use ($kind, $family): string {
        foreach (array_filter([$kind, $family]) as $owner) {
            if (\Illuminate\Support\Facades\Lang::has('param.'.$prefix.'.'.$owner.'.'.$k)) {
                return __('param.'.$prefix.'.'.$owner.'.'.$k);
            }
        }

        return __('param.'.$prefix.'.'.$k);
    };
    $own = fn (string $k) => \Illuminate\Support\Facades\Lang::has('param.'.$kind.'.'.$k) || ! $family ? 'param.'.$kind.'.'.$k : 'param.'.$family.'.'.$k;
    $label = fn (string $k) => $tr('f', $k);
    $at = fn (string $k, string $default) => $place[$k] ?? $default;      // the section a field, flag or choice is shown in
    $palette = $config['colors'];
    // a built-in colour name as the code of the farm's spool nearest to it (the same name when there is no catalogue)
    $spool = fn (string $name) => $palette['legacy'][$name] ?? $name;
    $colorChoices = collect($choices)->filter(fn ($o, $k) => \App\Domain\Tools\ParametricGenerator::isColor($k));
    $plainChoices = collect($choices)->reject(fn ($o, $k) => \App\Domain\Tools\ParametricGenerator::isColor($k));
    $hasInput = $presets || $texts || $artwork || $plainChoices->isNotEmpty();
    $sections = array_filter([
        'input' => $hasInput ? __('toolpage.section.input') : null,
        'size' => __('toolpage.section.size'),
        'colors' => __('toolpage.section.colors'),
        'print' => \App\Support\NextStep::text('param.step.inquiry'),   // "print or download" / "download" / "inquiry": what this site offers
    ]);
    // fields, flags and choices that belong to one choice only: the script hides them for the others
    $whenOf = fn (string $key) => isset($when[$key]) ? 'data-when="'.e($when[$key][0].'='.implode(',', $when[$key][1])).'"' : '';
    // which wall of the model a size moves when it is dragged in the viewer (x width, y depth, z height)
    $handles = \App\Domain\Tools\ParametricGenerator::HANDLES[$kind] ?? [];
    // which section a warning of the tool belongs to; everything else is about the size
    $warnAt = ['thin_lines' => 'input', 'outlines_ignored' => 'input', 'missing_chars' => 'input', 'separate_pieces' => 'input', 'floating_pieces' => 'input', 'pieces_tied' => 'input', 'qr_one_color' => 'colors', 'qr_low_contrast' => 'colors', 'qr_inverted' => 'colors'];
    $folded = \App\Domain\Tools\ParametricGenerator::FOLDED;
    $fieldsAt = fn (string $section) => collect($fields)->filter(fn ($f, $k) => $at($k, 'size') === $section);
    $flagsAt = fn (string $section) => collect($flags)->filter(fn ($flag) => $at($flag, 'size') === $section);
@endphp

@extends('tools.page', ['tool' => $kind, 'module' => 'param', 'lead' => __('param.'.$kind.'.lead'), 'sections' => $sections, 'available' => $available, 'goLabel' => \App\Support\NextStep::text('param.go')])

@push('head')
<script>
    window.MP_PARAM = {
        kind: @json($kind),
        family: @json($family),
        sample: @json($sample ?? null),
        preview: @json(route('api.tools.param.preview')),
        create: @json(route('api.tools.param')),
        home: @json(route('home')),
        presets: {{ \Illuminate\Support\Js::from($presets) }},
        fills: {{ \Illuminate\Support\Js::from($fills) }},
        artworkUrl: @json(route('api.tools.artwork')),
        files: @json(url('/api/files')),
        from: @json(request('from')),
        config: {{ \Illuminate\Support\Js::from(\Illuminate\Support\Arr::except($config, ['colors'])) }},
        locale: @json(app()->getLocale()),
        handles: {{ \Illuminate\Support\Js::from($handles) }},
        warnAt: {{ \Illuminate\Support\Js::from($warnAt) }},
        i18n: {{ \Illuminate\Support\Js::from($i18n) }},
    };
</script>
@endpush

@section('price-note'){{ \App\Support\NextStep::text('param.estimate.note') }} {{ \App\Support\NextStep::text('param.go.hint') }}@endsection

@section('stage')
    <div id="param-bom" class="card hidden p-4 text-sm"></div>
    <p class="text-xs text-muted">{{ \App\Support\NextStep::text($own('tip')) }}</p>
@endsection

@section('panel')
<form id="param-form" novalidate>
    {{-- 1 · what it is made from: a preset to start with, the text, the picture, the kind of thing --}}
    @if($hasInput)
    <x-tool-section id="input" :title="__('toolpage.section.input')">
        @if($presets)
            <fieldset>
                <legend class="lbl">{{ __('param.presets') }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach($presets as $key => $values)
                        <button type="button" class="chip !py-1" data-preset="{{ $key }}">{{ __('param.preset.'.$key) }}</button>
                    @endforeach
                </div>
            </fieldset>
        @endif

        @if($family === 'shape' && $artwork)
            {{-- the picture comes first here: the three ways to one (upload, the library, my pictures), then what is done with it --}}
            <fieldset>
                <legend class="lbl">{{ __('param.shape.picture') }}</legend>
                <div class="mt-2 flex items-start gap-3">
                    <span id="param-artwork-thumb" class="hidden h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-white p-1.5"></span>
                    <div class="min-w-0">
                        <button type="button" id="param-artwork-open" class="btn-quiet !min-h-10 gap-1.5 !px-3 !py-2 text-sm"><x-icon name="image" class="h-4 w-4" />{{ __('toolpage.artwork.choose') }}</button>
                        <p id="param-artwork-state" class="mt-1 text-sm text-muted" aria-live="polite"></p>
                    </div>
                </div>
                <p class="hint mt-1 !text-xs">{{ __('param.shape.picture.hint') }}</p>
                <input id="param-artwork" type="file" accept=".svg,image/svg+xml,image/png,image/jpeg,image/webp" class="sr-only" tabindex="-1" aria-hidden="true">
            </fieldset>
            @foreach($flagsAt('input') as $flag)
                <label class="flex items-start gap-3 text-sm text-ink" {!! $whenOf($flag) !!}>
                    <input data-flag="{{ $flag }}" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" @checked(in_array($flag, $flagsOn, true))>
                    <span><span class="font-medium">{{ $tr('flag', $flag) }}</span><br><span class="text-muted">{{ $tr('flag', $flag.'.hint') }}</span></span>
                </label>
            @endforeach
            <div class="grid gap-3">
                @foreach($fieldsAt('input') as $key => $f)
                    @continue(in_array($key, $folded, true))
                    @include('tools._num', ['key' => $key, 'f' => $f, 'label' => $label($key), 'unit' => $unit($key), 'when' => $whenOf($key)])
                @endforeach
            </div>
            <details class="text-sm" id="shape-adjust">
                <summary class="cursor-pointer font-medium text-ink underline decoration-line underline-offset-4">{{ __('param.shape.adjust') }}</summary>
                <div class="mt-3 grid gap-3">
                    @foreach($fieldsAt('input') as $key => $f)
                        @continue(! in_array($key, $folded, true))
                        @include('tools._num', ['key' => $key, 'f' => $f, 'label' => $label($key), 'unit' => $unit($key), 'when' => $whenOf($key)])
                    @endforeach
                </div>
            </details>
        @endif

        @if($texts || ($artwork && $family !== 'shape'))
            <fieldset>
                <legend class="lbl">{{ __($own('content')) }}</legend>
                <div class="mt-2 grid gap-3">
                    @foreach($texts as $key => $t)
                        <label class="text-sm font-medium text-ink">{{ $tr('t', $key) }}
                            <input data-text="{{ $key }}" maxlength="{{ $t[0] }}" value="{{ $t[2] }}" @if($t[1]) required @endif class="field" @if($key === 'url') inputmode="url" autocapitalize="off" spellcheck="false" @endif>
                        </label>
                    @endforeach
                </div>
                @if($kind !== 'qr' && $texts)
                    {{-- symbols the typefaces can draw: a click writes one where the cursor is. Drawn in the tool's own symbol
                         face (engines/fonts/NotoEmoji, the one the model is made from), not as the system's colour emoji. --}}
                    <div id="param-symbols" class="mt-2 flex flex-wrap items-center gap-1" role="group" aria-label="{{ __('param.symbols') }}">
                        <span class="mr-1 text-xs text-muted">{{ __('param.symbols') }}</span>
                        @foreach(['♥', '★', '☺', '♪', '✿', '☀', '☾', '✓', '🐾', '🎂', '🎄', '🎁', '👑', '⚽', '🚀', '🦋', '🐱', '🐶', '🌸'] as $symbol)
                            <button type="button" data-symbol="{{ $symbol }}" class="tool-symbol">{{ $symbol }}</button>
                        @endforeach
                    </div>
                @endif
                @if($artwork && $family !== 'shape')
                    {{-- a picture instead of the text: upload, our library of silhouettes, or one uploaded before --}}
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="button" id="param-artwork-open" class="btn-quiet !min-h-10 gap-1.5 !px-3 !py-2 text-sm"><x-icon name="image" class="h-4 w-4" />{{ __('toolpage.artwork.choose') }}</button>
                        <span id="param-artwork-thumb" class="hidden h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-line bg-white p-1"></span>
                        <p id="param-artwork-state" class="text-sm text-muted" aria-live="polite"></p>
                    </div>
                    <p class="hint mt-1 !text-xs">{{ __('param.artwork.hint') }}</p>
                    <input id="param-artwork" type="file" accept=".svg,image/svg+xml,image/png,image/jpeg,image/webp" class="sr-only" tabindex="-1" aria-hidden="true">
                @endif
            </fieldset>
        @endif

        @foreach($plainChoices as $key => $options)
            @continue($at($key, 'input') !== 'input')
            <fieldset {!! $whenOf($key) !!}>
                <legend class="lbl">{{ $tr('c', $key) }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach($options as $i => $o)
                        <label class="tool-choice">
                            <input type="radio" name="c-{{ $key }}" data-choice="{{ $key }}" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ $tr('o', $o) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </x-tool-section>
    @endif

    {{-- 2 · sizes: the main ones with a slider (and a handle in the viewer), the rest under "more" --}}
    <x-tool-section id="size" :title="__('toolpage.section.size')">
        @foreach($plainChoices as $key => $options)
            @continue($at($key, 'input') !== 'size')
            <fieldset {!! $whenOf($key) !!}>
                <legend class="lbl">{{ $tr('c', $key) }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach($options as $i => $o)
                        <label class="tool-choice">
                            <input type="radio" name="c-{{ $key }}" data-choice="{{ $key }}" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ $tr('o', $o) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <fieldset>
            <legend class="sr-only">{{ __($own('size')) }}</legend>
            <div class="grid gap-3">
                @foreach($main as $key)
                    @include('tools._num', ['key' => $key, 'f' => $fields[$key], 'label' => $label($key), 'unit' => $unit($key), 'when' => $whenOf($key)])
                @endforeach
            </div>
        </fieldset>
        @if($family === 'shape' && in_array('eyelet', $flags, true))
            {{-- the eyelet goes where it is dragged to in the preview; this puts it back on top --}}
            <div data-when="eyelet=on">
                <button type="button" id="shape-eyelet-top" class="chip !py-1 text-sm">{{ __('param.shape.eyelet.top') }}</button>
                <p class="hint mt-1 !text-xs">{{ __('param.shape.eyelet.drag') }}</p>
            </div>
        @endif

        @foreach($flagsAt('size') as $flag)
            <label class="flex items-start gap-3 text-sm text-ink" {!! $whenOf($flag) !!}>
                <input data-flag="{{ $flag }}" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" @checked(in_array($flag, $flagsOn, true))>
                <span><span class="font-medium">{{ $tr('flag', $flag) }}</span><br><span class="text-muted">{{ $tr('flag', $flag.'.hint') }}</span></span>
            </label>
        @endforeach

        @if($kind === 'modular')
            <fieldset>
                <legend class="lbl">{{ __('param.bins') }}</legend>
                <p class="hint" id="bins-help">{{ __('param.bins.hint') }}</p>
                <div id="bin-grid" class="mt-2 grid select-none gap-1 rounded-xl bg-page p-2" role="grid" aria-describedby="bins-help"></div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-ink">{{ __('param.bins.color') }}</span>
                    <div id="bin-colors" class="flex flex-wrap items-center gap-1.5"></div>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <button type="button" id="bins-fill" class="chip !py-1 text-sm">{{ __('param.bins.fill') }}</button>
                    <button type="button" id="bins-remove" class="chip !py-1 text-sm">{{ __('param.bins.remove') }}</button>
                    <button type="button" id="bins-clear" class="chip !py-1 text-sm">{{ __('param.bins.clear') }}</button>
                </div>
                <p id="bins-state" class="mt-1 text-sm text-muted" aria-live="polite"></p>
            </fieldset>
        @endif

        @if($kind === 'box')
            <fieldset>
                <legend class="lbl">{{ __('param.holes') }}</legend>
                <p class="hint">{{ __('param.holes.hint') }}</p>
                <div id="holes" class="mt-2 space-y-2"></div>
                <button type="button" id="add-hole" class="chip mt-2 inline-flex items-center gap-1 !py-1 text-sm"><x-icon name="plus" class="h-3.5 w-3.5" />{{ __('param.holes.add') }}</button>
            </fieldset>
        @endif

        @if($fieldsAt('size')->count() > count($main))
        <details class="text-sm">
            <summary class="cursor-pointer font-medium text-ink underline decoration-line underline-offset-4">{{ __('param.more') }}</summary>
            <div class="mt-3 grid grid-cols-2 gap-3">
                @foreach($fieldsAt('size') as $key => $f)
                    @continue(in_array($key, $main, true))
                    <label class="text-sm font-medium text-ink" {!! $whenOf($key) !!}>{{ $label($key) }}
                        <span class="tool-unit mt-1" data-unit="{{ $unit($key) }}">
                            <input data-param="{{ $key }}" type="number" inputmode="decimal" min="{{ $f[0] }}" max="{{ $f[1] }}" step="{{ $f[3] }}" value="{{ $f[2] }}" class="field !mt-0">
                        </span>
                        <span class="text-xs font-normal text-muted">{{ $f[0] }}–{{ $f[1] }}</span>
                    </label>
                @endforeach
            </div>
            @if($kind === 'box')<p class="hint mt-2">{{ __('param.clearance.hint') }}</p>@endif
        </details>
        @endif
    </x-tool-section>

    {{-- 3 · colours: every separately printed part with one swatch from the farm's spools (a click opens the window) --}}
    <x-tool-section id="colors" :title="__('toolpage.section.colors')">
        @if($family === 'shape')
            {{-- how many colours the picture is reduced to; each one a filament of the farm, listed below from the top layer down --}}
            <div class="grid gap-3">
                @foreach($fieldsAt('colors') as $key => $f)
                    @continue($key !== 'colors_n')
                    @include('tools._num', ['key' => $key, 'f' => $f, 'label' => $label($key), 'unit' => $unit($key), 'when' => $whenOf($key)])
                @endforeach
            </div>
            <p id="shape-found" class="hint !text-xs" aria-live="polite"></p>
        @endif
        @foreach($colorChoices as $key => $options)
            {{-- a colour that changes the design itself (the plate and the code of a QR sign): kept with the model --}}
            <div class="flex items-center gap-3">
                <input type="hidden" data-choice="{{ $key }}" data-color value="{{ $spool($options[0]) }}">
                <button type="button" class="tool-swatch" data-swatch-for="{{ $key }}" aria-label="{{ __('param.c.'.$kind.'.'.$key) }}: {{ __('toolpage.color.pick') }}"></button>
                <span class="min-w-0 text-sm"><span class="block font-medium text-ink">{{ __('param.c.'.$kind.'.'.$key) }}</span><span class="block truncate text-muted" data-swatch-name="{{ $key }}"></span></span>
            </div>
            @if(\Illuminate\Support\Facades\Lang::has('param.c.'.$kind.'.'.$key.'.hint'))<p class="hint !text-xs">{{ \App\Support\NextStep::text('param.c.'.$kind.'.'.$key.'.hint') }}</p>@endif
        @endforeach
        @if($kind === 'cookie')
            {{-- icing piped by hand: draw on the biscuit in the preview; every filament drawn with becomes a part --}}
            <fieldset id="cookie-icing">
                <legend class="lbl">{{ __('param.cookie.icing') }}</legend>
                <button type="button" id="cookie-draw" class="btn-ink mt-2 w-full gap-2" aria-pressed="false"><x-icon name="sparkles" class="h-4 w-4" /><span>{{ __('param.cookie.draw') }}</span></button>
                <div class="mt-3 flex items-center gap-3">
                    <button type="button" class="tool-swatch" id="cookie-pen" aria-label="{{ __('param.cookie.pen') }}: {{ __('toolpage.color.pick') }}"></button>
                    <span class="min-w-0 text-sm"><span class="block font-medium text-ink">{{ __('param.cookie.pen') }}</span><span class="block truncate text-muted" id="cookie-pen-name"></span></span>
                </div>
                <label class="mt-3 block text-sm font-medium text-ink">{{ __('param.cookie.width') }}
                    <span class="mt-1 flex items-center gap-3"><input type="range" id="cookie-width" min="1.5" max="4" step="0.5" value="2.5" class="min-w-0 flex-1 accent-ink"><span class="num w-14 text-right text-muted" id="cookie-width-v">2,5 mm</span></span>
                </label>
                <div class="mt-3 flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('param.cookie.nib') }}">
                    @foreach(['round', 'flat', 'dots'] as $i => $nib)
                        <label class="tool-choice"><input type="radio" name="cookie-nib" value="{{ $nib }}" class="sr-only" @checked($i === 0)>{{ __('param.cookie.nib.'.$nib) }}</label>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <button type="button" id="cookie-undo" class="chip !py-1 text-sm">{{ __('param.cookie.undo') }}</button>
                    <button type="button" id="cookie-clear" class="chip !py-1 text-sm">{{ __('param.cookie.clear') }}</button>
                </div>
                <p id="cookie-count" class="hint mt-1 !text-xs" aria-live="polite"></p>
            </fieldset>
        @endif
        {{-- the parts of the design, filled by the script as the preview says which there are --}}
        <div id="tool-parts" class="space-y-2" data-own-colors="{{ $colorChoices->isNotEmpty() || $kind === 'modular' ? '1' : '0' }}"></div>
        @if($kind === 'modular')<p class="hint !text-xs">{{ __('toolpage.color.bins') }}</p>@endif
        <div id="tool-recent" class="hidden">
            <div class="text-xs text-muted">{{ __('toolpage.color.recent') }}</div>
            <div id="tool-recent-list" class="mt-1 flex flex-wrap gap-1.5"></div>
        </div>
        @if($family === 'shape')
            <div class="grid gap-3">
                @foreach($fieldsAt('colors') as $key => $f)
                    @continue($key === 'colors_n')
                    @include('tools._num', ['key' => $key, 'f' => $f, 'label' => $label($key), 'unit' => $unit($key), 'when' => $whenOf($key)])
                @endforeach
            </div>
            @foreach($flagsAt('colors') as $flag)
                <label class="flex items-start gap-3 text-sm text-ink" {!! $whenOf($flag) !!}>
                    <input data-flag="{{ $flag }}" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" @checked(in_array($flag, $flagsOn, true))>
                    <span><span class="font-medium">{{ $tr('flag', $flag) }}</span><br><span class="text-muted">{{ $tr('flag', $flag.'.hint') }}</span></span>
                </label>
            @endforeach
            {{-- how this design gets its colours in print: one filament, swaps by height, or a printer that changes filament itself --}}
            <p id="shape-print" class="rounded-lg bg-page p-3 text-sm text-ink" aria-live="polite"></p>
        @endif
        @unless($palette['farm'])<p class="hint !text-xs">{{ __('toolpage.color.builtin') }}</p>@endunless
        {{-- the one colour the order starts from (the first part's); two-colour designs carry theirs in the design --}}
        <input type="hidden" id="param-color" value="">
    </x-tool-section>

    {{-- 4 · the print: material and how many --}}
    <x-tool-section id="print" :title="\App\Support\NextStep::text('param.step.inquiry')">
        <div class="grid grid-cols-2 gap-3">
            <label class="lbl">{{ __('calc.material') }}
                <select id="param-material" class="field">
                    @foreach($config['materials'] as $m)<option value="{{ $m['code'] }}" @selected($m['code'] === $config['default_material'])>{{ $m['label'] }} ({{ $m['code'] }})</option>@endforeach
                </select>
            </label>
            <label class="lbl">{{ __('calc.quantity') }}
                <input id="param-qty" type="number" inputmode="numeric" min="1" max="1000" value="1" class="field">
            </label>
        </div>
        {{-- the slicer project straight from here: the design is saved and the printer picker opens --}}
        <button id="param-3mf" type="button" class="hidden">{{ __('param.download.project') }}</button>
    </x-tool-section>
</form>
@endsection
