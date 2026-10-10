@php
    // the 3D map of a city or a landscape (session B, docs/W.md): a place, its area from above, the settings, the colours
    $unit = fn (string $k) => $k === 'exaggeration' ? '×' : ($k === 'default_h' ? 'm' : 'mm');
    $i18n = collect(['param.working', 'param.failed', 'param.preview_failed', 'param.too_fast', 'param.creating', 'toolpage.color.pick', 'toolpage.color.out', 'param.go'])->mapWithKeys(fn ($k) => [$k => \App\Support\NextStep::text($k)])
        ->merge(collect(['place.searching', 'place.none', 'place.pick', 'place.chosen', 'place.preview.loading', 'place.preview.facts', 'place.preview.facts.landscape', 'scale', 'facts.city', 'facts.landscape',
            'part.base', 'part.roads', 'part.buildings', 'part.water', 'part.terrain', 'go', 'creating', 'queued', 'failed',
            'stage.queued', 'stage.osm', 'stage.terrain', 'stage.reading', 'stage.buildings', 'stage.roads', 'stage.terrain_mesh', 'stage.writing', 'stage.done',
            'error.osm_down', 'error.dem_down', 'error.places_down', 'error.busy', 'error.area_too_big', 'error.daily_limit', 'error.map_failed', 'error.empty_result', 'error.frame_too_wide', 'error.osm_unreadable', 'error.not_yet',
            'warn.no_buildings', 'warn.no_roads', 'warn.flat'])->mapWithKeys(fn ($k) => ['map.'.$k => $k === 'error.daily_limit' ? __('map.'.$k, ['n' => $daily['anon'], 'h' => 24]) : __('map.'.$k)]))->all();      // (only the limit's text has numbers: ":n" would eat ":name")
    $sections = ['place' => __('map.step.place'), 'settings' => __('map.step.settings'), 'colors' => __('map.step.colors')];
@endphp

@extends('tools.page', ['tool' => 'map', 'module' => 'map', 'lead' => __('map.lead'), 'sections' => $sections, 'available' => $available, 'unavailable' => __('map.unavailable'), 'goLabel' => __('map.go')])

@push('head')
<script>
    window.MP_MAP = {
        places: @json(route('api.tools.map.places')),
        preview: @json(route('api.tools.map.preview')),
        create: @json(route('api.tools.map')),
        home: @json(route('home')),
        files: @json(url('/api/files')),
        from: @json($from),
        sides: {{ \Illuminate\Support\Js::from($sides) }},
        parts: {{ \Illuminate\Support\Js::from($parts) }},
        colors: {{ \Illuminate\Support\Js::from($colors) }},
        i18n: {{ \Illuminate\Support\Js::from($i18n) }},
    };
</script>
@endpush

@section('viewer-empty'){{ __('map.place.hint') }}@endsection

@section('stage')
    <p id="map-facts" class="hidden text-sm text-ink"></p>
    <p class="text-xs text-muted">{{ \App\Support\NextStep::text('map.tip') }}</p>
    <p class="text-xs text-muted">{{ __('map.attribution') }}</p>
@endsection

