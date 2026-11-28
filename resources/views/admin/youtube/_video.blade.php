{{-- one waiting cut of many prints or how-to video, with its own approval (a print's Short and 16:9: the video studio) --}}
@php($file = in_array($v->kind, ['weekly', 'howto', 'price', 'monthly'], true) ? $v->path : ($v->kind === 'wide' ? $v->order?->timelapse_wide_path : ($v->order?->timelapse_short_path ?: $v->order?->timelapse_path)))
@php($vertical = $v->kind === 'price' || ($v->kind === 'howto' && ($v->meta['variant'] ?? null) === 'short'))
<div>
    <h3 class="text-sm font-bold">{{ $kindNames[$v->kind] ?? $v->kind }}{{ $v->kind === 'howto' ? ($vertical ? ' · Short 9:16' : ' · 16:9') : '' }}
        @if($v->score !== null)
            <span class="float-right rounded-full px-2 py-0.5 text-xs font-bold {{ $v->score >= 60 ? 'bg-ok-soft text-ok' : ($v->score >= 35 ? 'bg-action-soft text-action-dark' : 'bg-slate-100 text-slate-500') }}"
                  title="Odhad zajímavosti z délky tisku, počtu vrstev a detailu (0–100)">zajímavost {{ $v->score }}</span>
        @endif
    </h3>
    @if($file)
        <video src="{{ route('admin.youtube.file', $v) }}?v={{ @filemtime(Storage::disk(config('farm.disk'))->path($file)) }}" controls playsinline preload="metadata"
               class="mt-2 w-full rounded-xl border border-slate-200 {{ $vertical ? 'mx-auto max-w-[16rem]' : ($v->kind === 'short' && $v->order?->timelapse_short_path ? 'mx-auto max-w-sm' : '') }}"></video>
    @endif
    <p class="mt-2 text-sm font-semibold">{{ $label[$v->status] ?? $v->status }}
        @if($v->studioUrl())<a href="{{ $v->studioUrl() }}" target="_blank" rel="noopener" class="ml-2 text-xs font-normal underline">otevřít v YouTube Studiu</a>@endif
    </p>
    @if($v->error)<p class="mt-1 text-xs text-red-700">{{ $v->error }}</p>@endif
    @if($v->order?->isTest())<p class="mt-1 text-xs font-semibold text-action-dark">{{ __('youtube.queue.test') }}</p>@endif
    @if($repeat = app(\App\Domain\YouTube\FarmVideos::class)->repeated($v))
        <p class="mt-1 text-xs font-semibold text-action-dark">{{ __('youtube.queue.repeated', ['date' => $repeat->published_at?->format('j. n. Y')]) }}</p>
    @endif
    @if($v->analytics)
        <p class="mt-1 text-xs text-slate-600">{{ __('youtube.analytics.summary', ['views' => $v->analytics['views'] ?? 0, 'retention' => $v->analytics['averageViewPercentage'] ?? 0, 'seconds' => $v->analytics['averageViewDuration'] ?? 0]) }}
            @if(isset($v->analytics['engagedViews'])) · {{ __('youtube.analytics.engaged', ['views' => $v->analytics['engagedViews']]) }}@endif</p>
    @endif
    @if($v->music)<p class="mt-1 text-xs text-slate-500">♪ {{ pathinfo($v->music, PATHINFO_FILENAME) }} (hudba je ve videu, i v tom zákaznickém)</p>@endif

    @if(in_array($v->status, ['pending', 'uploaded'], true))
        <form method="post" action="{{ route('admin.youtube.publish', $v) }}" class="mt-2 space-y-2">
            @csrf
            <label class="block text-xs font-semibold text-slate-600">Název (max. 100 znaků)
                <input name="title" value="{{ old('title', $v->title) }}" required maxlength="100" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal"></label>
            <label class="block text-xs font-semibold text-slate-600">Popis
                <textarea name="description" rows="{{ $v->kind === 'weekly' ? 14 : 7 }}" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">{{ old('description', $v->description) }}</textarea></label>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-700">
                <label class="flex items-center gap-1"><input type="radio" name="when" value="slot" checked class="accent-action"> zveřejnit v nejbližším volném termínu: <strong>{{ $when($nextSlot) }}</strong></label>
                <label class="flex items-center gap-1"><input type="radio" name="when" value="now" class="accent-action"> hned</label>
            </div>
            {{-- the page and Instagram take the Short only: one video per print there --}}
            @if($metaAvailable && ($v->kind === 'short' || $vertical))
                @php($chosen = $v->share ?? ['facebook', 'facebook_link', 'instagram'])
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-700">
                    <input type="hidden" name="share_choice" value="1">
                    <span class="text-slate-500">Ve stejný čas také:</span>
                    <label class="flex items-center gap-1"><input type="checkbox" name="share[]" value="facebook" @checked(in_array('facebook', $chosen, true)) class="accent-action"> Facebook stránka (video)</label>
                    <label class="flex items-center gap-1"><input type="checkbox" name="share[]" value="facebook_link" @checked(in_array('facebook_link', $chosen, true)) class="accent-action"> Facebook příspěvek s odkazem na matplace.com</label>
                    <label class="flex items-center gap-1"><input type="checkbox" name="share[]" value="instagram" @checked(in_array('instagram', $chosen, true)) class="accent-action"> Instagram (Reel)</label>
                </div>
            @endif
            <button class="btn-primary text-sm">{{ $v->status === 'pending' ? 'Nahrát na YouTube a zveřejnit' : 'Zveřejnit na YouTube' }}</button>
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
