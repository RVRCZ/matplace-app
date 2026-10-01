@extends('layouts.app', ['title' => __('designer.import.from', ['source' => __('designer.source.'.$source)]).' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-4xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('designer.import.from', ['source' => __('designer.source.'.$source)]) }}</h1>
        <a href="{{ route('designer.dashboard') }}" class="text-sm text-action-dark">← {{ __('designer.back') }}</a>
    </div>
    <p class="mt-2 text-sm text-slate-700">{{ __('designer.import.lead') }}</p>
    @include('partials.flash')
    @if($listError)<p class="note-warn mt-3 text-sm">{{ __('designer.import.list_failed') }} ({{ __('designer.import.error.'.$listError) }})</p>@endif
    @if($room === 0)<p class="note-warn mt-3 text-sm">{{ __('designer.card.limit', ['max' => \App\Models\DesignerProfile::MAX_MODELS]) }}</p>@endif

    <form method="post" action="{{ route('designer.import.start', $source) }}" class="mt-4 space-y-4" data-import-form>
        @csrf
        @if($candidates !== null)
            <section class="card p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold">{{ __('designer.import.yours', ['n' => count($candidates)]) }}</h2>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-muted"><span data-import-count>0</span> / {{ $max }}</span>
                        <button type="button" class="text-action-dark underline" data-import-all data-max="{{ $max }}">{{ __('designer.import.select_new') }}</button>
                        <button type="button" class="text-slate-600 underline" data-import-none>{{ __('designer.import.select_none') }}</button>
                    </div>
                </div>
                @if(! $candidates)
                    <p class="mt-3 text-sm text-slate-500">{{ __('designer.import.no_models') }}</p>
                @else
                    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach($candidates as $c)
                            <li>
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line p-2 {{ $c['imported'] ? 'opacity-60' : 'hover:border-action' }}">
                                    <input type="checkbox" name="ids[]" value="{{ $c['id'] }}" @disabled($c['imported']) @checked(! $c['imported'] && in_array($c['id'], (array) old('ids', []), true)) data-import-item>
                                    @if($c['image'])<img src="{{ $c['image'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="h-12 w-16 shrink-0 rounded-lg object-cover">@endif
                                    <span class="min-w-0 text-sm">
                                        <span class="block truncate font-medium">{{ $c['title'] }}</span>
                                        <span class="flex gap-1.5 text-xs">
                                            @if($c['imported'])<span class="text-ok">{{ __('designer.import.already') }}</span>@endif
                                            @if($c['is_remix'])<span class="text-amber-800">{{ __('designer.cards.remix') }}</span>@endif
                                        </span>
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        <section class="card p-4">
            <label class="lbl">{{ $candidates === null ? __('designer.import.links') : __('designer.import.links_more') }}
                <textarea name="links" rows="{{ $candidates === null ? 8 : 3 }}" class="field font-mono text-sm" placeholder="{{ $source === 'printables' ? 'https://www.printables.com/model/123456-nazev' : 'https://makerworld.com/en/models/123456-nazev' }}">{{ old('links') }}</textarea>
            </label>
            <p class="hint mt-1">{{ __('designer.import.links_hint', ['source' => __('designer.source.'.$source), 'max' => $max]) }}</p>
        </section>

        <div class="card space-y-3 p-4">
            <p class="text-sm text-slate-700">{{ __('designer.import.what') }}</p>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="author" value="1" required class="mt-1"> <span>{{ __('designer.import.author') }}</span></label>
            <button class="btn-primary" @disabled($room === 0)>{{ __('designer.import.start') }}</button>
        </div>
    </form>
</div>
@endsection
