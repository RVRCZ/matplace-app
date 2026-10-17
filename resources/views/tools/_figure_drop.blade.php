{{-- The photos of a bust or a pet straight in the empty viewer (Roman, 17 Oct 2026: "user comfort"): a tap opens the
     same inputs as the panel (front first, then the free sides), a drop hands them over, the chosen ones show here as
     small pictures. resources/js/calc/figure.ts drives it; the first model hides it with the sample behind it. --}}
<button type="button" id="figure-drop" class="flex w-full max-w-sm flex-col items-center gap-1 rounded-xl border-2 border-dashed border-slate-400 bg-card/95 px-4 py-5 text-center shadow-sm transition hover:border-ink">
    <x-icon name="camera" class="h-8 w-8 text-ink" />
    <span data-drop-title class="text-lg font-semibold text-ink">{{ __('photo.add') }}</span>
    <span class="text-sm text-muted">{{ __('photo.add.hint') }}</span>
    <span id="figure-drop-thumbs" class="mt-2 hidden flex-wrap justify-center gap-2"></span>
    <span class="mt-1 text-xs text-muted">{{ $sample }}</span>
</button>
