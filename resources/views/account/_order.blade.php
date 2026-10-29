{{-- One farm order in a list: what, in which state, for how much, when. --}}
@php
    $tone = in_array($o->status, ['failed', 'cancelled'], true) ? 'bg-slate-100 text-slate-600' : (in_array($o->status, ['done', 'handed_over'], true) ? 'bg-ok-soft text-ok' : 'bg-action-soft text-action-dark');
@endphp
<div class="flex items-center gap-2 px-4 py-3 hover:bg-slate-50">
    <a href="{{ route('farm.orders.show', $o) }}" class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-2">
        <span class="min-w-0">
            <span class="block truncate font-medium">{{ $o->modelFile?->original_name ?? '—' }}</span>
            <span class="block text-xs text-slate-500">{{ $o->number ? $o->number.' · ' : '' }}{{ $o->created_at->format('j. n. Y') }}@if($o->color) · {{ $o->color->displayName() }}@endif</span>
        </span>
        <span class="flex shrink-0 items-center gap-3 text-sm">
            @if($o->price_total)<span class="font-semibold">@money($o->total())</span>@endif
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $tone }}">{{ $o->statusText() }}</span>
        </span>
    </a>
    @if($o->repeatable())
        {{-- "print again": the new order at once, on its page with the colours to choose (OrderController::again) --}}
        <form method="post" action="{{ route('farm.orders.again', $o) }}" class="shrink-0" onsubmit="if (this.dataset.sent) return false; this.dataset.sent = '1';">
            @csrf
            <button class="btn-quiet text-xs" title="{{ __('farm.order.repeat_hint') }}">{{ __('farm.order.repeat_short') }}</button>
        </form>
    @endif
</div>
