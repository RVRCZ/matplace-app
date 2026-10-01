@extends('layouts.app', ['title' => 'Bannery · admin', 'noindex' => true])

@section('content')
@include('admin.content._tabs')

<p class="mt-3 text-sm text-slate-600">Bannery se ukazují na úvodní stránce pod kalkulačkou, v pořadí podle čísla. Banner bez jazyka je ve všech jazycích.</p>

@foreach($banners->concat([null]) as $banner)
    <form method="post" action="{{ $banner ? route('admin.content.banners.update', $banner->id) : route('admin.content.banners.create') }}" enctype="multipart/form-data"
          class="mt-3 flex flex-wrap items-end gap-3 rounded-2xl border {{ $banner ? 'border-slate-200' : 'border-dashed border-slate-300' }} bg-white p-3 text-sm">@csrf
        @if($banner)<img src="{{ $banner->imageUrl() }}" alt="" class="h-16 w-32 rounded object-cover">@else<span class="w-32 text-xs font-bold uppercase text-slate-500">Nový banner</span>@endif
        <label class="flex-1 text-xs font-semibold text-slate-600">Popisek (alt text)<input name="title" required maxlength="160" value="{{ $banner?->title }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
        <label class="flex-1 text-xs font-semibold text-slate-600">Odkaz (https://… nebo /cesta)<input name="url" maxlength="500" value="{{ $banner?->url }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
        <label class="text-xs font-semibold text-slate-600">Jazyk
            <select name="locale" class="mt-1 block rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm font-normal">
                <option value="">všechny</option>
                @foreach(\App\Support\Locales::SUPPORTED as $l)<option value="{{ $l }}" @selected($banner?->locale === $l)>{{ $l }}</option>@endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-slate-600">Pořadí<input name="position" type="number" min="0" value="{{ $banner?->position ?? 0 }}" class="mt-1 block w-20 rounded-lg border border-slate-300 px-2 py-2 text-sm font-normal"></label>
        <label class="text-xs font-semibold text-slate-600">Obrázek<input name="image" type="file" accept="image/*" @if(! $banner) required @endif class="mt-1 block w-48 text-xs font-normal"></label>
        <label class="flex items-center gap-1 pb-2"><input type="checkbox" name="active" value="1" @checked($banner?->active ?? true) class="h-4 w-4 accent-action"> aktivní</label>
        <button class="btn-quiet min-h-0 px-3 py-2 text-sm">{{ $banner ? 'Uložit' : 'Přidat' }}</button>
        @if($banner)<button form="delete-banner-{{ $banner->id }}" class="pb-2 text-xs text-red-700 underline">smazat</button>@endif
    </form>
    @if($banner)<form id="delete-banner-{{ $banner->id }}" method="post" action="{{ route('admin.content.banners.delete', $banner->id) }}" onsubmit="return confirm('Smazat banner?')">@csrf</form>@endif
@endforeach
@endsection
