{{--
    A pet figurine from a photo. The page is the figure tool again (module `figure`, resources/js/calc/figure.ts reads the
    same ids): what a pet needs differently is here, as texts and as the step "style". The words the script says are the
    pet's own, handed to it under the keys it already asks for.
--}}
@php
    $say = ['generating' => 'pet.generating', 'done' => 'pet.done', 'failed' => 'pet.failed', 'rejected' => 'pet.rejected', 'skipped_view' => 'figure.skipped_view',
        'view.left' => 'figure.view.left', 'view.back' => 'figure.view.back', 'view.right' => 'figure.view.right', 'limit' => 'figure.limit', 'global_limit' => 'figure.global_limit',
        'need_photo' => 'figure.need_photo', 'need_consent' => 'figure.need_consent', 'agree_create' => 'figure.agree_create', 'limit.register' => 'figure.limit.register', 'remove' => 'figure.remove', 'back' => 'pet.back', 'gone' => 'pet.gone', 'thin' => 'pet.thin', 'thin.ok' => 'pet.thin.ok',
        'rebase.working' => 'pet.rebase.working', 'rebase.failed' => 'pet.rebase.failed', 'notify.sent' => 'figure.notify.sent', 'notify.failed' => 'figure.notify.failed',
        'rejected.no_animal' => 'pet.rejected.no_animal', 'rejected.person' => 'pet.rejected.person', 'rejected.several' => 'pet.rejected.several', 'rejected.cropped' => 'pet.rejected.cropped',
        'rejected.owner.no_person' => 'pet.rejected.owner.no_person', 'rejected.owner.several_people' => 'pet.rejected.owner.several_people', 'rejected.owner.cropped' => 'pet.rejected.owner.cropped', 'thin.owner' => 'pet.thin.owner',
        'preparing' => 'figure.preparing', 'again' => 'figure.again', 'again.why' => 'figure.again.why', 'again.rebase' => 'figure.again.rebase', 'again.rebase.why' => 'figure.again.rebase.why'];
    $i18n = collect($say)->mapWithKeys(fn ($key, $as) => ['figure.'.$as => __($key, ['n' => ':n', 'm' => ':m', 'mm' => ':mm', 'view' => ':view'])])->all()
        + ['photo.too_big' => __('photo.too_big', ['mb' => \App\Http\Controllers\Api\GenerationController::PHOTO_MB]), 'photo.add' => __('photo.add'), 'photo.add.more' => __('photo.add.more'), 'figure.view.front' => __('figure.view.front')];
    $choice = 'cursor-pointer rounded-lg border border-slate-300 bg-white text-ink has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50';
@endphp

@extends('tools.page', ['tool' => 'pet', 'module' => 'figure', 'lead' => __('pet.lead'), 'available' => $generator, 'unavailable' => __('figure.unavailable'), 'sample' => __('toolpage.sample.minutes'),
    'sections' => ['photo' => __('toolpage.section.photo'), 'style' => __('toolpage.section.style'), 'base' => __('toolpage.section.base')]])

@push('head')
<script>
    window.MP_FIGURE = { generate: @json(route('api.generate.store')), show: @json(url('/api/generate')), home: @json(route('home')), maxMb: {{ \App\Http\Controllers\Api\GenerationController::PHOTO_MB }}, register: @json(auth()->guest() && \Illuminate\Support\Facades\Route::has('register') ? route('register') : null), files: @json(url('/api/files')), i18n: @json($i18n) };
</script>
@endpush

@section('viewer-empty'){{ __('pet.step.model') }}@endsection

@section('viewer-drop')@include('tools._figure_drop', ['consent' => __('pet.consent'), 'limits' => trans_choice('figure.limits', $guestLimit, ['n' => $guestLimit, 'm' => $userLimit]), 'submit' => __('pet.submit'), 'name' => __('pet.name'), 'namePlaceholder' => __('pet.name_ph'), 'nameHint' => __('pet.name.first'),
    'owner' => ['label' => __('pet.owner.label'), 'alone' => __('pet.owner.alone'), 'with' => __('pet.owner.with'), 'consentOwner' => __('pet.consent.owner')]])@endsection

