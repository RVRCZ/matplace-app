@extends('layouts.app', ['title' => 'Katalog · admin', 'noindex' => true])

@section('content')
@include('admin.catalog._tabs')

<div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-slate-600">
    <span>Celkem <strong>{{ $counts['all'] }}</strong></span>
    <a href="{{ route('admin.catalog.index', ['visible' => 0]) }}" class="underline">skrytých {{ $counts['hidden'] }}</a>
    <a href="{{ route('admin.catalog.index', ['category' => 'none']) }}" class="underline">bez kategorie {{ $counts['uncategorised'] }}</a>
    <a href="{{ route('admin.catalog.review') }}" class="underline">ke kontrole {{ $counts['review'] }}</a>
    {{-- what search engines get: only pages with something of their own (text of ours, price, file); the rest say noindex, follow --}}
    <a href="{{ route('admin.catalog.index', ['enriched' => 1, 'sort' => 'views']) }}" class="underline" id="count-enriched">obohacených {{ $counts['enriched'] }}</a>
    <a href="{{ route('admin.catalog.index', ['index' => 1, 'sort' => 'views']) }}" class="underline" id="count-indexable" title="index vyžaduje: {{ implode(', ', $indexNeeds) ?: 'nic' }}">indexovaných {{ $counts['indexable'] }} z {{ $counts['shown'] }}</a>
    <form method="post" action="{{ route('admin.catalog.classify') }}" class="ml-auto flex items-center gap-2">@csrf
        <label class="text-xs">Zařadit pomocí AI <input type="number" name="limit" value="50" min="1" max="500" class="w-20 rounded border border-slate-300 px-2 py-1"> modelů bez kategorie</label>
        <button class="btn-quiet min-h-0 px-3 py-1.5 text-sm">Spustit</button>
    </form>
</div>

<form method="get" class="mt-3 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-3 text-sm">
    <label class="flex-1 text-xs font-semibold text-slate-600">Název, slug nebo adresa zdroje<input name="q" value="{{ $q }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
    <label class="text-xs font-semibold text-slate-600">Kategorie
        <select name="category" class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal">
            <option value="">všechny</option>
            <option value="none" @selected(request('category') === 'none')>bez kategorie</option>
            @foreach($categories as $c)<option value="{{ $c->id }}" @selected((string) request('category') === (string) $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->label('cs') }}</option>@endforeach
        </select>
    </label>
    <label class="text-xs font-semibold text-slate-600">Zdroj
        <select name="source" class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal">
            <option value="">všechny</option>
            @foreach($sources as $s)<option value="{{ $s }}" @selected(request('source') === $s)>{{ $s }}</option>@endforeach
        </select>
    </label>
    <label class="text-xs font-semibold text-slate-600">Viditelnost
        <select name="visible" class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal">
            <option value="">vše</option><option value="1" @selected(request('visible') === '1')>viditelné</option><option value="0" @selected(request('visible') === '0')>skryté</option>
        </select>
    </label>
    <label class="text-xs font-semibold text-slate-600">Obohaceno
        <select name="enriched" class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal">
            <option value="">vše</option><option value="1" @selected(request('enriched') === '1')>ano</option><option value="0" @selected(request('enriched') === '0')>ne</option>
        </select>
    </label>
    <label class="text-xs font-semibold text-slate-600">Indexováno
        <select name="index" class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal">
            <option value="">vše</option><option value="1" @selected(request('index') === '1')>ano</option><option value="0" @selected(request('index') === '0')>ne (noindex)</option>
        </select>
    </label>
    <label class="text-xs font-semibold text-slate-600">Řazení
        <select name="sort" class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal">
            <option value="">nejnovější</option><option value="views" @selected(request('sort') === 'views')>nejvíc zobrazení</option>
        </select>
    </label>
    <button class="btn-quiet text-sm">Filtrovat</button>
</form>

