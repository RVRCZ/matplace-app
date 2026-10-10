@php
    $i18n = collect(['figure.generating', 'figure.done', 'figure.failed', 'figure.rejected', 'figure.skipped_view', 'figure.view.left', 'figure.view.back', 'figure.view.right', 'figure.limit', 'figure.global_limit', 'figure.need_photo', 'figure.need_consent'])
        ->mapWithKeys(fn ($k) => [$k => __($k, ['n' => ':n', 'm' => ':m'])])->all();
    $choice = 'cursor-pointer rounded-lg border border-slate-300 bg-white text-center text-ink has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action';
@endphp

@extends('tools.page', ['tool' => 'figure', 'module' => 'figure', 'lead' => __('figure.lead'), 'available' => $generator, 'unavailable' => __('figure.unavailable'),
    'sections' => ['photo' => __('toolpage.section.photo'), 'base' => __('toolpage.section.base'), 'print' => \App\Support\NextStep::text('param.step.inquiry')]])

@push('head')
<script>
    window.MP_FIGURE = { generate: @json(route('api.generate.store')), show: @json(url('/api/generate')), home: @json(route('home')), i18n: @json($i18n) };
</script>
@endpush

@section('viewer-empty'){{ __('figure.step.model') }}@endsection

@section('panel')
<form id="figure-form">
    <x-tool-section id="photo" :title="__('toolpage.section.photo')">
        <fieldset>
            <legend class="sr-only">{{ __('figure.kind.bust') }} / {{ __('figure.kind.figure') }}</legend>
            <div class="grid grid-cols-2 gap-2" role="radiogroup">
                @foreach(['bust' => 'square-user-round', 'figure' => 'person-standing'] as $k => $ico)
                    <label class="{{ $choice }} p-3">
                        <input type="radio" name="kind" value="{{ $k }}" @checked($k === 'bust') class="sr-only"><x-icon :name="$ico" class="mx-auto h-6 w-6" /><div class="mt-1 font-medium">{{ __('figure.kind.'.$k) }}</div><div class="text-xs text-muted">{{ __('figure.kind.'.$k.'.hint') }}</div>
                    </label>
                @endforeach
            </div>
        </fieldset>

        {{-- how to take the photos: the length of the chest and the likeness stand on it --}}
        <details class="rounded-lg border border-line p-3 text-sm">
            <summary class="cursor-pointer font-medium text-ink">{{ __('figure.howto') }}</summary>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-ink">
                @foreach(['distance', 'frame', 'height', 'arms', 'wall', 'sides'] as $step)
                    <li>{{ __('figure.howto.'.$step) }}</li>
                @endforeach
            </ol>
            <p class="mt-2 text-xs text-muted">{{ __('figure.howto.note') }}</p>
        </details>

        <div class="relative">
            <label data-view-box="front" class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-line px-4 py-6 text-center hover:border-ink">
                <img id="figure-preview" data-view-preview="front" src="" alt="" class="mb-2 hidden max-h-44 rounded-lg">
                <x-icon name="camera" class="h-7 w-7 text-muted" />
                <span class="mt-1 font-medium text-ink">{{ __('figure.pick_photo') }}</span>
                <span class="text-sm text-muted">{{ __('figure.photo_tips') }}</span>
                <input id="figure-photo" name="image" data-view="front" type="file" accept="image/*" class="sr-only">
            </label>
            <button type="button" data-view-remove="front" class="tool-icon-btn absolute right-2 top-2 hidden" aria-label="{{ __('figure.remove') }}" title="{{ __('figure.remove') }}"><x-icon name="x" class="h-4 w-4" /></button>
        </div>

        {{-- more sides are always on the screen: the likeness is clearly better with them --}}
        <fieldset id="figure-views" class="rounded-lg border border-line p-3">
            <legend class="px-1 text-sm font-medium text-ink">{{ __('figure.views') }}</legend>
            <p class="text-sm text-ink"><strong>{{ __('figure.views.better') }}</strong> {{ __('figure.views.better_hint') }}</p>
            <div class="mt-3 grid grid-cols-3 gap-2">
                @foreach(['left' => 'arrow-left', 'back' => 'refresh-cw', 'right' => 'arrow-right'] as $view => $ico)
                    <div class="relative">
                        <label data-view-box="{{ $view }}" class="flex h-full cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-line p-2 text-center text-sm hover:border-ink">
                            <img data-view-preview="{{ $view }}" src="" alt="" class="mb-1 hidden max-h-20 rounded">
                            <x-icon :name="$ico" class="h-4 w-4 text-muted" />
                            <span class="font-medium text-ink">{{ __('figure.view.'.$view) }}</span>
                            <input name="image_{{ $view }}" data-view="{{ $view }}" type="file" accept="image/*" class="sr-only">
                        </label>
                        <button type="button" data-view-remove="{{ $view }}" class="tool-icon-btn absolute right-1 top-1 hidden !h-7 !w-7" aria-label="{{ __('figure.remove') }}: {{ __('figure.view.'.$view) }}" title="{{ __('figure.remove') }}"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                    </div>
                @endforeach
            </div>
            <p id="figure-views-msg" class="mt-2 hidden text-sm text-warn" aria-live="polite"></p>
            <p class="mt-2 text-xs text-muted">{{ __('figure.views.tips') }}</p>
        </fieldset>
    </x-tool-section>

    <x-tool-section id="base" :title="__('toolpage.section.base')">
        <label class="lbl">{{ __('figure.size') }} <span id="figure-size-val" class="num font-normal text-muted">80 mm</span>
            <input id="figure-size" name="target_mm" type="range" min="30" max="250" step="5" value="80" class="mt-1 w-full accent-ink">
        </label>

        <fieldset>
            <legend class="lbl">{{ __('figure.pedestal') }}</legend>
            <p class="mt-1 text-xs text-muted">{{ __('figure.pedestal.styles') }}</p>
            <div class="mt-2 grid grid-cols-3 gap-1.5 text-sm" role="radiogroup">
                @foreach(['socle', 'antique', 'cut', 'round', 'square', 'hexagon', 'column', 'plaque', 'none'] as $pk)
                    <label class="{{ $choice }} px-2 py-1.5">
                        <input type="radio" name="pedestal" value="{{ $pk }}" class="sr-only" @checked($pk === 'socle')>{{ __('figure.pedestal.'.$pk) }}
                    </label>
                @endforeach
            </div>
            <div id="figure-plaque" class="mt-3 grid gap-3">
                <label class="lbl">{{ __('figure.pedestal.name') }}<input name="pedestal_name" maxlength="24" class="field" placeholder="{{ __('figure.pedestal.name_ph') }}"></label>
                <label id="figure-dedication" class="lbl hidden">{{ __('figure.pedestal.dedication') }}<input name="pedestal_dedication" maxlength="40" class="field" placeholder="{{ __('figure.pedestal.dedication_ph') }}"></label>
                <p class="text-xs text-muted">{{ __('figure.pedestal.hint') }}</p>
            </div>
        </fieldset>
        <script>
            (() => {
                const named = {{ \Illuminate\Support\Js::from(\App\Domain\Generation\PedestalChanger::NAMED) }}, dedicated = {{ \Illuminate\Support\Js::from(\App\Domain\Generation\PedestalChanger::DEDICATED) }};
                const picked = (name) => document.querySelector('input[name=' + name + ']:checked')?.value;
                const fields = () => {
                    document.getElementById('figure-plaque').classList.toggle('hidden', !named.includes(picked('pedestal')));
                    document.getElementById('figure-dedication').classList.toggle('hidden', !dedicated.includes(picked('pedestal')));
                };
                document.querySelectorAll('input[name=pedestal]').forEach((r) => r.addEventListener('change', fields));
                // the turned foot belongs under a bust; a standing figure starts on the plain round base
                document.querySelectorAll('input[name=kind]').forEach((r) => r.addEventListener('change', () => {
                    const styles = {{ \Illuminate\Support\Js::from(\App\Domain\Generation\PedestalChanger::BUST_STYLES) }};
                    const now = picked('pedestal');
                    const to = picked('kind') === 'figure' ? (styles.includes(now) ? 'round' : now) : (now === 'round' ? 'socle' : now);
                    document.querySelector('input[name=pedestal][value=' + to + ']').checked = true;
                    fields();
                }));
                fields();
            })();
        </script>
    </x-tool-section>

    <x-tool-section id="print" :title="\App\Support\NextStep::text('param.step.inquiry')">
        <label class="flex items-start gap-3 text-sm text-ink"><input id="figure-consent" type="checkbox" name="consent" value="1" class="mt-0.5 h-5 w-5 accent-ink"> <span>{{ __('figure.consent') }}</span></label>
        <p class="text-xs text-muted">{{ __('figure.privacy') }}</p>

        <button id="figure-submit" type="submit" class="btn-ink w-full gap-1.5"><x-icon name="sparkles" class="h-4 w-4" />{{ __('figure.submit') }}</button>
        <div id="figure-progress" class="hidden"><div class="h-2 w-full overflow-hidden rounded-full bg-slate-200"><div id="figure-bar" class="h-2 w-0 bg-ink transition-all"></div></div><p class="mt-1 text-xs text-muted">{{ __('figure.step.wait') }}</p></div>
        <p id="figure-msg" class="hint" aria-live="polite"></p>
        <p class="text-xs text-muted">{{ __('figure.limits', ['n' => $guestLimit, 'm' => $userLimit]) }}</p>
    </x-tool-section>
</form>
@endsection
