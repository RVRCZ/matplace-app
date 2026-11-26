@extends('layouts.app', ['title' => 'Zpráva · e-maily · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

@php
    $fresh = trim((string) ($message['fresh'] ?? '')) !== '' ? $message['fresh'] : $message['text'];
    $quoted = trim((string) ($message['quoted'] ?? ''));
    $files = $message['attachments'] ?? [];
    $pictures = array_values(array_filter($files, fn ($f) => str_starts_with($f['mime'], 'image/')));
    $others = array_values(array_filter($files, fn ($f) => ! str_starts_with($f['mime'], 'image/')));
    $open = $drafts->firstWhere('status', 'draft');
@endphp

<p class="mt-2 text-sm"><a href="{{ route('admin.emails.inbox') }}" class="underline">← Příchozí</a></p>

{{-- the draft the watch already wrote: one click to read, change and send it (docs/AU.md §7) --}}
@if($open)
    <a href="{{ route('admin.emails.show', $open->id) }}" class="mt-3 flex items-center justify-between gap-3 rounded-2xl border border-action bg-action/5 p-4 text-sm">
        <span><strong>Koncept odpovědi je připravený</strong> · {{ $open->subject }}<span class="block text-xs text-slate-600">{{ \Illuminate\Support\Str::limit($open->body, 160) }}</span></span>
        <span class="btn-primary shrink-0 text-sm">Přečíst a odeslat</span>
    </a>
@endif

<section class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
    <p><strong>{{ $message['from_name'] ?: $message['from'] }}</strong> &lt;{{ $message['from'] }}&gt; · {{ $message['date'] ? \Illuminate\Support\Carbon::parse($message['date'])->timezone(config('app.timezone'))->format('j. n. Y H:i') : '' }}@if($message['unread']) · <span class="text-amber-700">nepřečtená</span>@endif</p>
    <h1 class="mt-1 text-lg font-bold">{{ $message['subject'] ?: '(bez předmětu)' }}</h1>
    <div class="mt-3 whitespace-pre-wrap text-base leading-relaxed text-slate-900">{{ trim($fresh) !== '' ? $fresh : '(zpráva bez textu — jen přílohy)' }}</div>

    @if($pictures)
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach($pictures as $f)
                <a href="{{ route('admin.emails.inbox.file', [$message['id'], $f['id']]) }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                    <img src="{{ route('admin.emails.inbox.file', [$message['id'], $f['id']]) }}" alt="{{ $f['filename'] }}" loading="lazy" class="h-48 w-full object-contain">
                    <span class="block truncate px-2 py-1 text-xs text-slate-500">{{ $f['filename'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
    @if($others)
        <ul class="mt-3 space-y-1 text-xs">
            @foreach($others as $f)
                <li>📎 <a href="{{ route('admin.emails.inbox.file', [$message['id'], $f['id']]) }}" target="_blank" rel="noopener" class="underline">{{ $f['filename'] }}</a> <span class="text-slate-500">({{ max(1, round($f['size'] / 1024)) }} kB)</span></li>
            @endforeach
        </ul>
    @endif

    @if($quoted !== '')
        <details class="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
            <summary class="cursor-pointer">Citovaný e-mail pod zprávou</summary>
            <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap font-sans">{{ $quoted }}</pre>
        </details>
    @endif
</section>

@if($orders->isNotEmpty())
    <section class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
        <h2 class="font-bold">Objednávky tohoto člověka</h2>
        <ul class="mt-1 space-y-1">
            @foreach($orders as $o)
                <li><a href="{{ route('admin.farm.orders.show', $o) }}" class="underline">{{ $o->number }}</a> · {{ \Illuminate\Support\Str::after(\App\Domain\Mail\ReplyContext::line($o), ' · ') }}</li>
            @endforeach
        </ul>
        <p class="mt-1 text-xs text-slate-500">Tyhle údaje dostává i AI, když píše odpověď.</p>
    </section>
@endif

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
    <h2 class="font-bold">{{ $drafts->isNotEmpty() ? 'Napsat jiný koncept' : 'Navrhnout odpověď' }}</h2>
    <div class="mt-2 grid gap-3 sm:grid-cols-[1fr_10rem]">
        <label class="lbl">Co má odpověď říct <span class="font-normal text-muted">nepovinné; AI vidí zprávu, přiložené fotky a objednávky výše</span>
            <textarea name="instruction" rows="3" maxlength="2000" class="field" placeholder="Např.: omluvit se, vytiskneme fenku znovu zdarma a pošleme do týdne.">{{ old('instruction') }}</textarea>
        </label>
        <label class="lbl">Jazyk
            <select name="locale" class="field">@foreach(['cs' => 'česky', 'en' => 'anglicky', 'es' => 'španělsky'] as $l => $name)<option value="{{ $l }}" @selected(old('locale', $locale) === $l)>{{ $name }}</option>@endforeach</select>
        </label>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-3">
        <button class="btn-primary text-sm" onclick="this.disabled=true;this.textContent='Píšu koncept…';this.form.submit()">{{ $drafts->isNotEmpty() ? 'Napsat znovu' : 'Navrhnout odpověď' }}</button>
        <span class="text-xs text-slate-500">Vznikne koncept (trvá to asi 20 s). Odejde až po schválení, jako odpověď ve vlákně z {{ config('services.gmail.inbox') }}.</span>
    </div>
</form>

@if($message['unread'])
    <form method="post" action="{{ route('admin.emails.inbox.read', $message['id']) }}" class="mt-3">@csrf<button class="btn-quiet min-h-0 px-3 py-1.5 text-xs">Označit jako přečtené bez odpovědi</button></form>
@endif
@endsection
