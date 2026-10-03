@extends('layouts.app', ['title' => 'Zpráva · e-maily · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<p class="mt-2 text-sm"><a href="{{ route('admin.emails.inbox') }}" class="underline">← Příchozí</a></p>

<section class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
    <p><strong>{{ $message['from_name'] ?: $message['from'] }}</strong> &lt;{{ $message['from'] }}&gt; · {{ $message['date'] }}@if($message['unread']) · <span class="text-amber-700">nepřečtená</span>@endif</p>
    <h1 class="mt-1 text-lg font-bold">{{ $message['subject'] ?: '(bez předmětu)' }}</h1>
    <pre class="mt-3 max-h-[32rem] overflow-auto whitespace-pre-wrap font-sans text-sm text-slate-800">{{ $message['text'] !== '' ? $message['text'] : '(zpráva bez textu — příloha nebo jen obrázek)' }}</pre>
</section>

@if($drafts->isNotEmpty())
    <section class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
        <h2 class="font-bold">Odpovědi</h2>
        <ul class="mt-1 space-y-1">
            @foreach($drafts as $d)
                <li><a href="{{ route('admin.emails.show', $d->id) }}" class="underline">{{ $d->subject }}</a> · {{ ['draft' => 'koncept ke schválení', 'approved' => 'schválený, neodeslaný', 'sent' => 'odesláno '.$d->sent_at?->format('j. n. Y H:i'), 'rejected' => 'zamítnutý'][$d->status] ?? $d->status }}</li>
            @endforeach
        </ul>
    </section>
@endif

<form method="post" action="{{ route('admin.emails.inbox.reply', $message['id']) }}" class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
    <h2 class="font-bold">Navrhnout odpověď</h2>
    <div class="mt-2 grid gap-3 sm:grid-cols-[1fr_10rem]">
        <label class="lbl">Co má odpověď říct <span class="font-normal text-muted">nepovinné; bez pokynu AI odpoví na to, co člověk píše, a u věcí, které neví, slíbí, že potvrdíte</span>
            <textarea name="instruction" rows="2" maxlength="2000" class="field" placeholder="Např.: zakázku jsme odeslali dnes, číslo zásilky doplním.">{{ old('instruction') }}</textarea>
        </label>
        <label class="lbl">Jazyk
            <select name="locale" class="field">@foreach(['cs' => 'česky', 'en' => 'anglicky', 'es' => 'španělsky'] as $l => $name)<option value="{{ $l }}" @selected(old('locale', 'cs') === $l)>{{ $name }}</option>@endforeach</select>
        </label>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-3">
        <button class="btn-primary text-sm">Navrhnout odpověď</button>
        <span class="text-xs text-slate-500">Vznikne koncept. Odejde až po schválení, jako odpověď ve vlákně z {{ config('services.gmail.inbox') }}.</span>
    </div>
</form>

@if($message['unread'])
    <form method="post" action="{{ route('admin.emails.inbox.read', $message['id']) }}" class="mt-3">@csrf<button class="btn-quiet min-h-0 px-3 py-1.5 text-xs">Označit jako přečtené bez odpovědi</button></form>
@endif
@endsection
