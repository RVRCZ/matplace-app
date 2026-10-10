{{--
    The one page every tool lives on: a 340 px panel of numbered sections on the left (all visible, the current one
    is marked as the page scrolls), the viewer over the full height on the right with its views, x-ray, spread view,
    print bed and status line, and under it the price with the two ways on: print it with us / download.

    A tool's own view extends this one and fills:
      @section('panel')        its sections, each <x-tool-section id="input" …> (resources/views/components/tool-section.blade.php)
      @section('stage')        optional extra blocks under the price (reports, a second viewer)
      @section('price-note')   optional text under the price (every tool has the same one today: what the estimate is, what the next step shows)
    and passes: tool (key in config/tools.php), module (which script drives the page: resources/js/calc/tool_page.ts),
    lead (one sentence), sections (id => label, in order), available + unavailable (the tool's engine is missing).
    Scripts: window.MP_TOOL (shared) next to the tool's own window.MP_<TOOL>.
--}}
@extends('layouts.app', ['title' => __('tools.'.$tool.'.title').' · matplace', 'tool' => $tool, 'wide' => true])

@php
    $available = $available ?? true;
    $next = \App\Support\NextStep::mode();
    $bed = config('pricing.bed_mm');
    $shared = [
        'tool' => $tool, 'module' => $module, 'locale' => app()->getLocale(), 'next' => $next,
        'home' => route('home'), 'files' => url('/api/files'), 'tools' => route('tools'),
        'bed' => ['x' => (float) $bed['x'], 'y' => (float) $bed['y'], 'z' => (float) $bed['z'], 'margin' => (float) ($config['bed_margin_mm'] ?? 0)],
        'colors' => $config['colors'] ?? app(\App\Domain\Farm\Palette::class)->payload(),
        // what the rough price is counted from (resources/js/calc/rough.ts); a tool without it shows no estimate
        'price' => isset($config['rough']) ? \Illuminate\Support\Arr::only($config, ['rough', 'orientation_profiles', 'round_to', 'materials', 'default_material']) : null,
        'artwork' => ['upload' => route('api.tools.artwork'), 'library' => route('api.artwork.library'), 'mine' => route('api.artwork.mine'), 'file' => url('/api/artwork/file')],
        'zip' => route('api.tools.param.zip'),
        'i18n' => collect(\Illuminate\Support\Arr::dot(['toolpage' => trans('toolpage')]))->all() + ['farm.finish' => collect((array) trans('farm.finish'))->all()],
    ];
@endphp

@push('head')
<script>window.MP_TOOL = {{ \Illuminate\Support\Js::from($shared) }};</script>
@endpush

@section('content')
<div id="tool-page" data-tool="{{ $tool }}" data-module="{{ $module }}" class="grid gap-4 lg:grid-cols-[340px_minmax(0,1fr)] lg:items-start">
    {{-- ── the panel ── --}}
    <div id="tool-panel" class="order-2 min-w-0 lg:order-1">
        <a href="{{ route('tools') }}" class="inline-flex items-center gap-1 text-sm text-muted hover:text-ink"><x-icon name="arrow-left" class="h-4 w-4" />{{ __('tools.title') }}</a>
        <div class="mt-1 flex items-start justify-between gap-2">
            <h1 class="text-2xl font-semibold leading-tight text-ink">{{ __('tools.'.$tool.'.title') }}</h1>
            <div class="flex shrink-0 gap-1" role="group" aria-label="{{ __('toolpage.history') }}">
                <button type="button" id="tool-undo" class="tool-icon-btn" title="{{ __('toolpage.undo') }} (Ctrl+Z)" aria-label="{{ __('toolpage.undo') }}" disabled><x-icon name="undo-2" class="h-4 w-4" /></button>
                <button type="button" id="tool-redo" class="tool-icon-btn" title="{{ __('toolpage.redo') }} (Ctrl+Shift+Z)" aria-label="{{ __('toolpage.redo') }}" disabled><x-icon name="redo-2" class="h-4 w-4" /></button>
            </div>
        </div>
        <p class="mt-1 text-sm text-muted">{{ $lead }}</p>
        @if($verified = config('tools.'.$tool.'.verified'))
            <p class="mt-2 inline-flex items-center gap-1 rounded-full bg-ok-soft px-2.5 py-1 text-xs font-medium text-ok"><x-icon name="badge-check" class="h-3.5 w-3.5" />{{ __('toolpage.verified', ['date' => \Illuminate\Support\Carbon::parse($verified)->isoFormat('LL')]) }}</p>
        @endif

        @unless($available)
            <div class="note-warn mt-4 text-sm">{{ $unavailable ?? __('sign.unavailable') }}</div>
        @else
            {{-- the steps, as anchors: every section stays on the page, the one in view is marked --}}
            {{-- four steps do not fit 340 px in one row ("Tisk nebo stažení"): they wrap, nothing is cut off --}}
            <nav id="tool-nav" class="sticky top-0 z-10 -mx-1 mt-3 flex flex-wrap gap-1 bg-page px-1 py-2" aria-label="{{ __('param.steps') }}">
                @foreach($sections as $id => $label)
                    <a href="#sec-{{ $id }}" data-nav="{{ $id }}" class="tool-nav-item"><span class="tool-nav-no">{{ $loop->iteration }}</span>{{ $label }}</a>
                @endforeach
            </nav>
            <button type="button" id="tool-restore" class="chip mb-2 hidden items-center gap-1.5 !py-1 text-xs"><x-icon name="rotate-ccw" class="h-3.5 w-3.5" />{{ __('toolpage.restore') }}</button>
            @yield('panel')
        @endunless
    </div>

    {{-- ── the stage: viewer, status, price ── --}}
    <div id="tool-stage" class="order-1 flex min-w-0 flex-col gap-3 lg:sticky lg:top-3 lg:order-2 lg:h-[calc(100vh-1.5rem)] lg:min-h-[560px]">
        <div class="card relative flex min-h-0 flex-1 flex-col overflow-hidden">
            <div class="relative min-h-0 flex-1">
                <canvas id="tool-viewer" class="block h-[55vh] w-full touch-none lg:absolute lg:inset-0 lg:h-full" role="img" aria-label="{{ __('param.viewer') }}"></canvas>
                <div id="tool-empty" class="pointer-events-none absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-muted">@yield('viewer-empty')</div>
                <div class="pointer-events-none absolute inset-x-3 top-3 flex flex-wrap items-start justify-between gap-2">
                    <div class="pointer-events-auto flex flex-col items-start gap-2">
                        <div id="tool-views" class="tool-bar" role="group" aria-label="{{ __('toolpage.views') }}">
                            @foreach(['iso', 'top', 'front', 'side', 'bottom'] as $v)
                                <button type="button" data-view="{{ $v }}" class="tool-bar-btn" @if($v === 'iso') aria-pressed="true" @endif>{{ __('toolpage.view.'.$v) }}</button>
                            @endforeach
                        </div>
                        {{-- what the preview shows: the whole set, one part, the imprint of a stamp… (filled by the tool) --}}
                        <div id="param-views" class="flex flex-wrap gap-1" role="group" aria-label="{{ __('param.view') }}"></div>
                    </div>
                    <div class="pointer-events-auto flex flex-wrap items-center justify-end gap-2">
                        <label id="tool-spread-wrap" class="tool-bar hidden items-center gap-2 px-2.5 text-xs font-medium text-ink"><x-icon name="layers" class="h-4 w-4" />{{ __('toolpage.spread') }}
                            <input id="tool-spread" type="range" min="0" max="100" value="0" class="w-24 accent-ink" aria-label="{{ __('toolpage.spread') }}">
                        </label>
                        <div class="tool-bar" role="group" aria-label="{{ __('toolpage.display') }}">
                            <button type="button" id="tool-fit" class="tool-bar-btn" title="{{ __('toolpage.fit') }}"><x-icon name="maximize" class="h-4 w-4" /><span class="hidden xl:inline">{{ __('toolpage.fit') }}</span></button>
                            <button type="button" id="tool-xray" class="tool-bar-btn" aria-pressed="false" title="{{ __('toolpage.xray') }}"><x-icon name="scan" class="h-4 w-4" /><span class="hidden xl:inline">{{ __('toolpage.xray') }}</span></button>
                            <button type="button" id="tool-bed" class="tool-bar-btn" aria-pressed="true" title="{{ __('toolpage.bed', ['x' => (int) $bed['x'], 'y' => (int) $bed['y']]) }}"><x-icon name="grid-3x3" class="h-4 w-4" /><span class="hidden xl:inline">{{ __('toolpage.bed.short') }}</span></button>
                        </div>
                    </div>
                </div>
                <div id="tool-busy" class="absolute bottom-3 right-3 hidden items-center gap-1.5 rounded-full bg-card/95 px-3 py-1 text-xs text-muted shadow-sm"><x-icon name="refresh-cw" class="h-3.5 w-3.5 animate-spin" />{{ __('param.working') }}</div>
            </div>
            <div id="tool-status" class="num flex min-h-10 flex-wrap items-center gap-x-2 gap-y-0.5 border-t border-line px-4 py-2 text-sm text-ink" role="status" aria-live="polite"><span class="text-muted">{{ __('toolpage.status.empty') }}</span></div>
            <dl id="param-dims" class="hidden grid-cols-1 gap-x-6 gap-y-1 border-t border-line px-4 py-2 text-sm sm:grid-cols-2" aria-live="polite"></dl>
        </div>

        <div id="tool-error" class="note-error hidden text-sm" role="alert"></div>

        {{-- the price and the two ways on --}}
        <div id="tool-price-card" class="card flex flex-wrap items-center gap-x-6 gap-y-3 p-4">
            <div class="min-w-[11rem] flex-1">
                <div class="text-xs font-medium uppercase tracking-wide text-muted">{{ __('param.estimate.title') }}</div>
                <div id="tool-price" class="num text-2xl font-semibold text-ink" aria-live="polite">—</div>
                <div id="tool-price-sub" class="num text-sm text-muted"></div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <button type="button" id="tool-download" class="btn-secondary gap-1.5" aria-haspopup="menu" aria-expanded="false" aria-controls="tool-download-menu" disabled><x-icon name="download" class="h-4 w-4" />{{ __('toolpage.download') }}<x-icon name="chevron-down" class="h-4 w-4" /></button>
                    <div id="tool-download-menu" role="menu" class="absolute bottom-full right-0 z-20 mb-2 hidden w-72 rounded-xl border border-line bg-card p-1 text-sm shadow-sm"></div>
                </div>
                <button type="button" id="tool-go" class="btn-primary gap-1.5" disabled><x-icon name="{{ $next === 'download' ? 'arrow-right' : 'printer' }}" class="h-4 w-4" /><span id="tool-go-label">{{ \App\Support\NextStep::text('param.go') }}</span></button>
            </div>
            <p class="w-full text-xs text-muted">@hasSection('price-note')@yield('price-note')@else{{ \App\Support\NextStep::text('param.estimate.note') }} {{ \App\Support\NextStep::text('param.go.hint') }}@endif</p>
        </div>
        @yield('stage')
    </div>
</div>
@endsection
