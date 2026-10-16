{{-- Calculator: third way to get the part — our own print farm. Logic in resources/js/calc/farm.ts (reads the /c/{token} address). --}}
{{-- until the farm is public only admins see the button (they try the farm out on the live site) --}}
@if(\App\Domain\Farm\FarmGate::offered(auth()->user()))
<div class="rounded-2xl border border-line bg-action-soft p-4">
    {{-- signed in: the order is made right away (resources/js/calc/farm.ts posts the calculation) and the customer lands
         on its page to pick material, settings and colours; a guest signs in first and continues from /farm --}}
    <button id="cta-farm" type="button" data-url="{{ route('farm.start') }}" data-store="{{ auth()->check() ? route('farm.orders.store') : '' }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-action px-4 py-4 text-lg font-bold text-white shadow-sm hover:bg-action-dark"><x-icon name="printer" class="h-5 w-5" />{{ __('farm.cta') }}</button>
    <p class="mt-2 text-xs text-slate-600">{{ __('farm.cta_hint') }}</p>
    {{-- a design drawn in free colours: the spools of ours nearest to them (filled by calculator.ts from file.nearest) --}}
    <p id="cta-farm-nearest" class="mt-2 hidden flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink" aria-live="polite"></p>
    <p id="cta-farm-note" class="mt-1 hidden text-xs text-amber-800">{{ __('farm.cta_wait') }}</p>
</div>
@endif
