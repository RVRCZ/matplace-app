{{--
    Trust on the sales path, only from things that are true (docs/AN.md §5, §12.4): the farm's numbers (App\Domain\Farm\PublicStats,
    read only while the class exists; the sentences follow the count, trans_choice), the latest photographed prints
    (PublicStats::latestPhotos, session 2), one published video from the farm (loaded from YouTube's no-cookie player after a
    click, nothing before; its title from the database only on the Czech page, the other languages get a neutral caption),
    and prices of real calculations (config/site.php example_items). No reviews, none exist. Under the result column, so the
    price and the button stay first on a phone.
--}}
@php
    $stats = class_exists(\App\Domain\Farm\PublicStats::class) ? (array) \App\Domain\Farm\PublicStats::summary() : [];
    $printed = (int) ($stats['printed'] ?? 0);
    $recent = (int) ($stats['last_30_days_printed'] ?? 0);
    // "within N days": the median rounded up, so half a day reads "within a day", never "1 days"
    $days = isset($stats['handed_over_days_median']) && $stats['handed_over_days_median'] !== null ? (int) ceil((float) $stats['handed_over_days_median']) : null;
    $videos = (int) ($stats['videos_published'] ?? 0);
    $photos = class_exists(\App\Domain\Farm\PublicStats::class) && method_exists(\App\Domain\Farm\PublicStats::class, 'latestPhotos') ? (array) \App\Domain\Farm\PublicStats::latestPhotos(4) : [];
    $video = \App\Models\FarmVideo::query()->with('order.modelFile')->where('status', \App\Models\FarmVideo::STATUS_PUBLISHED)->whereNotNull('youtube_id')->latest('published_at')->first(['id', 'youtube_id', 'title', 'farm_order_id']);
    // the tool a print came from, by its title in the page's language (null for a customer's own file)
    $toolTitle = function (?string $key): ?string {
        return $key !== null && $key !== '' && is_array(config('tools.'.$key)) ? __('tools.'.$key.'.title') : null;
    };
    $videoCaption = null;
    if ($video && app()->getLocale() !== 'cs') {
        $file = $video->order?->modelFile;
        $kind = $file ? ($file->origin === 'tool' ? $file->kind() : ($file->origin === 'generated' ? 'figure' : null)) : null;
        $videoCaption = ($t = $toolTitle($kind)) ? __('calc.trust.video_tool', ['tool' => $t]) : __('calc.trust.video_generic');
    }
    $currency = \App\Support\Currency::current();
    $examples = collect(config('site.example_items', []))->filter(fn ($i) => \App\Domain\Tools\ToolVisibility::isPublic($i['tool']) && isset($i['price'][$currency]))->unique('tool')->take(3);
@endphp
@if(\App\Domain\Farm\FarmGate::offered(auth()->user()) && ($printed > 0 || $photos || $video || $examples->isNotEmpty()))
<section id="calc-trust" class="rounded-2xl border border-slate-200 bg-white p-4" aria-labelledby="calc-trust-h">
    <h2 id="calc-trust-h" class="font-bold text-ink">{{ __('calc.trust.title') }}</h2>
    @if($printed > 0)
        <ul class="mt-2 space-y-1 text-sm text-slate-700" data-trust-numbers>
            <li>{{ trans_choice('calc.trust.printed', $printed, ['n' => $printed]) }}@if($recent > 0) {{ __('calc.trust.recent', ['n' => $recent]) }}@endif</li>
            @if($days !== null && $days > 0)<li>{{ trans_choice('calc.trust.days', $days, ['d' => $days]) }}</li>@endif
            @if($videos > 0)<li>{{ trans_choice('calc.trust.videos', $videos, ['n' => $videos]) }}</li>@endif
        </ul>
    @endif
    @if($photos)
        <h3 class="mt-4 text-sm font-semibold text-ink">{{ __('calc.trust.photos') }}</h3>
        <ul class="mt-2 grid grid-cols-4 gap-2" data-trust-photos>
            @foreach($photos as $p)
                @php $what = $toolTitle($p['tool'] ?? null) ?? __('calc.trust.photo_own'); @endphp
                <li>
                    <picture>
                        @if(! empty($p['webp']))<source type="image/webp" srcset="{{ $p['webp'] }}">@endif
                        <img src="{{ $p['url'] }}" alt="{{ __('calc.trust.photo_alt', ['what' => $what]) }}" width="300" height="300" loading="lazy" decoding="async" class="aspect-square h-auto w-full rounded-lg bg-slate-100 object-cover">
                    </picture>
                </li>
            @endforeach
        </ul>
    @endif
    @if($video)
        <h3 class="mt-4 text-sm font-semibold text-ink">{{ __('calc.trust.video') }}</h3>
        <div id="trust-video" class="relative mt-2 aspect-video overflow-hidden rounded-xl bg-ink" data-youtube="{{ $video->youtube_id }}">
            <button type="button" id="trust-video-play" class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-4 text-white" aria-label="{{ __('tools.video.play') }}">
                <span class="rounded-full bg-white/90 px-5 py-3 text-sm font-semibold text-ink shadow">▶ {{ __('tools.video.play') }}</span>
                <span class="text-center text-sm">{{ $videoCaption ?? $video->title }}</span>
            </button>
        </div>
        <p class="mt-1 text-xs text-slate-500">{{ __('tools.video.note') }}</p>
        <script>
            document.getElementById('trust-video-play').addEventListener('click', function () {
                var box = document.getElementById('trust-video');
                var f = document.createElement('iframe');
                f.src = 'https://www.youtube-nocookie.com/embed/' + box.dataset.youtube + '?autoplay=1&rel=0';
                f.allow = 'autoplay; encrypted-media; picture-in-picture'; f.allowFullscreen = true; f.className = 'absolute inset-0 h-full w-full'; f.title = 'YouTube';
                box.replaceChildren(f);
            });
        </script>
    @endif
    @if($examples->isNotEmpty())
        <h3 class="mt-4 text-sm font-semibold text-ink">{{ __('calc.trust.examples') }}</h3>
        <ul class="mt-2 divide-y divide-slate-100 text-sm" data-trust-examples>
            @foreach($examples as $i)
                <li class="flex items-center justify-between gap-3 py-2">
                    <a href="{{ url('/c/'.$i['calc']) }}" class="min-w-0 text-ink underline-offset-4 hover:underline">
                        <span class="block font-medium">{{ __('site.examples.item.'.$i['tool'], ['mm' => $i['mm'], 'size' => $i['size'] ?? '']) }}</span>
                        <span class="block text-xs text-slate-500">{{ __('site.examples.meta', ['material' => $i['material'], 'g' => $i['grams'], 'time' => $i['minutes'] >= 60 ? intdiv($i['minutes'], 60).' h '.($i['minutes'] % 60).' min' : $i['minutes'].' min']) }}</span>
                    </a>
                    <span class="shrink-0 font-semibold text-ink">{{ \App\Support\Money::of($i['price'][$currency], $currency)->format() }}</span>
                </li>
            @endforeach
        </ul>
        <p class="mt-1 text-xs text-slate-500">{{ __('calc.trust.examples_note') }}</p>
    @endif
</section>
@endif
