@extends('tools.page', ['tool' => 'relief', 'module' => 'relief', 'lead' => __('relief.lead'), 'available' => $available,
    'sections' => ['photo' => __('toolpage.section.photo'), 'settings' => __('toolpage.section.settings'), 'print' => \App\Support\NextStep::text('param.step.inquiry')]])

@push('head')
<script>
    window.MP_RELIEF = { url: @json(route('api.tools.relief')), home: @json(route('home')), files: @json(url('/api/files')),
        i18n: { working: @json(__('relief.working')), failed: @json(__('relief.failed')), not_image: @json(__('search.not_image')), photo_again: @json(__('relief.photo_again')),
            shades: @json(__('relief.shades')), fact: @json(__('relief.fact')), fact_lamp: @json(__('relief.fact.lamp')), backlit_lamp: @json(__('relief.backlit.lamp')), silhouette: @json(__('relief.warn.silhouette')),
            frame_too_wide: @json(__('relief.warn.frame_too_wide')), socket_too_big: @json(__('relief.warn.socket_too_big')), height_auto: @json(__('relief.height.auto')) } };
</script>
@endpush

@section('viewer-empty'){{ __('relief.step.model') }}@endsection

@section('panel')
<form id="relief-form">
    <x-tool-section id="photo" :title="__('toolpage.section.photo')">
        <fieldset>
            <legend class="lbl">{{ __('relief.make') }}</legend>
            <div class="mt-2 grid grid-cols-3 gap-2 text-sm" role="radiogroup">
                @foreach (['panel' => 'image', 'lamp' => 'lightbulb', 'wall' => 'frame'] as $k => $ico)
                    <label class="cursor-pointer rounded-lg border border-slate-300 bg-white p-3 text-center has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action">
                        <input type="radio" name="make" value="{{ $k }}" @checked($k === 'panel') class="sr-only">
                        <x-icon :name="$ico" class="mx-auto h-6 w-6 text-ink" />
                        <div class="mt-1 font-medium text-ink">{{ __('relief.make.'.$k) }}</div>
                        <div class="text-xs text-muted">{{ __('relief.make.'.$k.'.hint') }}</div>
                    </label>
                @endforeach
            </div>
            <input type="hidden" name="mode" value="lithophane">
            <input type="hidden" name="shape" value="rect">
        </fieldset>

        <label id="relief-drop" class="block cursor-pointer rounded-xl border-2 border-dashed border-line p-5 text-center hover:border-ink">
            <input id="relief-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
            <img id="relief-preview" alt="" class="mx-auto hidden max-h-48 rounded-lg">
            <div id="relief-pick" class="text-ink"><x-icon name="camera" class="mx-auto h-7 w-7 text-muted" /><span class="mt-1 block font-medium">{{ __('relief.pick') }}</span></div>
        </label>
        <p class="text-xs text-muted">{{ __('relief.privacy') }}</p>

        {{-- light and detail: the photo's own sliders change the small picture at once, the model follows on the next build --}}
        <details class="text-sm" open>
            <summary class="cursor-pointer font-medium text-ink underline decoration-line underline-offset-4">{{ __('relief.light') }}</summary>
            <div class="mt-3 grid gap-3">
                @foreach (['brightness' => [-50, 50, 0, 1, '%'], 'contrast' => [-50, 50, 0, 1, '%'], 'gamma' => [0.5, 2, 1, 0.05, '']] as $key => $f)
                    <label class="text-sm font-medium text-ink">{{ __('relief.'.$key) }} <span class="num font-normal text-muted" data-val="{{ $key }}">{{ $f[2] }}{{ $f[4] }}</span>
                        <input name="{{ $key }}" type="range" min="{{ $f[0] }}" max="{{ $f[1] }}" step="{{ $f[3] }}" value="{{ $f[2] }}" data-unit="{{ $f[4] }}" class="mt-1 w-full accent-ink">
                    </label>
                @endforeach
                <label class="flex items-center gap-2 text-sm text-ink"><input type="checkbox" name="invert" value="1" class="h-5 w-5 accent-ink"> {{ __('relief.invert') }}</label>
            </div>
        </details>
    </x-tool-section>

    <x-tool-section id="settings" :title="__('toolpage.section.settings')">
        <fieldset data-relief="panel wall">
            <legend class="lbl">{{ __('relief.shape') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach (['rect', 'circle', 'oval', 'heart', 'arch', 'tree', 'custom'] as $i => $s)
                    <label class="tool-choice"><input type="radio" name="shape-pick" value="{{ $s }}" class="sr-only" @checked($i === 0)>{{ __('relief.shape.'.$s) }}</label>
                @endforeach
            </div>
            <div id="relief-silhouette" class="mt-2 hidden">
                <div class="flex items-center gap-3">
                    <button type="button" id="relief-artwork-open" class="btn-quiet !min-h-10 gap-1.5 !px-3 !py-2 text-sm"><x-icon name="image" class="h-4 w-4" />{{ __('toolpage.artwork.choose') }}</button>
                    <span id="relief-artwork-thumb" class="hidden h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-line bg-white p-1"></span>
                    <span id="relief-artwork-state" class="text-sm text-muted"></span>
                </div>
                <input type="hidden" name="silhouette" value="">
                <p class="hint mt-1 !text-xs">{{ __('relief.shape.custom.hint') }}</p>
            </div>
        </fieldset>

        <div class="grid gap-3">
            <label class="lbl"><span data-relief="panel wall">{{ __('relief.plate.width') }}</span><span data-relief="lamp">{{ __('relief.circumference') }}</span> <span id="relief-w-val" class="num font-normal text-muted">100 mm</span>
                <input id="relief-w" name="width" type="range" min="40" max="250" step="5" value="100" class="mt-1 w-full accent-ink">
            </label>
            <label class="lbl"><span data-relief="panel wall">{{ __('relief.plate.height') }}</span><span data-relief="lamp">{{ __('relief.lamp.height') }}</span> <span id="relief-h-val" class="num font-normal text-muted">{{ __('relief.height.auto') }}</span>
                <input id="relief-h" name="height" type="range" min="0" max="250" step="5" value="0" class="mt-1 w-full accent-ink">
            </label>
            <p class="hint !text-xs"><button type="button" id="relief-ratio" class="underline">{{ __('relief.ratio') }}</button></p>
            <label class="lbl" data-relief="panel wall">{{ __('relief.frame') }} <span id="relief-f-val" class="num font-normal text-muted">2 mm</span>
                <input id="relief-f" name="frame" type="range" min="0" max="6" step="0.5" value="2" class="mt-1 w-full accent-ink">
                <span class="block text-xs font-normal text-muted">{{ __('relief.frame.hint') }}</span>
            </label>
        </div>

        <fieldset data-relief="panel wall">
            <legend class="lbl">{{ __('relief.hang') }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach (['none', 'hole', 'eyelet'] as $i => $h)
                    <label class="tool-choice"><input type="radio" name="hang" value="{{ $h }}" class="sr-only" @checked($i === 0)>{{ __('relief.hang.'.$h) }}</label>
                @endforeach
            </div>
            <label class="mt-3 flex items-center gap-2 text-sm text-ink"><input type="checkbox" name="stand" value="1" class="h-5 w-5 accent-ink"> {{ __('relief.stand') }}</label>
        </fieldset>

        <fieldset data-relief="lamp">
            <legend class="lbl">{{ __('relief.socket') }}</legend>
            <div class="mt-2 grid gap-2" role="radiogroup">
                @foreach (['e27', 'e14', 'led', 'none'] as $i => $s)
                    <label class="tool-choice !justify-start"><input type="radio" name="socket" value="{{ $s }}" class="sr-only" @checked($i === 0)>{{ __('relief.socket.'.$s) }}</label>
                @endforeach
            </div>
        </fieldset>

        <div class="grid grid-cols-2 gap-3">
            <label class="lbl">{{ __('relief.min') }} <span class="num font-normal text-muted" data-val="min_thickness">0.8 mm</span>
                <input name="min_thickness" type="range" min="0.4" max="1.2" step="0.2" value="0.8" data-unit=" mm" class="mt-1 w-full accent-ink">
            </label>
            <label class="lbl">{{ __('relief.max') }} <span class="num font-normal text-muted" data-val="max_thickness">3 mm</span>
                <input name="max_thickness" type="range" min="2" max="5" step="0.2" value="3" data-unit=" mm" class="mt-1 w-full accent-ink">
            </label>
        </div>
        <p id="relief-shades" class="hint !text-xs"></p>
    </x-tool-section>

    <x-tool-section id="print" :title="\App\Support\NextStep::text('param.step.inquiry')">
        <button class="btn-ink w-full gap-1.5"><x-icon name="sparkles" class="h-4 w-4" />{{ __('relief.submit') }}</button>
        <p id="relief-msg" class="hint" aria-live="polite"></p>
        <div id="relief-backlit" class="hidden">
            <div class="mt-2 flex flex-wrap gap-1.5" role="group">
                <button type="button" class="chip chip-on !py-1 text-sm" data-lit="1" aria-pressed="true">{{ __('relief.backlit') }}</button>
                <button type="button" class="chip !py-1 text-sm" data-lit="0" aria-pressed="false">{{ __('relief.surface') }}</button>
            </div>
            <p class="hint mt-1 !text-xs">{{ __('relief.backlit.hint') }}</p>
        </div>
    </x-tool-section>
</form>
@endsection
