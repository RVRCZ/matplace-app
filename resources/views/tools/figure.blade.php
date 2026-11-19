@php
    $i18n = collect(['figure.generating', 'figure.done', 'figure.failed', 'figure.rejected', 'figure.skipped_view', 'figure.view.left', 'figure.view.back', 'figure.view.right', 'figure.limit', 'figure.global_limit', 'figure.need_photo', 'figure.need_consent', 'figure.limit.register', 'figure.remove', 'figure.again', 'figure.again.why', 'figure.notify.sent', 'figure.notify.failed', 'figure.made.height', 'figure.made.rest', 'figure.made.note', 'figure.again.rebase', 'figure.again.rebase.why', 'figure.rebase.working', 'figure.rebase.failed', 'figure.rejected.cropped'])
        ->mapWithKeys(fn ($k) => [$k => __($k, ['n' => ':n', 'm' => ':m', 'mm' => ':mm', 'w' => ':w', 'd' => ':d'])])->all() + ['photo.too_big' => __('photo.too_big', ['mb' => \App\Http\Controllers\Api\GenerationController::PHOTO_MB]), 'photo.add' => __('photo.add'), 'photo.add.more' => __('photo.add.more'), 'figure.view.front' => __('figure.view.front')];
    $bust = app(\App\Domain\Generation\BustStyles::class);
    $choice = 'cursor-pointer rounded-lg border border-slate-300 bg-white text-center text-ink has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action';
@endphp

@extends('tools.page', ['tool' => 'figure', 'module' => 'figure', 'lead' => __('figure.lead'), 'available' => $generator, 'unavailable' => __('figure.unavailable'), 'sample' => __('toolpage.sample.minutes'),
    'sections' => ['photo' => __('toolpage.section.photo'), 'base' => __('toolpage.section.base')]])

@push('head')
<script>
    window.MP_FIGURE = { generate: @json(route('api.generate.store')), show: @json(url('/api/generate')), home: @json(route('home')), files: @json(url('/api/files')), maxMb: {{ \App\Http\Controllers\Api\GenerationController::PHOTO_MB }}, register: @json(auth()->guest() && \Illuminate\Support\Facades\Route::has('register') ? route('register') : null), i18n: @json($i18n) };
</script>
@endpush

@section('viewer-empty'){{ __('figure.step.model') }}@endsection

@section('viewer-drop')@include('tools._figure_drop', ['consent' => __('figure.consent'), 'limits' => trans_choice('figure.limits', $guestLimit, ['n' => $guestLimit, 'm' => $userLimit]), 'submit' => __('figure.submit')])@endsection

