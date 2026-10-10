{{-- Papel picado, the step "Placement": the frame in the preview moves, sizes and turns the portrait; this puts it back --}}
<div data-when="treatment=portrait">
    <button type="button" id="papel-place-reset" class="chip inline-flex items-center gap-1.5 !py-1 text-sm"><x-icon name="rotate-ccw" class="h-3.5 w-3.5" />{{ __('param.papel.place.reset') }}</button>
    <p class="hint mt-2 !text-xs">{{ __('param.papel.place.hint') }}</p>
</div>
<p class="hint !text-xs" data-when="treatment=cutout">{{ __('param.papel.place.cutout') }}</p>
