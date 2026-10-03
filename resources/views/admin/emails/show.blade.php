@extends('layouts.app', ['title' => 'E-mail · admin', 'noindex' => true])

@php $editable = in_array($email->status, ['draft', 'approved'], true); @endphp

@section('content')
@include('admin.nav')
@include('partials.flash')

<p class="mt-2 text-sm"><a href="{{ route('admin.emails.index', ['status' => $email->status]) }}" class="underline">← E-maily</a></p>
<p class="mt-2 text-sm text-slate-600">
    {{ ['draft' => 'Koncept, čeká na schválení', 'approved' => 'Schválený, odeslání se nepodařilo', 'sent' => 'Odeslaný', 'rejected' => 'Zamítnutý, neodešel'][$email->status] ?? $email->status }}
    · {{ $email->generated_by_ai ? 'napsala AI' : 'systémové oznámení' }} · jazyk {{ $email->locale }}
    @if($email->sent_at) · odesláno {{ $email->sent_at->format('j. n. Y H:i') }}@endif
    @if($email->approver) · {{ $email->status === 'rejected' ? 'zamítl' : 'schválil' }} {{ $email->approver->name }}@endif
</p>
@if($email->instruction)<p class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">Zadání pro AI: {{ $email->instruction }}</p>@endif
@if($email->isReply())
    <section class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
        <h2 class="text-xs font-bold uppercase text-slate-500">Odpověď na zprávu ze schránky</h2>
        @if($original)
            <p class="mt-1"><strong>{{ $original['from_name'] ?: $original['from'] }}</strong> &lt;{{ $original['from'] }}&gt; · {{ $original['date'] }}</p>
            <p class="font-semibold">{{ $original['subject'] }}</p>
            <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap font-sans text-xs text-slate-700">{{ $original['text'] }}</pre>
            <a href="{{ route('admin.emails.inbox.show', $original['id']) }}" class="mt-1 inline-block text-xs underline">zpráva ve schránce</a>
        @else
            <p class="mt-1 text-xs text-slate-500">Původní zprávu se teď nepodařilo načíst; odpověď odejde ve vlákně i tak.</p>
        @endif
    </section>
@endif

<form method="post" action="{{ route('admin.emails.update', $email->id) }}" class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
    <label class="lbl">Komu<input name="to" type="email" required maxlength="190" value="{{ old('to', $email->to) }}" @disabled(! $editable) class="field"></label>
    <label class="lbl mt-3">Předmět<input name="subject" required maxlength="250" value="{{ old('subject', $email->subject) }}" @disabled(! $editable) class="field"></label>
    <label class="lbl mt-3">Text<textarea name="body" rows="14" required maxlength="20000" @disabled(! $editable) class="field">{{ old('body', $email->body) }}</textarea></label>
    @if($editable)
        <div class="mt-3 flex flex-wrap gap-2">
            <button name="action" value="approve" class="btn-primary text-sm">Schválit a odeslat</button>
            <button name="action" value="save" class="btn-quiet text-sm">Jen uložit změny</button>
            <button name="action" value="reject" class="btn-quiet text-sm text-red-700" onclick="return confirm('Zamítnout? E-mail neodejde.')">Zamítnout</button>
        </div>
        <p class="mt-2 text-xs text-slate-500">Odejde text, který je teď ve formuláři.</p>
    @endif
</form>
@endsection