@section('panel')
<form id="figure-form" novalidate>
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
                    {{-- how much of the body the photo has to show depends on what is made: a bust or a whole figure --}}
                    <li @if($step === 'frame') data-by-kind data-bust="{{ __('figure.howto.frame') }}" data-figure="{{ __('figure.howto.frame.figure') }}" @endif>{{ __('figure.howto.'.$step) }}</li>
                @endforeach
            </ol>
            <p class="mt-2 text-xs text-muted">{{ __('figure.howto.note') }}</p>
        </details>
        {{-- the how-to video of this page, once it is public on the channel (queued by farm:howto-video with link /tools/figure) --}}
        @if($howtoVideo = app(\App\Domain\YouTube\FarmVideos::class)->howtoFor('/tools/figure'))
            <a href="{{ $howtoVideo }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm font-medium text-ink underline decoration-line underline-offset-4 hover:decoration-ink"><span aria-hidden="true">▶</span>{{ __('figure.howto.video') }}</a>
        @endif

        @include('tools._figure_photos')
        <span id="figure-tips-by-kind" hidden data-bust="{{ __('figure.photo_tips') }}" data-figure="{{ __('figure.photo_tips.figure') }}"></span>
    </x-tool-section>

    <x-tool-section id="base" :title="__('toolpage.section.base')">
        <label class="lbl">{{ __('figure.size') }} <span id="figure-size-val" class="num font-normal text-muted">{{ (int) config('ai.default_target_mm', 150) }} mm</span>
            <input id="figure-size" name="target_mm" type="range" min="30" max="250" step="5" value="{{ (int) config('ai.default_target_mm', 150) }}" class="mt-1 w-full accent-ink">
        </label>

        <fieldset>
            <legend class="lbl">{{ __('figure.pedestal') }}</legend>
            {{-- one line about every bust style on offer: what the chest and the foot look like, where the name stands --}}
            <ul id="figure-bust-styles" class="mt-1 space-y-0.5 text-xs text-muted">
                @foreach($bust->shown() as $pk)
                    <li><span class="font-medium text-ink">{{ __('figure.pedestal.'.$pk) }}:</span> {{ __('figure.pedestal.about.'.$pk) }}</li>
                @endforeach
            </ul>
            <div class="mt-2 grid grid-cols-3 gap-1.5 text-sm" role="radiogroup">
                {{-- the bust's own styles first, the ones the owner shows (BustStyles, /admin/ai), then the plain bases --}}
                @foreach($bust->allowed() as $pk)
                    <label class="{{ $choice }} px-2 py-1.5" @if(in_array($pk, \App\Domain\Generation\PedestalChanger::BUST_STYLES, true)) data-bust-style @endif>
                        <input type="radio" name="pedestal" value="{{ $pk }}" class="sr-only" @checked($pk === $bust->default())>{{ __('figure.pedestal.'.$pk) }}
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
                    const to = picked('kind') === 'figure' ? (styles.includes(now) ? 'round' : now) : (now === 'round' ? {{ \Illuminate\Support\Js::from($bust->default()) }} : now);
                    document.querySelector('input[name=pedestal][value=' + to + ']').checked = true;
                    fields();
                    byKind();
                }));
                // what belongs to a bust only (its three styles of the chest and the words about them) is not offered for a
                // whole figure, and the photo tips say how much of the body has to be in the picture (15 Nov 2026)
                const byKind = () => {
                    const figure = picked('kind') === 'figure';
                    document.querySelectorAll('[data-bust-style]').forEach((el) => el.classList.toggle('hidden', figure));
                    document.getElementById('figure-bust-styles').classList.toggle('hidden', figure);
                    const tips = document.getElementById('figure-tips-by-kind');
                    const shown = document.querySelector('[data-photo-tips]');
                    if (tips && shown) shown.textContent = figure ? tips.dataset.figure : tips.dataset.bust;
                    document.querySelectorAll('[data-by-kind]').forEach((el) => { el.textContent = figure ? el.dataset.figure : el.dataset.bust; });
                };
                document.getElementById('figure-form').addEventListener('figure:filled', () => { fields(); byKind(); });
                fields();
                byKind();
            })();
        </script>

        {{-- the step ends with the model being made; the material and the number of pieces follow as the page's last step --}}
        <label class="flex items-start gap-3 text-sm text-ink"><input id="figure-consent" type="checkbox" name="consent" value="1" required class="mt-0.5 h-5 w-5 accent-ink"> <span>{{ __('figure.consent') }}</span></label>
        <p class="text-xs text-muted">{{ __('figure.privacy') }}</p>

        {{-- before the click: the free models a day and how long one takes (docs/AF.md §3) --}}
        <p class="text-xs text-muted">{{ trans_choice('figure.limits', $guestLimit, ['n' => $guestLimit, 'm' => $userLimit]) }}</p>
        <button id="figure-submit" type="submit" class="btn-ink w-full gap-1.5"><x-icon name="sparkles" class="h-4 w-4" />{{ __('figure.submit') }}</button>
        <div id="figure-progress" class="hidden"><div class="h-2 w-full overflow-hidden rounded-full bg-slate-200"><div id="figure-bar" class="h-2 w-0 bg-ink transition-all"></div></div><p class="mt-1 text-xs text-muted">{{ __('figure.step.wait') }}</p></div>
        <p id="figure-msg" class="hint" aria-live="polite"></p>
        {{-- another base or another name under the model that is on the stage: seconds of geometry, no new generation --}}
        <div id="figure-rebase-box" class="hidden">
            <button id="figure-rebase" type="button" class="btn-quiet w-full">{{ __('figure.rebase') }}</button>
            <p class="mt-1 text-xs text-muted">{{ __('figure.rebase.hint') }}</p>
        </div>
        {{-- "send me the result": a model takes 2 to 4 minutes; one e-mail with the link, nothing else (docs/AN.md §12.5) --}}
        {{-- a block, not a form: a form inside the page's form is dropped by the browser and the box then showed all the time --}}
        <div id="figure-notify" class="mt-2 rounded-lg bg-slate-50 p-3 text-sm" hidden>
            <label for="figure-notify-email" class="block text-xs text-muted">{{ __('figure.notify.lead') }}</label>
            <div class="mt-1 flex gap-2"><input id="figure-notify-email" type="email" required maxlength="190" autocomplete="email" placeholder="{{ __('figure.notify.email') }}" class="field !mt-0 min-w-0 flex-1 text-sm"><button type="button" class="btn-secondary !min-h-11 px-3 text-sm">{{ __('figure.notify.send') }}</button></div>
            <p class="mt-1 text-xs text-muted">{{ __('figure.notify.consent') }}</p>
            <p class="mt-1 text-xs" data-notify-msg aria-live="polite"></p>
        </div>
    </x-tool-section>
</form>
@endsection
