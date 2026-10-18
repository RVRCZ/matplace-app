@extends('layouts.app', ['title' => __('farm.admin.nav.videos').' · admin', 'noindex' => true])

@php
    $label = ['pending' => 'Ke schválení (na YouTube zatím není)', 'queued' => 'Schváleno, čeká na nahrání', 'uploading' => 'Nahrává se', 'uploaded' => 'Na YouTube jako soukromé, čeká na zveřejnění', 'scheduled' => 'Naplánováno', 'published' => 'Zveřejněno', 'rejected' => 'Zamítnuto', 'withdrawn' => 'Zákazník odvolal souhlas', 'failed' => 'Nahrání selhalo'];
    $tz = config('youtube.publish_timezone', 'Europe/Prague');
    $when = fn ($t) => ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'][$t->dayOfWeek].' '.$t->format('j. n. H:i');
    $viewNames = ['top' => 'shora', 'left' => 'zleva', 'right' => 'zprava', 'phone' => 'z mobilu'];
    $kindNames = ['short' => 'Short (čtverec)', 'wide' => 'Klasické video 16:9', 'weekly' => 'Týdenní sestřih', 'howto' => 'Instruktážní video'];
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
    <p class="mt-2 text-xs text-slate-500">Z každého tisku vzniká čtvercový Short a klasické video 16:9, každou neděli v 18:00 týdenní sestřih; všechna mají hudbu. Video čeká tady, jen u zakázek, kde zákazník dal souhlas. Na YouTube se nahraje až po schválení a hned je veřejné; co zamítnete, se nikam nenahrává (YouTube počítá každé nahrání do denního limitu kanálu).</p>
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
        <h3 class="mt-4 text-sm font-semibold">Podle druhu videa</h3>
        <div class="mt-1 overflow-x-auto">
            <table class="w-full min-w-[32rem] text-sm">
                <thead class="text-left text-xs text-slate-500">
                    <tr><th class="py-1 font-normal"></th><th class="py-1 font-normal">za 7 dní</th><th class="py-1 font-normal">za 30 dní</th><th class="py-1 font-normal">celkem</th></tr>
                </thead>
                <tbody>
                    @foreach($kinds as $kind => $row)
                        <tr class="border-t border-slate-100">
                            <td class="py-1 font-semibold">{{ $kindNames[$kind] ?? $kind }}</td>
                            @foreach(['7', '30', 'all'] as $span)
                                <td class="py-1 text-slate-700">{{ $row[$span]['videos'] }} vid. · {{ $n($row[$span]['views']) }} zhl. · {{ $n($row[$span]['likes']) }} lajků</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-1 text-xs text-slate-500">Videa zveřejněná v daném období a jejich dosavadní zhlédnutí a lajky.</p>
        @if($top->isNotEmpty())
            <h3 class="mt-4 text-sm font-semibold">Nejsledovanější</h3>
            <ol class="mt-1 space-y-1 text-sm">
                @foreach($top as $v)
                    <li class="flex flex-wrap justify-between gap-2">
                        <span><a href="{{ $v->watchUrl() }}" target="_blank" rel="noopener" class="underline">{{ $v->title }}</a> <span class="text-xs text-slate-500">· {{ $v->order?->number ?? $kindNames[$v->kind] }}</span></span>
                        <span class="text-slate-600">{{ $n($v->views) }} zhlédnutí · {{ $n($v->likes) }} lajků</span>
                    </li>
                @endforeach
            </ol>
        @endif
        <p class="mt-2 text-xs text-slate-500">Načítá se každou hodinu{{ $totals['at'] ? ', naposledy '.\Illuminate\Support\Carbon::parse($totals['at'])->timezone($tz)->format('j. n. H:i') : '' }}. Naplánovaná videa, která YouTube mezitím zveřejnil, a videa zveřejněná ručně ve Studiu se tu tím označí jako zveřejněná.</p>
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

