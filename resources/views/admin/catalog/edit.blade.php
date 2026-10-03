@extends('layouts.app', ['title' => $model->title.' · admin', 'noindex' => true])

@section('content')
@include('admin.catalog._tabs')

<div class="mt-3 grid gap-4 lg:grid-cols-[1fr_20rem]">
    <form method="post" action="{{ route('admin.catalog.update', $model->id) }}" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
        <label class="lbl">Název<input name="title" required maxlength="250" value="{{ old('title', $model->title) }}" class="field"></label>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="lbl">Kategorie
                <select name="category_id" class="field">
                    <option value="">bez kategorie</option>
                    @foreach($categories as $c)<option value="{{ $c->id }}" @selected((int) old('category_id', $model->category_id) === $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->label('cs') }}</option>@endforeach
                </select>
            </label>
            <label class="lbl">Licence
                <select name="license" class="field">
                    @foreach($licenses as $l)<option value="{{ $l }}" @selected(old('license', $model->license ?: 'unknown') === $l)>{{ __('models.license.'.$l) }}</option>@endforeach
                </select>
            </label>
            <label class="lbl">Autor<input name="author_name" maxlength="160" value="{{ old('author_name', $model->author_name) }}" class="field"></label>
            <label class="lbl">Štítky (oddělené čárkou)<input name="tags" maxlength="500" value="{{ old('tags', implode(', ', (array) $model->tags)) }}" class="field"></label>
        </div>
        @if($model->ai_category_id || $model->ai_mismatch)
            <p class="note-warn mt-3 text-sm">AI navrhuje: <strong>{{ \App\Models\CatalogCategory::find($model->ai_category_id)?->label('cs') ?? '—' }}</strong> ({{ round($model->ai_confidence * 100) }} %){{ $model->ai_mismatch ? ', obrázek podle ní neodpovídá názvu' : '' }}. {{ $model->ai_reason }} Uložením formuláře rozhodnete sami.</p>
        @endif

        @foreach(['cs' => 'Popis česky', 'en' => 'Popis anglicky', 'es' => 'Popis španělsky'] as $locale => $label)
            <label class="lbl mt-3">{{ $label }} @if($locale !== 'cs')<span class="font-normal text-muted">(stránka /{{ $locale }}/model/… existuje jen s textem)</span>@endif
                <textarea name="description[{{ $locale }}]" rows="5" class="field">{{ old('description.'.$locale, $offered[$locale] ?? ($model->description[$locale] ?? '')) }}</textarea>
            </label>
        @endforeach
        @if($offered)<p class="mt-1 text-xs text-amber-800">V polích je návrh od AI. Do katalogu se zapíše až uložením.</p>@endif

        <label class="mt-3 flex items-center gap-2"><input type="checkbox" name="visible" value="1" @checked(old('visible', $model->getAttribute('visible'))) class="h-4 w-4 accent-action"> Viditelný v katalogu</label>
        <button class="btn-primary mt-3 text-sm">Uložit</button>
    </form>

    <aside class="space-y-3 text-sm">
        <div class="rounded-2xl border border-slate-200 bg-white p-3">
            @if($model->thumbUrl())<img src="{{ $model->thumbUrl() }}" alt="" class="w-full rounded-lg">@endif
            <p class="mt-2 text-xs text-slate-600">Zdroj: {{ $model->source }} @if($model->hasWebLink())· <a href="{{ $model->external_url }}" target="_blank" rel="noopener" class="underline">otevřít</a>@endif
                @if($model->slug)· <a href="{{ route('catalog.show', $model->slug) }}" target="_blank" class="underline">stránka</a>@endif</p>
            <p class="text-xs text-slate-600">Zobrazení: {{ $model->view_count }} · {{ $model->license_restricted ? 'licence: jen pro vlastní potřebu' : 'licence dovoluje i prodej výtisků' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-3">
            <h2 class="font-bold">AI</h2>
            <form method="post" action="{{ route('admin.catalog.classify.one', $model->id) }}" class="mt-2">@csrf<button class="btn-quiet w-full min-h-0 py-2 text-sm">Navrhnout kategorii</button></form>
            <form method="post" action="{{ route('admin.catalog.text', $model->id) }}" class="mt-2">@csrf<input type="hidden" name="how" value="picture"><button class="btn-quiet w-full min-h-0 py-2 text-sm">Popis z obrázku</button></form>
            <form method="post" action="{{ route('admin.catalog.text', $model->id) }}" class="mt-2">@csrf<input type="hidden" name="how" value="rewrite"><button class="btn-quiet w-full min-h-0 py-2 text-sm">Přepsat popis ze zdroje</button></form>
            <p class="mt-2 text-xs text-slate-500">Každé volání je vidět v sekci AI. Texty se jen nabídnou do formuláře.</p>
        </div>
    </aside>
</div>
@endsection
