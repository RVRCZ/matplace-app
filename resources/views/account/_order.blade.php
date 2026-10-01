{{-- One farm order in a list: what, in which state, for how much, when. --}}
@php
    $tone = in_array($o->status, ['failed', 'cancelled'], true) ? 'bg-slate-100 text-slate-600' : (in_array($o->status, ['done', 'handed_over'], true) ? 'bg-ok-soft text-ok' : 'bg-action-soft text-action-dark');
@endphp
<a href="{{ route('farm.orders.show', $o) }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-slate-50">
    <span class="min-w-0">
        <span class="block truncate font-medium">{{ $o->modelFile?->original_name ?? '—' }}</span>
        <span class="block text-xs text-slate-500">{{ $o->number ? $o->number.' · ' : '' }}{{ $o->created_at->format('j. n. Y') }}@if($o->color) · {{ $o->color->displayName() }}@endif</span>
    </span>
    <span class="flex shrink-0 items-center gap-3 text-sm">
        @if($o->price_total)<span class="font-semibold">@money($o->total())</span>@endif
        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $tone }}">{{ __('farm.status.'.$o->status) }}</span>
    </span>
</a>
