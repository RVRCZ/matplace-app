@extends('layouts.app', ['title' => 'E-maily · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<nav class="mt-2 flex flex-wrap gap-2 text-sm" aria-label="Stav">
    <a href="{{ route('admin.emails.inbox') }}" class="chip">Příchozí</a>
    @foreach(['draft' => 'Ke schválení', 'approved' => 'Schválené, neodeslané', 'sent' => 'Odeslané', 'rejected' => 'Zamítnuté'] as $key => $label)
        <a href="{{ route('admin.emails.index', ['status' => $key]) }}" class="chip {{ $status === $key ? 'chip-on' : '' }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
    @endforeach
</nav>
<p class="mt-1 text-xs text-slate-500">E-mail, který napsala AI, odejde až po schválení. Systémová oznámení (ověření e-mailu, stav zakázky, sledování zásilky) odcházejí hned a tady jsou jen v přehledu odeslaných.</p>

<div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Kdy</th><th class="px-3 py-2">Komu</th><th class="px-3 py-2">Předmět</th><th class="px-3 py-2">Původ</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($emails as $e)
                <tr><td class="px-3 py-2 text-xs text-slate-500">{{ ($e->sent_at ?? $e->created_at)->format('j. n. Y H:i') }}</td><td class="px-3 py-2">{{ $e->to }}</td>
                    <td class="px-3 py-2"><a href="{{ route('admin.emails.show', $e->id) }}" class="font-semibold underline">{{ $e->subject ?: '(bez předmětu)' }}</a>@if($e->error)<span class="block text-xs text-red-700">{{ $e->error }}</span>@endif</td>
                    <td class="px-3 py-2 text-xs">{{ $e->generated_by_ai ? 'AI' : 'systém' }} · {{ $e->locale }}</td></tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">Nic.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $emails->links() }}</div>

<h2 class="mt-6 font-bold">Napsat e-mail s AI</h2>
<form method="post" action="{{ route('admin.emails.write') }}" class="mt-2 rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
    <div class="grid gap-3 sm:grid-cols-[1fr_10rem]">
        <label class="lbl">Komu<input name="to" type="email" required maxlength="190" value="{{ old('to') }}" class="field"></label>
        <label class="lbl">Jazyk
            <select name="locale" class="field">@foreach(['cs' => 'česky', 'en' => 'anglicky', 'es' => 'španělsky'] as $l => $name)<option value="{{ $l }}" @selected(old('locale', 'cs') === $l)>{{ $name }}</option>@endforeach</select>
        </label>
    </div>
    <label class="lbl mt-3">Co má e-mail říct <span class="font-normal text-muted">vašimi slovy; AI nic dalšího neslíbí</span>
        <textarea name="instruction" rows="3" required minlength="10" maxlength="2000" class="field" placeholder="Poděkuj za nahrání modelů a vysvětli, že soubor váza.stl neprošel kontrolou, protože není uzavřený. Poraď nástroj Oprava modelu.">{{ old('instruction') }}</textarea>
    </label>
    <label class="lbl mt-3">Podklady <span class="font-normal text-muted">nepovinné: co nám člověk napsal, údaje o zakázce</span>
        <textarea name="context" rows="3" maxlength="8000" class="field">{{ old('context') }}</textarea>
    </label>
    <button class="btn-primary mt-3 text-sm">Napsat návrh</button>
    <span class="ml-2 text-xs text-slate-500">Vznikne koncept. Nic se neodešle, dokud ho neschválíte.</span>
</form>
@endsection