{{-- "obohatit vybrané": the ticked rows go to the queue, Gemini writes a text of ours for each (catalog:enrich does the same from the shell) --}}
<form method="post" action="{{ route('admin.catalog.enrich') }}" id="enrich-form" class="mt-3 flex flex-wrap items-center gap-3 text-sm" onsubmit="return this.querySelector('input[name=&quot;ids[]&quot;]:checked') ? confirm('Napsat texty pro vybrané modely přes Gemini (do fronty)?') : (alert('Zaškrtněte modely v tabulce.'), false)">
    @csrf
    <button class="btn-quiet min-h-0 px-3 py-1.5 text-sm">Obohatit vybrané (Gemini)</button>
    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="force" value="1" class="h-4 w-4 accent-action"> i ty, co text už mají (přepsat)</label>
    <button type="button" class="text-xs underline" onclick="document.querySelectorAll('#enrich-form input[name=&quot;ids[]&quot;]').forEach(function (c) { c.checked = true; })">vybrat vše na stránce</button>
    <span class="text-xs text-slate-500">Index vyžaduje: {{ implode(', ', $indexNeeds) ?: 'nic' }} (config/catalog.php). Z příkazové řádky: <code>php artisan catalog:enrich --dry-run</code>.</span>
</form>

<div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2"></th><th class="px-3 py-2"></th><th class="px-3 py-2">Model</th><th class="px-3 py-2">Kategorie</th><th class="px-3 py-2">Licence</th><th class="px-3 py-2">Jazyky</th><th class="px-3 py-2">Zobrazení</th><th class="px-3 py-2">Obohaceno</th><th class="px-3 py-2">Index</th><th class="px-3 py-2"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($models as $m)
                @php $gaps = $m->getAttribute('visible') && $m->slug ? $m->indexGaps() : ['hidden']; @endphp
                <tr class="{{ $m->getAttribute('visible') ? '' : 'opacity-60' }}" data-model="{{ $m->id }}" data-index="{{ $gaps ? 'no' : 'yes' }}">
                    <td class="px-3 py-2"><input type="checkbox" name="ids[]" value="{{ $m->id }}" form="enrich-form" class="h-4 w-4 accent-action" aria-label="vybrat {{ $m->title }}"></td>
                    <td class="px-3 py-2">@if($m->thumbUrl())<img src="{{ $m->thumbUrl() }}" alt="" loading="lazy" class="h-12 w-16 rounded object-cover">@endif</td>
                    <td class="px-3 py-2"><a href="{{ route('admin.catalog.edit', $m->id) }}" class="font-semibold underline">{{ $m->title }}</a>
                        <span class="block text-xs text-slate-500">{{ $m->source }} · {{ $m->author_name ?: 'autor neuveden' }} @if($m->slug)· <a href="{{ route('catalog.show', $m->slug) }}" target="_blank" class="underline">/model/{{ $m->slug }}</a>@endif</span></td>
                    <td class="px-3 py-2">{{ $m->categoryRow?->label('cs') ?? '—' }}@if($m->ai_category_id || $m->ai_mismatch)<span class="block text-xs text-amber-700">AI: ke kontrole</span>@endif</td>
                    <td class="px-3 py-2 text-xs">{{ $m->license ?: '—' }}<span class="block {{ $m->license_restricted ? 'text-slate-500' : 'text-ok' }}">{{ $m->license_restricted ? 'jen pro sebe' : 'i na prodej' }}</span></td>
                    <td class="px-3 py-2 text-xs uppercase">{{ implode(' ', $m->locales()) }}</td>
                    <td class="px-3 py-2">{{ $m->view_count }}</td>
                    <td class="px-3 py-2 text-xs">{{ $m->enriched_at?->format('j. n. Y') ?? '—' }}@if($m->enriched_price_czk)<span class="block text-slate-500">{{ round($m->enriched_price_czk) }} Kč</span>@endif</td>
                    <td class="px-3 py-2 text-xs">@if(! $gaps)<span class="font-semibold text-ok">ano</span>@else<span class="text-slate-500">ne</span><span class="block text-slate-400">chybí: {{ implode(', ', array_map(fn ($g) => ['enriched' => 'text', 'price' => 'cena', 'file' => 'soubor', 'hidden' => 'skrytý'][$g] ?? $g, $gaps)) }}</span>@endif</td>
                    <td class="px-3 py-2 text-right">
                        <form method="post" action="{{ route('admin.catalog.toggle', $m->id) }}">@csrf<button class="text-xs underline">{{ $m->getAttribute('visible') ? 'skrýt' : 'zobrazit' }}</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-3 py-6 text-center text-slate-500">Nic nenalezeno.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $models->links() }}</div>
@endsection
