@extends('layouts.app', ['title' => 'Hlídač · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('admin.farm.nav')
@include('partials.flash')

<div class="mt-3 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold">Hlídač serveru a schránky</h1>
    <form method="post" action="{{ route('admin.health.run') }}">@csrf<button class="btn-quiet">Zkontrolovat teď</button></form>
</div>
<p class="mt-1 text-sm text-slate-600">
    Každých 10 minut se server zeptá sám sebe na disk, spadlé úlohy, frontu, chyby a zálohu; každých 5 minut projde schránku info@.
    Upozornění chodí na
    @if($to !== '')<strong>{{ $to }}</strong>@else<strong class="text-red-700">nikam</strong>@endif
    (<a href="{{ route('admin.farm.settings') }}#farm-notify" class="underline">nastavení „Můj e-mail pro hlídače“</a>).
</p>

@if(session('health_output'))
    <pre class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs">{{ session('health_output') }}</pre>
@endif

<div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Kontrola</th><th class="px-3 py-2">Stav</th><th class="px-3 py-2">Co říká</th><th class="px-3 py-2">Změna</th><th class="px-3 py-2">Naposledy</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($checks as $c)
                <tr>
                    <td class="px-3 py-2 font-medium">{{ $labels[$c->key] ?? $c->key }}</td>
                    <td class="px-3 py-2">@if($c->ok)<span class="text-ok">OK</span>@else<span class="font-semibold text-red-700">Problém</span>@endif</td>
                    <td class="px-3 py-2 text-slate-700">{{ $c->message }}</td>
                    <td class="px-3 py-2 text-xs text-slate-500">{{ $c->changed_at?->timezone(config('app.timezone'))->format('j. n. H:i') }}</td>
                    <td class="px-3 py-2 text-xs text-slate-500">{{ $c->checked_at?->timezone(config('app.timezone'))->format('j. n. H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">Ještě neproběhla žádná kontrola.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<h2 class="mt-6 text-lg font-semibold">Schránka info@</h2>
<p class="mt-1 text-sm text-slate-600">
    @if(is_array($mailbox) && ! empty($mailbox['checked_at']))
        Naposledy projitá {{ \Illuminate\Support\Carbon::parse($mailbox['checked_at'])->timezone(config('app.timezone'))->format('j. n. H:i') }},
        nepřečtených od lidí: <strong>{{ (int) ($mailbox['unread_human'] ?? 0) }}</strong>.
    @else
        Hlídač schránky zatím neběžel.
    @endif
    <a href="{{ route('admin.emails.inbox') }}" class="underline">Otevřít schránku</a>
</p>
<div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Přišlo</th><th class="px-3 py-2">Od</th><th class="px-3 py-2">Předmět</th><th class="px-3 py-2">Upozornění</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($alerts as $a)
                <tr>
                    <td class="px-3 py-2 text-xs text-slate-500">{{ $a->received_at?->timezone(config('app.timezone'))->format('j. n. Y H:i') }}</td>
                    <td class="px-3 py-2">{{ $a->from_name ?: $a->from }}<span class="block text-xs text-slate-500">{{ $a->from }}</span></td>
                    <td class="px-3 py-2"><a href="{{ route('admin.emails.inbox.show', $a->message_id) }}" class="underline">{{ $a->subject ?: '(bez předmětu)' }}</a></td>
                    <td class="px-3 py-2 text-xs text-slate-500">{{ $a->alerted_at ? 'posláno '.$a->alerted_at->timezone(config('app.timezone'))->format('j. n. H:i') : 'nikomu (bez adresy)' }}{{ $a->reminded_at ? ', připomenuto '.$a->reminded_at->timezone(config('app.timezone'))->format('j. n.') : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">Zatím žádný e-mail od člověka.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
