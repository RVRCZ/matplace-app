@extends('layouts.app', ['title' => 'Ke kontrole · admin', 'noindex' => true])

@section('content')
@include('admin.catalog._tabs')

<p class="mt-3 text-sm text-slate-600">Modely, u kterých si AI nebyla jistá kategorií (pod {{ (int) (\App\Domain\Catalog\CategoryClassifier::SURE * 100) }} %), nebo u kterých obrázek neodpovídá názvu. Potvrďte návrh, nebo vyberte jinou kategorii.</p>

@php
    $row = function ($type, $item, $title, $image, $current, $editUrl) use ($categories) {
        return compact('type', 'item', 'title', 'image', 'current', 'editUrl');
    };
    $rows = collect($models->items())->map(fn ($m) => $row('catalog_model', $m, $m->title, $m->thumbUrl(), $m->categoryRow, route('admin.catalog.edit', $m->id)))
        ->concat($cards->map(fn ($c) => $row('designer_model', $c, $c->title.' ('.$c->profile?->display_name.')', $c->coverUrl(), $c->categoryRow, route('admin.catalog.cards', ['q' => $c->slug]))));
@endphp

<div class="mt-3 space-y-2">
    @forelse($rows as $r)
        @php $suggested = $categories->firstWhere('id', $r['item']->ai_category_id); @endphp
        <form method="post" action="{{ route('admin.catalog.resolve') }}" class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 text-sm">@csrf
            <input type="hidden" name="type" value="{{ $r['type'] }}"><input type="hidden" name="id" value="{{ $r['item']->id }}">
            @if($r['image'])<img src="{{ $r['image'] }}" alt="" loading="lazy" class="h-16 w-20 rounded object-cover">@endif
            <div class="min-w-0 flex-1">
                <a href="{{ $r['editUrl'] }}" class="font-semibold underline">{{ $r['title'] }}</a>
                <p class="text-xs text-slate-600">Teď: {{ $r['current']?->label('cs') ?? 'bez kategorie' }} · AI: <strong>{{ $suggested?->label('cs') ?? 'neví' }}</strong> ({{ round($r['item']->ai_confidence * 100) }} %)@if($r['item']->ai_mismatch) · <span class="text-amber-700">obrázek neodpovídá názvu</span>@endif</p>
                @if($r['item']->ai_reason)<p class="text-xs text-slate-500">{{ $r['item']->ai_reason }}</p>@endif
            </div>
            <select name="category_id" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5">
                <option value="">ponechat, jak je</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected($suggested?->id === $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->label('cs') }}</option>@endforeach
            </select>
            <button class="btn-primary min-h-0 px-3 py-1.5 text-sm">Potvrdit</button>
        </form>
    @empty
        <p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">Nic ke kontrole.</p>
    @endforelse
</div>
<div class="mt-4">{{ $models->links() }}</div>
@endsection
