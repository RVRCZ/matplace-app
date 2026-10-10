@php
    $family = $family ?? null;
    $place = $place ?? [];
    $integer = fn (array $f) => $f[3] === 1;
    $unit = fn (string $k) => in_array($k, ['rows', 'cols', 'count', 'ribs', 'colors_n', 'bg_strength', 'spikes', 'links', 'soften'], true) ? '' : (in_array($k, ['angle', 'twist'], true) ? '°' : (in_array($k, ['flute', 'contrast', 'brightness', 'saturation', 'eye_pos', 'text_size', 'text_y', 'darkness'], true) ? '%' : 'mm'));
    $unitOf = $unit;
    $unit = fn (string $k) => ['density' => '', 'portrait_scale' => '×', 'portrait_turn' => '°', 'detail' => '%', 'trim' => '%'][$k] ?? $unitOf($k);
    $i18n = collect(['param.working', 'param.failed', 'param.preview_failed', 'param.too_fast', 'param.text_required', 'param.estimate', 'param.outer', 'param.inner', 'param.cell', 'param.slot', 'param.hole', 'param.hole.remove', 'param.creating', 'param.too_many_holes',
        'param.warn.stand_angle_45', 'param.warn.stand_angle_55', 'param.warn.stand_angle_70', 'param.wall.front', 'param.wall.back', 'param.wall.left', 'param.wall.right', 'param.shape.circle', 'param.shape.rect', 'param.hole.w', 'param.hole.d', 'param.hole.h', 'param.hole.x', 'param.hole.z',
        'param.part.body', 'param.part.lid', 'param.part.all', 'param.part.saucer', 'param.part.handle', 'param.part.stand', 'param.part.imprint', 'param.part.cut', 'param.part.body.logo', 'param.part.stand.logo', 'param.part.body.notes', 'param.part.stand.notes', 'param.part.body.hair_tie', 'param.part.stand.hair_tie', 'param.part.body.candle_stand', 'param.part.stand.candle_stand', 'param.part.body.vase', 'param.part.body.stamp', 'param.part.body.qr', 'param.part.body.lightbox', 'param.warn.floating_pieces', 'param.need.glue_optional', 'param.part.tray', 'param.part.bin', 'param.bom', 'param.bom.line', 'param.unit', 'param.bins.free', 'param.bins.pick_end', 'param.bins.taken', 'param.bins.bin', 'param.bins.empty',
        'color.white', 'color.black', 'color.grey', 'color.brown', 'color.red', 'color.blue', 'color.green', 'color.yellow', 'color.orange', 'param.part.face', 'param.part.diffuser', 'param.part.back', 'param.part.plate', 'param.part.text', 'param.part.stamp', 'param.bridges', 'param.lightbox.led', 'param.need.led_strip8', 'param.need.led_strip10', 'param.need.led_module', 'param.need.usb_power', 'param.need.tape', 'param.view', 'param.artwork.uploading', 'param.artwork.failed', 'param.artwork.remove',
        'param.warn.cup_narrow', 'param.warn.pieces_tied', 'param.warn.letters_tied', 'param.warn.stand_tippy', 'param.warn.papel_airy', 'param.warn.papel_lost', 'param.papel.ties', 'param.warn.insert_big', 'param.insert.things', 'param.cup.pocket', 'param.beads.count', 'param.part.body.beads', 'param.part.text.beads', 'param.warn.thread_try', 'param.warn.seal_try', 'param.need.liner', 'param.fits', 'param.warn.thin_lines', 'param.warn.outlines_ignored', 'param.warn.missing_chars', 'param.warn.separate_pieces', 'param.need.glue', 'param.needs', 'param.qr.facts', 'param.warn.qr_one_color', 'param.warn.qr_low_contrast', 'param.warn.qr_inverted', 'param.vase.facts', 'param.saucer'])->mapWithKeys(fn ($k) => [$k => __($k)])->all();
    if ($family === 'shape') {
        // the picture in colours: its list of colours, the notes on how it prints, the eyelet
        $i18n += collect(['part.body', 'part.rim', 'part.color', 'colors.share', 'colors.up', 'colors.down', 'colors.merge', 'colors.merge.into', 'colors.split', 'colors.found', 'colors.picture', 'print.one', 'print.swap1', 'print.swap', 'print.many', 'print.multi',
            'eyelet.drag', 'eyelet.top', 'each', 'pair', 'warn.pieces_tied', 'warn.magnet_no_room', 'warn.magnet_shows', 'warn.name_small', 'warn.name_no_room', 'warn.caption_photo', 'part.icing', 'thickened', 'magnet.fact', 'chain.fact', 'pockets.grid', 'pockets.holes', 'pin.fact', 'stand.fact',
            // a part may be called by what it is in this tool (the plate of a gingerbread is "the gingerbread")
            ...array_filter(['part.body.'.$kind, 'part.color_1.'.$kind], fn ($k) => \Illuminate\Support\Facades\Lang::has('param.shape.'.$k))])->mapWithKeys(fn ($k) => ['shape.'.$k => \App\Support\NextStep::text(\Illuminate\Support\Facades\Lang::has('param.shape.'.$k.'.'.$kind) ? 'param.shape.'.$k.'.'.$kind : 'param.shape.'.$k)])->all();
    }
    if ($kind === 'papel') {
        // the portrait: its two parts, its warnings, what the frame in the preview says while it is dragged
        $i18n += collect(['part.body.papel', 'part.details.papel', 'warn.portrait_fine', 'warn.portrait_whole', 'papel.swap', 'papel.one', 'papel.place.at', 'papel.place.size'])
            ->mapWithKeys(fn ($k) => ['param.'.$k => \App\Support\NextStep::text('param.'.$k)])->all();
    }
    if ($kind === 'compose') {
        $i18n += collect(['layer.text', 'layer.art', 'layer.shape', 'layer.up', 'layer.down', 'layer.hide', 'layer.show', 'layer.copy', 'layer.remove', 'limit', 'empty', 'unit'])->mapWithKeys(fn ($k) => ['compose.'.$k => __('param.compose.'.$k)])->all();
    }
    if ($kind === 'cookie') {
        $i18n += collect(['draw', 'draw.on', 'count', 'hint', 'limit', 'stroke', 'stroke.move', 'stroke.remove', 'nib.round', 'nib.flat', 'nib.dots', 'nib.candy', 'nib.sprinkles'])->mapWithKeys(fn ($k) => ['cookie.'.$k => __('param.cookie.'.$k)])->all();
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
    // a colour field starts at a colour, never at a spool: the built-in name of its first option as the colour it stands for
    $spool = fn (string $name) => $palette['named'][$name] ?? $name;
    $colorChoices = collect($choices)->filter(fn ($o, $k) => \App\Domain\Tools\ParametricGenerator::isColor($k));
    $plainChoices = collect($choices)->reject(fn ($o, $k) => \App\Domain\Tools\ParametricGenerator::isColor($k));
    $hasInput = $presets || $texts || $artwork || $plainChoices->isNotEmpty();
    $sections = array_filter([
        'input' => $hasInput ? __('toolpage.section.input') : null,
        'size' => __('toolpage.section.size'),
        'colors' => __('toolpage.section.colors'),
    ]);     // the last step, the material and the number of pieces, is the page's own (tools/page.blade.php)
    // steps only this tool has (ParametricGenerator::SECTIONS: id → the step it follows), and its own names for the common ones
    $extra = \App\Domain\Tools\ParametricGenerator::SECTIONS[$kind] ?? [];
    if ($extra) {
        $ordered = [];
        foreach ($sections as $id => $title) {
            $ordered[$id] = \Illuminate\Support\Facades\Lang::has('param.'.$kind.'.step.'.$id) ? __('param.'.$kind.'.step.'.$id) : $title;
            foreach (array_keys($extra, $id, true) as $step) {
                $ordered[$step] = __('param.'.$kind.'.step.'.$step);
            }
        }
        $sections = $ordered;
    }
    $first = \App\Domain\Tools\ParametricGenerator::FIRST[$kind] ?? null;      // the choice the first step opens with
    // fields, flags and choices that belong to one choice only: the script hides them for the others
    $whenOf = fn (string $key) => isset($when[$key]) ? 'data-when="'.e($when[$key][0].'='.implode(',', $when[$key][1])).'"' : '';
    // which wall of the model a size moves when it is dragged in the viewer (x width, y depth, z height)
    $handles = \App\Domain\Tools\ParametricGenerator::HANDLES[$kind] ?? [];
    // which section a warning of the tool belongs to; everything else is about the size
    $warnAt = ['portrait_fine' => 'input', 'portrait_whole' => 'input', 'cup_narrow' => 'input', 'thin_lines' => 'input', 'outlines_ignored' => 'input', 'missing_chars' => 'input', 'separate_pieces' => 'input', 'floating_pieces' => 'input', 'pieces_tied' => 'input', 'letters_tied' => 'input', 'qr_one_color' => 'colors', 'qr_low_contrast' => 'colors', 'qr_inverted' => 'colors'];
    $folded = \App\Domain\Tools\ParametricGenerator::FOLDED;
    $fieldsAt = fn (string $section) => collect($fields)->filter(fn ($f, $k) => $at($k, 'size') === $section);
    $flagsAt = fn (string $section) => collect($flags)->filter(fn ($flag) => $at($flag, 'size') === $section);
@endphp

@extends('tools.page', ['tool' => $tool ?? $kind, 'module' => 'param', 'lead' => __('param.'.($tool ?? $kind).(($quickForm ?? null) === true ? '.lead_form' : '.lead')), 'sections' => $sections, 'available' => $available])

@push('head')
<script>
    window.MP_PARAM = {
        kind: @json($kind),
        family: @json($family),
        sample: @json($sample ?? null),
        preset: @json($preset ?? null),
        captioned: @json($captioned ?? false),
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

@section('stage')
    <div id="param-bom" class="card hidden p-4 text-sm"></div>
    <p class="text-xs text-muted">{{ \App\Support\NextStep::text($own('tip')) }}</p>
@endsection

@section('panel')
<form id="param-form" novalidate>
    {{-- 1 · what it is made from: a preset to start with, the text, the picture, the kind of thing --}}
    @if($hasInput)
    <x-tool-section id="input" :title="$sections['input']">
        @if($first && isset($plainChoices[$first]))
            {{-- what is made of the picture comes before the picture: the rest of the step depends on it --}}
            <fieldset>
                <legend class="lbl">{{ $tr('c', $first) }}</legend>
                <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                    @foreach($plainChoices[$first] as $i => $o)
                        <label class="tool-choice">
                            <input type="radio" name="c-{{ $first }}" data-choice="{{ $first }}" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ $tr('o', $o) }}
                        </label>
                    @endforeach
                </div>
                @if(\Illuminate\Support\Facades\Lang::has('param.c.'.$kind.'.'.$first.'.hint'))<p class="hint mt-1 !text-xs">{{ __('param.c.'.$kind.'.'.$first.'.hint') }}</p>@endif
            </fieldset>
        @endif
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
                    {{-- a tool about a photo takes it right here (its own block); the window with the library stays one button away --}}
                    @includeIf('tools._'.$kind.'_upload')
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="button" id="param-artwork-open" class="btn-quiet !min-h-10 gap-1.5 !px-3 !py-2 text-sm" @if(\Illuminate\Support\Facades\Lang::has('param.'.$kind.'.library')) data-label="{{ __('param.'.$kind.'.library') }}" @endif><x-icon name="image" class="h-4 w-4" />{{ \Illuminate\Support\Facades\Lang::has('param.'.$kind.'.library') ? __('param.'.$kind.'.library') : __('toolpage.artwork.choose') }}</button>
                        <span id="param-artwork-thumb" class="hidden h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-line bg-white p-1"></span>
                        <p id="param-artwork-state" class="text-sm text-muted" aria-live="polite"></p>
                    </div>
                    <p class="hint mt-1 !text-xs">{{ __(\Illuminate\Support\Facades\Lang::has('param.'.$kind.'.artwork.hint') ? 'param.'.$kind.'.artwork.hint' : 'param.artwork.hint') }}</p>
                    <input id="param-artwork" type="file" accept=".svg,image/svg+xml,image/png,image/jpeg,image/webp" class="sr-only" tabindex="-1" aria-hidden="true">
                @endif
            </fieldset>
        @endif

        @if($extra)
            {{-- a tool with steps of its own: its block for this step (the two small pictures of a portrait), then what it placed here --}}
            @includeIf('tools._'.$kind.'_input')
            @include('tools._placed', ['step' => 'input'])
        @endif

        @if($kind === 'compose')
            {{-- the layers of a composition, from the bottom up (the list shows the top one first, as they lie), and the fields of the chosen one --}}
            <fieldset class="min-w-0">
                <legend class="lbl">{{ __('param.compose.layers') }}</legend>
                <p class="hint !text-xs">{{ __('param.compose.hint') }}</p>
                <div id="compose-layers" class="mt-2 space-y-1"></div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach(['text', 'art', 'shape'] as $add)
                        <button type="button" class="chip !py-1 text-sm" data-add-layer="{{ $add }}">+ {{ __('param.compose.add.'.$add) }}</button>
                    @endforeach
                </div>
                <div id="compose-edit" class="mt-3 hidden space-y-3 rounded-lg border border-line bg-slate-50 p-3">
                    <label class="block text-sm font-medium text-ink" data-layer-for="text">{{ __('param.compose.text') }}
                        <input id="compose-text" maxlength="40" class="field">
                    </label>
                    <label class="block text-sm font-medium text-ink" data-layer-for="text">{{ __('param.c.typeface') }}
                        <select id="compose-font" class="field">
                            @foreach($fonts ?? [] as $face => $font)<option value="{{ $face }}">{{ $font[1] }}</option>@endforeach
                        </select>
                    </label>
                    <div class="flex items-center gap-2" data-layer-for="art">
                        <button type="button" id="compose-art" class="btn-quiet !min-h-10 gap-1.5 !px-3 !py-2 text-sm"><x-icon name="image" class="h-4 w-4" />{{ __('toolpage.artwork.change') }}</button>
                        <span id="compose-art-name" class="min-w-0 truncate text-sm text-muted"></span>
                    </div>
                    <label class="block text-sm font-medium text-ink" data-layer-for="shape">{{ __('param.compose.shape') }}
                        <select id="compose-shape" class="field">
                            @foreach($layerShapes ?? [] as $shape)<option value="{{ $shape }}">{{ __(\Illuminate\Support\Facades\Lang::has('param.o.sign.'.$shape) ? 'param.o.sign.'.$shape : 'param.compose.shape.'.$shape) }}</option>@endforeach
                        </select>
                    </label>
                    <div class="tool-swatch-row">
                        <button type="button" class="tool-swatch" id="compose-color" aria-label="{{ __('toolpage.color.pick') }}"></button>
                        <span class="min-w-0 text-sm"><span class="block font-medium text-ink">{{ __('param.compose.color') }}</span><span class="block truncate text-muted" id="compose-color-name"></span></span>
                    </div>
                    @foreach(['w' => [5, 250, 1], 'x' => [-150, 150, 0.5], 'y' => [-150, 150, 0.5], 'turn' => [-180, 180, 1]] as $slide => [$lo, $hi, $by])
                        <label class="block text-sm font-medium text-ink">{{ __('param.compose.'.$slide) }}
                            <span class="mt-1 flex items-center gap-3"><input type="range" data-layer-slide="{{ $slide }}" min="{{ $lo }}" max="{{ $hi }}" step="{{ $by }}" class="min-w-0 flex-1 accent-ink"><span class="num w-16 text-right text-muted" data-layer-value="{{ $slide }}"></span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        @foreach($plainChoices as $key => $options)
            @continue($at($key, 'input') !== 'input' || $key === $first)
            @if($key === 'typeface' && count($options) > 4)
                @include('tools._fonts')
                @continue
            @endif
            @if($key === 'shape' && $kind === 'sign')
                @include('tools._shapes')
                @continue
            @endif
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
        @if(($quickForm ?? null) === false)
            {{-- the address is the composer now; the quick form it used to be does what layers cannot (a picture in several colours, a plate that sizes itself) --}}
            <p class="hint !text-xs">{{ __('param.compose.form.'.$tool) }} <a href="{{ url()->current() }}?form=1" class="font-semibold text-action-dark underline" rel="nofollow">{{ __('param.compose.form.open') }}</a></p>
        @elseif(($composeAs ?? null) && (($quickForm ?? null) === true || \App\Domain\Tools\ToolVisibility::canOpen(auth()->user(), 'compose')))
            {{-- the form lays the thing out by itself; who wants more pieces or his own layout takes it to the composer --}}
            {{-- (the composer of this very address, or the composer's own page where that one is open to the visitor) --}}
            <p class="hint !text-xs">{{ __('param.compose.more') }} <a href="{{ ($quickForm ?? null) === true ? url()->current() : route('tools.compose', ['preset' => $composeAs]) }}" class="font-semibold text-action-dark underline">{{ __('param.compose.open') }}</a></p>
        @endif
    </x-tool-section>
    @endif

    @foreach(array_keys($extra, 'input', true) as $step)
        @include('tools._section', ['step' => $step])
    @endforeach

    {{-- 2 · sizes: the main ones with a slider (and a handle in the viewer), the rest under "more" --}}
    <x-tool-section id="size" :title="$sections['size']">
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
        @if($family === 'shape' && array_intersect(['eyelet', 'hang'], $flags))
            {{-- the eyelet goes where it is dragged to in the preview; this puts it back on top --}}
            <div data-when="{{ in_array('hang', $flags, true) ? 'hang' : 'eyelet' }}=on">
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

    @foreach(array_keys($extra, 'size', true) as $step)
        @include('tools._section', ['step' => $step])
    @endforeach

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
            <div class="tool-swatch-row">
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
                <div class="tool-swatch-row mt-3">
                    <button type="button" class="tool-swatch" id="cookie-pen" aria-label="{{ __('param.cookie.pen') }}: {{ __('toolpage.color.pick') }}"></button>
                    <span class="min-w-0 text-sm"><span class="block font-medium text-ink">{{ __('param.cookie.pen') }}</span><span class="block truncate text-muted" id="cookie-pen-name"></span></span>
                </div>
                <label class="mt-3 block text-sm font-medium text-ink">{{ __('param.cookie.width') }}
                    <span class="mt-1 flex items-center gap-3"><input type="range" id="cookie-width" min="1.5" max="4" step="0.5" value="2.5" class="min-w-0 flex-1 accent-ink"><span class="num w-14 text-right text-muted" id="cookie-width-v">2,5 mm</span></span>
                </label>
                <div class="mt-3 flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('param.cookie.nib') }}">
                    @foreach(['round', 'flat', 'dots', 'candy', 'sprinkles'] as $i => $nib)
                        <label class="tool-choice"><input type="radio" name="cookie-nib" value="{{ $nib }}" class="sr-only" @checked($i === 0)>{{ __('param.cookie.nib.'.$nib) }}</label>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <button type="button" id="cookie-undo" class="chip !py-1 text-sm">{{ __('param.cookie.undo') }}</button>
                    <button type="button" id="cookie-clear" class="chip !py-1 text-sm">{{ __('param.cookie.clear') }}</button>
                </div>
                <p id="cookie-count" class="hint mt-1 !text-xs" aria-live="polite"></p>
                <div id="cookie-strokes" class="mt-2 space-y-1"></div>
            </fieldset>
        @endif
        {{-- the parts of the design, filled by the script as the preview says which there are --}}
        <div id="tool-parts" class="space-y-2" data-own-colors="{{ $colorChoices->isNotEmpty() || in_array($kind, ['modular', 'compose'], true) ? '1' : '0' }}"></div>
        @if($kind === 'modular')<p class="hint !text-xs">{{ __('toolpage.color.bins') }}</p>@endif
        @if($kind === 'papel')<p class="hint !text-xs" data-when="treatment=portrait">{{ \App\Support\NextStep::text('param.papel.colors.hint') }}</p>@endif
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
        {{-- the one colour the order starts from (the first part's); two-colour designs carry theirs in the design --}}
        <input type="hidden" id="param-color" value="">
        {{-- the slicer project straight from here: the design is saved and the printer picker opens --}}
        <button id="param-3mf" type="button" class="hidden">{{ __('param.download.project') }}</button>
    </x-tool-section>
    {{-- 4 · the print, material and how many: the last step of every tool, drawn by tools/page.blade.php --}}
</form>
@endsection
