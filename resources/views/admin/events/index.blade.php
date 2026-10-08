@extends('layouts.app', ['title' => 'Akce · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
    @foreach(['' => 'vše', 'suggested' => 'navržené', 'verify' => 'k ověření', 'verified' => 'ověřené'] as $key => $label)
        <a href="{{ route('admin.events.index', $key ? ['status' => $key] : []) }}" class="chip {{ $status === $key ? 'chip-on' : '' }}">{{ $label }}@if($key !== '') <span class="num">{{ $counts[$key] ?? 0 }}</span>@endif</a>
    @endforeach
    <a href="{{ route('admin.events.create') }}" class="btn-primary ml-auto text-sm">Nová akce</a>
</div>
<p class="mt-2 text-xs text-slate-500">Trhy a akce pro /tools/vendors. „K ověření“ jsou z první dávky (termíny a poplatky podle paměti, ne z živého webu); „navržené“ poslali návštěvníci. Ověřit = zkontrolovat datum, místo a poplatek, případně doplnit, a potvrdit.</p>

<div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Akce</th><th class="px-3 py-2">Typ</th><th class="px-3 py-2">Místo</th><th class="px-3 py-2">Termín</th><th class="px-3 py-2">Stánek</th><th class="px-3 py-2">Stav</th><th class="px-3 py-2"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($events as $e)
                <tr>
                    <td class="px-3 py-2"><a href="{{ route('admin.events.edit', $e->id) }}" class="font-semibold underline">{{ $e->name }}</a>@if($e->url) <a href="{{ $e->url }}" target="_blank" rel="noopener" class="text-xs text-slate-500 underline">web</a>@endif @if($e->suggested_by)<span class="block text-xs text-slate-500">navrhl: {{ $e->suggested_by }}</span>@endif</td>
                    <td class="px-3 py-2">{{ __('sell.vendors.type.'.$e->type) }}</td>
                    <td class="px-3 py-2">{{ $e->city }} ({{ $e->country }})@if($e->lat === null) <span class="text-xs text-red-700">bez souřadnic</span>@endif</td>
                    <td class="num px-3 py-2">{{ $e->starts_on?->isoFormat('L') }}@if($e->ends_on && ! $e->ends_on->eq($e->starts_on)) – {{ $e->ends_on->isoFormat('L') }}@endif</td>
                    <td class="px-3 py-2">{{ $e->stall_fee }}</td>
                    <td class="px-3 py-2">{{ ['suggested' => 'navržená', 'verify' => 'k ověření', 'verified' => 'ověřená'][$e->status] ?? $e->status }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">
                        @if($e->status !== 'verified')<form method="post" action="{{ route('admin.events.verify', $e->id) }}" class="inline">@csrf<button class="text-ok underline">ověřit</button></form> · @endif
                        <form method="post" action="{{ route('admin.events.destroy', $e->id) }}" class="inline" onsubmit="return confirm('Smazat akci?')">@csrf @method('delete')<button class="text-red-700 underline">smazat</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Žádná akce. Naplňte první dávku: <code>php artisan db:seed --class=MarketEventsSeeder</code>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
