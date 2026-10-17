{{-- One tool as a row of the catalogue on a phone (tools/index.blade.php, docs/AF.md §2): the photo 96 px on the left,
     the name and two lines at most on the right. The whole row is one link; the search reads the same words as the big card. --}}
@php
    $keywords = __('tools.keywords.'.$key);
    $words = __('tools.'.$key.'.title').' '.__('tools.'.$key.'.hint').' '.($keywords === 'tools.keywords.'.$key ? '' : $keywords);
@endphp
<a href="{{ route($tool['route']) }}" data-key="{{ $key }}" data-cats="{{ implode(' ', $tool['categories']) }}" data-words="{{ \App\Domain\Farm\Palette::plain($words) }}" class="card flex items-center gap-3 overflow-hidden p-2 pr-3 transition hover:border-ink">
    <span class="block w-24 shrink-0 overflow-hidden rounded-lg bg-studio">@include('tools.picture', ['key' => $key, 'sizes' => '96px'])</span>
    <span class="min-w-0 flex-1">
        <span class="block font-semibold leading-snug text-ink">{{ __('tools.'.$key.'.title') }}</span>
        <span class="mt-0.5 line-clamp-2 text-sm text-muted">{{ __('tools.'.$key.'.hint') }}</span>
    </span>
    <x-icon name="chevron-right" class="h-4 w-4 text-muted" />
</a>