{{-- waiting for a decision: the Short and the 16:9 of one print side by side, each approved on its own --}}
<h2 class="mt-6 text-lg font-bold">Ke schválení</h2>
@forelse($waiting as $group)
    @php($o = $group->first()->order)
    <section class="mt-3 rounded-2xl border border-slate-200 bg-white p-4">
        @if($o)
            <p class="text-xs text-slate-500">
                <a href="{{ route('admin.farm.orders.show', $o) }}" class="underline">{{ $o->number }}</a>
                · {{ $o->color?->material?->label() }} {{ $o->color?->displayName() }} · {{ $o->user?->email }}
                · souhlas {{ $o->video_consent_at?->format('j. n. Y H:i') }}
            </p>
            @if(array_key_exists($o->id, $finish))
                @php($fi = $finish[$o->id])
                <p class="mt-1 text-xs text-slate-500">
                    @if($fi !== null)
                        Na konci videí: fotka {{ $fi + 1 }} ({{ $viewNames[$o->test_params['photos'][$fi]['view'] ?? ''] ?? '' }}) z <a href="{{ route('admin.farm.photobox', ['order' => $o->token]) }}" class="underline">foto-boxu</a>
                        <a href="{{ route('admin.farm.photos.show', [$o, $fi]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.farm.photos.show', [$o, $fi]) }}?thumb=1" alt="" class="ml-1 inline-block h-10 w-10 rounded object-cover align-middle"></a>
                    @else
                        Bez fotky hotového kusu: <a href="{{ route('admin.farm.photobox', ['order' => $o->token]) }}" class="underline">vyfotit ve foto-boxu</a>, videa se pak přestaví sama.
                    @endif
                </p>
            @endif
        @elseif($group->first()->kind === 'howto')
            <p class="text-xs text-slate-500">Instruktážní video {{ $group->first()->meta['name'] ?? '' }} · sestaveno {{ $group->first()->updated_at?->timezone($tz)->format('j. n. Y H:i') }} (php artisan farm:howto-video)</p>
        @else
            <p class="text-xs text-slate-500">Týden {{ $group->first()->meta['week'] ?? '' }} · {{ count($group->first()->meta['orders'] ?? []) }} tisků · sestaveno {{ $group->first()->updated_at?->timezone($tz)->format('j. n. Y H:i') }}</p>
        @endif
        <div class="mt-3 grid gap-6 {{ $group->count() > 1 ? 'lg:grid-cols-2' : 'md:max-w-2xl' }}">
            @foreach($group as $v)
                @include('admin.youtube._video', ['v' => $v])
            @endforeach
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
                @if($v->order)<a href="{{ route('admin.farm.orders.show', $v->order) }}" class="underline">{{ $v->order->number }}</a> ·@endif
                <span class="text-xs font-semibold text-slate-500">{{ $kindNames[$v->kind] ?? $v->kind }}</span> · {{ $v->title }}
                @if($v->status === 'scheduled')
                    <span class="text-xs font-semibold text-action-dark">· Naplánováno na {{ $v->scheduled_at ? $when($v->scheduled_at->timezone($tz)) : '?' }}</span>
                @else
                    <span class="text-xs text-slate-500">· {{ $label[$v->status] ?? $v->status }} {{ ($v->published_at ?? $v->decided_at ?? $v->updated_at)?->timezone($tz)->format('j. n. Y') }}</span>
                @endif
                @if($v->score !== null)<span class="text-xs text-slate-500">· zajímavost {{ $v->score }}</span>@endif
                @if($v->views !== null)<span class="text-xs text-slate-600">· {{ number_format($v->views, 0, ',', ' ') }} zhlédnutí · {{ $v->likes === null ? '–' : number_format($v->likes, 0, ',', ' ') }} lajků</span>@endif
                @if($v->error)<span class="block text-xs text-red-700">{{ $v->error }}</span>@endif
                @if($v->share)
                    <span class="block text-xs">
                        @foreach($v->share as $platform)
                            @php($sp = ($shares[$v->id] ?? collect())->firstWhere('platform', $platform))
                            @php($name = ['instagram' => 'Instagram', 'facebook_link' => 'FB odkaz'][$platform] ?? 'Facebook')
                            @if(! $sp)
                                <span class="text-slate-500">{{ $name }}: po zveřejnění</span>
                            @elseif($sp->status === 'posted')
                                <span class="text-ok">{{ $name }} ✓</span>
                            @elseif($sp->status === 'failed')
                                <span class="text-red-700">{{ $name }} ✗ {{ $sp->error }}</span>
                                <form method="post" action="{{ route('admin.youtube.share_retry', [$v, $platform]) }}" class="inline">@csrf<button class="underline">zkusit znovu</button></form>
                            @else
                                <span class="text-slate-500">{{ $name }}: odesílá se</span>
                            @endif
                            @if(! $loop->last) · @endif
                        @endforeach
                    </span>
                @endif
            </span>
            <span class="flex gap-2">
                @if($v->status === 'scheduled')
                    <form method="post" action="{{ route('admin.youtube.publish', $v) }}">@csrf<input type="hidden" name="title" value="{{ $v->title }}"><input type="hidden" name="description" value="{{ $v->description }}"><input type="hidden" name="when" value="now"><button class="btn-quiet text-sm">Zveřejnit hned</button></form>
                    <form method="post" action="{{ route('admin.youtube.reject', $v) }}" onsubmit="return confirm('Zamítnout video? Z YouTube se smaže.')">@csrf<button class="btn-quiet text-sm text-red-700">Zamítnout</button></form>
                @endif
                @if($v->status === 'published' && $v->watchUrl())<a href="{{ $v->watchUrl() }}" target="_blank" rel="noopener" class="btn-quiet text-sm">Přehrát</a>@endif
                @if($v->status === 'published')
                    <form method="post" action="{{ route('admin.youtube.replace', $v) }}" onsubmit="return confirm('Nahradit zveřejněné video novou verzí (např. s fotkou z foto-boxu)? Staré se z YouTube smaže i se zhlédnutími, nové půjde znovu ke schválení.')">@csrf<button class="btn-quiet text-sm">Nahradit novou verzí</button></form>
                    <form method="post" action="{{ route('admin.youtube.reject', $v) }}" onsubmit="return confirm('Stáhnout video? Z YouTube se smaže.')">@csrf<button class="btn-quiet text-sm text-red-700">Stáhnout</button></form>
                @endif
            </span>
        </div>
    @empty
        <p class="text-sm text-slate-500">Zatím nic.</p>
    @endforelse
</div>
@endsection
