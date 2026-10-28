{{--
    "Takhle to vytiskli" (docs/AN.md §15): up to four photographed prints the farm made with this tool
    (App\Domain\Tools\ToolPhotos): the print on its backdrop, the material and the date; never the customer or what
    they paid (the prices stay with the examples of calculator/_examples). Nothing is shown without a photo.
--}}
@php $photos = \App\Domain\Tools\ToolPhotos::for($tool); @endphp
@if($photos)
<section id="tool-photos" class="mt-10" aria-labelledby="tool-photos-title">
    <h3 id="tool-photos-title" class="text-lg font-bold text-ink">{{ __('tools.photos.title') }}</h3>
    <p class="mt-1 text-sm text-muted">{{ __('tools.photos.lead') }}</p>
    <ul class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach($photos as $p)
            @php $when = $p['at'] ? \Carbon\Carbon::parse($p['at'])->format(app()->getLocale() === 'cs' ? 'j. n. Y' : 'j M Y') : null; @endphp
            <li class="card overflow-hidden" data-tool-photo>
                <picture>
                    @if(! empty($p['webp']))<source type="image/webp" srcset="{{ $p['webp'] }}">@endif
                    <img src="{{ $p['url'] }}" alt="{{ trim(__('tools.photos.alt', ['tool' => __('tools.'.$tool.'.title'), 'material' => $p['material'] ?? '', 'date' => $when ?? ''])) }}" width="600" height="400" loading="lazy" decoding="async" class="aspect-[3/2] w-full bg-studio object-cover">
                </picture>
                @if($p['material'] || $when)<p class="px-3 py-2 text-xs text-muted">{{ implode(' · ', array_filter([$p['material'], $when])) }}</p>@endif
            </li>
        @endforeach
    </ul>
</section>
@endif
