@extends('layouts.app', ['title' => $initial ? ($initial['file']['name'] ?? 'matplace') . ' · matplace' : 'matplace'])

@php
    $mode = $mode ?? 'public';
    $formats = strtoupper(implode(', ', $config['formats']));
    $i18n = collect([
        'calc.status.reading','calc.status.rough','calc.status.uploading','calc.status.queued','calc.status.done',
        'calc.status.failed','calc.status.converting','calc.price.from','calc.price.range','calc.price.per_piece',
        'calc.days','calc.cta.copied','calc.error.read','calc.error.upload','calc.error.too_big','calc.est_only',
        'calc.profile.budget','calc.profile.standard','calc.profile.express','calc.profile.mine','calc.others_from',
        'calc.breakdown.material','calc.breakdown.time','calc.breakdown.setup','calc.breakdown.total','calc.breakdown.discount','calc.breakdown.margin','calc.breakdown.min_price','calc.breakdown.rounded',
        'calc.warn.exceeds_typical_bed','calc.warn.supports_added','calc.warn.not_watertight',
        'calc.warn.multiple_shells','calc.warn.flipped_normals','calc.printers_count',
        'search.searching','search.identifying','search.none','search.error','search.not_image','search.daily_limit','search.open_source',
        'search.size_guess','search.price_range','search.range_hint','search.have_file','search.generate','search.designer_soon','hero.soon','calc.size','calc.material','inquiry.error',
        'search.gen_size','search.generating','search.gen_done','search.gen_failed','search.gen_daily_limit','search.gen_global_limit','search.gen_text_hint',
        'refine.working','refine.failed','pedestal.working','pedestal.failed','mold.working','mold.failed','mold.unavailable','mold.report','mold.report.undercuts','mold.report.large','calc.tip.mold','refine.photo_only','advice.title','advice.lead','advice.button','advice.working','advice.failed','advice.daily_limit','advice.unavailable','advice.level.important','advice.level.tip','advice.level.fine','advice.disclaimer','check.head.error','check.head.advice','check.head.ok','check.group.error','check.group.advice','check.group.ok','check.disclaimer','check.units_tiny','check.units_tiny.impact','check.units_huge','check.units_huge.impact','check.very_small','check.very_small.impact','check.exceeds_bed','check.exceeds_bed.impact','check.parts_fit','check.parts_fit.impact','check.part_exceeds_bed','check.part_exceeds_bed.impact','check.size_ok','check.size_ok.impact','check.too_thin','check.too_thin.impact','check.watertight_ok','check.watertight_ok.impact','check.not_watertight','check.not_watertight.impact','check.flipped_normals','check.flipped_normals.impact','check.multiple_shells','check.multiple_shells.impact','check.heavy_mesh','check.heavy_mesh.impact','check.very_coarse','check.very_coarse.impact',
        'calc.tip.organizer','calc.tip.modular','param.part.tray','param.part.bin','param.part.body.logo','param.part.stand.logo','param.part.body.vase','param.part.body.stamp','param.part.body.qr','param.part.body.lightbox','calc.tip.stencil','calc.tip.lightbox','param.part.face','param.part.diffuser','param.part.back','calc.tip.vase','calc.tip.logo','calc.tip.stamp','calc.tip.qr','calc.edit_design','param.part.saucer','param.part.handle','param.part.stand','calc.tip.box','calc.tip.phone_stand','calc.tip.cable_holder','download.parts','param.part.body','param.part.lid','calc.tip.generated','calc.tip.lithophane','calc.tip.relief','calc.tip.sign',
        'calc.mode.normal','calc.mode.silent','calc.mode.sport','calc.facts.rough','calc.facts.material','calc.facts.layers','calc.facts.supports','calc.facts.supports_yes','calc.facts.supports_no','calc.facts.infill','calc.facts.length','calc.status.done_facts',
    ])->mapWithKeys(fn ($k) => [$k => __($k, ['max' => $config['max_upload_mb'], 'n' => ':n'])])->all();
@endphp

@push('head')
<script>
    window.MP_CONFIG = @json($config);
    window.MP_I18N = @json($i18n);
    window.MP_INITIAL = @json($initial);
    window.MP_MODE = @json($mode);
    window.MP_OWN_PROFILE_ID = @json($ownProfileId ?? null);
    window.MP_ROUTES = { uploads: @json(route('api.uploads.store')), calculations: @json(route('api.calculations.store')), calcShow: @json(url('/api/calculations')), files: @json(url('/api/files')), search: @json(route('api.search')), describe: @json(route('api.describe')), inquiries: @json(route('api.inquiries.store')), generate: @json(route('api.generate.store')), generateShow: @json(url('/api/generate')), paramPart: @json(url('/api/tools/param')), printers: @json(route('api.printers')), printerShow: @json(url('/printers/id')), quoteStore: @json(auth()->check() && auth()->user()->isPrinter() ? route('printer.quotes.store') : null), csrf: @json(csrf_token()) };
