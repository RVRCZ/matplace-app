@extends('layouts.app', ['title' => 'Karty designérů · admin', 'noindex' => true])

@section('content')
@include('admin.catalog._tabs')

<form method="get" class="mt-3 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-white p-3 text-sm">
    <label class="flex-1 text-xs font-semibold text-slate-600">Název nebo slug<input name="q" value="{{ $q }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
    <label class="text-xs font-semibold text-slate-600">Soubor
        <select name="file" class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal">
            <option value="">všechny</option><option value="1" @selected(request('file') === '1')>se souborem</option><option value="0" @selected(request('file') === '0')>bez souboru</option>
        </select>
    </label>
    <button class="btn-quiet text-sm">Filtrovat</button>
</form>

<div class="mt-3 space-y-2">
    @forelse($cards as $card)
        <div class="rounded-2xl border border-slate-200 bg-white p-3 text-sm {{ $card->getAttribute('visible') ? '' : 'opacity-70' }}">
            <div class="flex flex-wrap items-center gap-3">
                @if($card->coverUrl())<img src="{{ $card->coverUrl() }}" alt="" loading="lazy" class="h-14 w-16 rounded object-cover">@endif
                <div class="min-w-0 flex-1">
                    <span class="font-semibold">{{ $card->title }}</span>
                    <span class="block text-xs text-slate-600">{{ $card->profile?->display_name }} · {{ $card->source }} · odměna @money((float) $card->royalty_czk, 'CZK') · tisků {{ $card->order_count }}
                        @if($card->isPrintable()) · <a href="{{ route('models.show', $card->slug) }}" target="_blank" class="text-ok underline">lze vytisknout</a>
                        @elseif($card->model_file_id) · soubor: {{ $card->file_status ?: 'čeká' }}
                        @else · bez souboru @endif
                    </span>
                    @if($card->slice_summary)<span class="block text-xs text-slate-500">{{ $card->slice_summary['grams'] ?? '?' }} g · {{ $card->slice_summary['minutes'] ?? '?' }} min · {{ $card->max_mm ? round($card->max_mm).' mm' : '' }}</span>@endif
                </div>
                <form method="post" action="{{ route('admin.catalog.cards.update', $card->id) }}" class="flex flex-wrap items-center gap-2">@csrf
                    <select name="catalog_category_id" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5">
                        <option value="">bez kategorie</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}" @selected($card->catalog_category_id === $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->label('cs') }}</option>@endforeach
                    </select>
                    <label class="flex items-center gap-1"><input type="checkbox" name="visible" value="1" @checked($card->getAttribute('visible')) class="h-4 w-4 accent-action"> viditelná</label>
                    <button class="btn-quiet min-h-0 px-3 py-1.5 text-sm">Uložit</button>
                </form>
                @if($card->model_file_id)
                    <form method="post" action="{{ route('admin.catalog.cards.reslice', $card->id) }}">@csrf<button class="text-xs underline" title="Přepočítá rozměry, gramy a čas dnešními profily">přeslicovat</button></form>
                @endif
            </div>
        </div>
    @empty
        <p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">Žádné karty.</p>
    @endforelse
</div>
<div class="mt-4">{{ $cards->links() }}</div>
@endsection
