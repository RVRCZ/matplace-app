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

{{-- how the channel does --}}
@if($account)
    @php $n = fn ($v) => $v === null ? '–' : number_format($v, 0, ',', ' '); @endphp
    <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-bold">Statistiky</h2>
            <form method="post" action="{{ route('admin.youtube.stats') }}">@csrf<button class="btn-quiet text-sm">Načíst z YouTube</button></form>
        </div>
        <dl class="mt-2 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div><dt class="text-xs text-slate-500">Zhlédnutí</dt><dd class="text-2xl font-extrabold">{{ $n($totals['views']) }}</dd></div>
            <div><dt class="text-xs text-slate-500">Lajky</dt><dd class="text-2xl font-extrabold">{{ $n($totals['likes']) }}</dd></div>
            <div><dt class="text-xs text-slate-500">Komentáře</dt><dd class="text-2xl font-extrabold">{{ $n($totals['comments']) }}</dd></div>
            <div><dt class="text-xs text-slate-500">Zveřejněná videa</dt><dd class="text-2xl font-extrabold">{{ $totals['published'] }}</dd></div>
        </dl>
        @if($top->isNotEmpty())
            <h3 class="mt-4 text-sm font-semibold">Nejsledovanější</h3>
            <ol class="mt-1 space-y-1 text-sm">
                @foreach($top as $v)
                    <li class="flex flex-wrap justify-between gap-2">
                        <span><a href="{{ $v->watchUrl() }}" target="_blank" rel="noopener" class="underline">{{ $v->title }}</a> <span class="text-xs text-slate-500">· {{ $v->order?->number }}</span></span>
                        <span class="text-slate-600">{{ $n($v->views) }} zhlédnutí · {{ $n($v->likes) }} lajků</span>
                    </li>
                @endforeach
            </ol>
        @endif
        <p class="mt-2 text-xs text-slate-500">Načítá se každý den v 6:10{{ $totals['at'] ? ', naposledy '.\Illuminate\Support\Carbon::parse($totals['at'])->format('j. n. H:i') : '' }}. Videa zveřejněná ručně v YouTube Studiu se tu tím označí jako zveřejněná.</p>
    </section>
@endif

{{-- a print made for the channel --}}
@if($account)
    <section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Ukázkový tisk pro YouTube</h2>
        <p class="text-xs text-slate-500">Na volnou tiskárnu vytiskne efektní model jen kvůli videu: nic se neplatí, po přípravě jde rovnou do fronty (start po potvrzení volné podložky), hlava parkuje po každé vrstvě a video se nahraje ke schválení. Nejvíc zhlédnutí mají složité tisky s mnoha vrstvami a detaily. Model nahrajte v kalkulačce a vložte sem odkaz na ni.</p>
        <form method="post" action="{{ route('admin.youtube.showcase') }}" class="mt-2 grid gap-2 sm:grid-cols-[2fr_2fr_1fr_auto] sm:items-end">
            @csrf
            <label class="block text-xs font-semibold text-slate-600">Odkaz na kalkulaci nebo UUID modelu
                <input name="model" value="{{ old('model') }}" required placeholder="https://beta.matplace.com/c/…" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
            <label class="block text-xs font-semibold text-slate-600">Tiskárna a cívka
                <select name="slot" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">
                    @foreach($slots as $s)<option value="{{ $s->id }}" @selected((int) old('slot') === $s->id)>{{ $s->printer->name }} · slot {{ $s->slot }} · {{ $s->color?->material?->label() }} {{ $s->color?->displayName() }}</option>@endforeach
                </select></label>
            <label class="block text-xs font-semibold text-slate-600">Kvalita
                <select name="quality" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">
                    @foreach($qualities as $q)<option value="{{ $q }}" @selected(old('quality', 'fine') === $q)>{{ __('farm.quality.'.$q) }}</option>@endforeach
                </select></label>
            <button class="btn-primary text-sm">Vytisknout ukázku</button>
        </form>
        @if($showcases->isNotEmpty())
            <ul class="mt-3 space-y-1 text-sm">
                @foreach($showcases as $o)
                    <li><a href="{{ route('admin.farm.orders.show', $o) }}" class="underline">{{ $o->number }}</a> · {{ $o->printer?->name }} · {{ $o->color?->material?->label() }} {{ $o->color?->displayName() }}
                        <span class="text-xs text-slate-500">· {{ __('farm.status.'.$o->status) }}{{ $o->video?->views !== null ? ' · '.number_format($o->video->views, 0, ',', ' ').' zhlédnutí' : '' }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>
@endif

{{-- waiting for a decision --}}
<h2 class="mt-6 text-lg font-bold">Ke schválení</h2>
@forelse($waiting as $v)
    @php($o = $v->order)
    <section class="mt-3 grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
        <div>
            @if($o?->timelapse_path)
                <video src="{{ route('admin.youtube.file', $o) }}?v={{ @filemtime(Storage::disk(config('farm.disk'))->path($o->timelapse_short_path ?: $o->timelapse_path)) }}" controls muted playsinline preload="metadata" class="w-full rounded-xl border border-slate-200 {{ $o->timelapse_short_path ? 'mx-auto max-w-sm' : '' }}"></video>
                @if($o->timelapse_short_path)<p class="mt-1 text-xs text-slate-500">Čtvercový Short · <a href="{{ route('admin.farm.orders.timelapse', $o) }}?v={{ @filemtime(Storage::disk(config('farm.disk'))->path($o->timelapse_path)) }}" target="_blank" class="underline">širokoúhlá verze pro zákazníka</a></p>@endif
            @endif
            <p class="mt-2 text-xs text-slate-500">
                <a href="{{ route('admin.farm.orders.show', $o) }}" class="underline">{{ $o->number }}</a>
                · {{ $o->color?->material?->label() }} {{ $o->color?->displayName() }} · {{ $o->user?->email }}
                · souhlas {{ $o->video_consent_at?->format('j. n. Y H:i') }}
            </p>
        </div>
        <div>
            @if($v->score !== null)
                <span class="float-right rounded-full px-2 py-0.5 text-xs font-bold {{ $v->score >= 60 ? 'bg-ok-soft text-ok' : ($v->score >= 35 ? 'bg-action-soft text-action-dark' : 'bg-slate-100 text-slate-500') }}"
                      title="Odhad zajímavosti z délky tisku, počtu vrstev a detailu (0–100)">zajímavost {{ $v->score }}</span>
            @endif
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
                @if($v->status === 'uploaded')
                    <form method="post" action="{{ route('admin.youtube.replace', $v) }}" onsubmit="return confirm('Smazat soukromou verzi z YouTube a nahrát aktuální video?')">@csrf<button class="btn-quiet text-sm" title="po přestavění videa (php artisan farm:timelapse)">Nahradit novou verzí</button></form>
                @endif
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
                @if($v->score !== null)<span class="text-xs text-slate-500">· zajímavost {{ $v->score }}</span>@endif
                @if($v->views !== null)<span class="text-xs text-slate-600">· {{ number_format($v->views, 0, ',', ' ') }} zhlédnutí · {{ $v->likes === null ? '–' : number_format($v->likes, 0, ',', ' ') }} lajků</span>@endif
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
