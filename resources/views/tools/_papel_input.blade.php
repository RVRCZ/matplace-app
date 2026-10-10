{{-- Papel picado, the step "Photo": the picture as it came and the portrait made of it (the server's own, the one that is printed) --}}
<div id="papel-thumbs" class="hidden grid-cols-2 gap-2" data-when="treatment=portrait">
    <figure class="min-w-0">
        <span id="papel-original" class="flex h-28 items-center justify-center overflow-hidden rounded-xl border border-line bg-white p-1"></span>
        <figcaption class="mt-1 text-center text-xs text-muted">{{ __('param.papel.thumb.original') }}</figcaption>
    </figure>
    <figure class="min-w-0">
        <span id="papel-portrait" class="flex h-28 items-center justify-center overflow-hidden rounded-xl border border-line bg-white p-1"></span>
        <figcaption class="mt-1 text-center text-xs text-muted">{{ __('param.papel.thumb.portrait') }}</figcaption>
    </figure>
</div>
<p class="hint !text-xs" data-when="treatment=portrait">{{ __('param.papel.photo.hint') }}</p>
{{-- shown by the script when the server cannot cut a person out of a photo (rembg is not installed): the tick is then off --}}
<p id="papel-isolate-off" class="note-warn hidden text-xs">{{ __('param.papel.isolate.off') }}</p>
