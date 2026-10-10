{{-- Papel picado: the photo goes in right here, one click or a drop anywhere on the page; the library and "my pictures" are the button under it --}}
<label id="param-artwork-drop" class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed border-slate-300 bg-white p-3 transition hover:border-ink">
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-page text-ink"><x-icon name="upload" class="h-5 w-5" /></span>
    <span class="min-w-0">
        <span class="block text-sm font-semibold text-ink">{{ __('param.papel.upload') }}</span>
        <span class="block text-xs text-muted">{{ __('param.papel.upload.hint') }}</span>
    </span>
    <input id="param-artwork-file" type="file" accept="image/png,image/jpeg,image/webp,.svg,image/svg+xml" class="sr-only">
</label>
<p id="param-artwork-msg" class="hidden text-sm" role="status" aria-live="polite"></p>