@section('panel')
<form id="figure-form" data-kind="pet" novalidate>
    <input type="hidden" name="kind" value="pet">

    <x-tool-section id="photo" :title="__('toolpage.section.photo')">
        {{-- what a photo of an animal needs: the legs and the tail in the picture, and nobody holding it --}}
        <details class="rounded-lg border border-line p-3 text-sm" open>
            <summary class="cursor-pointer font-medium text-ink">{{ __('pet.howto') }}</summary>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-ink">
                @foreach(['body', 'angle', 'pose', 'wall', 'alone'] as $step)
                    <li>{{ __('pet.howto.'.$step) }}</li>
                @endforeach
            </ol>
            <p class="mt-2 text-xs text-muted">{{ __('pet.howto.note') }}</p>
        </details>

        @include('tools._figure_photos', ['pick' => __('pet.pick_photo'), 'tips' => __('pet.photo_tips'), 'betterHint' => __('pet.views.better_hint'), 'viewsTips' => __('pet.views.tips')])

        {{-- the animal alone (the default) or with its owner (Roman, 15 Nov 2026): a person in the figure changes the photo
             check, the cleaning of the photo, the styles offered and the consent; the dialog in the viewer has the same choice
             (figure-drop-owner-*), the script below keeps the two in step --}}
        <fieldset>
            <legend class="lbl">{{ __('pet.owner.label') }}</legend>
            <div class="mt-2 grid gap-2" role="radiogroup">
                @foreach(['0' => 'alone', '1' => 'with'] as $value => $key)
                    <label class="{{ $choice }} flex items-start gap-3 p-3 text-left">
                        <input type="radio" name="with_person" value="{{ $value }}" @checked((string) $value === '0') class="mt-1 h-4 w-4 accent-ink">
                        <span><span class="block font-medium">{{ __('pet.owner.'.$key) }}</span><span class="block text-sm text-muted">{{ __('pet.owner.'.$key.'.hint') }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        {{-- asked with the photo (Roman, 14 Nov 2026): a figurine with the name is a gift; with a name the plinth that
             carries it is the base offered first. Not required. --}}
        <label class="lbl">{{ __('pet.name') }}<input name="pedestal_name" maxlength="24" class="field" placeholder="{{ __('pet.name_ph') }}" autocomplete="off">
            <span class="mt-1 block text-xs font-normal text-muted">{{ __('pet.name.first') }}</span>
        </label>
    </x-tool-section>

    <x-tool-section id="style" :title="__('toolpage.section.style')">
        <fieldset>
            <legend class="sr-only">{{ __('pet.style') }}</legend>
            <div class="grid gap-2" role="radiogroup">
                @foreach($styles as $i => $style)
                    <label class="{{ $choice }} flex items-start gap-3 p-3 text-left">
                        <input type="radio" name="style" value="{{ $style }}" @checked($i === 0) class="mt-1 h-4 w-4 accent-ink">
                        <span><span class="block font-medium">{{ __('pet.style.'.$style) }}</span><span class="block text-sm text-muted">{{ __('pet.style.'.$style.'.hint') }}</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <p id="pet-owner-style" class="hidden text-sm text-ink">{{ __('pet.owner.style') }}</p>
        {{-- only the miniature is grown by us: how much is its one setting --}}
        <fieldset id="pet-roughness" class="hidden">
            <legend class="lbl">{{ __('pet.roughness') }}</legend>
            <div class="mt-2 grid grid-cols-3 gap-1.5 text-sm" role="radiogroup">
                @foreach([1, 2, 3] as $level)
                    <label class="{{ $choice }} px-2 py-1.5 text-center"><input type="radio" name="roughness" value="{{ $level }}" class="sr-only" @checked($level === 2)>{{ __('pet.roughness.'.$level) }}</label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-muted">{{ __('pet.roughness.hint') }}</p>
        </fieldset>
    </x-tool-section>

    <x-tool-section id="base" :title="__('toolpage.section.base')">
        <label class="lbl">{{ __('pet.size') }} <span id="figure-size-val" class="num font-normal text-muted">{{ (int) config('ai.default_target_mm', 150) }} mm</span>
            <input id="figure-size" name="target_mm" type="range" min="30" max="250" step="5" value="{{ (int) config('ai.default_target_mm', 150) }}" class="mt-1 w-full accent-ink">
            <span class="mt-1 block text-xs font-normal text-muted">{{ __('pet.size.hint') }}</span>
            <span id="pet-owner-size" class="mt-1 hidden text-xs font-normal text-ink">{{ __('pet.owner.size') }}</span>
        </label>

        <fieldset>
            <legend class="lbl">{{ __('pet.base') }}</legend>
            <div class="mt-2 grid grid-cols-2 gap-1.5 text-sm" role="radiogroup">
                @foreach($bases as $i => $base)
                    <label class="{{ $choice }} px-2 py-1.5 text-center"><input type="radio" name="pedestal" value="{{ $base }}" class="sr-only" @checked($i === 0)>{{ __('pet.base.'.$base) }}</label>
                @endforeach
            </div>
            <p id="pet-base-note" class="mt-2 hidden text-xs text-ink">{{ __('pet.base.miniature') }}</p>
            <p id="pet-base-none" class="tool-warn mt-2 hidden text-sm">{{ __('pet.base.none.hint') }}</p>
            <p class="mt-2 text-xs text-muted">{{ __('pet.base.hint') }}</p>
            {{-- where the name stands and the second line live on a base: without one there is nothing to write them on --}}
            <div id="pet-name-box" class="mt-3 grid gap-3">
                <label class="lbl">{{ __('pet.name_side') }}
                    <select name="name_side" class="field">
                        @foreach(['front', 'right', 'back', 'left', 'auto'] as $side)<option value="{{ $side }}" @selected($side === 'front')>{{ __('pet.name_side.'.$side) }}</option>@endforeach
                    </select>
                </label>
                <label id="pet-dedication" class="lbl hidden">{{ __('pet.dedication') }}<input name="pedestal_dedication" maxlength="40" class="field" placeholder="{{ __('pet.dedication_ph') }}" autocomplete="off"></label>
                <p class="text-xs text-muted">{{ __('pet.name.hint') }} {{ __('pet.name_side.hint') }}</p>
            </div>
        </fieldset>
        <script>
            (() => {
                // a miniature stands on its disc; the second line belongs to the plinth alone. figure.ts calls this again
                // when it fills the form from a stored figurine (the event below).
                const form = document.getElementById('figure-form');
                const picked = (name) => form.querySelector('input[name=' + name + ']:checked')?.value;
                // the animal with its owner: the realistic style only, the consent of a photographed person, the dialog's choice in step
                const owner = () => picked('with_person') === '1';
                const consent = document.getElementById('figure-consent');
                const dropConsent = document.getElementById('figure-drop-consent');
                let wasOwner = false;
                const fields = () => {
                    const withOwner = owner();
                    if (withOwner) {
                        const real = form.querySelector('input[name=style][value=realistic]');
                        if (real && !real.checked) { real.checked = true; }
                    }
                    form.querySelectorAll('input[name=style]').forEach((r) => { r.disabled = withOwner && r.value !== 'realistic'; });
                    document.getElementById('pet-owner-style').classList.toggle('hidden', !withOwner);
                    document.getElementById('pet-owner-size').classList.toggle('hidden', !withOwner);
                    document.querySelectorAll('[data-consent], [data-privacy]').forEach((el) => {
                        const on = (el.dataset.consent ?? el.dataset.privacy) === (withOwner ? '1' : '0');
                        el.classList.toggle('hidden', !on);
                    });
                    document.querySelectorAll('[data-drop-consent]').forEach((el) => el.classList.toggle('hidden', el.dataset.dropConsent !== (withOwner ? '1' : '0')));
                    const mirror = document.getElementById('figure-drop-owner-' + (withOwner ? '1' : '0'));
                    if (mirror && !mirror.checked) mirror.checked = true;
                    // the consent given for the animal alone is not the one for a person: it has to be given again
                    if (withOwner && !wasOwner && consent?.checked) { consent.checked = false; if (dropConsent) dropConsent.checked = false; }
                    wasOwner = withOwner;
                    const miniature = picked('style') === 'miniature';
                    document.getElementById('pet-roughness').classList.toggle('hidden', !miniature);
                    document.getElementById('pet-base-note').classList.toggle('hidden', !miniature);
                    form.querySelectorAll('input[name=pedestal]').forEach((r) => { r.disabled = miniature && r.value !== 'round'; });
                    if (miniature) form.querySelector('input[name=pedestal][value=round]').checked = true;
                    document.getElementById('pet-dedication').classList.toggle('hidden', picked('pedestal') !== 'plaque');
                    document.getElementById('pet-name-box').classList.toggle('hidden', picked('pedestal') === 'none');
                    document.getElementById('pet-base-none').classList.toggle('hidden', picked('pedestal') !== 'none');
                };
                // the plinth with the name on its front is the base the page opens with (Roman, 15 Nov 2026); the miniature
                // takes the disc and gives the plinth back when it is left, until the visitor picks a base by hand (or a stored
                // figurine brings its own). The name field of the dialog in the viewer is the same field: the dialog comes
                // later in the page than this script, so it is found by delegation, not by id at this moment.
                const name = form.querySelector('input[name=pedestal_name]');
                let own = false;
                const byName = () => {
                    const first = document.getElementById('figure-drop-name');
                    if (first && first.value !== name.value) first.value = name.value;
                    if (!own && picked('style') !== 'miniature') {
                        const base = form.querySelector('input[name=pedestal][value=plaque]');
                        if (base && !base.checked) { base.checked = true; base.dispatchEvent(new Event('change', { bubbles: true })); }
                    }
                    fields();
                };
                name.addEventListener('input', byName);
                document.addEventListener('input', (e) => {
                    if (e.target?.id !== 'figure-drop-name') return;
                    name.value = e.target.value;
                    name.dispatchEvent(new Event('input', { bubbles: true })); name.dispatchEvent(new Event('change', { bubbles: true }));
                });
                form.querySelectorAll('input[name=pedestal]').forEach((r) => r.addEventListener('change', (e) => { if (e.isTrusted) { own = true; form.dataset.pedestalOwn = '1'; } fields(); }));
                form.querySelectorAll('input[name=style]').forEach((r) => r.addEventListener('change', byName));
                form.querySelectorAll('input[name=with_person]').forEach((r) => r.addEventListener('change', byName));
                document.addEventListener('change', (e) => {
                    const r = e.target;
                    if (r?.name !== 'drop_with_person' || !r.checked) return;
                    const panel = form.querySelector('input[name=with_person][value="' + r.value + '"]');
                    if (panel && !panel.checked) { panel.checked = true; panel.dispatchEvent(new Event('change', { bubbles: true })); }
                });
                form.addEventListener('figure:filled', () => {
                    own = form.dataset.pedestalOwn === '1' || own;
                    const first = document.getElementById('figure-drop-name'); if (first) first.value = name.value;
                    fields();
                });
                fields();
            })();
        </script>

        <label class="flex items-start gap-3 text-sm text-ink"><input id="figure-consent" type="checkbox" name="consent" value="1" required class="mt-0.5 h-5 w-5 accent-ink"> <span data-consent="0">{{ __('pet.consent') }}</span><span data-consent="1" class="hidden">{{ __('pet.consent.owner') }}</span></label>
        <p class="text-xs text-muted" data-privacy="0">{{ __('pet.privacy') }}</p>
        <p class="hidden text-xs text-muted" data-privacy="1">{{ __('pet.privacy.owner') }}</p>

        {{-- before the click: the free models a day and how long one takes (docs/AF.md §3) --}}
        <p class="text-xs text-muted">{{ trans_choice('figure.limits', $guestLimit, ['n' => $guestLimit, 'm' => $userLimit]) }}</p>
        <button id="figure-submit" type="submit" class="btn-ink w-full gap-1.5"><x-icon name="sparkles" class="h-4 w-4" />{{ __('pet.submit') }}</button>
        <div id="figure-progress" class="hidden"><div class="h-2 w-full overflow-hidden rounded-full bg-slate-200"><div id="figure-bar" class="h-2 w-0 bg-ink transition-all"></div></div><p class="mt-1 text-xs text-muted">{{ __('pet.wait') }}</p></div>
        <p id="figure-msg" class="hint" aria-live="polite"></p>
        {{-- "send me the result": a model takes 2 to 4 minutes; one e-mail with the link, nothing else (docs/AN.md §12.5) --}}
        <div id="figure-notify" class="mt-2 rounded-lg bg-slate-50 p-3 text-sm" hidden>
            <label for="figure-notify-email" class="block text-xs text-muted">{{ __('figure.notify.lead') }}</label>
            <div class="mt-1 flex gap-2"><input id="figure-notify-email" type="email" required maxlength="190" autocomplete="email" placeholder="{{ __('figure.notify.email') }}" class="field !mt-0 min-w-0 flex-1 text-sm"><button type="button" class="btn-secondary !min-h-11 px-3 text-sm">{{ __('figure.notify.send') }}</button></div>
            <p class="mt-1 text-xs text-muted">{{ __('figure.notify.consent') }}</p>
            <p class="mt-1 text-xs" data-notify-msg aria-live="polite"></p>
        </div>
        {{-- once a figurine exists: its thinnest place, and another base under the same figure without a new generation --}}
        <p id="figure-thin-ok" class="hidden rounded-lg bg-ok-soft px-3 py-2 text-sm text-ok" aria-live="polite"></p>
        <div id="figure-rebase-box" class="hidden">
            <button id="figure-rebase" type="button" class="btn-quiet w-full">{{ __('pet.rebase') }}</button>
            <p class="mt-1 text-xs text-muted">{{ __('pet.rebase.hint') }}</p>
        </div>
    </x-tool-section>
</form>
@endsection
