@extends('layouts.app', ['title' => 'Návrhy kolekcí · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<p class="mt-2 text-sm"><a href="{{ route('admin.collections.index') }}" class="underline">← Kolekce</a></p>
<h1 class="mt-2 text-xl font-extrabold">Návrhy kolekcí</h1>
<p class="mt-1 max-w-2xl text-sm text-slate-600">Skupiny modelů, které sdílejí štítek ve stejné kategorii a ještě nejsou v žádné kolekci: nejvýše {{ \App\Domain\Catalog\CollectionSuggester::THEMES }} témat po {{ \App\Domain\Catalog\CollectionSuggester::PER_THEME }} modelech, tisknutelné karty designérů napřed. Skupiny hledá program, AI je jen pojmenuje (jedno volání).</p>

<form method="post" action="{{ route('admin.collections.suggest') }}" class="mt-3">@csrf
    <button class="btn-primary text-sm">{{ $asked ? 'Navrhnout znovu' : 'Navrhnout kolekce' }}</button>
</form>

<div class="mt-4 space-y-3">
    @forelse($themes as $theme)
        <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ $theme['title']['cs'] }}</h2>
                    <p class="text-slate-600">{{ $theme['description']['cs'] }}</p>
                    <p class="text-xs text-slate-500">{{ $theme['title']['en'] }} · {{ $theme['title']['es'] }} · štítek „{{ $theme['tag'] }}“@if($theme['category']), kategorie {{ $theme['category'] }}@endif · {{ count($theme['items']) }} modelů</p>
                </div>
                <form method="post" action="{{ route('admin.collections.from_suggestion') }}">@csrf<input type="hidden" name="key" value="{{ $theme['key'] }}"><button class="btn-quiet min-h-0 px-3 py-2 text-sm">Založit kolekci</button></form>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach($theme['items'] as $item)
                    <span class="w-24 text-xs" title="{{ $item['title'] }}">
                        @if($item['image'])<img src="{{ $item['image'] }}" alt="" loading="lazy" class="h-16 w-24 rounded object-cover">@else<span class="block h-16 w-24 rounded bg-slate-100"></span>@endif
                        <span class="block truncate">{{ $item['title'] }}</span>
                        @if($item['type'] === 'designer_model')<span class="text-ok">k tisku</span>@endif
                    </span>
                @endforeach
            </div>
        </div>
    @empty
        @if($asked)<p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">Žádná skupina alespoň {{ \App\Domain\Catalog\CollectionSuggester::MIN_MODELS }} modelů se společným štítkem se nenašla.</p>@endif
    @endforelse
</div>
@endsection
