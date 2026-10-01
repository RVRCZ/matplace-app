@extends('layouts.app', ['title' => 'Hledat a přidat · admin', 'noindex' => true])

@section('content')
@include('admin.catalog._tabs')

<form method="get" class="mt-3 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-3 text-sm">
    <label class="flex-1 text-xs font-semibold text-slate-600">Co hledat na Printables, MakerWorldu a MakerOnline<input name="q" value="{{ $q }}" required minlength="2" placeholder="phone stand" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
    <span class="flex gap-3 pb-2">
        @foreach($all as $s)<label class="flex items-center gap-1"><input type="checkbox" name="sources[]" value="{{ $s }}" @checked(in_array($s, $picked)) class="h-4 w-4 accent-action"> {{ $s }}</label>@endforeach
    </span>
    <button class="btn-primary text-sm">Hledat</button>
</form>

<form method="post" action="{{ route('admin.catalog.import') }}" class="mt-3">@csrf
    @if($q !== '')
        <p class="text-sm text-slate-600">{{ count($results) }} výsledků. Zaškrtněte, co má přijít do inspiračního katalogu; z Printables a MakerWorldu se dotáhne popis, obrázky, licence a autor.</p>
        <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($results as $c)
                @php $canonical = \App\Domain\Catalog\CatalogImporter::canonical((string) $c->externalUrl); $have = $known[$canonical] ?? null; @endphp
                <label class="card flex gap-3 p-2 text-sm {{ $have ? 'opacity-60' : '' }}">
                    <input type="checkbox" name="items[]" @disabled($have) value="{{ json_encode(['url' => $c->externalUrl, 'title' => $c->title, 'preview' => $c->previewUrl, 'source' => $c->source, 'author' => $c->authorName, 'license' => $c->license]) }}" class="mt-1 h-4 w-4 shrink-0 accent-action">
                    @if($c->previewUrl)<img src="{{ $c->previewUrl }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="h-16 w-20 shrink-0 rounded object-cover">@endif
                    <span class="min-w-0">
                        <span class="block truncate font-semibold">{{ $c->title }}</span>
                        <span class="block text-xs text-slate-500">{{ $c->source }} · {{ $c->authorName ?: '?' }} · {{ $c->license ?: 'licence neuvedena' }}</span>
                        <a href="{{ $c->externalUrl }}" target="_blank" rel="noopener" class="text-xs underline">zdroj</a>
                        @if($have) · <a href="{{ route('admin.catalog.edit', $have) }}" class="text-xs underline">už v katalogu</a>@endif
                    </span>
                </label>
            @empty
                <p class="text-sm text-slate-500">Nic nenalezeno. Zdroj mohl dotaz odmítnout; zkuste to znovu nebo vložte adresy níže.</p>
            @endforelse
        </div>
    @endif

    <label class="mt-4 block text-xs font-semibold text-slate-600">Nebo vložte adresy modelů (Printables, MakerWorld), jednu na řádek, nejvýše {{ \App\Domain\Catalog\CatalogImporter::MAX_ITEMS }}
        <textarea name="urls" rows="4" placeholder="https://www.printables.com/model/123456-phone-stand" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs font-normal">{{ old('urls') }}</textarea>
    </label>
    <button class="btn-primary mt-2 text-sm">Přidat do inspiračního katalogu</button>
</form>

@if($imports->isNotEmpty())
    <h2 class="mt-6 font-bold">Poslední importy</h2>
    <ul class="mt-2 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white text-sm">
        @foreach($imports as $i)
            <li class="flex justify-between gap-2 px-3 py-2"><a href="{{ route('admin.catalog.imports.show', $i) }}" class="underline">{{ $i->created_at->format('j. n. Y H:i') }}</a><span class="text-slate-600">celkem {{ $i->total }}, hotovo {{ $i->done }}, selhalo {{ $i->failed }}</span></li>
        @endforeach
    </ul>
@endif
@endsection
