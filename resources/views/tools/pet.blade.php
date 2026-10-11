{{--
    A pet figurine from a photo. The page is the figure tool again (module `figure`, resources/js/calc/figure.ts reads the
    same ids): what a pet needs differently is here, as texts and as the step "style". The words the script says are the
    pet's own, handed to it under the keys it already asks for.
--}}
@php
    $say = ['generating' => 'pet.generating', 'done' => 'pet.done', 'failed' => 'pet.failed', 'rejected' => 'pet.rejected', 'skipped_view' => 'figure.skipped_view',
        'view.left' => 'figure.view.left', 'view.back' => 'figure.view.back', 'view.right' => 'figure.view.right', 'limit' => 'figure.limit', 'global_limit' => 'figure.global_limit',
        'need_photo' => 'figure.need_photo', 'need_consent' => 'figure.need_consent', 'back' => 'pet.back', 'gone' => 'pet.gone', 'thin' => 'pet.thin', 'thin.ok' => 'pet.thin.ok',
        'rebase.working' => 'pet.rebase.working', 'rebase.failed' => 'pet.rebase.failed',
        'rejected.no_animal' => 'pet.rejected.no_animal', 'rejected.person' => 'pet.rejected.person', 'rejected.several' => 'pet.rejected.several', 'rejected.cropped' => 'pet.rejected.cropped'];
    $i18n = collect($say)->mapWithKeys(fn ($key, $as) => ['figure.'.$as => __($key, ['n' => ':n', 'm' => ':m', 'mm' => ':mm', 'view' => ':view'])])->all();
    $choice = 'cursor-pointer rounded-lg border border-slate-300 bg-white text-ink has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50';
@endphp

@extends('tools.page', ['tool' => 'pet', 'module' => 'figure', 'lead' => __('pet.lead'), 'available' => $generator, 'unavailable' => __('figure.unavailable'),
    'sections' => ['photo' => __('toolpage.section.photo'), 'style' => __('toolpage.section.style'), 'base' => __('toolpage.section.base')]])

@push('head')
<script>
    window.MP_FIGURE = { generate: @json(route('api.generate.store')), show: @json(url('/api/generate')), home: @json(route('home')), files: @json(url('/api/files')), i18n: @json($i18n) };
</script>
@endpush

@section('viewer-empty'){{ __('pet.step.model') }}@endsection

@section('panel')
<form id="figure-form" data-kind="pet">
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
        <label class="lbl">{{ __('pet.size') }} <span id="figure-size-val" class="num font-normal text-muted">80 mm</span>
            <input id="figure-size" name="target_mm" type="range" min="30" max="250" step="5" value="80" class="mt-1 w-full accent-ink">
            <span class="mt-1 block text-xs font-normal text-muted">{{ __('pet.size.hint') }}</span>
        </label>

        <fieldset>
            <legend class="lbl">{{ __('pet.base') }}</legend>
            <div class="mt-2 grid grid-cols-3 gap-1.5 text-sm" role="radiogroup">
                @foreach($bases as $i => $base)
                    <label class="{{ $choice }} px-2 py-1.5 text-center"><input type="radio" name="pedestal" value="{{ $base }}" class="sr-only" @checked($i === 0)>{{ __('pet.base.'.$base) }}</label>
                @endforeach
            </div>
            <p id="pet-base-note" class="mt-2 hidden text-xs text-ink">{{ __('pet.base.miniature') }}</p>
            <p class="mt-2 text-xs text-muted">{{ __('pet.base.hint') }}</p>
            <div class="mt-3 grid gap-3">
                <label class="lbl">{{ __('pet.name') }}<input name="pedestal_name" maxlength="24" class="field" placeholder="{{ __('pet.name_ph') }}" autocomplete="off"></label>
                <label class="lbl">{{ __('pet.name_side') }}
                    <select name="name_side" class="field">
                        @foreach(\App\Domain\Generation\PedestalChanger::PET_NAME_SIDES as $side)<option value="{{ $side }}">{{ __('pet.name_side.'.$side) }}</option>@endforeach
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
                const fields = () => {
                    const miniature = picked('style') === 'miniature';
                    document.getElementById('pet-roughness').classList.toggle('hidden', !miniature);
                    document.getElementById('pet-base-note').classList.toggle('hidden', !miniature);
                    form.querySelectorAll('input[name=pedestal]').forEach((r) => { r.disabled = miniature && r.value !== 'round'; });
                    if (miniature) form.querySelector('input[name=pedestal][value=round]').checked = true;
                    document.getElementById('pet-dedication').classList.toggle('hidden', picked('pedestal') !== 'plaque');
                };
                form.querySelectorAll('input[name=style], input[name=pedestal]').forEach((r) => r.addEventListener('change', fields));
                form.addEventListener('figure:filled', fields);
                fields();
            })();
        </script>

        <label class="flex items-start gap-3 text-sm text-ink"><input id="figure-consent" type="checkbox" name="consent" value="1" class="mt-0.5 h-5 w-5 accent-ink"> <span>{{ __('pet.consent') }}</span></label>
        <p class="text-xs text-muted">{{ __('pet.privacy') }}</p>

        <button id="figure-submit" type="submit" class="btn-ink w-full gap-1.5"><x-icon name="sparkles" class="h-4 w-4" />{{ __('pet.submit') }}</button>
        <div id="figure-progress" class="hidden"><div class="h-2 w-full overflow-hidden rounded-full bg-slate-200"><div id="figure-bar" class="h-2 w-0 bg-ink transition-all"></div></div><p class="mt-1 text-xs text-muted">{{ __('pet.wait') }}</p></div>
        <p id="figure-msg" class="hint" aria-live="polite"></p>
        {{-- once a figurine exists: its thinnest place, and another base under the same figure without a new generation --}}
        <p id="figure-thin-ok" class="hidden rounded-lg bg-ok-soft px-3 py-2 text-sm text-ok" aria-live="polite"></p>
        <div id="figure-rebase-box" class="hidden">
            <button id="figure-rebase" type="button" class="btn-quiet w-full">{{ __('pet.rebase') }}</button>
            <p class="mt-1 text-xs text-muted">{{ __('pet.rebase.hint') }}</p>
        </div>
        <p class="text-xs text-muted">{{ __('figure.limits', ['n' => $guestLimit, 'm' => $userLimit]) }}</p>
    </x-tool-section>
</form>
@endsection
