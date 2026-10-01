{{-- The designer profile on the account page: switch it on, or open it with three numbers of what it brings. --}}
@php $designer = \App\Models\DesignerProfile::where('user_id', $user->id)->first(); @endphp
<section id="designer" class="card mt-5 flex flex-wrap items-center justify-between gap-4 p-4">
    @if($designer && $user->isDesigner())
        @php $headline = app(\App\Domain\Designer\DesignerStats::class)->headline($designer); @endphp
        <div class="min-w-0">
            <h2 class="font-bold">{{ __('designer.card_title') }}</h2>
            <p class="truncate text-sm text-muted">{{ $designer->display_name }} · {{ $designer->visible ? __('designer.state.public') : __('designer.state.private') }}</p>
        </div>
        <dl class="flex gap-6 text-center text-sm">
            <div><dd class="text-lg font-bold">{{ $headline['visits'] }}</dd><dt class="text-xs text-muted">{{ __('designer.stats.visits_30') }}</dt></div>
            <div><dd class="text-lg font-bold">{{ $headline['prints'] }}</dd><dt class="text-xs text-muted">{{ __('designer.stats.prints') }}</dt></div>
            <div><dd class="text-lg font-bold">{{ number_format($headline['rewards'], 0, ',', ' ') }} Kč</dd><dt class="text-xs text-muted">{{ __('designer.stats.rewards') }}</dt></div>
        </dl>
        <a href="{{ route('designer.dashboard') }}" class="btn-secondary min-h-0 px-4 py-2 text-sm">{{ __('designer.open') }}</a>
    @else
        <div class="min-w-0 max-w-xl">
            <h2 class="font-bold">{{ __('designer.card_title') }}</h2>
            <p class="text-sm text-muted">{{ __('designer.pitch') }}</p>
        </div>
        <form method="post" action="{{ route('designer.enable') }}">
            @csrf
            <button class="btn-secondary min-h-0 px-4 py-2 text-sm" @disabled(! $user->hasVerifiedEmail()) @if(! $user->hasVerifiedEmail()) title="{{ __('user.verify.needed') }}" @endif>{{ __('designer.enable') }}</button>
        </form>
    @endif
</section>
