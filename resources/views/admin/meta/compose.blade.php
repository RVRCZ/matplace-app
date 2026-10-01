@extends('layouts.app', ['title' => 'Náhled příspěvku · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<p class="mt-2 text-sm"><a href="{{ route('admin.meta.index') }}" class="underline">← Facebook a Instagram</a></p>
<h1 class="mt-2 text-xl font-extrabold">Náhled příspěvku: {{ $draft['title'] }}</h1>

@if($already->isNotEmpty())
    <p class="note-warn mt-2 text-sm">O tomhle už příspěvek vyšel: {{ $already->map(fn ($p) => $p->platform.' '.$p->posted_at?->format('j. n. Y'))->implode(', ') }}.</p>
@endif

<div class="mt-3 grid gap-4 lg:grid-cols-[22rem_1fr]">
    <div class="rounded-2xl border border-slate-200 bg-white p-3">
        @if($draft['image'])<img src="{{ $draft['image'] }}" alt="" class="w-full rounded-lg">@else<p class="note-warn text-sm">Bez obrázku: na Instagram to nepůjde.</p>@endif
        <p class="mt-2 break-all text-xs text-slate-500">Odkaz: {{ $draft['link'] }} <span class="block">(při zveřejnění dostane UTM značky, na Instagramu se odkaz nepřidává)</span></p>
    </div>
    <form method="post" action="{{ route('admin.meta.publish') }}" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">@csrf
        <input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="id" value="{{ $id }}"><input type="hidden" name="locale" value="{{ $locale }}">
        <label class="lbl">Text příspěvku <span class="font-normal text-muted">návrh od AI, upravte ho podle sebe</span>
            <textarea name="text" rows="9" required maxlength="2000" class="field">{{ old('text', $draft['text']) }}</textarea>
        </label>
        <div class="mt-3 flex flex-wrap gap-4">
            <label class="flex items-center gap-2"><input type="checkbox" name="platforms[]" value="facebook" checked class="h-4 w-4 accent-action"> Facebook stránka</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="platforms[]" value="instagram" @checked($draft['image']) @disabled(! $draft['image']) class="h-4 w-4 accent-action"> Instagram</label>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <button class="btn-primary text-sm">Zveřejnit</button>
            <span class="text-xs text-slate-500">Jiný jazyk textu:
                @foreach(\App\Support\Locales::SUPPORTED as $l)<a href="{{ route('admin.meta.compose', ['type' => $type, 'id' => $id, 'locale' => $l]) }}" class="{{ $l === $locale ? 'font-bold' : 'underline' }} uppercase">{{ $l }}</a> @endforeach
                (nový návrh = nové volání AI)</span>
        </div>
    </form>
</div>
@endsection
