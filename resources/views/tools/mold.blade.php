@php
    $i18n = collect(['mold.page.bad_format', 'mold.page.too_big', 'mold.page.uploading', 'mold.page.processing', 'mold.page.building', 'mold.page.failed', 'mold.page.model_failed',
        'mold.unavailable', 'mold.report', 'mold.report.parts', 'mold.report.filled', 'mold.page.analysing', 'mold.page.analysis.none', 'mold.page.analysis.rigid', 'mold.page.analysis.flexible', 'mold.page.analysis.silicone', 'mold.page.options', 'mold.page.building.fill', 'calc.tip.mold.parts', 'mold.page.cast', 'mold.report.silicone', 'mold.report.silicone.how', 'mold.report.angle', 'mold.report.large', 'mold.verdict.rigid', 'mold.verdict.flexible', 'mold.verdict.silicone',
        'calc.tip.mold', 'calc.tip.mold.silicone', 'mold.page.model', 'mold.page.cast.title', 'tools.mold.title',
        'mold.page.volumes', 'mold.report.volumes', 'mold.report.vents', 'mold.report.vents.none', 'mold.report.core', 'mold.report.shell', 'mold.report.tray', 'mold.report.tray.how', 'mold.report.two_part', 'mold.report.two_part.how',
        'mold.warn.core_none', 'mold.warn.shell_supports'])
        ->mapWithKeys(fn ($k) => [$k => \App\Support\NextStep::text($k, ['max' => $config['max_upload_mb']])])->all();
    $select = 'field !mt-1 text-sm';
@endphp

@extends('tools.page', ['tool' => 'mold', 'module' => 'mold', 'lead' => __('mold.lead'), 'available' => $available, 'unavailable' => __('mold.unavailable'),
    'sections' => ['file' => __('toolpage.section.file'), 'settings' => __('toolpage.section.settings'), 'result' => __('toolpage.section.result')]])

