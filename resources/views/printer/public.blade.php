@extends('layouts.app', ['title' => $p->display_name.' · matplace'])

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        @if($p->cover_path)
            <img src="{{ $p->mediaUrl($p->cover_path) }}" alt="" class="h-40 w-full object-cover sm:h-56">
        @else
            <div class="h-20 bg-gradient-to-r from-action to-action-dark sm:h-28"></div>
        @endif
        <div class="flex flex-wrap items-end gap-4 px-5 pb-5">
            <div class="-mt-10 h-20 w-20 shrink-0 overflow-hidden rounded-2xl border-4 border-white bg-slate-100 sm:h-24 sm:w-24">
                @if($p->logo_path)<img src="{{ $p->mediaUrl($p->logo_path) }}" alt="" class="h-full w-full object-cover">
                @else<div class="flex h-full w-full items-center justify-center text-3xl">🖨️</div>@endif
            </div>
            <div class="min-w-0 flex-1 pt-3">
                <h1 class="text-2xl font-extrabold">{{ $p->display_name }}</h1>
                <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-slate-600">
                    @if($p->user->city)<span>📍 {{ $p->user->city }}</span>@endif
                    @if($ratingAvg)<span>★ {{ number_format($ratingAvg, 1, ',', '') }} ({{ $ratings->count() }})</span>@endif
                    @if($p->ico_verified_at)<span class="text-action-dark" title="{{ $p->ico_subject_name }}">✓ {{ __('printer.public.verified') }}</span>@endif
                    @if($p->capacity === 'paused')<span class="text-amber-700">{{ __('printer.public.paused') }}</span>
                    @else<span>{{ __('printer.public.lead', ['n' => $p->lead_time_days]) }}</span>@endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-[1.6fr_1fr]">
        <div class="space-y-4">
            @if($p->bio)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 text-slate-700">{!! nl2br(e($p->bio)) !!}</div>
            @endif

            @if($p->videoEmbedUrl())
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-black">
                    <iframe src="{{ $p->videoEmbedUrl() }}" title="video" loading="lazy" allowfullscreen class="aspect-video w-full" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
            @elseif($p->video_path)
                <video controls preload="none" class="w-full rounded-2xl border border-slate-200 bg-black" @if($p->cover_path) poster="{{ $p->mediaUrl($p->cover_path) }}" @endif>
                    <source src="{{ $p->mediaUrl($p->video_path) }}">
                </video>
            @endif

            @if($p->portfolioItems->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-lg font-bold">{{ __('printer.profile.portfolio') }}</h2>
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach($p->portfolioItems as $item)
                            <a href="{{ $p->mediaUrl($item->photo_path) }}" target="_blank" class="block">
                                <img src="{{ $p->mediaUrl($item->photo_path) }}" alt="{{ $item->title }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover">
                                @if($item->title)<div class="mt-1 truncate text-xs text-slate-500">{{ $item->title }}</div>@endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-bold">{{ __('printer.public.ratings') }}</h2>
                @forelse($ratings as $r)
                    <div class="mt-3 border-t border-slate-100 pt-3 text-sm first:border-0">
                        <div class="text-amber-500">{{ str_repeat('★', (int) $r->score) }}<span class="text-slate-300">{{ str_repeat('★', 5 - (int) $r->score) }}</span> <span class="text-xs text-slate-400">{{ $r->created_at?->format('j. n. Y') }}</span></div>
                        @if($r->comment)<p class="mt-1 text-slate-700">{{ $r->comment }}</p>@endif
                    </div>
                @empty
                    <p class="mt-2 text-sm text-slate-500">{{ __('printer.public.no_ratings') }}</p>
                @endforelse
            </div>
        </div>

        <aside class="space-y-4">
            <a href="{{ route('home') }}" class="block rounded-2xl bg-action p-5 text-white hover:bg-action-dark">
                <div class="text-lg font-bold">{{ __('printer.public.cta') }}</div>
                <div class="text-sm text-white">{{ __('printer.public.cta_hint') }}</div>
            </a>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm">
                @if($p->machines->isNotEmpty())
                    <h3 class="font-bold">{{ __('printer.public.machines') }}</h3>
                    <ul class="mt-1 space-y-1 text-slate-700">
                        @foreach($p->machines as $m)
                            <li>{{ $m->name }}@if($m->count > 1) × {{ $m->count }}@endif @if($m->bed_x)<span class="text-slate-400">{{ $m->bed_x }}×{{ $m->bed_y }}×{{ $m->bed_z }} mm</span>@endif</li>
                        @endforeach
                    </ul>
                @endif
                @if($p->materials->isNotEmpty())
                    <h3 class="mt-4 font-bold">{{ __('printer.public.materials') }}</h3>
                    <div class="mt-1 flex flex-wrap gap-1">
                        @foreach($p->materials as $m)<span class="rounded-full bg-slate-100 px-2 py-0.5" title="{{ __('materials.'.$m->material_code.'.label') }}">{{ $m->material_code }}</span>@endforeach
                    </div>
                @endif
                @if(!empty($p->services))
                    <h3 class="mt-4 font-bold">{{ __('printer.f.services') }}</h3>
                    <ul class="mt-1 space-y-1 text-slate-700">@foreach($p->services as $sv)<li>✓ {{ __('printer.service.'.$sv) }}</li>@endforeach</ul>
                @endif
                @if(!empty($p->delivery_options))
                    <h3 class="mt-4 font-bold">{{ __('printer.f.delivery') }}</h3>
                    <div class="mt-1 text-slate-700">{{ collect($p->delivery_options)->map(fn ($d) => __('printer.delivery.'.$d))->join(', ') }}</div>
                @endif
                @if(!empty($p->languages))
                    <h3 class="mt-4 font-bold">{{ __('printer.f.languages') }}</h3>
                    <div class="mt-1 text-slate-700">{{ collect($p->languages)->map(fn ($l) => __('printer.lang.'.$l))->join(', ') }}</div>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection
