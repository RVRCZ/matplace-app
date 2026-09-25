{{-- Calculator: third way to get the part — our own print farm. Logic in resources/js/calc/farm.ts (reads the /c/{token} address). --}}
{{-- until the farm is public only admins see the button (they try the farm out on the live site) --}}
@if(config('farm.enabled') && config('farm.open', true) && (config('farm.public') || auth()->user()?->isAdmin()))
<div class="rounded-2xl border border-line bg-action-soft p-4">
    <button id="cta-farm" type="button" data-url="{{ route('farm.start') }}" class="w-full rounded-xl bg-ink px-4 py-3 font-semibold text-white">🖨️ {{ __('farm.cta') }}</button>
    <p class="mt-2 text-xs text-slate-600">{{ __('farm.cta_hint') }}</p>
    <p id="cta-farm-note" class="mt-1 hidden text-xs text-amber-800">{{ __('farm.cta_wait') }}</p>
</div>
@endif