@push('head')
{{-- on a phone the panel comes first here: the title, the gallery and the upload before the viewer (docs/Z.md, N1) --}}
<style>@media (max-width: 1023.98px) { #tool-panel { order: 1; } #tool-stage { order: 3; } }</style>
<script>
    window.MP_I18N = {{ \Illuminate\Support\Js::from($i18n) }};
    window.MP_MOLD = { upload: @json(route('api.uploads.store')), files: @json(url('/api/files')), home: @json(route('home')), from: @json($from), formats: {{ \Illuminate\Support\Js::from($config['formats']) }}, maxMb: {{ (int) $config['max_upload_mb'] }} };
</script>
@endpush

@section('viewer-empty'){{ __('mold.page.pick') }}@endsection

@section('panel')
{{-- the kinds of mold, one picture each (the examples of the catalogue): a click sets the kind --}}
@if($gallery)
<div id="mold-gallery" class="mb-3 grid grid-cols-3 gap-1.5 sm:grid-cols-5" role="group" aria-label="{{ __('mold.gallery') }}">
    @foreach($gallery as $i => $g)
        @php $key = (string) (config('tools.mold.seo.examples')[$i]['key'] ?? ''); $p = (array) (config('tools.mold.seo.examples')[$i]['params'] ?? []); @endphp
        <button type="button" class="mold-tile rounded-xl border border-line bg-card p-1 text-left transition hover:border-ink" data-type="{{ $p['type'] ?? 'rigid' }}" data-variant="{{ $p['variant'] ?? 'sleeve' }}" data-parts="{{ $p['parts'] ?? 2 }}" aria-pressed="false">
            <img src="{{ $g['url'] }}" alt="{{ $g['caption'] }}" width="320" height="240" loading="lazy" class="aspect-[4/3] w-full rounded-lg bg-slate-50 object-cover">
            <span class="mt-1 block text-xs font-medium leading-tight text-ink">{{ __('mold.gallery.'.$key) }}</span>
        </button>
    @endforeach
</div>
@endif
<form id="mold-form">
    <x-tool-section id="file" :title="__('toolpage.section.file')">
        <label id="mold-drop" class="block cursor-pointer rounded-xl border-2 border-dashed border-line p-6 text-center hover:border-ink">
            <input id="mold-file" type="file" class="sr-only" accept="{{ collect($config['formats'])->map(fn ($f) => '.'.$f)->join(',') }}">
            <x-icon name="upload" class="mx-auto h-7 w-7 text-muted" />
            <span id="mold-pick" class="mt-1 block font-medium text-ink">{{ __('mold.page.pick') }}</span>
            <span class="block text-sm text-muted">{{ strtoupper(implode(', ', $config['formats'])) }} · {{ __('check.page.max', ['max' => $config['max_upload_mb']]) }}</span>
            <span id="mold-source" class="mt-2 hidden text-sm font-medium text-ink"></span>
        </label>
        <p id="mold-status" class="note-warn hidden text-sm" role="status" aria-live="polite"></p>
        {{-- what a printed mold would hold on to: painted red on the model in the viewer, said in words here --}}
        <div id="mold-analysis" class="hidden text-sm text-ink" aria-live="polite">
            <p id="mold-analysis-text"></p>
            <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted">
                <span><span class="mr-1 inline-block h-3 w-3 rounded-sm align-middle" style="background:#d72828"></span>{{ __('mold.page.legend.hidden') }}</span>
                <span><span class="mr-1 inline-block h-3 w-3 rounded-sm align-middle" style="background:#6f93c4"></span><span class="mr-1 inline-block h-3 w-3 rounded-sm align-middle" style="background:#c9a662"></span>{{ __('mold.page.legend.parts') }}</span>
            </p>
            <p id="mold-options" class="mt-1 text-xs text-muted"></p>
            <p id="mold-volumes" class="mt-1 text-xs text-ink"></p>
        </div>
    </x-tool-section>

    <x-tool-section id="settings" :title="__('toolpage.section.settings')">
        <label class="lbl">{{ __('mold.type') }}
            <select id="mold-type" class="{{ $select }}">
                @foreach($types as $t)<option value="{{ $t }}">{{ __('mold.type.'.$t) }}</option>@endforeach
            </select>
        </label>
        <label data-mold-silicone class="lbl">{{ __('mold.variant') }}
            <select id="mold-variant" class="{{ $select }}">
                @foreach($variants as $v)<option value="{{ $v }}">{{ __('mold.variant.'.$v) }}</option>@endforeach
            </select>
            <span id="mold-variant-hint" class="block text-xs font-normal text-muted"></span>
        </label>
        <label class="lbl">{{ __('mold.wall') }}
            <select id="mold-wall" class="{{ $select }}">
                @foreach($walls as $w)<option value="{{ $w }}" @selected($w === 8)>{{ $w }} mm</option>@endforeach
            </select>
        </label>
        <label data-mold-rigid class="lbl">{{ __('mold.parts') }}
            <select id="mold-parts" class="{{ $select }}">
                @foreach($parts as $n)<option value="{{ $n }}">{{ __('mold.parts.'.$n) }}</option>@endforeach
            </select>
        </label>
        <label data-mold-two class="lbl">{{ __('mold.axis') }}
            <select id="mold-axis" class="{{ $select }}">
                @foreach($axes as $a)<option value="{{ $a }}">{{ __('mold.axis.'.$a) }}</option>@endforeach
            </select>
        </label>
        <label data-mold-two class="lbl">{{ __('mold.split') }}
            <select id="mold-split" class="{{ $select }}">
                <option value="">{{ __('mold.split.auto') }}</option>
                @foreach($splits as $s)<option value="{{ $s }}">{{ __('mold.split.at', ['n' => $s]) }}</option>@endforeach
            </select>
        </label>
        <label data-mold-rigid class="flex items-start gap-3 text-sm text-ink">
            <input id="mold-fill" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink">
            <span><span class="font-medium">{{ __('mold.fill') }}</span><br><span class="text-muted">{{ __('mold.fill.hint') }}</span></span>
        </label>
        <label class="lbl">{{ __('mold.vents') }}
            <select id="mold-vents" class="{{ $select }}">
                @foreach($vents as $v)<option value="{{ $v }}">{{ __('mold.vents.'.$v) }}</option>@endforeach
            </select>
            <span class="block text-xs font-normal text-muted">{{ __('mold.vents.hint') }}</span>
        </label>
        <label data-mold-core class="flex items-start gap-3 text-sm text-ink">
            <input id="mold-core" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink">
            <span><span class="font-medium">{{ __('mold.core') }}</span><br><span class="text-muted">{{ __('mold.core.hint') }}</span></span>
        </label>
        <label data-mold-core-wall class="lbl hidden">{{ __('mold.core_wall') }}
            <select id="mold-core-wall" class="{{ $select }}">
                @foreach($coreWalls as $w)<option value="{{ $w }}" @selected($w === 5)>{{ $w }} mm</option>@endforeach
            </select>
        </label>
        <details class="text-sm">
            <summary class="cursor-pointer font-medium text-ink underline decoration-line underline-offset-4">{{ __('mold.advanced') }}</summary>
            <div class="mt-3 grid gap-3">
                <label class="lbl">{{ __('mold.clearance') }}
                    <select id="mold-clearance" class="{{ $select }}">
                        @foreach($clearances as $c)<option value="{{ $c }}" @selected($c === '0.15')>{{ $c }} mm</option>@endforeach
                    </select>
                </label>
                <label data-mold-rigid class="lbl">{{ __('mold.keys_type') }}
                    <select id="mold-keys-type" class="{{ $select }}">
                        @foreach($keyTypes as $k)<option value="{{ $k }}">{{ __('mold.keys_type.'.$k) }}</option>@endforeach
                    </select>
                </label>
                <label data-mold-rigid class="lbl">{{ __('mold.funnel_at') }}
                    <select id="mold-funnel-at" class="{{ $select }}">
                        @foreach($funnels as $f)<option value="{{ $f }}">{{ __('mold.funnel_at.'.$f) }}</option>@endforeach
                    </select>
                </label>
            </div>
        </details>
        <button id="mold-go" class="btn-ink w-full gap-1.5" disabled><x-icon name="box" class="h-4 w-4" />{{ __('mold.apply') }}</button>
        <p class="text-xs text-muted">{{ __('mold.hint') }}</p>
    </x-tool-section>

    <x-tool-section id="result" :title="__('toolpage.section.result')">
        <p id="mold-wait" class="text-sm text-muted">{{ __('toolpage.status.empty') }}</p>
        <div id="mold-result" class="hidden">
            <div id="mold-report" class="text-sm text-ink"></div>
            {{-- undercuts were filled: the shape that will really be cast (one of the views over the viewer), added material in orange --}}
            <p id="mold-cast" class="mt-3 hidden text-sm text-ink"><span class="mr-1 inline-block h-3 w-3 rounded-sm align-middle" style="background:#e68c28"></span><span id="mold-cast-text"></span></p>
            <button id="mold-to-silicone" type="button" class="btn-secondary mt-3 hidden w-full">{{ __('mold.page.to_silicone') }}</button>
        </div>
    </x-tool-section>
</form>
@endsection
