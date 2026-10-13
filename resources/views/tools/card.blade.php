{{-- One tool: picture of the result, what it is, one clear action. The whole card is one link (one tab stop). --}}
@php
    $keywords = __('tools.keywords.'.$key);
    // what the search of the page looks through: the name, the sentence under it and the tool's own keywords
    $words = __('tools.'.$key.'.title').' '.__('tools.'.$key.'.hint').' '.($keywords === 'tools.keywords.'.$key ? '' : $keywords);
@endphp
<a href="{{ route($tool['route']) }}" data-cats="{{ implode(' ', $tool['categories']) }}" data-words="{{ \App\Domain\Farm\Palette::plain($words) }}" class="card group flex flex-col overflow-hidden transition hover:border-ink">
    <span class="relative block overflow-hidden bg-studio">
        @include('tools.picture', ['key' => $key])
        @if(! empty($badge))
            {{-- "printed most often": the home page marks what the farm prints most --}}
            <span class="absolute right-2 top-2 inline-flex items-center rounded-full bg-action px-2 py-0.5 text-xs font-bold text-white shadow-sm">{{ $badge }}</span>
        @endif
        {{-- the "verified by printing" badge left the cards on 13 Oct 2026 (Roman); the date stays in config/tools.php --}}
    </span>
    <span class="flex flex-1 flex-col p-4">
        <span class="text-lg font-semibold text-ink">{{ __('tools.'.$key.'.title') }}</span>
        <span class="mt-1 flex-1 text-sm text-muted">{{ __('tools.'.$key.'.hint') }}</span>
        <span class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-ink group-hover:underline">{{ __('tools.'.$key.'.action') }}<x-icon name="arrow-right" class="h-4 w-4" /></span>
    </span>
</a>
