@extends('layouts.app', ['title' => __('farm.admin.nav.videos').' · admin', 'noindex' => true])

@php
    $label = ['queued' => 'Čeká na nahrání', 'uploading' => 'Nahrává se', 'uploaded' => 'Čeká na schválení (soukromé)', 'published' => 'Zveřejněno', 'rejected' => 'Zamítnuto', 'withdrawn' => 'Zákazník odvolal souhlas', 'failed' => 'Nahrání selhalo'];
@endphp

@section('content')
@include('admin.farm.nav')

{{-- the channel --}}
<section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
    <h2 class="font-bold">YouTube kanál</h2>
    @if(! $configured)
        <p class="mt-1 text-sm text-red-700">V .env chybí <code>YOUTUBE_CLIENT_ID</code> a <code>YOUTUBE_CLIENT_SECRET</code> (Google Cloud projekt matplace-youtube → Clients).</p>
    @elseif($account)
        <p class="mt-1 text-sm">Připojeno: <a href="{{ $account->url() }}" target="_blank" rel="noopener" class="font-semibold underline">{{ $account->channel_title }}</a>
            <span class="text-xs text-slate-500">· {{ $account->created_at->format('j. n. Y H:i') }}</span></p>
        <form method="post" action="{{ route('admin.youtube.disconnect') }}" class="mt-2" onsubmit="return confirm('Odpojit kanál? Nová videa se přestanou nahrávat.')">@csrf<button class="btn-quiet text-sm">Odpojit</button></form>
    @else
        <p class="mt-1 text-sm text-slate-600">Kanál není připojený. Přihlaste se účtem, pod kterým kanál je (r.v@matplace.cz), a povolte přístup.</p>
        <form method="post" action="{{ route('admin.youtube.connect') }}" class="mt-2">@csrf<button class="btn-primary text-sm">Připojit YouTube kanál</button></form>
    @endif
    <p class="mt-2 text-xs text-slate-500">Videa se nahrávají jako soukromá, jen u zakázek, kde zákazník dal souhlas. Veřejná jsou až po schválení tady.</p>
</section>

{{-- waiting for a decision --}}
<h2 class="mt-6 text-lg font-bold">Ke schválení</h2>
@forelse($waiting as $v)
    @php($o = $v->order)
    <section class="mt-3 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
        <div>
            @if($o?->timelapse_path)
                <video src="{{ route('admin.farm.orders.timelapse', $o) }}" controls muted playsinline preload="metadata" class="w-full rounded-xl border border-slate-200"></video>
            @endif
            <p class="mt-2 text-xs text-slate-500">
                <a href="{{ route('admin.farm.orders.show', $o) }}" class="underline">{{ $o->number }}</a>
                · {{ $o->color?->material?->label() }} {{ $o->color?->displayName() }} · {{ $o->user?->email }}
                · souhlas {{ $o->video_consent_at?->format('j. n. Y H:i') }}
            </p>
        </div>
        <div>
            <p class="text-sm font-semibold">{{ $label[$v->status] ?? $v->status }}
                @if($v->studioUrl())<a href="{{ $v->studioUrl() }}" target="_blank" rel="noopener" class="ml-2 text-xs font-normal underline">otevřít v YouTube Studiu</a>@endif
            </p>
            @if($v->error)<p class="mt-1 text-xs text-red-700">{{ $v->error }}</p>@endif

            @if($v->status === 'uploaded')
                <form method="post" action="{{ route('admin.youtube.publish', $v) }}" class="mt-2 space-y-2">
                    @csrf
                    <label class="block text-xs font-semibold text-slate-600">Název (max. 100 znaků)
                        <input name="title" value="{{ old('title', $v->title) }}" required maxlength="100" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
                    <label class="block text-xs font-semibold text-slate-600">Popis
                        <textarea name="description" rows="7" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">{{ old('description', $v->description) }}</textarea></label>
                    <button class="btn-primary text-sm">Zveřejnit na YouTube</button>
                </form>
            @endif
            <div class="mt-2 flex flex-wrap gap-2">
                @if(in_array($v->status, ['failed', 'uploading', 'queued'], true))
                    <form method="post" action="{{ route('admin.youtube.retry', $v) }}">@csrf<button class="btn-quiet text-sm">Nahrát znovu</button></form>
                @endif
                <form method="post" action="{{ route('admin.youtube.reject', $v) }}" onsubmit="return confirm('Zamítnout video? Z YouTube se smaže.')">@csrf<button class="btn-quiet text-sm text-red-700">Zamítnout</button></form>
            </div>
        </div>
    </section>
@empty
    <p class="mt-2 text-sm text-slate-500">Nic nečeká.</p>
@endforelse

@if($missing->isNotEmpty())
    <h2 class="mt-6 text-lg font-bold">Se souhlasem, ale nenahrané</h2>
    <p class="text-xs text-slate-500">Tisky dokončené dřív, než byl kanál připojený.</p>
    <div class="mt-2 space-y-2">
        @foreach($missing as $o)
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                <span><a href="{{ route('admin.farm.orders.show', $o) }}" class="underline">{{ $o->number }}</a> · {{ $o->color?->material?->label() }} {{ $o->color?->displayName() }}</span>
                <form method="post" action="{{ route('admin.youtube.queue', $o) }}">@csrf<button class="btn-quiet text-sm">Nahrát na YouTube</button></form>
            </div>
        @endforeach
    </div>
@endif

<h2 class="mt-6 text-lg font-bold">Vyřízené</h2>
<div class="mt-2 space-y-2">
    @forelse($done as $v)
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
            <span>
                <a href="{{ route('admin.farm.orders.show', $v->order) }}" class="underline">{{ $v->order?->number }}</a> · {{ $v->title }}
                <span class="text-xs text-slate-500">· {{ $label[$v->status] ?? $v->status }} {{ ($v->published_at ?? $v->decided_at ?? $v->updated_at)?->format('j. n. Y') }}</span>
                @if($v->error)<span class="block text-xs text-red-700">{{ $v->error }}</span>@endif
            </span>
            <span class="flex gap-2">
                @if($v->status === 'published' && $v->watchUrl())<a href="{{ $v->watchUrl() }}" target="_blank" rel="noopener" class="btn-quiet text-sm">Přehrát</a>@endif
                @if($v->status === 'published')
                    <form method="post" action="{{ route('admin.youtube.reject', $v) }}" onsubmit="return confirm('Stáhnout video? Z YouTube se smaže.')">@csrf<button class="btn-quiet text-sm text-red-700">Stáhnout</button></form>
                @endif
            </span>
        </div>
    @empty
        <p class="text-sm text-slate-500">Zatím nic.</p>
    @endforelse
</div>
@endsection
