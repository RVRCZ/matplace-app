@extends('layouts.app', ['title' => __('farm.admin.nav.orders').' · admin', 'noindex' => true])

@section('content')
@include('admin.farm.nav')

<form method="get" class="mt-4 flex flex-wrap gap-2 text-sm">
    <a href="{{ route('admin.farm.orders') }}" class="chip {{ $status === '' ? 'chip-on' : '' }}">✓ {{ __('farm.status.paid') }}+</a>
    @foreach($statuses as $s)
        <a href="{{ route('admin.farm.orders', ['status' => $s]) }}" class="chip {{ $status === $s ? 'chip-on' : '' }}">{{ __('farm.status.'.$s) }}</a>
    @endforeach
</form>

<div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr><th class="px-3 py-2">#</th><th class="px-3 py-2">{{ __('farm.start.model') }}</th><th class="px-3 py-2">E-mail</th><th class="px-3 py-2">{{ __('farm.order.color') }}</th><th class="px-3 py-2">{{ __('farm.order.time') }}</th><th class="px-3 py-2">g</th><th class="px-3 py-2">Kč</th><th class="px-3 py-2">Status</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($orders as $o)
                <tr>
                    <td class="px-3 py-2"><a class="font-semibold underline" href="{{ route('admin.farm.orders.show', $o) }}">{{ $o->number ?? '#'.$o->id }}</a><span class="block text-xs text-slate-500">{{ $o->created_at->format('j. n. H:i') }}</span></td>
                    <td class="px-3 py-2">{{ $o->modelFile?->original_name }}<span class="block text-xs text-slate-500">{{ $o->quality }} / {{ $o->strength }} · {{ $o->printer?->name }}</span></td>
                    <td class="px-3 py-2">{{ $o->user?->email }}</td>
                    <td class="px-3 py-2">{{ $o->color?->name ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $o->est_minutes }}@if($o->actual_minutes) <span class="text-xs text-slate-500">→ {{ $o->actual_minutes }}</span>@endif</td>
                    <td class="px-3 py-2">{{ $o->est_grams }}@if($o->actual_grams) <span class="text-xs text-slate-500">→ {{ $o->actual_grams }}</span>@endif</td>
                    <td class="px-3 py-2 font-semibold">{{ $o->price_total ? number_format($o->price_total, 0, ',', ' ') : '—' }}</td>
                    <td class="px-3 py-2">{{ __('farm.status.'.$o->status) }}@if($o->error)<span class="block text-xs text-red-700">{{ $o->error }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-3 py-6 text-center text-slate-500">{{ __('farm.no_orders') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
