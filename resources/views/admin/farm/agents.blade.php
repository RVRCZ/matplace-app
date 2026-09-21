@extends('layouts.app', ['title' => __('farm.admin.nav.agents').' · admin', 'noindex' => true])

@section('content')
@include('admin.farm.nav')

@if(session('agent_token'))
    <div class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-4">
        <p class="text-sm font-semibold text-amber-900">{{ __('farm.admin.token_once', ['name' => session('agent_token')['name']]) }}</p>
        <code class="mt-2 block select-all break-all rounded-lg bg-white px-3 py-2 text-sm">{{ session('agent_token')['token'] }}</code>
    </div>
@endif

<div class="mt-4 space-y-3">
    @foreach($agents as $a)
        <section class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-white p-4">
            <div>
                <h2 class="font-bold">{{ $a->name }} @if($a->revoked_at)<span class="text-xs font-normal text-red-700">zrušen</span>@endif</h2>
                <p class="text-xs text-slate-500">verze {{ $a->version ?? '—' }} · poslední heartbeat {{ $a->last_heartbeat_at?->diffForHumans() ?? 'nikdy' }} · IP {{ $a->last_ip ?? '—' }}</p>
                <p class="text-xs text-slate-500">Tiskárny: {{ $a->printers->pluck('name')->implode(', ') ?: '—' }}</p>
            </div>
            <div class="flex gap-2">
                <form method="post" action="{{ route('admin.farm.agents.rotate', $a) }}" onsubmit="return confirm('Vydat nový token? Starý přestane platit.')">@csrf<button class="btn-quiet text-sm">Nový token</button></form>
                @if(! $a->revoked_at)<form method="post" action="{{ route('admin.farm.agents.revoke', $a) }}" onsubmit="return confirm('Opravdu zrušit přístup agenta?')">@csrf<button class="btn-quiet text-sm text-red-700">Zrušit přístup</button></form>@endif
            </div>
        </section>
    @endforeach
</div>

<form method="post" action="{{ route('admin.farm.agents.create') }}" class="mt-4 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-4">
    @csrf
    <label class="block flex-1 text-xs font-semibold text-slate-600">Název nového agenta (např. „Dílna – mini PC“)<input name="name" required maxlength="80" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
    <button class="btn-primary text-sm">Vytvořit a zobrazit token</button>
</form>
@endsection
