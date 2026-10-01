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

    <p class="mt-8 text-sm text-muted">{{ __('site.tool.more') }} <a href="{{ route('tools') }}" class="font-semibold text-action-dark underline">{{ __('footer.tools') }}</a></p>
</section>
