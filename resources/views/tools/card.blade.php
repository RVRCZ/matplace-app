{{-- One tool: picture of the result, what it is, one clear action. The whole card is one link (one tab stop). --}}
<a href="{{ route($tool['route']) }}" data-cats="{{ implode(' ', $tool['categories']) }}" class="card group flex flex-col overflow-hidden hover:border-action">
    <span class="block overflow-hidden">@include('tools.picture', ['key' => $key])</span>
    <span class="flex flex-1 flex-col p-4">
        <span class="text-lg font-bold text-ink">{{ __('tools.'.$key.'.title') }}</span>
        <span class="mt-1 flex-1 text-sm text-muted">{{ __('tools.'.$key.'.hint') }}</span>
        <span class="mt-3 font-semibold text-action-dark group-hover:underline">{{ __('tools.'.$key.'.action') }} →</span>
    </span>
</a>
