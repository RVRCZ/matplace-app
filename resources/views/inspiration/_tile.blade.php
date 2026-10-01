{{-- A model of the inspiration catalogue in a grid. $model = CatalogModel --}}
@php
    // the page in this language when the model has a text in it, else its Czech page
    $href = localized_route('catalog.show', $model->slug, in_array(app()->getLocale(), $model->locales(), true) ? app()->getLocale() : 'cs');
    $thumb = $model->thumbUrl();
@endphp
<article class="card flex flex-col overflow-hidden">
    <a href="{{ $href }}" class="block aspect-[4/3] overflow-hidden bg-slate-50" tabindex="-1" aria-hidden="true">
        @if($thumb)
            <img src="{{ $thumb }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
        @else
            <span class="flex h-full w-full items-center justify-center text-4xl text-slate-300">◇</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col p-3">
        <h3 class="text-sm font-semibold leading-snug"><a href="{{ $href }}" class="hover:text-action-dark">{{ $model->title }}</a></h3>
        <p class="mt-auto truncate pt-1 text-xs text-muted">{{ __('models.source.'.(\Illuminate\Support\Facades\Lang::has('models.source.'.$model->source) ? $model->source : 'other')) }}@if($model->author_name) · {{ $model->author_name }}@endif</p>
    </div>
</article>
