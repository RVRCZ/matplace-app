{{-- The photos of a bust or a pet straight in the empty viewer (Roman, 17 Oct 2026: "user comfort"), and since 18 Oct the
     whole start there: a tap opens the same inputs as the panel (front first, then the free sides), a drop hands them
     over, the chosen ones show here as small pictures with a cross that takes them away again, and the model is made from
     here (the same form, requestSubmit) with the consent mirrored from the panel and the limit and the time beside it.
     resources/js/calc/figure.ts drives it; the logo of dots takes its place while the model is made, the model after.
     $consent, $limits (with the time a model takes), $submit: the texts of the tool (bust or pet). --}}
<div id="figure-drop" class="flex max-h-full w-full max-w-sm flex-col items-center gap-2 overflow-y-auto rounded-xl border-2 border-dashed border-slate-400 bg-card/95 px-4 py-3 text-center shadow-sm">
    <button type="button" id="figure-drop-pick" class="flex w-full flex-col items-center gap-0.5 rounded-lg px-2 py-1 transition hover:bg-slate-50">
        <span class="flex items-center gap-2"><x-icon name="camera" class="h-6 w-6 text-ink" /><span data-drop-title class="text-lg font-semibold text-ink">{{ __('photo.add') }}</span></span>
        <span data-drop-hint class="text-xs text-muted">{{ __('photo.add.hint') }}</span>
    </button>
    {{-- once the front photo is there, the other sides are offered at once, each as a place of its own (Roman, 14 Nov 2026) --}}
    <div id="figure-drop-more" class="hidden">
        <p class="text-base font-semibold text-ink">{{ __('figure.drop.more') }}</p>
        <p class="text-xs text-muted">{{ __('figure.drop.more.hint') }}</p>
    </div>
    <div id="figure-drop-thumbs" class="hidden flex-wrap justify-center gap-3 pt-1"></div>
    {{-- the consent (mirrored from the panel, figure.ts; its red frame stays until it is ticked), the limit and the button
         show up once the front photo is there: an empty dialog only asks for the photo (Roman, 18 Oct 2026) --}}
    <div id="figure-drop-go" class="hidden w-full flex-col gap-2">
        {{-- the pet's name with the photo (Roman, 14 Nov 2026): only where the tool hands a label ($name); the page's own
             script keeps it in step with the field of the panel --}}
        @isset($name)
            <label class="block text-left text-xs font-medium text-ink">{{ $name }}<input id="figure-drop-name" type="text" maxlength="24" autocomplete="off" placeholder="{{ $namePlaceholder ?? '' }}" class="field !mt-1 text-sm">
                @isset($nameHint)<span class="mt-0.5 block font-normal text-muted">{{ $nameHint }}</span>@endisset
            </label>
        @endisset
        <label class="flex items-start gap-2 text-left text-xs text-ink"><input id="figure-drop-consent" type="checkbox" required class="mt-0.5 h-5 w-5 shrink-0 accent-ink"><span>{{ $consent }}</span></label>
        <p class="text-xs text-muted">{{ $limits }}</p>
        <button type="button" id="figure-create" class="btn-primary w-full gap-1.5" disabled><x-icon name="sparkles" class="h-4 w-4" />{{ $submit }}</button>
    </div>
    <p id="figure-drop-msg" class="hidden text-xs font-medium text-danger" role="alert"></p>
</div>
