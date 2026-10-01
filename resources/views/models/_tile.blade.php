{{-- A printable model in the catalogue grid: picture, name, designer, what it costs from. $card = DesignerModel --}}
@php
    $cover = $card->coverUrl();
    $from = app(\App\Domain\Catalog\ModelPricing::class)->quote($card);
@endphp
<article class="card flex flex-col overflow-hidden">
    <a href="{{ $card->publicUrl() }}" class="block aspect-[4/3] overflow-hidden bg-slate-50" tabindex="-1" aria-hidden="true">
        @if($cover)
            <img src="{{ $cover }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
        @else
            <span class="flex h-full w-full items-center justify-center text-4xl text-slate-300">◇</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col p-3">
        <h3 class="text-sm font-semibold leading-snug"><a href="{{ $card->publicUrl() }}" class="hover:text-action-dark">{{ $card->title }}</a></h3>
        <p class="truncate text-xs text-muted">{{ $card->profile->display_name }}</p>
        <div class="mt-auto flex flex-wrap items-center justify-between gap-1.5 pt-2 text-xs">
            @if($from['available'])<span class="text-sm font-bold text-ink">{{ __('models.from', ['price' => $from['total_text']]) }}</span>@endif
            @if($card->download_allowed)<span class="rounded-full bg-action-soft px-2 py-0.5 font-semibold text-action-dark">{{ __('designer.badge.download') }}</span>@endif
        </div>
    </div>
</article>
