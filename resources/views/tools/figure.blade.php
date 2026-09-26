@extends('layouts.app', ['title' => __('tools.figure.title').' · matplace'])

@php
    $i18n = collect(['figure.generating', 'figure.done', 'figure.failed', 'figure.rejected', 'figure.rejected_view', 'figure.view.left', 'figure.view.back', 'figure.view.right', 'figure.limit', 'figure.global_limit', 'figure.need_photo', 'figure.need_consent'])
        ->mapWithKeys(fn ($k) => [$k => __($k, ['n' => ':n', 'm' => ':m'])])->all();
@endphp

@push('head')
<script>
    window.MP_FIGURE = { generate: @json(route('api.generate.store')), show: @json(url('/api/generate')), home: @json(route('home')), i18n: @json($i18n) };
</script>
@endpush

@section('content')
<div class="mx-auto max-w-2xl">
    <a href="{{ route('tools') }}" class="text-sm text-action-dark underline">← {{ __('tools.title') }}</a>
    <h1 class="mt-1 text-2xl font-extrabold text-ink">{{ __('tools.figure.title') }}</h1>
    <p class="hint">{{ __('figure.lead') }}</p>

    <ol class="steps mt-3" aria-label="{{ __('param.steps') }}">
        <li aria-current="step"><span class="step-no">1</span>{{ __('figure.step.photo') }}</li>
        <li><span class="step-no">2</span>{{ __('figure.step.model') }}</li>
        <li><span class="step-no">3</span>{{ \App\Support\NextStep::text('param.step.inquiry') }}</li>
    </ol>

    @unless($generator)
        <div class="note-warn mt-4 text-sm">{{ __('figure.unavailable') }}</div>
    @else
    <form id="figure-form" class="card mt-4 space-y-5 p-5">
        <fieldset>
            <legend class="sr-only">{{ __('figure.kind.bust') }} / {{ __('figure.kind.figure') }}</legend>
            <div class="grid grid-cols-2 gap-2" role="radiogroup">
                @foreach(['bust' => '🗿', 'figure' => '🧍'] as $k => $ico)
                    <label class="cursor-pointer rounded-xl border border-slate-300 bg-white p-3 text-center has-[:checked]:border-action has-[:checked]:bg-action-soft has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action">
                        <input type="radio" name="kind" value="{{ $k }}" @checked($k === 'bust') class="sr-only"><div class="text-2xl" aria-hidden="true">{{ $ico }}</div><div class="font-semibold text-ink">{{ __('figure.kind.'.$k) }}</div><div class="text-xs text-muted">{{ __('figure.kind.'.$k.'.hint') }}</div>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 px-4 py-8 text-center hover:border-action hover:bg-action-soft">
            <img id="figure-preview" src="" alt="" class="mb-2 hidden max-h-48 rounded-lg">
            <span class="text-lg font-semibold text-ink">{{ __('figure.pick_photo') }}</span>
            <span class="text-sm text-muted">{{ __('figure.photo_tips') }}</span>
            <input id="figure-photo" name="image" type="file" accept="image/*" class="sr-only">
        </label>

        <details id="figure-views" class="rounded-xl border border-line p-3">
            <summary class="cursor-pointer text-sm font-semibold text-ink">{{ __('figure.views') }} <span class="font-normal text-muted">{{ __('figure.views.hint') }}</span></summary>
            <div class="mt-3 grid grid-cols-3 gap-2">
                @foreach(['left' => '⬅', 'back' => '🔄', 'right' => '➡'] as $view => $ico)
                    <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 p-3 text-center text-sm hover:border-action hover:bg-action-soft">
                        <img data-view-preview="{{ $view }}" src="" alt="" class="mb-1 hidden max-h-24 rounded">
                        <span aria-hidden="true">{{ $ico }}</span>
                        <span class="font-semibold text-ink">{{ __('figure.view.'.$view) }}</span>
                        <input name="image_{{ $view }}" data-view="{{ $view }}" type="file" accept="image/*" class="sr-only">
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-muted">{{ __('figure.views.tips') }}</p>
        </details>

        <label class="lbl">{{ __('figure.size') }} <span id="figure-size-val" class="font-normal text-action-dark">80 mm</span>
            <input id="figure-size" name="target_mm" type="range" min="30" max="250" step="5" value="80" class="mt-1 w-full accent-action">
        </label>

        <fieldset>
            <legend class="lbl">{{ __('figure.pedestal') }}</legend>
            <div class="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-6" role="radiogroup">
                @foreach(['round' => '⬤', 'square' => '◼', 'hexagon' => '⬢', 'column' => '▂', 'plaque' => '▭', 'none' => '∅'] as $pk => $ico)
                    <label class="cursor-pointer rounded-xl border border-slate-300 bg-white p-2 text-center text-ink has-[:checked]:border-action has-[:checked]:bg-action-soft has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action">
                        <input type="radio" name="pedestal" value="{{ $pk }}" class="sr-only" @checked($pk === 'round')><div class="text-lg" aria-hidden="true">{{ $ico }}</div>{{ __('figure.pedestal.'.$pk) }}
                    </label>
                @endforeach
            </div>
            <div id="figure-plaque" class="mt-3 hidden grid gap-3 sm:grid-cols-2">
                <label class="lbl">{{ __('figure.pedestal.name') }}<input name="pedestal_name" maxlength="24" class="field" placeholder="{{ __('figure.pedestal.name_ph') }}"></label>
                <label class="lbl">{{ __('figure.pedestal.dedication') }}<input name="pedestal_dedication" maxlength="40" class="field" placeholder="{{ __('figure.pedestal.dedication_ph') }}"></label>
                <p class="text-xs text-muted sm:col-span-2">{{ __('figure.pedestal.hint') }}</p>
            </div>
        </fieldset>
        <script>document.querySelectorAll('input[name=pedestal]').forEach((r) => r.addEventListener('change', () => document.getElementById('figure-plaque').classList.toggle('hidden', !(r.checked && r.value === 'plaque'))));</script>

        <label class="flex items-start gap-3 text-sm text-ink"><input id="figure-consent" type="checkbox" name="consent" value="1" class="mt-0.5 h-5 w-5 accent-action"> <span>{{ __('figure.consent') }}</span></label>
        <p class="text-xs text-muted">{{ __('figure.privacy') }}</p>

        <button class="btn-primary w-full">{{ __('figure.submit') }}</button>
        <div id="figure-progress" class="hidden"><div class="h-2 w-full overflow-hidden rounded-full bg-slate-200"><div id="figure-bar" class="h-2 w-0 bg-action transition-all"></div></div><p class="mt-1 text-xs text-muted">{{ __('figure.step.wait') }}</p></div>
        <p id="figure-msg" class="hint" aria-live="polite"></p>
        <p class="text-xs text-muted">{{ __('figure.limits', ['n' => $guestLimit, 'm' => $userLimit]) }}</p>
    </form>
    @endunless
</div>
@endsection
