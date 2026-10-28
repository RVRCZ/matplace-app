@extends('layouts.app', ['title' => 'Search Console · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
<nav class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Statistiky">
    <a href="{{ route('admin.stats.funnel') }}" class="chip">Cesty návštěvníků</a>
    <a href="{{ route('admin.stats.search') }}" class="chip">Vyhledávání</a>
    <a href="{{ route('admin.ai.index') }}" class="chip">AI aktivita</a>
    <a href="{{ route('admin.stats.speed') }}" class="chip">Rychlost</a>
    <a href="{{ route('admin.stats.tools') }}" class="chip">Nástroje týdně</a>
    <a href="{{ route('admin.search_console.index') }}" class="chip chip-on">Search Console</a>
</nav>

@if(session('status'))<p class="note-ok mt-3 text-sm" role="status">{{ session('status') }}</p>@endif
@if(session('error'))<p class="note-warn mt-3 text-sm" role="alert">{{ session('error') }}</p>@endif

{{-- the account --}}
<section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4" data-account="{{ $account ? ($account->ready() ? 'ready' : 'no-site') : 'none' }}">
    <h2 class="font-bold">Účet Google pro Search Console</h2>
    @if(! $configured)
        <p class="mt-1 text-sm text-red-700">V .env chybí <code>YOUTUBE_CLIENT_ID</code> a <code>YOUTUBE_CLIENT_SECRET</code> (nebo vlastní <code>SEARCH_CONSOLE_CLIENT_ID</code> / <code>SEARCH_CONSOLE_CLIENT_SECRET</code>): stejný OAuth klient jako YouTube.</p>
    @elseif($account)
        <p class="mt-1 text-sm">Připojeno <span class="text-xs text-slate-500">{{ $account->created_at->format('j. n. Y H:i') }}</span>
            @if($account->ready()) · vlastnost <span class="font-semibold">{{ $account->site_url }}</span>@endif</p>
        @if(count((array) $account->sites) > 1 || ! $account->ready())
            <form method="post" action="{{ route('admin.search_console.site') }}" class="mt-2 flex flex-wrap items-center gap-2 text-sm">@csrf
                <label class="lbl">Vlastnost
                    <select name="site" class="field">
                        @foreach((array) $account->sites as $site)<option value="{{ $site }}" @selected($site === $account->site_url)>{{ $site }}</option>@endforeach
                    </select>
                </label>
                <button class="btn-secondary text-sm">Použít tuto vlastnost</button>
            </form>
            @if(! (array) $account->sites)<p class="mt-1 text-sm text-red-700">Účet nemá v Search Console žádnou vlastnost: musí být vlastník nebo uživatel matplace.com.</p>@endif
        @endif
        <div class="mt-3 flex flex-wrap gap-2">
            <form method="post" action="{{ route('admin.search_console.pull') }}">@csrf<button class="btn-primary text-sm" @disabled(! $account->ready())>Stáhnout teď</button></form>
            <form method="post" action="{{ route('admin.search_console.disconnect') }}" onsubmit="return confirm('Odpojit účet? Stažené řádky zůstanou, nové se nestahují. Oprávnění u Googlu zůstane (sdílí ho YouTube kanál); zrušit je lze v účtu Google.')">@csrf<button class="btn-quiet text-sm">Odpojit</button></form>
        </div>
        <p class="mt-2 text-xs text-slate-500">
            @if($account->last_pulled_at)Poslední stažení {{ $account->last_pulled_at->format('j. n. Y H:i') }} ({{ $account->last_pulled_from?->format('j. n.') }} až {{ $account->last_pulled_to?->format('j. n. Y') }}).@else Zatím nic staženo: první stažení jde 16 měsíců zpět (tolik API drží), pak každou noc poslední 3 dny.@endif
            @if($account->last_error)<span class="block text-amber-700">Chyba: {{ $account->last_error }}</span>@endif
        </p>
    @else
        <p class="mt-1 text-sm text-slate-600">Účet není připojený. Přihlaste se účtem, který je v Search Console vlastník nebo uživatel vlastnosti matplace.com, a povolte čtení (rozsah webmasters.readonly).</p>
        <form method="post" action="{{ route('admin.search_console.connect') }}" class="mt-2">@csrf<button class="btn-primary text-sm">Připojit Search Console</button></form>
        <details class="mt-3 text-xs text-slate-500"><summary class="cursor-pointer">Co musí být v Google Cloud</summary>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                <li>V projektu s YouTube zapnutá „Google Search Console API“.</li>
                <li>V OAuth klientovi redirect URI přesně <code>{{ $callback }}</code>.</li>
                <li>Na obrazovce souhlasu (v režimu testování) rozsah webmasters.readonly a váš účet mezi testovacími uživateli.</li>
            </ul>
        </details>
    @endif
    <p class="mt-2 text-xs text-slate-500">Denně v 5:20 se stahují poslední 3 dny (Search Console má 2 až 3 dny zpoždění); řádky se přepisují, nic se nepočítá dvakrát. Čísla jsou na <a href="{{ route('admin.stats.tools') }}" class="underline">Nástroje týdně</a>; CSV export v <code>docs/data</code> zůstává jako záloha, čte se jen bez dat z API.</p>
</section>

{{-- what the database holds --}}
<section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
    <h2 class="font-bold">Stažená data</h2>
    @if($rows > 0)
        <p class="mt-1 text-sm">{{ number_format($rows, 0, ',', ' ') }} řádků, dny {{ \Carbon\Carbon::parse($range->first)->format('j. n. Y') }} až {{ \Carbon\Carbon::parse($range->last)->format('j. n. Y') }}.</p>
        <table class="mt-2 w-full max-w-md text-left text-sm" data-days>
            <thead class="text-xs uppercase text-slate-500"><tr><th class="py-1">Den</th><th class="py-1 text-right">Zobrazení</th><th class="py-1 text-right">Kliknutí</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($days as $d)
                    <tr><td class="py-1">{{ \Carbon\Carbon::parse($d->date)->format('j. n. Y') }}</td><td class="py-1 text-right">{{ number_format($d->impressions, 0, ',', ' ') }}</td><td class="py-1 text-right">{{ number_format($d->clicks, 0, ',', ' ') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="mt-1 text-sm text-slate-600">Zatím žádné řádky.</p>
    @endif
</section>
@endsection
