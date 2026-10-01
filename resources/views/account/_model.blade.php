{{--
    One model of "My models": picture, name, where it came from, and what can be done with it.
    $m = ModelFile, $lock = why it cannot be deleted (null | order | card), $farm = the farm takes orders
--}}
@php
    $ready = $m->isReady();
    $picture = $m->previewUrl();
    $toolUrl = $m->toolUrl();
    $label = $m->origin === 'tool' && \Illuminate\Support\Facades\Lang::has('tools.'.$m->kind().'.title') ? __('tools.'.$m->kind().'.title') : __('user.models.kind.'.(in_array($m->kind(), ['repaired', 'mold'], true) ? $m->kind() : (in_array($m->origin, ['generated', 'tool'], true) ? $m->origin : 'upload')));
@endphp
<article class="card flex flex-col overflow-hidden" data-model="{{ $m->uuid }}">
    <a href="{{ $toolUrl ?? route('home', ['open' => $m->uuid]) }}" class="block aspect-[4/3] overflow-hidden bg-slate-50" tabindex="-1" aria-hidden="true">
        @if($picture)
            <img src="{{ $picture }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
        @elseif($ready && ($m->triangles === null || $m->triangles <= 400000))
            {{-- no picture yet: the browser draws the model once and stores the picture (resources/js/site/thumbs.ts) --}}
            <img alt="" class="h-full w-full object-cover" data-thumb-stl="{{ route('api.files.stl', $m) }}" data-thumb-store="{{ route('api.files.preview.store', $m) }}" data-thumb-kind="{{ $m->kind() }}"
                 src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 3'/%3E">
        @else
            <span class="flex h-full w-full items-center justify-center text-4xl text-slate-300">◇</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col p-3">
        <h3 class="truncate text-sm font-semibold" title="{{ $m->original_name }}">{{ $m->original_name }}</h3>
        <p class="text-xs text-muted">{{ $label }} · {{ $m->created_at->format('j. n. Y') }}</p>
        @unless($ready)<p class="mt-1 text-xs text-amber-800">{{ __('user.models.not_ready') }}</p>@endunless
        <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 pt-3 text-sm">
            @if($ready && $farm)<a href="{{ route('farm.start', ['file' => $m->uuid]) }}" class="font-semibold text-action-dark hover:underline">{{ __('user.models.order') }}</a>@endif
            @if($toolUrl)
                <a href="{{ $toolUrl }}" class="text-ink hover:underline">{{ __('user.models.open_tool') }}</a>
            @elseif($ready)
                <a href="{{ route('home', ['open' => $m->uuid]) }}" class="text-ink hover:underline">{{ __('user.models.open') }}</a>
            @endif
            @if($ready)<button type="button" class="text-ink hover:underline" data-pick-printer="{{ $m->uuid }}" data-pick-name="{{ $m->original_name }}" data-pick-stl="{{ route('api.files.stl', $m) }}">{{ __('user.models.download') }}</button>@endif
            @if($lock)
                <span class="cursor-not-allowed text-slate-400" title="{{ __('user.models.locked_'.$lock) }}" aria-disabled="true">{{ __('user.models.delete') }}</span>
            @else
                <form method="post" action="{{ route('account.models.delete', $m) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('user.models.delete_confirm', ['name' => $m->original_name]) }}">
                    @csrf
                    <button class="text-slate-500 hover:text-red-700 hover:underline">{{ __('user.models.delete') }}</button>
                </form>
            @endif
        </div>
        @if($lock)<p class="mt-1 text-xs text-slate-500">{{ __('user.models.locked_'.$lock) }}</p>@endif
    </div>
</article>