@section('panel')
<form id="map-form" novalidate>
    {{-- 1 · the place: a name or coordinates, the places it may mean, the kind of map, the range, and the area from above --}}
    <x-tool-section id="place" :title="__('map.step.place')">
        <label class="block text-sm font-medium text-ink" for="map-place">{{ __('map.place.label') }}</label>
        <div class="mt-1 flex gap-2">
            <input id="map-place" type="text" class="field !mt-0 min-w-0 flex-1" placeholder="{{ __('map.place.placeholder') }}" autocomplete="off" maxlength="120">
            <button type="button" id="map-search" class="btn-quiet !min-h-10 shrink-0 gap-1.5 !px-3 !py-2 text-sm"><x-icon name="search" class="h-4 w-4" />{{ __('map.place.search') }}</button>
        </div>
        <p class="hint !text-xs">{{ __('map.place.hint') }}</p>
        <p id="map-place-state" class="hidden text-sm text-muted" aria-live="polite"></p>
        <div id="map-places" class="hidden space-y-1" role="listbox"></div>
        <p id="map-chosen" class="hidden rounded-lg bg-page px-3 py-2 text-sm font-medium text-ink" aria-live="polite"></p>

        <fieldset>
            <legend class="lbl">{{ __('map.c.type') }}</legend>
            <div class="mt-2 grid gap-2" role="radiogroup">
                @foreach($choices['type'] as $i => $o)
                    <label class="tool-choice !justify-start !text-left">
                        <input type="radio" name="c-type" data-choice="type" value="{{ $o }}" class="sr-only" @checked($i === 0)>
                        <span class="block"><span class="block font-medium">{{ __('map.o.type.'.$o) }}</span><span class="block text-xs font-normal opacity-80">{{ __('map.o.type.'.$o.'.hint') }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <fieldset>
            <legend class="lbl">{{ __('map.c.side') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach($choices['side'] as $o)
                    <label class="tool-choice" data-side-for="{{ implode(',', array_keys(array_filter($sides, fn ($list) => in_array((int) $o, $list, true)))) }}">
                        <input type="radio" name="c-side" data-choice="side" value="{{ $o }}" class="sr-only" @checked($o === '1000')>{{ __('map.o.side.'.$o) }}
                    </label>
                @endforeach
            </div>
            <p class="hint mt-1 !text-xs">{{ __('map.c.side.hint') }}</p>
        </fieldset>

        {{-- the area from above, drawn by the server from the same data the model is built from --}}
        <div id="map-preview-box" class="hidden">
            <div class="lbl">{{ __('map.place.preview') }}</div>
            <div class="relative mt-2 overflow-hidden rounded-xl border border-line bg-white">
                <img id="map-preview" alt="" class="block aspect-square w-full">
                <div id="map-preview-busy" class="absolute inset-0 hidden items-center justify-center bg-white/70 text-sm text-muted">{{ __('map.place.preview.loading') }}</div>
            </div>
            <p id="map-preview-facts" class="mt-1 text-xs text-muted" aria-live="polite"></p>
            <p class="hint !text-xs">{{ __('map.place.preview.hint') }}</p>
        </div>
    </x-tool-section>

    {{-- 2 · the settings: the style, then the plate, the buildings (a landscape: the relief), and what the map shows.
         Each conditional block gets !max-h-none: the shared [data-when] style caps a block at 20rem, which a block of
         several fields overflows on a phone (round 3). --}}
    <x-tool-section id="settings" :title="__('map.step.settings')">
        <fieldset>
            <legend class="lbl">{{ __('map.c.style') }}</legend>
            <div class="mt-2 grid gap-2" role="radiogroup">
                @foreach($choices['style'] as $i => $o)
                    <label class="tool-choice !justify-start !text-left">
                        <input type="radio" name="c-style" data-choice="style" value="{{ $o }}" class="sr-only" @checked($i === 0)>
                        <span class="block"><span class="block font-medium">{{ __('map.o.style.'.$o) }}</span><span class="block text-xs font-normal opacity-80">{{ __('map.o.style.'.$o.'.hint') }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="space-y-3">
            <legend class="lbl mb-2">{{ __('map.g.plate') }}</legend>
            @include('tools._num', ['key' => 'size', 'f' => $fields['size'], 'label' => __('map.f.size'), 'unit' => $unit('size'), 'when' => ''])
            <p class="hint !-mt-1 !text-xs">{{ __('map.f.size.hint') }} <span id="map-scale" class="font-medium text-ink"></span></p>
            @include('tools._num', ['key' => 'base_h', 'f' => $fields['base_h'], 'label' => __('map.f.base_h'), 'unit' => $unit('base_h'), 'when' => ''])
            <label class="flex items-start gap-3 text-sm text-ink">
                <input data-flag="frame" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" @checked(in_array('frame', $flagsOn, true))>
                <span><span class="font-medium">{{ __('map.flag.frame') }}</span><br><span class="text-muted">{{ __('map.flag.frame.hint') }}</span></span>
            </label>
            <div class="space-y-3 !max-h-none" data-when="frame=on">
                @include('tools._num', ['key' => 'frame_mm', 'f' => $fields['frame_mm'], 'label' => __('map.f.frame_mm'), 'unit' => $unit('frame_mm'), 'when' => ''])
                <label class="block text-sm font-medium text-ink">{{ __('map.t.name') }}
                    <input data-text="name" maxlength="40" class="field">
                    <span class="block text-xs font-normal text-muted">{{ __('map.t.name.hint') }}</span>
                </label>
            </div>
        </fieldset>

        <fieldset class="space-y-3 !max-h-none" data-when="type=city">
            <legend class="lbl mb-2">{{ __('map.g.buildings') }}</legend>
            @include('tools._num', ['key' => 'default_h', 'f' => $fields['default_h'], 'label' => __('map.f.default_h'), 'unit' => $unit('default_h'), 'when' => ''])
            <p class="hint !-mt-1 !text-xs">{{ __('map.f.default_h.hint') }}</p>
            <div>
                <div class="text-sm font-medium text-ink">{{ __('map.c.roofs') }}</div>
                <div class="mt-1.5 flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('map.c.roofs') }}">
                    @foreach($choices['roofs'] as $i => $o)
                        <label class="tool-choice"><input type="radio" name="c-roofs" data-choice="roofs" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('map.o.roofs.'.$o) }}</label>
                    @endforeach
                </div>
                <p class="hint mt-1.5 !text-xs">{{ __('map.c.roofs.hint') }}</p>
            </div>
        </fieldset>

        <fieldset class="space-y-3 !max-h-none" data-when="type=landscape">
            <legend class="lbl mb-2">{{ __('map.g.relief') }}</legend>
            @include('tools._num', ['key' => 'exaggeration', 'f' => $fields['exaggeration'], 'label' => __('map.f.exaggeration'), 'unit' => $unit('exaggeration'), 'when' => ''])
            <p class="hint !-mt-1 !text-xs">{{ __('map.f.exaggeration.hint') }}</p>
            <label class="flex items-start gap-3 text-sm text-ink">
                <input data-flag="towns" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" @checked(in_array('towns', $flagsOn, true))>
                <span><span class="font-medium">{{ __('map.flag.towns') }}</span><br><span class="text-muted">{{ __('map.flag.towns.hint') }}</span></span>
            </label>
        </fieldset>

        <fieldset class="space-y-3">
            <legend class="lbl mb-2">{{ __('map.g.features') }}</legend>
            <div>
                <div class="text-sm font-medium text-ink">{{ __('map.c.roads') }}</div>
                <div class="mt-1.5 flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ __('map.c.roads') }}">
                    @foreach($choices['roads'] as $i => $o)
                        <label class="tool-choice"><input type="radio" name="c-roads" data-choice="roads" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ __('map.o.roads.'.$o) }}</label>
                    @endforeach
                </div>
                <p class="hint mt-1.5 !text-xs">{{ __('map.c.roads.hint') }}</p>
            </div>
            @foreach(['water' => 'city,landscape', 'rail' => 'city', 'green' => 'city'] as $flag => $types)
                <label class="flex items-start gap-3 text-sm text-ink !max-h-none" data-when="type={{ $types }}">
                    <input data-flag="{{ $flag }}" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" @checked(in_array($flag, $flagsOn, true))>
                    <span><span class="font-medium">{{ __('map.flag.'.$flag) }}</span><br><span class="text-muted">{{ __('map.flag.'.$flag.'.hint') }}</span></span>
                </label>
            @endforeach
        </fieldset>
    </x-tool-section>

    {{-- 3 · the colours: one per part, printed one above the other by height --}}
    <x-tool-section id="colors" :title="__('map.step.colors')">
        <div id="map-parts" class="space-y-2"></div>
        <p class="hint !text-xs" data-when="type=city">{{ __('map.colors.hint') }}</p>
        <p class="hint !text-xs" data-when="type=landscape">{{ __('map.colors.hint.landscape') }}</p>
        <p id="map-wait" class="note-warn hidden text-sm" role="status" aria-live="polite"></p>
    </x-tool-section>
</form>
@endsection
