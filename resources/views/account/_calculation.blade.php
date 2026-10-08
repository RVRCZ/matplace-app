{{-- One saved calculation: the model, the settings and the price (or time and material while there is no price list). --}}
<a href="{{ route('calc.share', $c) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
    <div class="min-w-0">
        <div class="truncate font-medium">{{ $c->modelFile?->original_name ?? '—' }}</div>
        <div class="text-xs text-slate-500">{{ $c->params['material'] ?? '' }} · {{ __('user.dash.pieces', ['n' => $c->params['quantity'] ?? 1]) }} · {{ $c->created_at->format('j. n. Y H:i') }}</div>
    </div>
    <div class="shrink-0 text-right text-sm font-semibold">
        @if(config('features.marketplace') || ($c->slicer && \App\Http\Controllers\Api\CalculationController::describe($c)['prices']))
            @php $tot = collect(\App\Http\Controllers\Api\CalculationController::describe($c)['prices'] ?? $c->rough['prices'] ?? [])->pluck('total'); @endphp
            @if($tot->isNotEmpty())@money($tot->min())@if($tot->count() > 1) – @money($tot->max())@endif @else —@endif
        @else
            @php $min = $c->slicer['minutes'] ?? $c->rough['minutes'] ?? null; @endphp
            @if($min){{ $min >= 60 ? intdiv($min, 60).' h '.($min % 60).' min' : $min.' min' }} · {{ round($c->slicer['grams'] ?? $c->rough['grams'] ?? 0) }} g@else —@endif
        @endif
    </div>
</a>
@if(\Illuminate\Support\Facades\Route::has('tools.cost') && ($c->slicer['grams'] ?? $c->rough['grams'] ?? null))
    {{-- session 4: the same piece on the visitor's own printer --}}
    <div class="px-4 pb-2 text-xs"><a href="{{ route('tools.cost', ['from' => $c->token]) }}" class="text-action-dark underline">{{ __('sell.cost.from_calc') }}</a></div>
@endif
