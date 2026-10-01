@extends('layouts.app', ['title' => ($collection->title['cs'] ?? 'Kolekce').' · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<p class="mt-2 text-sm"><a href="{{ route('admin.collections.index') }}" class="underline">← Kolekce</a>
    @if($collection->getAttribute('visible')) · <a href="{{ route('collections.show', $collection->slug) }}" target="_blank" class="underline">veřejná stránka</a>@else · <a href="{{ route('collections.show', $collection->slug) }}" target="_blank" class="underline">náhled (vidí jen admin)</a>@endif
    · <a href="{{ route('admin.meta.compose', ['type' => 'collection', 'id' => $collection->id]) }}" class="underline">příspěvek na Facebook / Instagram</a></p>

<form method="post" action="{{ route('admin.collections.update', $collection->id) }}" enctype="multipart/form-data" class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
    <div class="grid gap-3 sm:grid-cols-3">
        @foreach(['cs' => 'česky', 'en' => 'anglicky', 'es' => 'španělsky'] as $locale => $label)
            <div>
                <label class="lbl">Název {{ $label }}<input name="title[{{ $locale }}]" maxlength="160" @if($locale === 'cs') required @endif value="{{ old('title.'.$locale, $collection->title[$locale] ?? '') }}" class="field"></label>
                <label class="lbl mt-2">Popis {{ $label }}<textarea name="description[{{ $locale }}]" rows="3" maxlength="1000" class="field">{{ old('description.'.$locale, $collection->description[$locale] ?? '') }}</textarea></label>
            </div>
        @endforeach
    </div>
    <p class="mt-1 text-xs text-slate-500">Stránka kolekce existuje v jazycích, ve kterých má název.</p>
    <label class="mt-2 flex items-center gap-2"><input type="checkbox" name="translate" value="1" class="h-4 w-4 accent-action"> Prázdné jazyky doplnit překladem z češtiny</label>

    <div class="mt-3 grid gap-3 sm:grid-cols-4">
        <label class="lbl sm:col-span-2">Adresa (slug)<input name="slug" required maxlength="120" pattern="[a-z0-9-]+" value="{{ old('slug', $collection->slug) }}" class="field"></label>
        <label class="lbl">Pořadí<input name="position" type="number" min="0" value="{{ old('position', $collection->position) }}" class="field"></label>
        <label class="lbl">Obálka<input name="cover" type="file" accept="image/*" class="mt-1 block w-full text-sm"></label>
    </div>
    @if($collection->cover_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($collection->cover_path) }}" alt="" class="mt-2 h-24 rounded-lg">@endif

    <h2 class="mt-5 font-bold">Modely ({{ $items->count() }})</h2>
    <div class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200">
        @forelse($items as $item)
            @php $m = $item->designerModel ?? $item->catalogModel; @endphp
            <div class="flex items-center gap-3 px-3 py-2">
                <input type="number" name="order[{{ $item->id }}]" value="{{ $item->position }}" min="0" class="w-16 rounded border border-slate-300 px-2 py-1" aria-label="Pořadí">
                @if($m && ($image = $item->designerModel ? $m->coverUrl() : $m->thumbUrl()))<img src="{{ $image }}" alt="" loading="lazy" class="h-10 w-12 rounded object-cover">@endif
                <span class="min-w-0 flex-1 truncate">{{ $m?->title ?? 'model už neexistuje' }} <span class="text-xs text-slate-500">{{ $item->designerModel ? 'karta designéra'.($m->isPrintable() ? '' : ' (teď nejde vytisknout, na stránce se neukáže)') : 'inspirace' }}</span></span>
                <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="remove[]" value="{{ $item->id }}" class="h-4 w-4"> odebrat</label>
            </div>
        @empty
            <p class="px-3 py-4 text-slate-500">Zatím prázdná.</p>
        @endforelse
    </div>
    <label class="lbl mt-3">Přidat modely: adresy nebo slugy, jeden na řádek (<code>/models/…</code> = karta designéra, <code>/model/…</code> = inspirace)
        <textarea name="add" rows="3" class="field font-mono text-xs" placeholder="https://matplace.com/models/stojanek-na-telefon"></textarea>
    </label>

    <label class="mt-3 flex items-center gap-2"><input type="checkbox" name="visible" value="1" @checked(old('visible', $collection->getAttribute('visible'))) class="h-4 w-4 accent-action"> Veřejná</label>
    <button class="btn-primary mt-3 text-sm">Uložit</button>
</form>

<form method="post" action="{{ route('admin.collections.delete', $collection->id) }}" class="mt-3" onsubmit="return confirm('Smazat kolekci? Modely v katalogu zůstanou.')">@csrf
    <button class="text-sm text-red-700 underline">Smazat kolekci</button>
</form>
@endsection
