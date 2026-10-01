@extends('layouts.app', ['title' => ($card->exists ? $card->title : __('designer.card.add')).' · matplace', 'noindex' => true])

@section('content')
@php
    $new = ! $card->exists;
    $check = (array) ($card->file_check ?? []);
    $problems = array_filter((array) ($check['rejected'] ?? ($card->file_status === 'failed' ? ($check['items'] ?? []) : [])), fn ($i) => ($i['level'] ?? '') === 'error');
    $summary = (array) ($card->slice_summary ?? []);
@endphp
<div class="mx-auto max-w-3xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="min-w-0 truncate text-2xl font-extrabold">{{ $new ? __('designer.card.add') : $card->title }}</h1>
        <a href="{{ route('designer.dashboard') }}" class="text-sm text-action-dark">← {{ __('designer.back') }}</a>
    </div>
    @include('partials.flash')

    {{-- texts, reward, download --}}
    <form method="post" action="{{ $new ? route('designer.models.store') : route('designer.models.update', $card->id) }}" class="card mt-4 space-y-4 p-5">
        @csrf
        <label class="lbl">{{ __('designer.card.name') }}<input name="title" required maxlength="200" value="{{ old('title', $card->title) }}" class="field"></label>
        @unless($new)
            <p class="text-sm text-muted">
                {{ __('designer.card.origin.'.$card->source) }}
                @if($card->external_url) · <a href="{{ $card->external_url }}" rel="nofollow noopener" target="_blank" class="underline">{{ __('designer.badge.at', ['source' => __('designer.source.'.$card->source)]) }} ↗</a>@endif
                @if($card->license_source) · {{ __('designer.card.license_there', ['license' => $card->license_source]) }}@endif
            </p>
        @endunless

        <fieldset class="space-y-3">
            <legend class="lbl">{{ __('designer.card.description') }}</legend>
            @foreach(\App\Support\Locales::SUPPORTED as $l)
                <label class="block text-sm text-muted">{{ __('site.languages.'.$l) }}@if($card->source_locale === $l) <span class="text-xs">({{ __('designer.card.original') }})</span>@endif
                    <textarea name="description[{{ $l }}]" rows="4" maxlength="8000" class="field text-ink">{{ old('description.'.$l, $card->description[$l] ?? '') }}</textarea>
                </label>
            @endforeach
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="translate" value="1" class="mt-1" @checked($new)> <span>{{ __('designer.card.translate') }}</span></label>
        </fieldset>

        @if(($categories ?? collect())->isNotEmpty())
            <label class="lbl">{{ __('models.category') }}
                <select name="catalog_category_id" class="field">
                    <option value="">—</option>
                    @foreach($categories as $top)
                        <option value="{{ $top->id }}" @selected((int) old('catalog_category_id', $card->catalog_category_id) === $top->id)>{{ $top->label() }}</option>
                        @foreach($top->children as $child)
                            <option value="{{ $child->id }}" @selected((int) old('catalog_category_id', $card->catalog_category_id) === $child->id)>&nbsp;&nbsp;{{ $child->label() }}</option>
                        @endforeach
                    @endforeach
                </select>
            </label>
        @endif

        <label class="lbl">{{ __('designer.royalty.label') }}
            <span class="mt-1 flex items-center gap-2 font-normal"><input name="royalty_czk" type="number" min="0" max="{{ \App\Models\DesignerProfile::MAX_ROYALTY_CZK }}" step="1" required value="{{ old('royalty_czk', (int) $card->royalty_czk) }}" class="field mt-0 w-32"> {{ \App\Support\Money::symbol('CZK') }}@if(\App\Support\Currency::current() !== 'CZK') <span class="text-xs text-muted">≈ @money((float) $card->royalty_czk)</span>@endif</span>
            <span class="hint mt-1 block font-normal">{{ __('designer.royalty.hint') }}</span>
        </label>

        @unless($new)
            <fieldset class="space-y-2 rounded-xl border border-line p-3">
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="download_allowed" value="1" class="mt-1" @checked(old('download_allowed', $card->download_allowed)) data-toggle="download-license"> <span><strong>{{ __('designer.download.allow') }}</strong><br><span class="text-muted">{{ __('designer.download.hint') }}</span></span></label>
                <label id="download-license" class="block text-sm text-muted {{ old('download_allowed', $card->download_allowed) ? '' : 'hidden' }}">{{ __('designer.download.license') }}
                    <select name="download_license" class="field text-ink">
                        <option value="">—</option>
                        @foreach(\App\Models\DesignerModel::DOWNLOAD_LICENSES as $license)
                            <option value="{{ $license }}" @selected(old('download_license', $card->download_license) === $license)>{{ __('designer.license.'.$license) }}</option>
                        @endforeach
                    </select>
                </label>
            </fieldset>

            @if($card->is_remix)
                <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
                    <p><strong>{{ __('designer.remix.title') }}</strong> {{ __('designer.remix.lead') }}
                        @if($card->remix_source_url)<a href="{{ $card->remix_source_url }}" rel="nofollow noopener" target="_blank" class="underline">{{ __('designer.remix.source') }} ↗</a>@endif
                    </p>
                    @if($card->remix_confirmed_at)
                        <p class="mt-1 font-semibold">✓ {{ __('designer.remix.confirmed', ['date' => $card->remix_confirmed_at->format('j. n. Y')]) }}</p>
                    @else
                        <label class="mt-2 flex items-start gap-2"><input type="checkbox" name="remix_confirmed" value="1" class="mt-1"> <span>{{ __('designer.remix.confirm') }}</span></label>
                    @endif
                </div>
            @endif

            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="visible" value="1" @checked(old('visible', $card->visible))> {{ __('designer.card.visible') }}</label>
        @endunless
        <button class="btn-primary w-full">{{ $new ? __('designer.card.create') : __('user.profile.save') }}</button>
    </form>

    @unless($new)
        {{-- the file the farm prints --}}
        <section id="file" class="card mt-4 p-5">
            <h2 class="font-bold">{{ __('designer.file.title') }}</h2>
            @if($card->file_status === 'checking')
                <p class="note-warn mt-2 text-sm" data-reload-in="4">{{ __('designer.file.checking') }}</p>
            @elseif($card->model_file_id)
                <div class="mt-2 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div>
                        <p class="font-semibold text-ok">✓ {{ __('designer.file.ready', ['name' => $card->modelFile?->original_name]) }}</p>
                        @if(! empty($summary['dims']))
                            <p class="text-muted">{{ round($summary['dims']['x']) }} × {{ round($summary['dims']['y']) }} × {{ round($summary['dims']['z']) }} mm@if(! empty($summary['grams'])) · {{ round($summary['grams']) }} g@endif @if(! empty($summary['minutes'])) · {{ intdiv((int) $summary['minutes'], 60) }} h {{ (int) $summary['minutes'] % 60 }} min ({{ $summary['material'] ?? '' }})@endif</p>
                        @endif
                    </div>
                    <form method="post" action="{{ route('designer.models.file.remove', $card->id) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('designer.file.remove_confirm') }}">@csrf<button class="text-slate-500 underline hover:text-red-700">{{ __('designer.file.remove') }}</button></form>
                </div>
            @else
                <p class="hint mt-1">{{ __('designer.file.none') }}</p>
            @endif
            @if($problems)
                <div class="note-error mt-3 text-sm">
                    <p class="font-semibold">{{ __($card->model_file_id ? 'designer.file.rejected_kept' : 'designer.file.rejected') }}</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach($problems as $item)
                            <li>{{ __('check.'.$item['code']) !== 'check.'.$item['code'] ? __('check.'.$item['code'], $item['params'] ?? []) : __('designer.file.error.unreadable') }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($card->needsRemixConfirmation())
                <p class="note-warn mt-3 text-sm">{{ __('designer.file.error.remix') }}</p>
            @elseif($card->file_status !== 'checking')
                <form method="post" action="{{ route('designer.models.file', $card->id) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                    @csrf
                    <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-line bg-slate-50 px-4 py-8 text-center text-sm hover:border-action" data-dropzone>
                        <span class="font-semibold" data-dropzone-label data-empty="{{ __('designer.file.drop') }}">{{ __('designer.file.drop') }}</span>
                        <span class="text-muted">{{ __('designer.file.formats', ['max' => \App\Domain\Designer\CardFiles::MAX_FILE_MB]) }}</span>
                        <input type="file" name="file" required accept=".stl,.3mf,.zip" class="sr-only">
                    </label>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="author" value="1" required class="mt-1"> <span>{{ __('designer.file.author') }}</span></label>
                    <button class="btn-secondary">{{ $card->model_file_id ? __('designer.file.replace') : __('designer.file.upload') }}</button>
                </form>
            @endif
        </section>

        {{-- pictures --}}
        <section id="images" class="card mt-4 p-5">
            <h2 class="font-bold">{{ __('designer.images.title') }}</h2>
            @if($card->images->isNotEmpty())
                <ul class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach($card->images as $image)
                        <li class="overflow-hidden rounded-xl border {{ $image->is_cover ? 'border-action' : 'border-line' }}">
                            <img src="{{ $image->url(true) }}" alt="" loading="lazy" class="aspect-[4/3] w-full object-cover">
                            <div class="flex items-center justify-between gap-2 px-2 py-1.5 text-xs">
                                @if($image->is_cover)<span class="font-semibold text-action-dark">{{ __('designer.images.cover') }}</span>
                                @else<form method="post" action="{{ route('designer.models.images.cover', [$card->id, $image->id]) }}">@csrf<button class="underline">{{ __('designer.images.make_cover') }}</button></form>@endif
                                <form method="post" action="{{ route('designer.models.images.delete', [$card->id, $image->id]) }}">@csrf<button class="text-slate-500 underline hover:text-red-700">{{ __('user.models.delete') }}</button></form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if($card->images->count() < \App\Models\DesignerModel::MAX_IMAGES)
                <form method="post" action="{{ route('designer.models.images.add', $card->id) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                    @csrf
                    <input type="file" name="images[]" accept="image/*" multiple required class="min-w-0 flex-1">
                    <button class="btn-quiet min-h-0 px-3 py-2">{{ __('designer.images.add') }}</button>
                </form>
                <p class="hint mt-1">{{ __('designer.images.hint', ['max' => \App\Models\DesignerModel::MAX_IMAGES]) }}</p>
            @endif
        </section>

        {{-- the link to this model --}}
        <section class="card mt-4 p-5">
            <h2 class="font-bold">{{ __('designer.share.title_model') }}</h2>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <input readonly value="{{ $share['url'] }}" class="field mt-0 min-w-0 flex-1 text-sm" onfocus="this.select()" aria-label="{{ __('designer.share.title_model') }}">
                <button type="button" class="btn-quiet min-h-0 px-3 py-2 text-sm" data-copy="{{ $share['url'] }}" data-copied="{{ __('designer.share.copied') }}">{{ __('designer.share.copy') }}</button>
            </div>
            <details class="mt-3 text-sm">
                <summary class="cursor-pointer text-action-dark">{{ __('designer.share.text_title') }}</summary>
                <div class="mt-2 space-y-2">
                    @foreach($share['texts'] as $l => $text)
                        <div class="flex items-start gap-2">
                            <span class="mt-2 w-7 shrink-0 text-xs font-bold uppercase text-muted">{{ $l }}</span>
                            <textarea readonly rows="2" class="field mt-0 flex-1 text-sm" onfocus="this.select()">{{ $text }}</textarea>
                            <button type="button" class="btn-quiet min-h-0 px-3 py-2 text-sm" data-copy="{{ $text }}" data-copied="{{ __('designer.share.copied') }}">{{ __('designer.share.copy') }}</button>
                        </div>
                    @endforeach
                </div>
            </details>
        </section>

        <form method="post" action="{{ route('designer.models.delete', $card->id) }}" class="mt-6" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('designer.card.delete_confirm') }}">
            @csrf
            <button class="text-sm text-slate-500 underline hover:text-red-700">{{ __('designer.card.delete') }}</button>
        </form>
    @endunless
</div>
@endsection
