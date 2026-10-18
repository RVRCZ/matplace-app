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
    <div id="figure-drop-thumbs" class="hidden flex-wrap justify-center gap-2"></div>
    {{-- the same consent as in the panel (mirrored both ways, figure.ts); its red frame stays until it is ticked --}}
    <label class="flex items-start gap-2 text-left text-xs text-ink"><input id="figure-drop-consent" type="checkbox" required class="mt-0.5 h-5 w-5 shrink-0 accent-ink"><span>{{ $consent }}</span></label>
    <p class="text-xs text-muted">{{ $limits }}</p>
    <button type="button" id="figure-create" class="btn-primary w-full gap-1.5" disabled><x-icon name="sparkles" class="h-4 w-4" />{{ $submit }}</button>
    <p id="figure-drop-msg" class="hidden text-xs font-medium text-danger" role="alert"></p>
</div>
