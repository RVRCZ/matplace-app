@extends('layouts.app', ['title' => __('tools.figure.title').' · matplace'])

@php
    $i18n = collect(['figure.generating', 'figure.done', 'figure.failed', 'figure.rejected', 'figure.limit', 'figure.global_limit', 'figure.need_photo', 'figure.need_consent'])
        ->mapWithKeys(fn ($k) => [$k => __($k, ['n' => ':n', 'm' => ':m'])])->all();
@endphp

@push('head')
<script>
    window.MP_FIGURE = { generate: @json(route('api.generate.store')), show: @json(url('/api/generate')), home: @json(route('home')), i18n: @json($i18n) };
</script>
@endpush

@section('content')
<div class="mx-auto max-w-2xl">
    <a href="{{ route('tools') }}" class="text-sm text-action-dark">← {{ __('tools.title') }}</a>
    <h1 class="mt-1 text-2xl font-extrabold">{{ __('tools.figure.title') }}</h1>
    <p class="text-slate-600">{{ __('figure.lead') }}</p>

    @unless($generator)
        <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ __('figure.unavailable') }}</div>
    @else
    <form id="figure-form" class="mt-5 space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="grid grid-cols-2 gap-2">
            <label class="cursor-pointer rounded-xl border border-slate-300 p-3 text-center has-[:checked]:border-action has-[:checked]:bg-action-soft">
                <input type="radio" name="kind" value="bust" checked class="sr-only"><div class="text-2xl">🗿</div><div class="font-semibold">{{ __('figure.kind.bust') }}</div><div class="text-xs text-slate-500">{{ __('figure.kind.bust.hint') }}</div>
            </label>
            <label class="cursor-pointer rounded-xl border border-slate-300 p-3 text-center has-[:checked]:border-action has-[:checked]:bg-action-soft">
                <input type="radio" name="kind" value="figure" class="sr-only"><div class="text-2xl">🧍</div><div class="font-semibold">{{ __('figure.kind.figure') }}</div><div class="text-xs text-slate-500">{{ __('figure.kind.figure.hint') }}</div>
            </label>
        </div>

        <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-action px-4 py-8 text-center hover:border-action hover:bg-action-soft">
            <img id="figure-preview" src="" alt="" class="mb-2 hidden max-h-48 rounded-lg">
            <span class="text-lg font-semibold">{{ __('figure.pick_photo') }}</span>
            <span class="text-sm text-slate-500">{{ __('figure.photo_tips') }}</span>
            <input id="figure-photo" name="image" type="file" accept="image/*" class="sr-only">
        </label>

        <label class="block text-sm font-semibold">{{ __('figure.size') }} <span id="figure-size-val" class="font-normal text-action-dark">80 mm</span>
            <input id="figure-size" name="target_mm" type="range" min="30" max="250" step="5" value="80" class="mt-1 w-full accent-action">
        </label>

        <fieldset>
            <legend class="text-sm font-semibold">{{ __('figure.pedestal') }}</legend>
            <div class="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                @foreach(['round' => '⬤', 'square' => '◼', 'hexagon' => '⬢', 'column' => '▂', 'plaque' => '▭'] as $pk => $ico)
                    <label class="cursor-pointer rounded-xl border border-slate-300 p-2 text-center has-[:checked]:border-action has-[:checked]:bg-action-soft has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action">
                        <input type="radio" name="pedestal" value="{{ $pk }}" class="sr-only" @checked($pk === 'round')><div class="text-lg" aria-hidden="true">{{ $ico }}</div>{{ __('figure.pedestal.'.$pk) }}
                    </label>
                @endforeach
            </div>
            <div id="figure-plaque" class="mt-3 hidden grid gap-3 sm:grid-cols-2">
                <label class="block text-sm font-semibold">{{ __('figure.pedestal.name') }}<input name="pedestal_name" maxlength="24" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal" placeholder="{{ __('figure.pedestal.name_ph') }}"></label>
                <label class="block text-sm font-semibold">{{ __('figure.pedestal.dedication') }}<input name="pedestal_dedication" maxlength="40" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal" placeholder="{{ __('figure.pedestal.dedication_ph') }}"></label>
                <p class="text-xs text-slate-500 sm:col-span-2">{{ __('figure.pedestal.hint') }}</p>
            </div>
        </fieldset>
        <script>document.querySelectorAll('input[name=pedestal]').forEach((r) => r.addEventListener('change', () => document.getElementById('figure-plaque').classList.toggle('hidden', !(r.checked && r.value === 'plaque'))));</script>

        <label class="flex items-start gap-2 text-sm"><input id="figure-consent" type="checkbox" name="consent" value="1" class="mt-1"> <span>{{ __('figure.consent') }}</span></label>
        <p class="text-xs text-slate-500">{{ __('figure.privacy') }}</p>

        <button class="w-full rounded-xl bg-action px-4 py-3 font-semibold text-white disabled:opacity-60">{{ __('figure.submit') }}</button>
        <div id="figure-progress" class="hidden"><div class="h-2 w-full overflow-hidden rounded-full bg-slate-200"><div id="figure-bar" class="h-2 w-0 bg-action transition-all"></div></div></div>
        <p id="figure-msg" class="text-sm text-slate-600"></p>
        <p class="text-xs text-slate-400">{{ __('figure.limits', ['n' => $guestLimit, 'm' => $userLimit]) }}</p>
    </form>
    @endunless
</div>
@endsection