</script>
@endpush

@section('content')
<div id="calculator" data-state="idle" data-mode="{{ $mode }}">
    @if($mode === 'printer')
        <div class="mb-4">@include('printer.nav')</div>
    @endif

    {{-- ── Hero: one field ──────────────────────────────────────────── --}}
    @if($mode === 'printer')
    <section id="hero" class="calc-hero">
        <h1 class="text-2xl font-extrabold leading-tight sm:text-3xl">{{ __('printer.calc.title') }}</h1>
        <p class="mt-2 text-slate-600">{{ __('printer.calc.lead') }}</p>
        @include('calculator.inputs')
        @include('calculator.search_results')
    </section>
    @else
        @include('calculator.home')
    @endif

    {{-- ── Result: viewer + controls + price ─────────────────────────── --}}
    <section id="result" class="hidden">
        <div class="grid gap-4 lg:grid-cols-[1.2fr_1fr]">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="relative">
                <canvas id="viewer" class="block h-[45vh] w-full touch-none lg:h-[70vh]"></canvas>
                <div class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-xs font-medium text-slate-700 shadow" id="file-badge"></div>
                <div class="absolute bottom-3 left-3 rounded-full bg-white/90 px-3 py-1 text-xs text-slate-600 shadow" id="dims-badge"></div>
                </div>
                <p id="kind-tip" class="hidden border-t border-slate-100 bg-action-soft px-4 py-2 text-sm text-action-dark"></p>
                <div id="edit-design" class="hidden flex-wrap items-center gap-3 border-t border-slate-100 px-4 py-3">
                    <a id="edit-design-link" href="#" class="btn-secondary text-sm">{{ __('calc.edit_design') }}</a>
                    <span class="text-xs text-slate-500">{{ __('calc.edit_design.hint') }}</span>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between text-sm">
                        <span id="status" class="font-medium text-slate-600">{{ __('calc.status.reading') }}</span>
                        <span id="status-spinner" class="h-4 w-4 animate-spin rounded-full border-2 border-action border-t-transparent"></span>
                    </div>
                    <div class="mt-2 flex items-end gap-2">
                        <span id="price-main" class="text-4xl font-extrabold tracking-tight">—</span>
                        @if($config['marketplace'])<span class="pb-1 text-slate-500">{{ $config['currency'] === 'CZK' ? 'Kč' : $config['currency'] }}</span>@else<span class="pb-1 text-slate-500">{{ __('calc.time') }}</span>@endif
                    </div>
                    <div id="price-sub" class="mt-1 text-sm text-slate-500"></div>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                        <div><dt class="text-slate-500">{{ __('calc.weight') }}</dt><dd id="stat-grams" class="font-semibold">—</dd></div>
                        <div><dt class="text-slate-500">{{ $config['marketplace'] ? __('calc.time') : __('calc.facts.length') }}</dt><dd id="stat-time" class="font-semibold">—</dd></div>
                        <div><dt class="text-slate-500">{{ $config['marketplace'] ? __('calc.lead') : __('calc.facts.layers') }}</dt><dd id="stat-lead" class="font-semibold">—</dd></div>
                    </dl>
                    <ul id="warnings" class="mt-3 space-y-1 text-sm text-amber-700"></ul>
                    <details class="mt-3 text-sm"><summary class="cursor-pointer text-action-dark">{{ __('check.title') }}</summary><div id="model-check" class="mt-2 hidden rounded-xl bg-slate-50 p-3"></div><div id="model-advice"></div></details>
                    <form id="refine-box" class="mt-3 hidden rounded-xl bg-slate-50 p-3">
                        <label class="block text-sm font-semibold text-slate-700" for="refine-text">{{ __('refine.title') }}</label>
                        <div class="mt-1 flex gap-2">
                            <input id="refine-text" maxlength="300" placeholder="{{ __('refine.placeholder') }}" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <button class="rounded-lg bg-action px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">{{ __('refine.submit') }}</button>
                        </div>
                        <p id="refine-msg" class="mt-1 text-xs text-slate-500">{{ __('refine.hint') }}</p>
                    </form>
                    {{-- generated busts and figures: another base without a new generation --}}
                    <form id="pedestal-box" class="mt-3 hidden rounded-xl bg-slate-50 p-3">
                        <div class="text-sm font-semibold text-slate-700">{{ __('pedestal.title') }}</div>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label class="block text-xs font-semibold text-slate-600">{{ __('pedestal.type') }}
                                <select id="pedestal-type" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                                    @foreach(\App\Domain\Generation\PedestalChanger::TYPES as $pk)<option value="{{ $pk }}">{{ __('figure.pedestal.'.$pk) }}</option>@endforeach
                                </select>
                            </label>
                            <label class="block text-xs font-semibold text-slate-600">{{ __('pedestal.front') }}
                                <select id="pedestal-front" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                                    @foreach(\App\Domain\Generation\PedestalChanger::FRONTS as $fk)<option value="{{ $fk }}">{{ __('pedestal.front.'.$fk) }}</option>@endforeach
                                </select>
                            </label>
                            <label class="block text-xs font-semibold text-slate-600 sm:col-span-2">{{ __('pedestal.sink') }}
                                <select id="pedestal-sink" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                                    @foreach(\App\Domain\Generation\PedestalChanger::SINKS as $sk)<option value="{{ $sk }}">{{ $sk === 0 ? __('pedestal.sink.none') : __('pedestal.sink.by', ['n' => $sk]) }}</option>@endforeach
                                </select>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-slate-700 sm:col-span-2"><input id="pedestal-tidy" type="checkbox" class="accent-action"> {{ __('pedestal.tidy') }}</label>
                            <label data-plaque class="hidden text-xs font-semibold text-slate-600">{{ __('figure.pedestal.name') }}
                                <input id="pedestal-name" maxlength="24" placeholder="{{ __('figure.pedestal.name_ph') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm font-normal">
                            </label>
                            <label data-plaque class="hidden text-xs font-semibold text-slate-600">{{ __('figure.pedestal.dedication') }}
                                <input id="pedestal-dedication" maxlength="40" placeholder="{{ __('figure.pedestal.dedication_ph') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm font-normal">
                            </label>
                        </div>
                        <div class="mt-2 flex items-center gap-3">
                            <button class="rounded-lg bg-action px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">{{ __('pedestal.apply') }}</button>
                            <p id="pedestal-msg" class="text-xs text-slate-500" role="status">{{ __('pedestal.hint') }}</p>
                        </div>
                    </form>
                    {{-- any ready model: a two-part casting mold around it --}}
                    <form id="mold-box" class="mt-3 hidden rounded-xl bg-slate-50 p-3">
                        <div class="text-sm font-semibold text-slate-700">{{ __('mold.title') }}</div>
                        <p class="mt-1 text-xs text-slate-500">{{ __('mold.lead') }}</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <label class="block text-xs font-semibold text-slate-600">{{ __('mold.wall') }}
                                <select id="mold-wall" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                                    @foreach(\App\Domain\Tools\MoldGenerator::WALLS as $w)<option value="{{ $w }}" @selected($w === 8)>{{ $w }} mm</option>@endforeach
                                </select>
                            </label>
                            <label class="block text-xs font-semibold text-slate-600">{{ __('mold.axis') }}
                                <select id="mold-axis" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                                    @foreach(\App\Domain\Tools\MoldGenerator::AXES as $a)<option value="{{ $a }}">{{ __('mold.axis.'.$a) }}</option>@endforeach
                                </select>
                            </label>
                            <label class="block text-xs font-semibold text-slate-600">{{ __('mold.split') }}
                                <select id="mold-split" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                                    <option value="">{{ __('mold.split.auto') }}</option>
                                    @foreach(\App\Domain\Tools\MoldGenerator::SPLITS as $s)<option value="{{ $s }}">{{ __('mold.split.at', ['n' => $s]) }}</option>@endforeach
                                </select>
                            </label>
                        </div>
                        <div class="mt-2 flex items-center gap-3">
                            <button class="rounded-lg bg-action px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">{{ __('mold.apply') }}</button>
                            <p id="mold-msg" class="text-xs text-slate-500" role="status">{{ __('mold.hint') }}</p>
                        </div>
                    </form>
                    <p id="mold-report" class="mt-3 hidden rounded-xl bg-slate-50 p-3 text-xs text-slate-700" role="status"></p>
                    <details class="mt-3 text-sm" @if($mode === 'printer') open @endif>
                        <summary class="cursor-pointer text-action-dark">{{ $config['marketplace'] ? __('calc.breakdown') : __('calc.facts.title') }}</summary>
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
                        <span>{{ __('calc.infill') }}</span><span id="infill-val" class="text-action-dark">15 %</span>
                    </div>
                    <input id="infill" type="range" min="5" max="100" step="5" value="15" class="mt-1 w-full accent-action">
                    <div class="flex justify-between text-xs text-slate-500"><span>{{ __('calc.infill.light') }}</span><span>{{ __('calc.infill.solid') }}</span></div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <label class="text-sm font-semibold text-slate-700">{{ __('calc.quantity') }}
                            <input id="quantity" type="number" min="1" max="1000" value="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal">
                        </label>
                        <label class="text-sm font-semibold text-slate-700">{{ __('calc.scale') }} <span id="scale-val" class="font-normal text-action-dark">100 %</span>
                            <input id="scale" type="range" min="25" max="{{ (int) ($config['max_scale'] * 100) }}" step="5" value="100" class="mt-3 w-full accent-action">
                        </label>
                    </div>

                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-action-dark">{{ __('calc.more') }}</summary>
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
                        <form method="post" action="{{ route('printer.quotes.store') }}" id="quote-form" class="sm:col-span-2">@csrf<input type="hidden" name="calculation" id="quote-calc-token" value=""><button id="cta-quote" type="submit" class="w-full rounded-xl bg-action px-4 py-3 font-semibold text-white disabled:opacity-50" disabled>{{ __('printer.calc.create_quote') }}</button></form>
                    @elseif($config['marketplace'])
                        <button id="cta-make" type="button" class="rounded-xl bg-action px-4 py-3 font-semibold text-white disabled:opacity-60" title="{{ __('calc.cta.make.soon') }}">{{ __('calc.cta.make') }}</button>
                    @endif
                    <button id="cta-download" type="button" class="rounded-xl border border-action px-4 py-3 text-center font-semibold text-action-dark aria-disabled:opacity-50" aria-disabled="true">{{ __('calc.cta.download') }}</button>
                    <button id="cta-share" type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-3 font-semibold text-slate-700">{{ __('calc.cta.share') }}</button>
                    <button id="cta-new" type="button" class="rounded-xl border border-slate-300 bg-white px-4 py-3 font-semibold text-slate-700 {{ $mode === 'printer' ? 'sm:col-span-2' : '' }}">{{ __('calc.cta.new') }}</button>
                </div>
                <p id="make-note" class="hidden text-sm text-slate-500">{{ __('inquiry.wait_precise') }}</p>
                @if($mode !== 'printer')@include('farm.cta')@endif
                <div id="download-panel" class="hidden rounded-2xl border border-slate-200 bg-white p-4">
                    <div id="dl-picker">
                        <div class="font-bold">{{ __('download.title') }}</div>
                        <p class="text-sm text-slate-600">{{ __('download.lead') }}</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <label class="text-sm font-semibold">{{ __('download.vendor') }}<select id="dl-vendor" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></select></label>
                            <label class="text-sm font-semibold">{{ __('download.model') }}<select id="dl-model" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></select></label>
                        </div>
                        <p id="dl-note" class="mt-2 hidden rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900" data-too-big="{{ __('download.too_big') }}" data-no-material="{{ __('download.no_material') }}"></p>
                        <a id="dl-project" aria-disabled="true" class="mt-3 block rounded-xl bg-action px-4 py-3 text-center font-semibold text-white aria-disabled:opacity-50">{{ __('download.project') }}</a>
                        <p id="dl-how" class="mt-2 text-xs text-slate-500" data-orca="{{ __('download.how') }}" data-prusa="{{ __('download.how_prusa') }}">{{ __('download.how') }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('download.check') }}</p>
                    </div>
                    <a id="dl-stl" href="#" class="mt-3 block text-center text-sm text-action-dark underline">{{ __('download.stl') }}</a>
                    <div id="dl-parts" class="mt-2 hidden text-center text-sm"></div>
                </div>
                @if($mode !== 'printer' && $config['marketplace'])
                <div id="inquiry-panel" class="hidden rounded-2xl border border-line bg-action-soft p-4">
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
                        <label class="text-sm text-slate-600">{{ __('inquiry.form.color') }}<input name="color" maxlength="40" list="inquiry-colors" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        <datalist id="inquiry-colors">@foreach(['white','black','grey','brown','red','blue','green','yellow','orange','any'] as $c)<option value="{{ __('color.'.$c) }}">@endforeach</datalist>
                        <label class="text-sm text-slate-600">{{ __('inquiry.form.wanted_by') }}<input name="wanted_by" type="date" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                        <select name="delivery_pref" class="rounded-lg border border-slate-300 px-3 py-2 sm:col-span-2">
                            <option value="any">{{ __('inquiry.delivery.any') }}</option><option value="pickup">{{ __('inquiry.delivery.pickup') }}</option><option value="shipping">{{ __('inquiry.delivery.shipping') }}</option>
                        </select>
                        <textarea name="note" rows="2" placeholder="{{ __('inquiry.form.note') }}" class="rounded-lg border border-slate-300 px-3 py-2 sm:col-span-2"></textarea>
                        <p id="inquiry-error" class="hidden text-sm text-red-700 sm:col-span-2"></p>
                        <button type="submit" class="rounded-xl bg-action px-4 py-3 font-semibold text-white sm:col-span-2">{{ __('inquiry.form.submit') }}</button>
                        <p class="text-xs text-slate-500 sm:col-span-2">{{ __('inquiry.form.promise') }}</p>
                    </form>
                </div>
                @endif
            </div>
        </div>
    </section>
</div>
@endsection
