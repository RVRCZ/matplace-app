{{-- One tool as a row of the catalogue (tools/index.blade.php, docs/AF.md §2, docs/AH.md §3): the photo 96 px on the left;
     the name, what is entered and two lines at most on the right. The whole row is one link; the search reads the same
     words as the big card. A row of "our picks" ($pick) stays out of the filter: it shows only while nothing is filtered. --}}
@php
    $keywords = __('tools.keywords.'.$key);
    $words = __('tools.'.$key.'.title').' '.__('tools.'.$key.'.hint').' '.($keywords === 'tools.keywords.'.$key ? '' : $keywords);
    $input = \App\Domain\Tools\ToolInput::of($key, $tool);
@endphp
<a href="{{ route($tool['route']) }}" data-key="{{ $key }}" data-title="{{ __('tools.'.$key.'.title') }}" @unless(! empty($pick)) data-cats="{{ implode(' ', $tool['categories']) }}" data-words="{{ \App\Domain\Farm\Palette::plain($words) }}" @endunless class="card flex items-center gap-3 overflow-hidden p-2 pr-3 transition hover:border-ink">
    <span class="block w-24 shrink-0 overflow-hidden rounded-lg bg-studio">@include('tools.picture', ['key' => $key, 'sizes' => '96px'])</span>
    <span class="min-w-0 flex-1">
        <span class="block font-semibold leading-snug text-ink">{{ __('tools.'.$key.'.title') }}</span>
        <span class="mt-0.5 block text-xs font-medium text-ink">{{ __('tools.input.label') }}: {{ __('tools.input.'.$input) }}</span>
        <span class="mt-0.5 line-clamp-2 text-sm text-muted">{{ __('tools.'.$key.'.hint') }}</span>
    </span>
    <x-icon name="chevron-right" class="h-4 w-4 text-muted" />
</a>
