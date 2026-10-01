{{--
    One card of a portfolio in a grid. $card = DesignerModel (images, modelFile loaded).
    $manage = true: the designer's own list (links to the edit page, shows the state and the reward).
--}}
@php
    $manage = $manage ?? false;
    $cover = $card->coverUrl();
    $canPrint = $card->model_file_id !== null && $card->file_status === \App\Models\DesignerModel::FILE_READY && $card->author_confirmed_at !== null;
    $own = $canPrint && \Illuminate\Support\Facades\Route::has('models.show');
    $href = $manage ? route('designer.models.edit', $card->id) : ($own ? $card->publicUrl() : $card->external_url);
    $state = \App\Http\Controllers\Designer\ProfileController::state($card);
@endphp
<article class="card flex flex-col overflow-hidden">
    @if($href)<a href="{{ $href }}" @if(! $manage && ! $own) rel="nofollow noopener" target="_blank" @endif class="block aspect-[4/3] overflow-hidden bg-slate-50" tabindex="-1" aria-hidden="true">@else<div class="aspect-[4/3] overflow-hidden bg-slate-50">@endif
        @if($cover)
            <img src="{{ $cover }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
        @else
            <span class="flex h-full w-full items-center justify-center text-4xl text-slate-300">◇</span>
        @endif
    @if($href)</a>@else</div>@endif
    <div class="flex flex-1 flex-col p-3">
        <h3 class="text-sm font-semibold leading-snug">
            @if($href)<a href="{{ $href }}" @if(! $manage && ! $own) rel="nofollow noopener" target="_blank" @endif class="hover:text-action-dark">{{ $card->title }}</a>@else{{ $card->title }}@endif
        </h3>
        <div class="mt-auto flex flex-wrap items-center gap-1.5 pt-2 text-xs">
            @if($manage)
                <span class="rounded-full px-2 py-0.5 font-semibold {{ ['printable' => 'bg-ok-soft text-ok', 'checking' => 'bg-action-soft text-action-dark', 'failed' => 'bg-red-50 text-red-800', 'hidden' => 'bg-slate-100 text-slate-600', 'link' => 'bg-slate-100 text-slate-700'][$state] }}">{{ __('designer.cards.state.'.$state) }}</span>
                @if($card->needsRemixConfirmation())<span class="rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-900">{{ __('designer.cards.remix') }}</span>@endif
                <span class="text-muted">{{ __('designer.cards.reward', ['amount' => \App\Support\Money::price((float) $card->royalty_czk)->format()]) }}</span>
                <span class="ml-auto text-muted" title="{{ __('designer.cards.views_orders') }}">{{ $card->view_count }} · {{ $card->order_count }}</span>
            @else
                @if($canPrint)<span class="rounded-full bg-ok-soft px-2 py-0.5 font-semibold text-ok">{{ __('designer.badge.printable') }}</span>@endif
                @if($canPrint && $card->download_allowed)<span class="rounded-full bg-action-soft px-2 py-0.5 font-semibold text-action-dark">{{ __('designer.badge.download') }}</span>@endif
                @if(! $own && $card->external_url)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-700">{{ __('designer.badge.at', ['source' => __('designer.source.'.$card->source)]) }} ↗</span>@endif
            @endif
        </div>
    </div>
</article>
