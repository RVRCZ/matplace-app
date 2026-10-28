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
        {{-- the source's own text once a text of ours replaced it (catalog:enrich); read here, never edited --}}
        @if($original = $model->original())
            <details class="mt-3 rounded-xl border border-slate-200 p-3 text-sm"><summary class="cursor-pointer font-semibold">Původní popis zdroje ({{ $original['locale'] }}), zachovaný pod naším textem</summary><p class="mt-2 whitespace-pre-line text-slate-600">{{ $original['text'] }}</p></details>
        @endif
        @php $gaps = $model->indexGaps(); $gapWords = ['enriched' => 'text', 'price' => 'cena', 'file' => 'soubor']; @endphp
        <p class="mt-2 text-xs text-slate-500">Obohaceno: {{ $model->enriched_at?->format('j. n. Y H:i') ?? 'ne' }}{{ $model->enriched_price_czk ? ' · cena 1 ks '.round($model->enriched_price_czk).' Kč' : '' }} · index: {{ $gaps === [] ? 'ano' : 'ne (chybí '.implode(', ', array_map(fn ($g) => $gapWords[$g] ?? $g, $gaps)).')' }}</p>

        <label class="mt-3 flex items-center gap-2"><input type="checkbox" name="visible" value="1" @checked(old('visible', $model->getAttribute('visible'))) class="h-4 w-4 accent-action"> Viditelný v katalogu</label>
        <button class="btn-primary mt-3 text-sm">Uložit</button>
    </form>
    {{-- a text of ours for this one model, written by the queue (Gemini); the page is indexed once it also has a price and the file --}}
    <form method="post" action="{{ route('admin.catalog.enrich') }}" class="lg:col-start-1 -mt-2 text-sm" onsubmit="return confirm('Napsat text přes Gemini (do fronty)? Současný popis se přesune pod „původní popis zdroje“.')">@csrf
        <input type="hidden" name="ids[]" value="{{ $model->id }}"><input type="hidden" name="force" value="1">
        <button class="btn-quiet text-sm">{{ $model->isEnriched() ? 'Napsat náš text znovu (Gemini)' : 'Napsat náš text (Gemini)' }}</button>
    </form>

    <aside class="space-y-3 text-sm">
        <div class="rounded-2xl border border-slate-200 bg-white p-3">
            @if($model->thumbUrl())<img src="{{ $model->thumbUrl() }}" alt="" class="w-full rounded-lg">@endif
            <p class="mt-2 text-xs text-slate-600">Zdroj: {{ $model->source }} @if($model->hasWebLink())· <a href="{{ $model->external_url }}" target="_blank" rel="noopener" class="underline">otevřít</a>@endif
                @if($model->slug)· <a href="{{ route('catalog.show', $model->slug) }}" target="_blank" class="underline">stránka</a>@endif</p>
            <p class="text-xs text-slate-600">Zobrazení: {{ $model->view_count }} · {{ $model->license_restricted ? 'licence: jen pro vlastní potřebu' : 'licence dovoluje i prodej výtisků' }}</p>
        </div>
        {{-- the model's own file: uploaded by hand from the source, sliced for the page's price (docs/AN.md §15) --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-3" data-model-file>
            <h2 class="font-bold">Soubor modelu</h2>
            @if($model->modelFile)
                <p class="mt-1 text-xs text-slate-600">{{ $model->modelFile->original_name }} · {{ ['uploaded' => 'čeká na zpracování', 'processing' => 'zpracovává se', 'ready' => 'připraven', 'failed' => 'zpracování selhalo', 'deleted' => 'smazán'][$model->modelFile->status] ?? $model->modelFile->status }}</p>
                <p class="text-xs text-slate-600">Řez: @if($model->sliced_at)<span class="text-ok">{{ $model->sliced_at->format('j. n. Y') }}</span>, {{ $model->slice_minutes >= 60 ? intdiv((int) $model->slice_minutes, 60).' h '.($model->slice_minutes % 60).' min' : $model->slice_minutes.' min' }}, {{ $model->slice_grams }} g @if($model->sliceCalculation) · <a href="{{ url('/c/'.$model->sliceCalculation->token) }}" target="_blank" class="underline">kalkulace</a>@endif @elseif($model->slice_error)<span class="text-amber-700">selhal: {{ $model->slice_error }}</span>@else čeká @endif</p>
                <form method="post" action="{{ route('admin.catalog.slice', $model->id) }}" class="mt-2">@csrf<button class="btn-quiet w-full min-h-0 py-2 text-sm">Rozřezat znovu</button></form>
            @else
                <p class="mt-1 text-xs text-slate-600">Bez souboru: stáhněte ho u zdroje (STL nebo 3MF, u sady největší díl) a nahrajte sem. Zůstane skrytý, rozřeže se ve frontě a stránka pak ukáže cenu ve třech materiálech.</p>
            @endif
            <div class="mt-2">@include('admin.catalog._file_form', ['model' => $model, 'formats' => $formats])</div>
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
