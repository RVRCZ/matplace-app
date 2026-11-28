@extends('layouts.app', ['title' => 'Video '.$order->number.' · admin', 'noindex' => true])

@php
    $tz = config('youtube.publish_timezone', 'Europe/Prague');
    $when = fn ($t) => ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'][$t->dayOfWeek].' '.$t->format('j. n. H:i');
    $label = ['pending' => 'Nezveřejněno (na YouTube zatím není)', 'queued' => 'Schváleno, čeká na nahrání', 'uploading' => 'Nahrává se', 'uploaded' => 'Na YouTube jako soukromé, čeká na zveřejnění', 'scheduled' => 'Naplánováno', 'published' => 'Zveřejněno', 'rejected' => 'Zamítnuto', 'missing' => 'Na YouTube už není (smazané video)', 'withdrawn' => 'Zákazník odvolal souhlas', 'failed' => 'Nahrání selhalo'];
    $kindNames = ['design' => 'Návrh modelu', 'source' => 'Fotka od zákazníka (předloha)', 'sliced' => 'Po naslicování', 'growth' => 'Tisk roste (časosběr)', 'photo' => 'Hotový kus z fotoboxu', 'clip' => 'Video', 'image' => 'Obrázek'];
    $hints = [
        'design' => 'Barevný náhled modelu, jak ho uložil prohlížeč, jinak ho nakreslí server.',
        'source' => 'Osobní údaj (často obličej): zapněte jen se svolením zákazníka.',
        'sliced' => 'Díly v barvách cívek a podpěry, jak šly do tiskárny.',
        'growth' => $growth['master'] ? 'Z uloženého záznamu růstu.' : ($growth['frames'] >= 4 ? 'Ze snímků tisku ('.$growth['frames'].').' : 'Snímky tisku už nejsou, růst se ve videu neukáže.'),
        'photo' => $order->hasPhoto() ? 'Upravená fotka z fotoboxu.' : 'Zatím bez fotky: vyfoťte kus ve fotoboxu.',
    ];
    $studio = (array) config('youtube.cut.studio');
    $defaults = fn ($kind) => match ($kind) {
        'growth' => $studio['short']['growth'][0].'–'.$studio['short']['growth'][1].' s / 16:9 '.$studio['wide']['growth'][0].'–'.$studio['wide']['growth'][1].' s',
        'clip' => 'až '.$studio['short']['clip'].' s / 16:9 až '.$studio['wide']['clip'].' s',
        default => $studio['short']['still'].' s / 16:9 '.$studio['wide']['still'].' s',
    };
    $locked = $videos->filter(fn ($v) => $v->youtube_id)->keys();
    $allLocked = $videos->isNotEmpty() && $locked->count() === $videos->count();
    $stateText = ['waiting' => 'Změna uložena, videa se za chvíli začnou stavět…', 'building' => 'Videa se staví…', 'done' => 'Videa jsou postavená', 'none' => ''];
@endphp

@section('content')
@include('admin.farm.nav')

<p class="mt-4 text-sm"><a href="{{ route('admin.youtube.index') }}" class="underline">← Videa z tisků</a></p>

<header class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-xl font-extrabold">Video {{ $order->number }}</h1>
        <p class="text-xs text-slate-500">
            <a href="{{ route('admin.farm.orders.show', $order) }}" class="underline">zakázka</a>
            · {{ $order->color?->material?->label() }} {{ $order->color?->displayName() }}
            @if($order->user?->email) · {{ $order->user->email }}@endif
            · {{ $order->video_consent ? 'souhlas se zveřejněním '.$order->video_consent_at?->timezone($tz)->format('j. n. Y') : 'bez souhlasu se zveřejněním' }}
            · <a href="{{ route('admin.farm.orders.show', $order) }}#foto" class="font-semibold text-action-dark underline">Přidat fotku</a>
            · <a href="{{ route('admin.farm.photobox', ['order' => $order->token]) }}" class="underline">fotobox</a>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span data-studio-state data-state-url="{{ route('admin.youtube.studio.state', $order) }}" data-state="{{ $state['state'] }}"
              data-texts='@json($stateText)' class="font-semibold text-action-dark" role="status" aria-live="polite">{{ $stateText[$state['state']] ?? '' }}@if($state['state'] === 'done' && $state['at']) {{ \Illuminate\Support\Carbon::parse($state['at'])->timezone($tz)->format('H:i') }}@endif</span>
        @unless($allLocked)
            <form method="post" action="{{ route('admin.youtube.studio.rebuild', $order) }}">@csrf<button class="btn-quiet text-sm" title="Postaví Short i 16:9 znovu z médií a textů, jak jsou teď">Postavit znovu</button></form>
        @endunless
    </div>
