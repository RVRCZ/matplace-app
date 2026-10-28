{{-- Calculator: third way to get the part — our own print farm. Logic in resources/js/calc/farm.ts (reads the /c/{token} address). --}}
{{-- until the farm is public only admins see the button (they try the farm out on the live site) --}}
@if(\App\Domain\Farm\FarmGate::offered(auth()->user()))
<div class="rounded-2xl border border-line bg-action-soft p-4">
    {{-- the order is made right away (resources/js/calc/farm.ts posts the calculation) and the customer lands on its page
         to pick material, settings and colours; a guest too, without an account (the card payment makes one, docs/AO.md) --}}
    {{-- the start of an order, not a payment (audit 10/2026, bod 5): the order is made unpaid, the price and the payment come at its end --}}
    <button id="cta-farm" type="button" data-url="{{ route('farm.start') }}" data-store="{{ route('farm.orders.store') }}" data-busy="{{ __('farm.cta_busy') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-action px-4 py-4 text-lg font-bold text-white shadow-sm hover:bg-action-dark"><x-icon name="printer" class="h-5 w-5" />{{ __('farm.cta') }}</button>
    <p class="mt-2 text-xs text-slate-600">{{ __(auth()->check() ? 'farm.cta_hint' : 'farm.cta_hint_guest') }}</p>
    {{-- a design drawn in free colours: the spools of ours nearest to them (filled by calculator.ts from file.nearest) --}}
    <p id="cta-farm-nearest" class="mt-2 hidden flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink" aria-live="polite"></p>
    <p id="cta-farm-note" class="mt-1 hidden text-xs text-amber-800">{{ __('farm.cta_wait') }}</p>
</div>
@endif
