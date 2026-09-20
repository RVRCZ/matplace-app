@extends('layouts.app', ['title' => $initial ? ($initial['file']['name'] ?? 'matplace') . ' · matplace' : 'matplace'])

@php
    $mode = $mode ?? 'public';
    $formats = strtoupper(implode(', ', $config['formats']));
    $i18n = collect([
        'calc.status.reading','calc.status.rough','calc.status.uploading','calc.status.queued','calc.status.done',
        'calc.status.failed','calc.status.converting','calc.price.from','calc.price.range','calc.price.per_piece',
        'calc.days','calc.cta.copied','calc.error.read','calc.error.upload','calc.error.too_big','calc.est_only',
        'calc.profile.budget','calc.profile.standard','calc.profile.express','calc.profile.mine','calc.others_from',
        'calc.breakdown.material','calc.breakdown.time','calc.breakdown.setup','calc.breakdown.total',
        'calc.warn.exceeds_typical_bed','calc.warn.supports_added','calc.warn.not_watertight',
        'calc.warn.multiple_shells','calc.warn.flipped_normals','calc.printers_count',
        'search.searching','search.identifying','search.none','search.error','search.not_image','search.daily_limit','search.open_source',
        'search.size_guess','search.price_range','search.range_hint','search.have_file','search.generate','search.designer_soon','hero.soon','calc.size','calc.material','inquiry.error',
        'search.gen_size','search.generating','search.gen_done','search.gen_failed','search.gen_daily_limit','search.gen_global_limit','search.gen_text_hint',
    ])->mapWithKeys(fn ($k) => [$k => __($k, ['max' => $config['max_upload_mb'], 'n' => ':n'])])->all();
@endphp

@push('head')
<script>
    window.MP_CONFIG = @json($config);
    window.MP_I18N = @json($i18n);
    window.MP_INITIAL = @json($initial);
    window.MP_MODE = @json($mode);
    window.MP_OWN_PROFILE_ID = @json($ownProfileId ?? null);
    window.MP_ROUTES = { uploads: @json(route('api.uploads.store')), calculations: @json(route('api.calculations.store')), calcShow: @json(url('/api/calculations')), files: @json(url('/api/files')), search: @json(route('api.search')), describe: @json(route('api.describe')), inquiries: @json(route('api.inquiries.store')), generate: @json(route('api.generate.store')), generateShow: @json(url('/api/generate')), quoteStore: @json(auth()->check() && auth()->user()->isPrinter() ? route('printer.quotes.store') : null), csrf: @json(csrf_token()) };
</script>
@endpush

