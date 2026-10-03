@extends('layouts.app', ['title' => 'Příchozí · e-maily · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<nav class="mt-2 flex flex-wrap gap-2 text-sm" aria-label="Schránka">
    <a href="{{ route('admin.emails.index') }}" class="chip">← Odchozí a koncepty</a>
    <a href="{{ route('admin.emails.inbox') }}" class="chip {{ $all ? '' : 'chip-on' }}">Nepřečtené</a>
    <a href="{{ route('admin.emails.inbox', ['all' => 1]) }}" class="chip {{ $all ? 'chip-on' : '' }}">Všechny poslední</a>
</nav>
<p class="mt-1 text-xs text-slate-500">Schránka {{ $address }}. U zprávy klikněte na „Navrhnout odpověď“: AI napíše koncept, vy ho upravíte a schválením odejde jako odpověď ve vlákně.</p>

@unless($available)
    <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Schránka není připojená: v <code>.env</code> chybí <code>GMAIL_CLIENT_ID</code>, <code>GMAIL_CLIENT_SECRET</code> nebo soubor s tokenem (<code>GMAIL_TOKEN_PATH</code>).</p>
@endunless
@if($error)
    <p class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Schránku se nepodařilo načíst: {{ $error }}</p>
@endif

<div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Kdy</th><th class="px-3 py-2">Od</th><th class="px-3 py-2">Předmět</th><th class="px-3 py-2">Odpověď</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($messages as $m)
                @php $drafts = $answered[$m['id']] ?? collect(); @endphp
                <tr class="{{ $m['unread'] ? 'font-semibold' : '' }}">
                    <td class="px-3 py-2 text-xs text-slate-500">{{ $m['date'] ? \Carbon\Carbon::parse($m['date'])->timezone(config('app.timezone'))->format('j. n. Y H:i') : '' }}</td>
                    <td class="px-3 py-2">{{ $m['from_name'] ?: $m['from'] }}<span class="block text-xs font-normal text-slate-500">{{ $m['from'] }}</span></td>
                    <td class="px-3 py-2"><a href="{{ route('admin.emails.inbox.show', $m['id']) }}" class="underline">{{ $m['subject'] ?: '(bez předmětu)' }}</a><span class="block text-xs font-normal text-slate-500">{{ \Illuminate\Support\Str::limit($m['snippet'], 110) }}</span></td>
                    <td class="px-3 py-2 text-xs font-normal">
                        @if($drafts->contains('status', 'sent'))<span class="text-ok">odpovězeno</span>
                        @elseif($drafts->isNotEmpty())<a href="{{ route('admin.emails.show', $drafts->first()->id) }}" class="underline">koncept ke schválení</a>
                        @else<form method="post" action="{{ route('admin.emails.inbox.reply', $m['id']) }}">@csrf<input type="hidden" name="locale" value="cs"><button class="btn-quiet min-h-0 px-2 py-1 text-xs">Navrhnout odpověď</button></form>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">{{ $available && ! $error ? ($all ? 'Žádné zprávy.' : 'Nic nepřečteného.') : '—' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