</header>
@foreach($state['errors'] ?? [] as $kind => $error)
    <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $kind === 'wide' ? '16:9' : 'Short' }}: {{ $error }}</p>
@endforeach

{{-- the two videos built from the media below, each with its own texts and its own way to YouTube --}}
<div class="mt-4 grid gap-4 lg:grid-cols-2">
    @foreach(['short' => 'Short (na výšku 9:16)', 'wide' => 'Klasické video 16:9'] as $kind => $heading)
        @php($v = $videos->get($kind))
        @php($path = $kind === 'wide' ? $order->timelapse_wide_path : $order->timelapse_short_path)
        <section class="rounded-2xl border border-slate-200 bg-white p-4" aria-label="{{ $heading }}">
            <h2 class="font-bold">{{ $heading }}
                @if($kind === 'short')<span class="text-xs font-normal text-slate-500">· na YouTube se obvykle dívá víc lidí na Shorty; obě verze najednou si berou zhlédnutí</span>@endif
            </h2>
            @if($v && $path)
                <video data-studio-player="{{ $kind }}" src="{{ route('admin.youtube.file', $v) }}?v={{ @filemtime(Storage::disk(config('farm.disk'))->path($path)) }}" controls playsinline preload="metadata"
                       class="mt-2 w-full rounded-xl border border-slate-200 bg-black {{ $kind === 'short' ? 'mx-auto max-h-[70vh] max-w-xs' : '' }}"></video>
            @elseif(! $v)
                <p class="mt-2 text-sm text-slate-500">Bez souhlasu zákazníka se video nezveřejňuje.</p>
            @else
                <p class="mt-2 text-sm text-slate-500">Video zatím není postavené.</p>
            @endif

            @if($v)
                <p class="mt-2 text-sm font-semibold">{{ ($label[$v->status] ?? $v->status).($v->status === 'scheduled' && $v->scheduled_at ? ' na '.$when($v->scheduled_at->timezone($tz)) : '') }}
                    @if($v->watchUrl() && $v->status === 'published')<a href="{{ $v->watchUrl() }}" target="_blank" rel="noopener" class="ml-2 text-xs font-normal underline">přehrát na YouTube</a>@endif
                    @if($v->studioUrl())<a href="{{ $v->studioUrl() }}" target="_blank" rel="noopener" class="ml-2 text-xs font-normal underline">YouTube Studio</a>@endif
                    @if($v->views !== null)<span class="ml-2 text-xs font-normal text-slate-600">{{ number_format($v->views, 0, ',', ' ') }} zhlédnutí · {{ $v->likes === null ? '–' : number_format($v->likes, 0, ',', ' ') }} lajků</span>@endif
                </p>
                @if($v->error)<p class="mt-1 text-xs text-red-700">{{ $v->error }}</p>@endif
                @if($v->share && in_array($v->status, ['scheduled', 'published'], true))
                    <p class="mt-1 text-xs">
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
                    </p>
                @endif
                @if($v->youtube_id)<p class="mt-1 text-xs text-slate-500">Video je na YouTube: obraz je zamčený, změnit jde název a popis (uloží se i na YouTube).</p>@endif

                @if(! in_array($v->status, ['rejected', 'withdrawn'], true))
                    <form method="post" action="{{ route('admin.youtube.texts', $v) }}" class="mt-3 space-y-2">
                        @csrf
                        <label class="block text-xs font-semibold text-slate-600">Název (max. 100 znaků)
                            <input name="title" value="{{ old('title', $v->title) }}" required maxlength="100" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
                        <label class="block text-xs font-semibold text-slate-600">Úvodní text ve videu {{ $kind === 'short' ? '(pod logem na začátku)' : '(na úvodní kartě)' }}
                            <input name="intro" value="{{ old('intro', $v->meta['intro'] ?? '') }}" maxlength="120" placeholder="{{ $intros[$kind] }}" @disabled($v->youtube_id)
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal disabled:bg-slate-50">
                            <span class="font-normal text-slate-500">Prázdné = {{ $kind === 'short' ? 'název videu' : 'jméno modelu' }}. Po změně se video přestaví.</span></label>
                        <label class="block text-xs font-semibold text-slate-600">Popis
                            <textarea name="description" rows="7" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">{{ old('description', $v->description) }}</textarea></label>
                        @if(in_array($v->status, ['pending', 'uploaded', 'failed'], true))
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-700">
                                <label class="flex items-center gap-1"><input type="radio" name="when" value="slot" checked class="accent-action" data-publish-when> v nejbližším volném termínu: <strong>{{ $when($nextSlot) }}</strong></label>
                                <label class="flex items-center gap-1"><input type="radio" name="when" value="now" class="accent-action" data-publish-when> hned</label>
                            </div>
                            @if($metaAvailable && $kind === 'short')
                                @php($chosen = $v->share ?? ['facebook', 'facebook_link', 'instagram'])
                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-700">
                                    <input type="hidden" name="share_choice" value="1">
                                    <span class="text-slate-500">Ve stejný čas také:</span>
                                    <label class="flex items-center gap-1"><input type="checkbox" name="share[]" value="facebook" @checked(in_array('facebook', $chosen, true)) class="accent-action"> Facebook stránka (video)</label>
                                    <label class="flex items-center gap-1"><input type="checkbox" name="share[]" value="facebook_link" @checked(in_array('facebook_link', $chosen, true)) class="accent-action"> Facebook příspěvek s odkazem na matplace.com</label>
                                    <label class="flex items-center gap-1"><input type="checkbox" name="share[]" value="instagram" @checked(in_array('instagram', $chosen, true)) class="accent-action"> Instagram (Reel)</label>
                                </div>
                            @endif
                        @endif
                        <div class="flex flex-wrap gap-2">
                            <button class="btn-quiet text-sm">Uložit texty</button>
                            @if(in_array($v->status, ['pending', 'uploaded', 'failed'], true))
                                <button name="publish" value="slot" class="btn-primary text-sm" data-publish-button>Zveřejnit {{ $kind === 'short' ? 'Short' : '16:9' }}</button>
                            @endif
                        </div>
                    </form>
                @endif

                <div class="mt-2 flex flex-wrap gap-2">
                    @if($v->status === 'scheduled')
                        <form method="post" action="{{ route('admin.youtube.publish', $v) }}">@csrf<input type="hidden" name="title" value="{{ $v->title }}"><input type="hidden" name="description" value="{{ $v->description }}"><input type="hidden" name="when" value="now"><button class="btn-quiet text-sm">Zveřejnit hned</button></form>
                    @endif
                    @if(in_array($v->status, ['uploaded', 'scheduled', 'published'], true))
                        <form method="post" action="{{ route('admin.youtube.replace', $v) }}" onsubmit="return confirm('Smazat video z YouTube (i se zhlédnutími) a vrátit ho sem? Pak půjde měnit obraz a zveřejnit znovu.')">@csrf<button class="btn-quiet text-sm">Stáhnout a upravit</button></form>
                    @endif
                    @if(in_array($v->status, ['failed', 'uploading', 'queued'], true))
                        <form method="post" action="{{ route('admin.youtube.retry', $v) }}">@csrf<button class="btn-quiet text-sm">Nahrát znovu</button></form>
                    @endif
                    @if(! in_array($v->status, ['rejected', 'withdrawn'], true))
                        <form method="post" action="{{ route('admin.youtube.reject', $v) }}" onsubmit="return confirm('Zamítnout video? Pokud je na YouTube, smaže se.')">@csrf<button class="btn-quiet text-sm text-red-700">{{ $v->status === 'published' ? 'Stáhnout z YouTube' : 'Zamítnout' }}</button></form>
                    @endif
                </div>
            @endif
        </section>
    @endforeach