@section('content')
<div id="calculator" data-state="idle" data-mode="{{ $mode }}">
    @if($mode === 'printer')
        <div class="mb-4">@include('printer.nav')</div>
    @endif

    {{-- ── Hero: one field ──────────────────────────────────────────── --}}
    <section id="hero" class="calc-hero">
        @if($mode === 'printer')
            <h1 class="text-2xl font-extrabold leading-tight sm:text-3xl">{{ __('printer.calc.title') }}</h1>
            <p class="mt-2 text-slate-600">{{ __('printer.calc.lead') }}</p>
        @else
            <h1 class="text-2xl font-extrabold leading-tight sm:text-4xl">{{ __('app.tagline') }}</h1>
            <p class="mt-2 text-slate-600">{{ __('app.subline') }}</p>
        @endif

        <label id="dropzone" class="mt-6 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-teal-300 bg-white px-4 py-10 text-center transition hover:border-teal-500 hover:bg-teal-50">
            <svg class="h-10 w-10 text-teal-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0l-4 4m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
            <span class="mt-3 text-lg font-semibold">{{ __('hero.drop') }}</span>
            <span class="mt-1 text-sm text-slate-500">{{ __('hero.formats', ['formats' => $formats, 'max' => $config['max_upload_mb']]) }}</span>
            <input id="file-input" type="file" class="sr-only" accept="{{ implode(',', array_map(fn ($f) => '.' . $f, $config['formats'])) }}">
        </label>

        <div class="mt-3 flex flex-wrap gap-2 text-sm">
            <button type="button" class="rounded-full bg-teal-600 px-4 py-2 font-semibold text-white" onclick="document.getElementById('file-input').click()">{{ __('hero.choose_file') }}</button>
            @if($mode !== 'printer')
                @if($config['vision'])
                    <button type="button" id="hero-photo-btn" class="rounded-full border border-teal-600 bg-white px-4 py-2 font-semibold text-teal-700">📷 {{ __('hero.photo') }}</button>
                    <input id="photo-input" type="file" accept="image/*" capture="environment" class="sr-only">
                @endif
                <button type="button" id="hero-text-btn" class="rounded-full border border-teal-600 bg-white px-4 py-2 font-semibold text-teal-700">✍️ {{ __('hero.text') }}</button>
            @endif
        </div>
        <form id="search-form" class="mt-3 hidden gap-2 sm:flex">
            <input id="search-input" type="search" maxlength="200" placeholder="{{ __('search.placeholder') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3">
            <button class="mt-2 rounded-xl bg-teal-600 px-5 py-3 font-semibold text-white sm:mt-0">{{ __('search.button') }}</button>
        </form>
        <div id="search-busy" class="mt-3 hidden items-center justify-center gap-2 text-sm text-slate-600"><span class="h-4 w-4 animate-spin rounded-full border-2 border-teal-600 border-t-transparent"></span><span id="search-busy-text"></span></div>
        <p id="hero-error" class="mt-3 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"></p>
        <div id="describe-box" class="mt-4 hidden rounded-2xl border border-slate-200 bg-white p-4 text-left"></div>

        @if($mode !== 'printer')
        <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-5">
            @foreach ([['broken','📸', null],['idea','💡', null],['file','📄', null],['printer','🖨️', auth()->check() ? (auth()->user()->isPrinter() ? route('printer.dashboard') : route('account')) : route('register', ['role' => 'printer'])],['designer','🧩', null]] as [$k,$ico,$href])
                <a href="{{ $href ?? '#' }}" data-tile="{{ $k }}" class="calc-tile {{ in_array($k, ['file','idea']) || ($k === 'broken' && $config['vision']) || $href ? '' : 'opacity-70' }}" @if($k==='file') onclick="document.getElementById('file-input').click();return false;" @endif>
                    <span class="text-2xl">{{ $ico }}</span>
                    <span class="mt-1 font-semibold">{{ __('tiles.'.$k) }}</span>
                    <span class="text-xs text-slate-500">{{ __('tiles.'.$k.'.hint') }}</span>
                </a>
            @endforeach
        </div>
        @endif

        <section id="search-section" class="mt-8 hidden text-left">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-lg font-bold">{{ __('search.results_title') }} <span id="search-query" class="font-normal text-slate-500"></span></h2>
            </div>
            <p id="search-hint" class="hidden text-sm text-slate-500">{{ __('search.results_hint') }}</p>
            <div id="search-results" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"></div>
        </section>
    </section>

    {{-- ── Result: viewer + controls + price ─────────────────────────── --}}
    <section id="result" class="hidden">
        <div class="grid gap-4 lg:grid-cols-[1.2fr_1fr]">
            <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <canvas id="viewer" class="block h-[45vh] w-full touch-none lg:h-[70vh]"></canvas>
                <div class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-xs font-medium text-slate-700 shadow" id="file-badge"></div>
                <div class="absolute bottom-3 left-3 rounded-full bg-white/90 px-3 py-1 text-xs text-slate-600 shadow" id="dims-badge"></div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between text-sm">
                        <span id="status" class="font-medium text-slate-600">{{ __('calc.status.reading') }}</span>
                        <span id="status-spinner" class="h-4 w-4 animate-spin rounded-full border-2 border-teal-600 border-t-transparent"></span>
                    </div>
                    <div class="mt-2 flex items-end gap-2">
                        <span id="price-main" class="text-4xl font-extrabold tracking-tight">—</span>
                        <span class="pb-1 text-slate-500">{{ $config['currency'] === 'CZK' ? 'Kč' : $config['currency'] }}</span>
                    </div>
                    <div id="price-sub" class="mt-1 text-sm text-slate-500"></div>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                        <div><dt class="text-slate-500">{{ __('calc.weight') }}</dt><dd id="stat-grams" class="font-semibold">—</dd></div>
                        <div><dt class="text-slate-500">{{ __('calc.time') }}</dt><dd id="stat-time" class="font-semibold">—</dd></div>
                        <div><dt class="text-slate-500">{{ __('calc.lead') }}</dt><dd id="stat-lead" class="font-semibold">—</dd></div>
                    </dl>
                    <ul id="warnings" class="mt-3 space-y-1 text-sm text-amber-700"></ul>
                    <details class="mt-3 text-sm" @if($mode === 'printer') open @endif>
                        <summary class="cursor-pointer text-teal-700">{{ __('calc.breakdown') }}</summary>
                        <div id="breakdown" class="mt-2 space-y-2"></div>
                    </details>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-sm font-semibold text-slate-700">{{ __('calc.material') }}</div>
                    <div id="materials" class="mt-2 flex flex-wrap gap-2"></div>
                    <p id="material-hint" class="mt-1 text-xs text-slate-500"></p>

                    <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('calc.quality') }}</div>
                    <div id="quality" class="mt-2 grid grid-cols-3 gap-2">
                        @foreach ($config['qualities'] as $q)
                            <button type="button" data-quality="{{ $q }}" class="seg {{ $q === 'standard' ? 'seg-on' : '' }}">{{ __('calc.quality.'.$q) }}<span class="block text-xs font-normal text-slate-500">{{ $config['rough']['quality_layer_mm'][$q] }} mm</span></button>
                        @endforeach
                    </div>

                    <div class="mt-4 flex items-center justify-between text-sm font-semibold text-slate-700">
                        <span>{{ __('calc.infill') }}</span><span id="infill-val" class="text-teal-700">15 %</span>
                    </div>
                    <input id="infill" type="range" min="5" max="100" step="5" value="15" class="mt-1 w-full accent-teal-600">
                    <div class="flex justify-between text-xs text-slate-500"><span>{{ __('calc.infill.light') }}</span><span>{{ __('calc.infill.solid') }}</span></div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <label class="text-sm font-semibold text-slate-700">{{ __('calc.quantity') }}
                            <input id="quantity" type="number" min="1" max="1000" value="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal">
                        </label>
                        <label class="text-sm font-semibold text-slate-700">{{ __('calc.scale') }} <span id="scale-val" class="font-normal text-teal-700">100 %</span>
                            <input id="scale" type="range" min="25" max="{{ (int) ($config['max_scale'] * 100) }}" step="5" value="100" class="mt-3 w-full accent-teal-600">
                        </label>
                    </div>

                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-teal-700">{{ __('calc.more') }}</summary>
                        <div class="mt-2 text-sm font-semibold text-slate-700">{{ __('calc.supports') }}</div>
                        <div id="supports" class="mt-2 grid grid-cols-3 gap-2">
                            <button type="button" data-supports="auto" class="seg seg-on">{{ __('calc.supports.auto') }}</button>
                            <button type="button" data-supports="1" class="seg">{{ __('calc.supports.yes') }}</button>
                            <button type="button" data-supports="0" class="seg">{{ __('calc.supports.no') }}</button>
                        </div>
                    </details>
                </div>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @if($mode === 'printer')
                        <form method="post" action="{{ route('printer.quotes.store') }}" id="quote-form" class="sm:col-span-2">@csrf<input type="hidden" name="calculation" id="quote-calc-token" value=""><button id="cta-quote" type="submit" class="w-full rounded-xl bg-teal-600 px-4 py-3 font-semibold text-white disabled:opacity-50" disabled>{{ __('printer.calc.create_quote') }}</button></form>
                    @else
                        <button id="cta-make" type="button" class="rounded-xl bg-teal-600 px-4 py-3 font-semibold text-white disabled:opacity-60" title="{{ __('calc.cta.make.soon') }}">{{ __('calc.cta.make') }}</button>
                    @endif
                    <a id="cta-download" href="#" class="rounded-xl border border-teal-600 px-4 py-3 text-center font-semibold text-teal-700 aria-disabled:opacity-50" aria-disabled="true">{{ __('calc.cta.download') }}</a>
                    <button id="cta-share" type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-3 font-semibold text-slate-700">{{ __('calc.cta.share') }}</button>
                    <button id="cta-new" type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-3 font-semibold text-slate-700 {{ $mode === 'printer' ? 'sm:col-span-2' : '' }}">{{ __('calc.cta.new') }}</button>
                </div>
                <p id="make-note" class="hidden text-sm text-slate-500">{{ __('inquiry.wait_precise') }}</p>
                @if($mode !== 'printer')
                <div id="inquiry-panel" class="hidden rounded-2xl border border-teal-200 bg-teal-50 p-4">
                    <div class="font-bold">{{ __('inquiry.form.title') }}</div>
                    <p class="text-sm text-slate-600">{{ __('inquiry.form.hint') }}</p>
                    <form id="inquiry-form" class="mt-3 grid gap-2 sm:grid-cols-2">
                        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                        @guest
                            <input name="email" type="email" required placeholder="{{ __('auth.email') }}" class="rounded-lg border border-slate-300 px-3 py-2">
                            <input name="name" placeholder="{{ __('auth.name') }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        @else
                            <input name="name" value="{{ auth()->user()->name }}" placeholder="{{ __('auth.name') }}" class="rounded-lg border border-slate-300 px-3 py-2">
                            <input name="phone" value="{{ auth()->user()->phone }}" placeholder="{{ __('account.phone') }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        @endguest
                        <input name="zip" required value="{{ auth()->user()?->zip }}" placeholder="{{ __('account.zip') }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        <input name="city" value="{{ auth()->user()?->city }}" placeholder="{{ __('account.city') }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        <label class="text-sm text-slate-600">{{ __('calc.quantity') }}<input name="quantity" type="number" min="1" max="1000" value="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        <label class="text-sm text-slate-600">{{ __('inquiry.form.wanted_by') }}<input name="wanted_by" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        <select name="delivery_pref" class="rounded-lg border border-slate-300 px-3 py-2 sm:col-span-2">
                            <option value="any">{{ __('inquiry.delivery.any') }}</option><option value="pickup">{{ __('inquiry.delivery.pickup') }}</option><option value="shipping">{{ __('inquiry.delivery.shipping') }}</option>
                        </select>
                        <textarea name="note" rows="2" placeholder="{{ __('inquiry.form.note') }}" class="rounded-lg border border-slate-300 px-3 py-2 sm:col-span-2"></textarea>
                        <p id="inquiry-error" class="hidden text-sm text-red-700 sm:col-span-2"></p>
                        <button type="submit" class="rounded-xl bg-teal-600 px-4 py-3 font-semibold text-white sm:col-span-2">{{ __('inquiry.form.submit') }}</button>
                        <p class="text-xs text-slate-500 sm:col-span-2">{{ __('inquiry.form.promise') }}</p>
                    </form>
                </div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
