@extends('layouts.app', ['title' => 'Vyhledávání · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
<nav class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Statistiky">
    <a href="{{ route('admin.stats.funnel') }}" class="chip">Cesty návštěvníků</a>
    <a href="{{ route('admin.stats.search') }}" class="chip chip-on">Vyhledávání</a>
    <a href="{{ route('admin.ai.index') }}" class="chip">AI aktivita</a>
    <a href="{{ route('admin.stats.speed') }}" class="chip">Rychlost</a>
</nav>

<div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
    <span class="flex gap-1">@foreach([7, 30, 90] as $d)<a href="{{ route('admin.stats.search', array_filter(['days' => $d, 'locale' => $locale])) }}" class="chip {{ $days === $d ? 'chip-on' : '' }}">{{ $d }} dní</a>@endforeach</span>
    <span class="flex gap-1"><a href="{{ route('admin.stats.search', ['days' => $days]) }}" class="chip {{ $locale === null ? 'chip-on' : '' }}">všechny jazyky</a>
        @foreach(\App\Support\Locales::SUPPORTED as $l)<a href="{{ route('admin.stats.search', ['days' => $days, 'locale' => $l]) }}" class="chip {{ $locale === $l ? 'chip-on' : '' }}">{{ $l }}</a>@endforeach</span>
    <span class="text-slate-600">Hledání: <strong>{{ $total }}</strong></span>
    <a href="{{ route('admin.stats.search', array_filter(['days' => $days, 'locale' => $locale, 'export' => 'csv'])) }}" class="btn-quiet min-h-0 px-3 py-1.5 text-sm">Export CSV</a>
</div>
<p class="mt-1 text-xs text-slate-500">Ukládají se jen slova dotazu, jazyk a počet výsledků; žádný účet ani adresa. E-maily a dlouhá čísla v dotazu se před uložením začerní.</p>

<div class="mt-3 flex flex-wrap gap-3 text-sm">
    @foreach($byLocale as $row)
        <span class="rounded-xl border border-slate-200 bg-white px-3 py-2"><strong class="uppercase">{{ $row->locale }}</strong> {{ $row->searches }} hledání, z toho {{ $row->without }} bez výsledku u nás</span>
    @endforeach
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    @foreach(['Nejčastější dotazy' => $top, 'Dotazy bez výsledku v našem katalogu' => $empty] as $title => $rows)
        <section>
            <h2 class="font-bold">{{ $title }}</h2>
            <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Dotaz</th><th class="px-3 py-2 text-right">Hledání</th><th class="px-3 py-2 text-right">Lidí</th><th class="px-3 py-2 text-right">U nás</th><th class="px-3 py-2 text-right">Jinde</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rows as $r)
                            <tr><td class="px-3 py-2">{{ $r->query }}</td><td class="px-3 py-2 text-right font-semibold">{{ $r->searches }}</td><td class="px-3 py-2 text-right">{{ $r->people }}</td><td class="px-3 py-2 text-right">{{ $r->local }}</td><td class="px-3 py-2 text-right">{{ $r->external }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-3 py-4 text-center text-slate-500">Nic.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
@endsection
