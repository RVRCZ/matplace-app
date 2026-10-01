{{-- Banners of the home page (/admin/content/banners): the active ones of this language, in their order. Nothing when there are none. --}}
@php $banners = \App\Models\Banner::for(app()->getLocale())->limit(4)->get(); @endphp
@if($banners->isNotEmpty())
    <section class="mx-auto mt-8 grid max-w-5xl gap-3 {{ $banners->count() > 1 ? 'sm:grid-cols-2' : '' }}" aria-label="{{ __('site.banners') }}">
        @foreach($banners as $banner)
            @if($banner->url)
                <a href="{{ $banner->url }}" class="block overflow-hidden rounded-2xl border border-line transition hover:border-action"><img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" loading="lazy" class="w-full object-cover"></a>
            @else
                <div class="overflow-hidden rounded-2xl border border-line"><img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" loading="lazy" class="w-full object-cover"></div>
            @endif
        @endforeach
    </section>
@endif
