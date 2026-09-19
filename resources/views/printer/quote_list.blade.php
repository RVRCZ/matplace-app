@if($quotes->isEmpty())
    <p class="mt-2 text-sm text-slate-500">{{ __('printer.quotes.none') }}</p>
@else
    <div class="mt-2 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white">
        @foreach($quotes as $q)
            <a href="{{ route('printer.quotes.edit', $q) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
                <div class="min-w-0">
                    <div class="truncate font-medium">{{ $q->number }} · {{ $q->client_name ?: $q->client_email ?: __('quote.no_client') }}</div>
                    <div class="truncate text-xs text-slate-500">{{ $q->title ?: '—' }} · {{ $q->created_at->format('j. n. Y') }}</div>
                </div>
                <div class="text-right">
                    <div class="font-semibold">{{ number_format($q->total, 0, ',', ' ') }} Kč</div>
                    <span class="rounded-full px-2 py-0.5 text-xs {{ ['draft' => 'bg-slate-100 text-slate-600', 'sent' => 'bg-blue-50 text-blue-700', 'viewed' => 'bg-blue-50 text-blue-700', 'accepted' => 'bg-teal-50 text-teal-800', 'declined' => 'bg-red-50 text-red-700', 'expired' => 'bg-slate-100 text-slate-500'][$q->status] ?? '' }}">{{ __('quote.status.'.$q->status) }}</span>
                </div>
            </a>
        @endforeach
    </div>
@endif
