@extends('layouts.app', ['title' => __('farm.admin.nav.videos').' · admin', 'noindex' => true])

@php
    $tz = config('youtube.publish_timezone', 'Europe/Prague');
    $kindNames = ['short' => 'Short', 'wide' => '16:9'];
    // one flag per video: published (green), planned, waiting for the admin, settled otherwise
    $badge = function ($v) use ($tz) {
        return match ($v->status) {
            'published' => ['Zveřejněno '.$v->published_at?->timezone($tz)->format('j. n.'), 'bg-ok-soft text-ok'],
            'scheduled' => ['Naplánováno '.$v->scheduled_at?->timezone($tz)->format('j. n. H:i'), 'bg-action-soft text-action-dark'],
            'queued', 'uploading' => ['Nahrává se', 'bg-action-soft text-action-dark'],
            'uploaded' => ['Na YouTube soukromé', 'bg-action-soft text-action-dark'],
            'failed' => ['Nahrání selhalo', 'bg-red-50 text-red-700'],
            'rejected' => ['Zamítnuto', 'bg-slate-100 text-slate-500'],
            'withdrawn' => ['Souhlas odvolán', 'bg-slate-100 text-slate-500'],
            'missing' => ['Na YouTube už není', 'bg-slate-100 text-slate-500'],
            default => ['Nezveřejněno', 'bg-slate-100 text-slate-700'],
        };
    };
@endphp

@section('content')
@include('admin.farm.nav')

<div class="mt-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-xl font-extrabold">Videa z tisků</h1>
        <p class="text-xs text-slate-500">Ke každému tisku se souhlasem zákazníka vzniká Short a video 16:9. Kliknutím otevřete stavbu videa: média, texty a zveřejnění.</p>
    </div>
    <p class="text-sm">
        <a href="{{ route('admin.youtube.channel') }}" class="underline">Kanál, statistiky a ukázkový tisk</a>
        @if($otherWaiting) · <a href="{{ route('admin.youtube.channel') }}" class="font-semibold text-action-dark underline">sestřihy a návody ke schválení ({{ $otherWaiting }})</a>@endif
    </p>
</div>

<nav class="mt-3 flex flex-wrap gap-2 text-sm" aria-label="Filtr">
    @foreach(['all' => 'Vše', 'waiting' => 'Čeká na rozhodnutí', 'published' => 'Zveřejněné a naplánované'] as $key => $name)
        <a href="{{ route('admin.youtube.index', $key === 'all' ? [] : ['show' => $key]) }}" @if($filter === $key) aria-current="page" @endif
           class="rounded-full border px-3 py-1 {{ $filter === $key ? 'border-ink bg-ink text-white' : 'border-slate-300 bg-white' }}">{{ $name }}</a>
    @endforeach
</nav>

<ul class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
    @forelse($orders as $o)
        @php($short = $o->videos->firstWhere('kind', 'short'))
        @php($published = $o->videos->whereIn('status', ['published', 'scheduled'])->isNotEmpty())
        <li>
            <a href="{{ route('admin.youtube.studio', $o) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                @if($o->photoUrl())
                    <img src="{{ $o->photoUrl() }}" alt="" class="h-12 w-12 shrink-0 rounded-lg object-cover">
                @else
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-lg" aria-hidden="true">🎬</span>
                @endif
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold">{{ $short?->title ?? $o->videos->first()?->title ?? $o->number }}</span>
                    <span class="block text-xs text-slate-500">{{ $o->number }} · {{ $o->color?->material?->label() }} {{ $o->color?->displayName() }} · {{ ($o->finished_at ?? $o->created_at)?->timezone($tz)->format('j. n. Y') }}</span>
                </span>
                <span class="flex shrink-0 flex-col items-end gap-1 text-xs">
                    <span class="rounded-full px-2 py-0.5 font-bold {{ $published ? 'bg-ok-soft text-ok' : 'bg-slate-100 text-slate-600' }}">{{ $published ? '✓ zveřejněno' : 'nezveřejněno' }}</span>
                    @foreach(['short', 'wide'] as $kind)
                        @if($v = $o->videos->firstWhere('kind', $kind))
                            @php([$text, $class] = $badge($v))
                            <span class="rounded-full px-2 py-0.5 {{ $class }}">{{ $kindNames[$kind] }}: {{ $text }}@if($v->views !== null) · {{ number_format($v->views, 0, ',', ' ') }} zhl.@endif</span>
                        @endif
                    @endforeach
                </span>
            </a>
        </li>
    @empty
        <li class="px-4 py-6 text-sm text-slate-500">Zatím tu nic není.</li>
    @endforelse
</ul>

<div class="mt-4">{{ $orders->links() }}</div>
@endsection
