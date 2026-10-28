{{--
    A tool's page as content, under the form: what it is for, how it works, what it makes, questions people ask.
    Texts: lang/<locale>/tools_seo/<tool>.php, handed over by the layout as $seo (only in a language that has
    them). The layout prints the HowTo and FAQPage structured data into the head.
--}}
@php $examples = \App\Support\ToolSeo::examples($tool); @endphp
<section id="about-tool" class="mx-auto mt-12 max-w-3xl border-t border-line pt-8 text-slate-700" aria-labelledby="about-tool-title">
    <h2 id="about-tool-title" class="text-2xl font-extrabold text-ink">{{ $seo['h1'] }}</h2>
    @foreach($seo['intro'] as $paragraph)
        <p class="mt-3 leading-relaxed">{{ $paragraph }}</p>
    @endforeach

    {{-- a landing page (config/landing.php): results, before you order, who it is for --}}
    @include('tools._landing', ['tool' => $tool, 'part' => 'top'])

    {{-- the farm's photographed prints of this tool (docs/AN.md §15) --}}
    @include('tools._photos', ['tool' => $tool])

    @if($seo['steps'])
        <h3 class="mt-8 text-lg font-bold text-ink">{{ __('site.tool.how') }}</h3>
        <ol class="mt-3 space-y-3">
            @foreach($seo['steps'] as $i => $step)
                <li id="step-{{ $i + 1 }}" class="flex gap-3">
                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-action text-sm font-bold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                    <span><strong class="text-ink">{{ $step['name'] }}.</strong> {{ $step['text'] }}</span>
                </li>
            @endforeach
        </ol>
    @endif

    @if(! empty($seo['which']))
        {{-- which kind to choose: a short table (lang/<locale>/tools_seo/<tool>.php, 'which' => [['shape' => …, 'kind' => …], …]) --}}
        <h3 class="mt-8 text-lg font-bold text-ink">{{ __('tools.which.title') }}</h3>
        <table class="mt-3 w-full border-collapse text-sm">
            <thead><tr class="text-left text-xs uppercase tracking-wide text-muted"><th class="py-1 pr-3">{{ __('tools.which.shape') }}</th><th class="py-1">{{ __('tools.which.kind') }}</th></tr></thead>
            <tbody class="divide-y divide-line">
                @foreach($seo['which'] as $row)
                    <tr><td class="py-2 pr-3 align-top">{{ $row['shape'] }}</td><td class="py-2 align-top font-medium text-ink">{{ $row['kind'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($video = config('tools.'.$tool.'.video'))
        {{-- "how it looks": a video of the tool. Nothing of YouTube is loaded before the click (a poster of ours, then the
             no-cookie player); every tool gets it by 'video' => ['youtube' => id, 'poster' => path] in config/tools.php --}}
        @php $poster = asset($video['poster'] ?? 'img/tool-video/'.str_replace('_', '-', $tool).'.jpg'); @endphp
        <h3 class="mt-8 text-lg font-bold text-ink">{{ __('tools.video.title') }}</h3>
        <div id="tool-video" class="relative mt-3 aspect-video overflow-hidden rounded-2xl border border-line bg-black" data-youtube="{{ $video['youtube'] }}">
            <img src="{{ $poster }}" alt="{{ __('tools.video.title') }}" width="1280" height="720" loading="lazy" class="h-full w-full object-cover">
            <button type="button" id="tool-video-play" class="absolute inset-0 flex items-center justify-center" aria-label="{{ __('tools.video.play') }}">
                <span class="rounded-full bg-white/90 px-5 py-3 text-sm font-semibold text-ink shadow">▶ {{ __('tools.video.play') }}</span>
            </button>
        </div>
        <p class="mt-1 text-xs text-muted">{{ __('tools.video.note') }}</p>
        <script>
            document.getElementById('tool-video-play').addEventListener('click', function () {
                var box = document.getElementById('tool-video');
                var f = document.createElement('iframe');
                f.src = 'https://www.youtube-nocookie.com/embed/' + box.dataset.youtube + '?autoplay=1&rel=0';
                f.allow = 'autoplay; encrypted-media; picture-in-picture'; f.allowFullscreen = true; f.className = 'absolute inset-0 h-full w-full'; f.title = 'YouTube';
                box.replaceChildren(f);
            });
        </script>
        <x-jsonld :data="\App\Support\Schema::video($seo['h1'], $seo['description'], $poster, (string) $video['youtube'], (string) ($video['uploaded'] ?? ''), (int) ($video['seconds'] ?? 60))" />
    @endif

    @if($examples)
        <h3 class="mt-8 text-lg font-bold text-ink">{{ __('site.tool.examples') }}</h3>
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            @foreach($examples as $example)
                <figure class="card overflow-hidden">
                    <img src="{{ $example['url'] }}" alt="{{ $example['caption'] }}" width="640" height="480" loading="lazy" class="aspect-[4/3] w-full bg-slate-50 object-contain">
                    @if($example['caption'] !== '')<figcaption class="px-3 py-2 text-sm text-muted">{{ $example['caption'] }}</figcaption>@endif
                </figure>
            @endforeach
        </div>
    @endif

    @if($seo['faq'])
        <h3 class="mt-8 text-lg font-bold text-ink">{{ __('site.tool.faq') }}</h3>
        <div class="mt-3 divide-y divide-line rounded-2xl border border-line bg-card">
            @foreach($seo['faq'] as $item)
                <details class="group px-4 py-3">
                    <summary class="cursor-pointer list-none font-semibold text-ink marker:hidden">
                        <span class="mr-1 inline-block text-action transition group-open:rotate-90" aria-hidden="true">›</span>{{ $item['q'] }}
                    </summary>
                    <p class="mt-2 leading-relaxed">{{ $item['a'] }}</p>
                </details>
            @endforeach
        </div>
    @endif

    @include('tools._landing', ['tool' => $tool, 'part' => 'next'])

    <p class="mt-8 text-sm text-muted">{{ __('site.tool.more') }} <a href="{{ route('tools') }}" class="font-semibold text-action-dark underline">{{ __('footer.tools') }}</a></p>
</section>
