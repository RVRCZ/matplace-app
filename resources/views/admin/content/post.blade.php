@extends('layouts.app', ['title' => ($post->exists ? ($post->title['cs'] ?? 'Článek') : 'Nový článek').' · admin', 'noindex' => true])

@section('content')
@include('admin.content._tabs')

<p class="mt-2 text-sm"><a href="{{ route('admin.content.posts') }}" class="underline">← Blog</a>
    @if($post->exists) · <a href="{{ route('blog.show', $post->slug) }}" target="_blank" class="underline">{{ $post->isPublished() ? 'zobrazit článek' : 'náhled (vidí jen admin)' }}</a>@endif</p>

<form method="post" action="{{ $post->exists ? route('admin.content.posts.update', $post->id) : route('admin.content.posts.create') }}" enctype="multipart/form-data" class="mt-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
    @if($post->format === 'html')
        <p class="note-warn mb-3 text-sm">Článek je převzatý ze starého webu jako vyčištěné HTML. Text upravujte jako HTML (odstavce &lt;p&gt;, nadpisy &lt;h2&gt;); nové články se píší v Markdownu.</p>
    @endif
    <div class="grid gap-3 sm:grid-cols-3">
        <label class="lbl sm:col-span-2">Adresa (slug) <span class="font-normal text-muted">prázdné = podle českého titulku</span><input name="slug" maxlength="160" pattern="[a-z0-9-]+" value="{{ old('slug', $post->slug) }}" class="field"></label>
        <label class="lbl">Zveřejnit dne <span class="font-normal text-muted">prázdné = tlačítkem</span><input name="published_at" type="datetime-local" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}" class="field"></label>
    </div>

    @foreach(['cs' => 'Česky (povinné)', 'en' => 'Anglicky', 'es' => 'Španělsky'] as $locale => $label)
        <fieldset class="mt-4 rounded-xl border border-slate-200 p-3">
            <legend class="px-1 text-xs font-bold uppercase text-slate-500">{{ $label }}</legend>
            <label class="lbl">Titulek<input name="title[{{ $locale }}]" maxlength="200" @if($locale === 'cs') required @endif value="{{ old('title.'.$locale, $post->title[$locale] ?? '') }}" class="field"></label>
            <label class="lbl mt-2">Perex <span class="font-normal text-muted">do výpisu a jako popis pro vyhledávače</span><textarea name="excerpt[{{ $locale }}]" rows="2" maxlength="600" class="field">{{ old('excerpt.'.$locale, $post->excerpt[$locale] ?? '') }}</textarea></label>
            <label class="lbl mt-2">Text ({{ $post->format === 'html' ? 'HTML' : 'Markdown' }})<textarea name="body[{{ $locale }}]" rows="{{ $locale === 'cs' ? 18 : 8 }}" @if($locale === 'cs') required @endif data-post-body class="field font-mono text-xs">{{ old('body.'.$locale, $post->body[$locale] ?? '') }}</textarea></label>
        </fieldset>
    @endforeach
    <p class="mt-1 text-xs text-slate-500">Jazyková verze existuje, když má titulek i text. Bez ní adresa /en/blog/… odpoví „jen česky“.</p>

    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="lbl">Obálka<input name="cover" type="file" accept="image/*" class="mt-1 block w-full text-sm"></label>
        <label class="lbl">Obrázek do textu <span class="font-normal text-muted">nahraje se a vloží na konec českého textu</span><input type="file" accept="image/*" data-post-image="{{ route('admin.content.posts.image') }}" class="mt-1 block w-full text-sm"></label>
    </div>
    @if($post->coverUrl())<img src="{{ $post->coverUrl() }}" alt="" class="mt-2 h-28 rounded-lg">@endif

    <div class="mt-4 flex flex-wrap gap-2">
        <button name="action" value="save" class="btn-quiet text-sm">Uložit</button>
        @if($post->isPublished())
            <button name="action" value="withdraw" class="btn-quiet text-sm">Stáhnout z webu</button>
        @else
            <button name="action" value="publish" class="btn-primary text-sm">Uložit a zveřejnit</button>
        @endif
    </div>
</form>

@if($post->exists)
    <form method="post" action="{{ route('admin.content.posts.delete', $post->id) }}" class="mt-3" onsubmit="return confirm('Smazat článek?')">@csrf<button class="text-sm text-red-700 underline">Smazat článek</button></form>
@endif

<script>
// a picture for the text: uploaded at once, its Markdown (or an <img> for a taken-over article) is appended to the Czech text
document.querySelector('[data-post-image]')?.addEventListener('change', async (e) => {
    const input = e.target; if (!input.files?.length) return;
    const body = new FormData(); body.append('image', input.files[0]);
    const res = await fetch(input.dataset.postImage, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' } });
    if (!res.ok) { alert('Obrázek se nepodařilo nahrát.'); return; }
    const data = await res.json(); const area = document.querySelector('[data-post-body]');
    area.value += "\n\n" + (@json($post->format === 'html') ? `<img src="${data.url}" alt="">` : data.markdown) + "\n";
    input.value = '';
});
</script>
@endsection