</div>

{{-- the media both videos are built from, in this order --}}
<section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4" aria-labelledby="media-heading">
    <h2 id="media-heading" class="font-bold">Co je ve videu</h2>
    <p class="text-xs text-slate-500">Pořadí měníte přetažením (nebo šipkami). Vypnutá položka se do videa nedostane. Každá změna videa za pár sekund přestaví{{ $locked->isNotEmpty() && ! $allLocked ? ' (jen to, které ještě není na YouTube)' : '' }}.</p>
    @if($allLocked)
        <p class="mt-2 rounded-lg bg-action-soft px-3 py-2 text-sm text-action-dark">Videa jsou na YouTube, jejich obraz je zamčený. Měnit obraz jde po „Stáhnout a upravit“.</p>
    @endif

    <ol class="mt-3 space-y-2" data-studio-media data-order-url="{{ route('admin.youtube.media.order', $order) }}" @if($allLocked) data-locked @endif>
        @foreach($items as $item)
            @php($fileUrl = route('admin.youtube.media.file', $item))
            <li class="flex flex-wrap items-center gap-3 rounded-xl border px-3 py-2 {{ $item->included ? 'border-slate-200 bg-white' : 'border-dashed border-slate-300 bg-slate-50 opacity-70' }}"
                data-media-id="{{ $item->id }}" data-update-url="{{ route('admin.youtube.media.update', $item) }}" data-delete-url="{{ route('admin.youtube.media.delete', $item) }}"
                @unless($allLocked) draggable="true" @endunless>
                <span class="flex flex-col" aria-hidden="{{ $allLocked ? 'true' : 'false' }}">
                    <button type="button" class="px-1 text-slate-500 hover:text-ink" data-move="-1" aria-label="Posunout výš" @disabled($allLocked)>▲</button>
                    <button type="button" class="px-1 text-slate-500 hover:text-ink" data-move="1" aria-label="Posunout níž" @disabled($allLocked)>▼</button>
                </span>
                <span class="cursor-grab select-none text-slate-400" aria-hidden="true">⠿</span>
                <span class="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                    @if($item->kind === 'clip')
                        <video src="{{ $fileUrl }}" preload="metadata" muted playsinline class="h-full w-full object-cover"></video>
                    @elseif($item->kind === 'growth')
                        @if($growth['master'])<video src="{{ $fileUrl }}" preload="metadata" muted playsinline class="h-full w-full object-cover"></video>@else<span class="flex h-full items-center justify-center text-2xl" aria-hidden="true">⏱</span>@endif
                    @elseif($item->kind === 'image' || ($pictures[$item->id] ?? false))
                        <a href="{{ $fileUrl }}" target="_blank" rel="noopener"><img src="{{ $fileUrl }}" alt="" class="h-full w-full object-cover" loading="lazy"></a>
                    @else
                        <span class="flex h-full items-center justify-center p-1 text-center text-[10px] leading-tight text-slate-500">{{ in_array($item->kind, ['design', 'sliced'], true) ? 'nakreslí se při stavbě' : 'není' }}</span>
                    @endif
                </span>
                <span class="min-w-0 flex-1 text-sm">
                    <span class="block font-semibold">{{ $kindNames[$item->kind] ?? $item->kind }}@if(! empty($item->meta['name'])) <span class="font-normal text-slate-500">· {{ $item->meta['name'] }}</span>@endif
                        @if(! empty($item->meta['from_clip'])) <span class="font-normal text-slate-500">· {{ __('youtube.clips.kinds.'.$item->meta['from_clip']) }}</span>@endif</span>
                    <span class="block text-xs text-slate-500">{{ $hints[$item->kind] ?? '' }}@if(isset($item->meta['duration'])) Délka {{ number_format($item->meta['duration'], 1, ',', '') }} s{{ empty($item->meta['has_audio']) ? ', bez zvuku' : '' }}.@endif</span>
                </span>
                <span class="flex flex-wrap items-center gap-3 text-xs">
                    <label class="flex items-center gap-1"><input type="checkbox" data-media-field="included" @checked($item->included) @disabled($allLocked) class="accent-action"> ve videu</label>
                    <label class="flex items-center gap-1">
                        <input type="number" data-media-field="seconds" value="{{ $item->seconds }}" min="0.5" max="30" step="0.5" placeholder="{{ $defaults($item->kind) }}" @disabled($allLocked)
                               class="w-20 rounded border border-slate-300 px-1 py-0.5" aria-label="Sekund ve videu"> s
                    </label>
                    @if($item->kind === 'clip')
                        <label class="flex items-center gap-1" title="Zvuk klipu je slyšet, hudba pod ním ztichne"><input type="checkbox" data-media-field="sound" @checked($item->sound) @disabled($allLocked || empty($item->meta['has_audio'])) class="accent-action"> vlastní zvuk</label>
                    @endif
                    @unless($item->isAuto() || $allLocked)
                        <button type="button" class="text-red-700 underline" data-media-delete>Smazat</button>
                    @endunless
                </span>
            </li>
        @endforeach
    </ol>

    @unless($allLocked)
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <form method="post" action="{{ route('admin.youtube.media.store', $order) }}" enctype="multipart/form-data" class="rounded-xl border border-dashed border-slate-300 p-3">
                @csrf
                <h3 class="text-sm font-bold">Přidat videa a fotky</h3>
                <p class="text-xs text-slate-500">Video MP4, MOV nebo WEBM, obrázek JPG, PNG, WEBP nebo HEIC, i několik najednou (každý soubor do 150 MB). Zařadí se před hotový kus.</p>
                <label class="mt-2 block text-xs"><span class="sr-only">Soubory</span>
                    <input type="file" name="media[]" multiple required accept="video/mp4,video/webm,video/quicktime,video/x-m4v,image/jpeg,image/png,image/webp,image/heic,image/heif" class="max-w-full text-xs"></label>
                <button class="btn-quiet mt-2 text-sm">Nahrát</button>
            </form>
            <div class="rounded-xl border border-dashed border-slate-300 p-3" data-clip-recorder data-kind="clip"
                 data-upload-url="{{ route('admin.youtube.media.store', $order) }}"
                 data-camera-label="{{ __('youtube.clips.camera') }}" data-microphone-label="{{ __('youtube.clips.microphone') }}"
                 data-default-camera="{{ __('youtube.clips.default_camera') }}" data-default-microphone="{{ __('youtube.clips.default_microphone') }}"
                 data-start-label="{{ __('youtube.clips.start') }}" data-again-label="{{ __('youtube.clips.again') }}" data-upload-label="{{ __('youtube.clips.save') }}"
                 data-rewind-label="{{ __('youtube.clips.rewind') }}" data-guide="Natáčím"
                 data-remaining-label="{{ __('youtube.clips.remaining') }}" data-ready-label="{{ __('youtube.clips.ready') }}"
                 data-error-label="{{ __('youtube.clips.camera_error') }}" data-upload-error-label="{{ __('youtube.clips.upload_error') }}">
                <h3 class="text-sm font-bold">Natočit klip kamerou</h3>
                <p class="text-xs text-slate-500">Až 10 sekund z kamery tohoto počítače nebo telefonu.</p>
                <button type="button" class="btn-quiet mt-2 text-sm" data-record>{{ __('youtube.clips.record') }}</button>
                <div data-recorder-panel hidden class="mt-2 space-y-2"></div>
            </div>
        </div>
    @endunless
</section>
@endsection
