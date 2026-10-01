{{--
    "Download for my printer": one dialog for the whole page. Any button with data-pick-printer="<uuid>" opens it
    (resources/js/site/printer_pick.ts); the choice is remembered in the browser, the same as in the calculator.
--}}
<dialog id="mp-pick" class="m-auto w-[min(92vw,28rem)] rounded-2xl bg-white p-5 shadow-xl backdrop:bg-black/60" aria-labelledby="mp-pick-title"
        data-printers="{{ route('api.printers') }}" data-files="{{ url('/api/files') }}">
    <h2 id="mp-pick-title" class="text-lg font-bold">{{ __('user.pick.title') }}</h2>
    <p id="mp-pick-name" class="truncate text-sm font-semibold text-muted"></p>
    <p class="mt-2 text-sm text-slate-600">{{ __('user.pick.lead') }}</p>
    <div id="mp-pick-form" class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="lbl">{{ __('user.pick.vendor') }}<select id="mp-pick-vendor" class="field"></select></label>
        <label class="lbl">{{ __('user.pick.model') }}<select id="mp-pick-model" class="field"></select></label>
    </div>
    <p id="mp-pick-none" class="note-warn mt-3 hidden text-sm">{{ __('user.pick.none') }}</p>
    <a id="mp-pick-go" aria-disabled="true" class="btn-primary mt-4 w-full aria-disabled:pointer-events-none aria-disabled:opacity-50">{{ __('user.pick.download') }}</a>
    <p id="mp-pick-how" class="mt-2 text-xs text-slate-500" data-orca="{{ __('user.pick.how') }}" data-prusa="{{ __('user.pick.how_prusa') }}">{{ __('user.pick.how') }}</p>
    <div class="mt-4 flex items-center justify-between text-sm">
        <a id="mp-pick-stl" href="#" class="text-action-dark underline">{{ __('user.pick.stl') }}</a>
        <button type="button" id="mp-pick-close" class="text-slate-600 hover:text-ink">{{ __('user.pick.close') }}</button>
    </div>
</dialog>
