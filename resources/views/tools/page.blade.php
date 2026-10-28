{{--
    The one page every tool lives on: a 340 px panel of numbered sections on the left (all visible, the current one
    is marked as the page scrolls), the viewer over the full height on the right with its views, x-ray, spread view,
    print bed and status line, and under it the two ways on: continue to the calculation / download.

    A tool's own view extends this one and fills:
      @section('panel')        its sections, each <x-tool-section id="input" …> (resources/views/components/tool-section.blade.php)
      @section('stage')        optional extra blocks under the buttons (reports, a second viewer)
      @section('price-note')   optional text under the buttons (every tool has the same one today: what the next step shows)
    and passes: tool (key in config/tools.php), module (which script drives the page: resources/js/calc/tool_page.ts),
    lead (one sentence), sections (id => label, in order: the tool's own steps; the last one, "print" = the material and
    the number of pieces, is added here for every tool), available + unavailable (the tool's engine is missing),
    sample (a tool that starts from a photo: the sentence shown over a printed piece of it until there is a model);
    with @section('viewer-drop') the photos are picked right there, over the printed piece;
    fileInput (the id of the panel's file field of a tool that takes a model: the empty viewer takes the file too).
    Scripts: window.MP_TOOL (shared) next to the tool's own window.MP_<TOOL>.
--}}
@extends('layouts.app', ['title' => __('tools.'.$tool.'.title').' · matplace', 'tool' => $tool, 'wide' => true])

@php
    $available = $available ?? true;
    $sampleDrop = ! empty($sample) && trim($__env->yieldContent('viewer-drop')) !== '';
    $next = \App\Support\NextStep::mode();
    $bed = config('pricing.bed_mm');
    // the last step of every tool is the same: the material and how many pieces (the tool names only its own steps)
    $materials = $config['materials'] ?? [];
    $sections = ($sections ?? []) + ($materials ? ['print' => \App\Support\NextStep::text('param.step.inquiry')] : []);
    $shared = [
        'tool' => $tool, 'module' => $module, 'locale' => app()->getLocale(), 'next' => $next,
        'home' => route('home'), 'files' => url('/api/files'), 'tools' => route('tools'),
        'bed' => ['x' => (float) $bed['x'], 'y' => (float) $bed['y'], 'z' => (float) $bed['z'], 'margin' => (float) ($config['bed_margin_mm'] ?? 0)],
        'colors' => $config['colors'] ?? app(\App\Domain\Farm\Palette::class)->payload(),
        // what the rough price is counted from (resources/js/calc/rough.ts); a tool without it shows no estimate
        'price' => isset($config['rough']) ? \Illuminate\Support\Arr::only($config, ['rough', 'orientation_profiles', 'round_to', 'materials', 'default_material']) : null,
        'artwork' => ['upload' => route('api.tools.artwork'), 'library' => route('api.artwork.library'), 'mine' => route('api.artwork.mine'), 'file' => url('/api/artwork/file')],
        'zip' => route('api.tools.param.zip'),
        'shared' => route('api.shared'),   // the share event (ShareController)
        'i18n' => collect(\Illuminate\Support\Arr::dot(['toolpage' => trans('toolpage')]))->all() + ['farm.finish' => collect((array) trans('farm.finish'))->all()],
    ];
@endphp

@push('head')
<script>window.MP_TOOL = {{ \Illuminate\Support\Js::from($shared) }};</script>
@endpush

@section('content')
{{-- Under lg one column in the order a visitor from a search needs it: the name and the sentence, the model, the way on,
     then the steps (docs/AF.md §1). On lg the name and the panel on the left, the stage over both rows on the right.
     The DOM keeps the panel before the stage, so the keyboard still goes through the form before the buttons. --}}
{{-- From lg the grid fills the window under the header (stick-fit, docs/AN.md §18): the panel scrolls inside its row, the stage
     fills both rows, so the preview and the buttons stay while the options move; the text about the tool follows below the grid. --}}
<div id="tool-page" data-tool="{{ $tool }}" data-module="{{ $module }}" class="stick-fit grid gap-3 lg:grid-cols-[340px_minmax(0,1fr)] lg:grid-rows-[auto_minmax(0,1fr)] lg:items-start lg:gap-x-4 lg:gap-y-0">
    {{-- ── the name and what it is ── --}}
    <header id="tool-head" class="order-1 min-w-0 lg:col-start-1 lg:row-start-1">
        @php $heading = \App\Support\ToolSeo::heading($tool); @endphp
        {{-- a page people search for has its H1 on their query (tools_seo 'heading', docs/AP.md §2); the tool's name then stays on this line --}}
        <a href="{{ route('tools') }}" class="-my-2 inline-flex min-h-11 items-center gap-1 text-sm text-muted hover:text-ink"><x-icon name="arrow-left" class="h-4 w-4" />{{ __('tools.title') }}</a>@if($heading)<span class="-my-2 inline-flex min-h-11 items-center text-sm text-muted" id="tool-name"><span class="mx-1.5" aria-hidden="true">·</span>{{ __('tools.'.$tool.'.title') }}</span>@endif
        {{-- the name and undo/redo side by side; with the text at 200 % the buttons go under the name, never off the screen --}}
        <div class="mt-1 flex flex-wrap items-start justify-between gap-2">
            <h1 class="min-w-0 flex-1 basis-48 text-2xl font-semibold leading-tight text-ink">{{ $heading ?? __('tools.'.$tool.'.title') }}</h1>
            <div class="flex shrink-0 gap-1" role="group" aria-label="{{ __('toolpage.history') }}">
                <button type="button" id="tool-undo" class="tool-icon-btn" title="{{ __('toolpage.undo') }} (Ctrl+Z)" aria-label="{{ __('toolpage.undo') }}" disabled><x-icon name="undo-2" class="h-4 w-4" /></button>
                <button type="button" id="tool-redo" class="tool-icon-btn" title="{{ __('toolpage.redo') }} (Ctrl+Shift+Z)" aria-label="{{ __('toolpage.redo') }}" disabled><x-icon name="redo-2" class="h-4 w-4" /></button>
            </div>
        </div>
        <p id="tool-lead" class="mt-1 text-sm text-muted">{{ $lead }}</p>
        @if($verified = config('tools.'.$tool.'.verified'))
            <p class="mt-2 inline-flex items-center gap-1 rounded-full bg-ok-soft px-2.5 py-1 text-xs font-medium text-ok"><x-icon name="badge-check" class="h-3.5 w-3.5" />{{ __('toolpage.verified', ['date' => \Illuminate\Support\Carbon::parse($verified)->isoFormat('LL')]) }}</p>
        @endif
    </header>

    {{-- ── the panel ── --}}
    <div id="tool-panel" class="stick-scroll order-3 min-w-0 lg:col-start-1 lg:row-start-2 lg:self-stretch">
        @unless($available)
            <div class="note-warn text-sm lg:mt-4">{{ $unavailable ?? __('sign.unavailable') }}</div>
        @else
            {{-- the steps, as anchors: every section stays on the page, the one in view is marked --}}
            {{-- four steps do not fit 340 px in one row ("Materiál a počet kusů"): they wrap, nothing is cut off --}}
            <nav id="tool-nav" class="sticky top-0 z-10 -mx-1 flex lg:mt-3 flex-wrap gap-1 bg-page px-1 py-2" aria-label="{{ __('param.steps') }}">
                @foreach($sections as $id => $label)
                    <a href="#sec-{{ $id }}" data-nav="{{ $id }}" class="tool-nav-item"><span class="tool-nav-no">{{ $loop->iteration }}</span>{{ $label }}</a>
                @endforeach
            </nav>
            <button type="button" id="tool-restore" class="chip mb-2 hidden items-center gap-1.5 !py-1 text-xs"><x-icon name="rotate-ccw" class="h-3.5 w-3.5" />{{ __('toolpage.restore') }}</button>
            @yield('panel')
            @if($materials)
                {{-- the material and how many: the calculator opens with them (Stage.material, Stage.quantity) --}}
                <x-tool-section id="print" :title="$sections['print']" class="!mt-3">
                    {{-- the material over the whole width of the panel: beside the number of pieces its name was cut off ("Běžný plast (PL") --}}
                    <div class="grid gap-3">
                        <label class="lbl">{{ __('calc.material') }}
                            <select id="param-material" class="field">
                                @foreach($materials as $m)<option value="{{ $m['code'] }}" @selected($m['code'] === ($config['default_material'] ?? null))>{{ $m['label'] }} ({{ $m['code'] }})</option>@endforeach
                            </select>
                        </label>
                        <label class="lbl">{{ __('calc.quantity') }}
                            <input id="param-qty" type="number" inputmode="numeric" min="1" max="1000" value="1" class="field block !w-24">
                        </label>
                    </div>
                </x-tool-section>
            @endif
        @endunless
    </div>

    {{-- ── the stage: viewer, status, the ways on ── --}}
    <div id="tool-stage" class="order-2 flex min-w-0 scroll-mt-3 flex-col gap-3 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:h-full lg:min-h-0">
        <div class="card relative flex min-h-0 flex-1 flex-col overflow-hidden">
            <div class="relative min-h-0 flex-1">
                <canvas id="tool-viewer" class="block h-[46vh] w-full touch-none lg:absolute lg:inset-0 lg:h-full" role="img" aria-label="{{ __('param.viewer') }}"></canvas>
                @if(! empty($sample))
                    {{-- a tool that starts from a photo has no model yet: a printed piece of it instead of an empty box,
                         and when the model comes. The first model hides it (Stage.show), the logo of dots too. --}}
                    <div id="tool-empty" class="@unless($sampleDrop) pointer-events-none @endunless absolute inset-0 bg-studio">
                        <div id="tool-sample" class="tool-sample h-full">@include('tools.picture', ['key' => $tool, 'eager' => true, 'sizes' => '(min-width: 1024px) 60vw, 100vw'])</div>
                        @if($sampleDrop)
                            {{-- the photos go in right here (a tool's own @section('viewer-drop'): the bust, the pet) --}}
                            <div class="absolute inset-0 flex items-center justify-center p-4 pt-16">@yield('viewer-drop')</div>
                        @else
                            <p class="absolute inset-x-3 bottom-3 mx-auto max-w-md rounded-lg bg-card/95 px-3 py-2 text-center text-sm text-ink shadow-sm"><span class="block text-xs text-muted">{{ __('toolpage.sample') }}</span>{{ $sample }}</p>
                        @endif
                    </div>
                @else
                    @if(! empty($fileInput))
                        {{-- a tool that takes a model: the empty viewer is where it goes, a tap opens the panel's own file
                             field and a drop hands it over (tool_page.ts); it said "drop or tap" before and did neither --}}
                        <label id="tool-empty" for="{{ $fileInput }}" data-file-for="{{ $fileInput }}" class="absolute inset-3 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line p-6 pt-16 text-center text-sm text-muted transition hover:border-ink"><x-icon name="upload" class="h-7 w-7 text-ink" /><span>@yield('viewer-empty')</span></label>
                    @else
                        <div id="tool-empty" class="pointer-events-none absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-muted">@yield('viewer-empty')</div>
                    @endif
                @endif
                <div class="pointer-events-none absolute inset-x-3 top-3">
                    {{-- under lg the bar of views folds into one button: on a phone eight buttons over the model read as a tool for experts --}}
                    <div class="tool-bar pointer-events-auto w-fit lg:hidden">
                        <button type="button" id="tool-view-toggle" class="tool-bar-btn min-h-11 px-3 text-sm" aria-expanded="false" aria-controls="tool-viewbar"><x-icon name="eye" class="h-4 w-4" />{{ __('toolpage.views') }}<x-icon name="chevron-down" class="h-4 w-4" /></button>
                    </div>
                    <div id="tool-viewbar" class="flex flex-wrap items-start justify-between gap-2 max-lg:mt-2">
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
                                <button type="button" id="tool-fit" class="tool-bar-btn" title="{{ __('toolpage.fit') }}"><x-icon name="maximize" class="h-4 w-4" /><span class="hidden max-lg:inline xl:inline">{{ __('toolpage.fit') }}</span></button>
                                <button type="button" id="tool-xray" class="tool-bar-btn" aria-pressed="false" title="{{ __('toolpage.xray') }}"><x-icon name="scan" class="h-4 w-4" /><span class="hidden max-lg:inline xl:inline">{{ __('toolpage.xray') }}</span></button>
                                <button type="button" id="tool-bed" class="tool-bar-btn" aria-pressed="true" title="{{ __('toolpage.bed', ['x' => (int) $bed['x'], 'y' => (int) $bed['y']]) }}"><x-icon name="grid-3x3" class="h-4 w-4" /><span class="hidden max-lg:inline xl:inline">{{ __('toolpage.bed.short') }}</span></button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="tool-busy" class="absolute bottom-3 right-3 hidden items-center gap-1.5 rounded-full bg-card/95 px-3 py-1 text-xs text-muted shadow-sm"><x-icon name="refresh-cw" class="h-3.5 w-3.5 animate-spin" />{{ __('param.working') }}</div>
            </div>
            <div id="tool-status" class="num flex min-h-10 flex-wrap items-center gap-x-2 gap-y-0.5 border-t border-line px-4 py-2 text-sm text-ink" role="status" aria-live="polite"><span class="text-muted">{{ __('toolpage.status.empty') }}</span></div>
            <dl id="param-dims" class="hidden grid-cols-1 gap-x-6 gap-y-1 border-t border-line px-4 py-2 text-sm sm:grid-cols-2" aria-live="polite"></dl>
        </div>

        <div id="tool-error" class="note-error hidden text-sm" role="alert"></div>

        {{-- the two ways on; the price shows first in the calculation (no estimate here: Roman, 16 Oct 2026) --}}
        <div id="tool-go-card" class="card flex flex-wrap items-center justify-end gap-x-6 gap-y-3 p-4">
            {{-- on a phone one under the other over the whole card, the main one first: "Get the price and print options" does
                 not fit beside "Download the model" at 390 px; side by side on wider screens as before --}}
            <div class="flex w-full flex-col-reverse gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
                <div class="relative">
                    <button type="button" id="tool-download" class="btn-secondary w-full gap-1.5" aria-haspopup="menu" aria-expanded="false" aria-controls="tool-download-menu" disabled><x-icon name="download" class="h-4 w-4" />{{ __('toolpage.download') }}<x-icon name="chevron-down" class="h-4 w-4" /></button>
                    <div id="tool-download-menu" role="menu" class="absolute bottom-full right-0 z-20 mb-2 hidden w-72 rounded-xl border border-line bg-card p-1 text-sm shadow-sm"></div>
                </div>
                {{-- share the result: the phone's share sheet, else the link copied (Stage.shareable; docs/AN.md §12) --}}
                <button type="button" id="tool-share" class="btn-secondary w-full gap-1.5 sm:w-auto" hidden data-copied="{{ __('toolpage.share.copied') }}"><x-icon name="share" class="h-4 w-4" />{{ __('toolpage.share') }}</button>
                {{-- on a phone the way on is the bar fixed to the bottom (x-action-bar below), which mirrors this button; the button itself stays for lg --}}
                <span class="max-lg:hidden lg:contents"><button type="button" id="tool-go" class="btn-primary gap-1.5" disabled><span id="tool-go-label">{{ __('param.go') }}</span><x-icon name="arrow-right" class="h-4 w-4" /></button></span>
            </div>
            <p class="w-full text-xs text-muted">@hasSection('price-note')@yield('price-note')@else{{ \App\Support\NextStep::text('param.go.hint') }}@endif</p>
        </div>
        @yield('stage')
    </div>
</div>
{{-- a phone: the way on in a bar fixed to the bottom, the status of the model as its second line (components/action-bar, docs/AN.md §18) --}}
<x-action-bar for="tool-go" sub="tool-status" class="lg:hidden" />
@endsection
