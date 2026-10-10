@extends('layouts.app', ['title' => __('farm.title').' · matplace', 'noindex' => true])

@php
    $printer = \App\Models\FarmPrinter::where('enabled', true)->orderBy('id')->first();
    $startCfg = [
        'upload' => route('api.uploads.store'), 'files' => url('/api/files'), 'maxMb' => (int) $settings['max_upload_mb'],
        'text' => [
            'uploading' => __('farm.start.uploading'), 'processing' => __('farm.start.processing'), 'failed' => __('farm.start.upload_failed'),
            'badFormat' => __('farm.start.bad_format'), 'tooBig' => __('farm.start.too_big', ['max' => $settings['max_upload_mb']]),
        ],
    ];
@endphp

@push('head')
<script>window.MP_FARM_START = {{ \Illuminate\Support\Js::from($startCfg) }};</script>
@endpush

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-extrabold">{{ __('farm.title') }}</h1>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('account.orders') }}" class="text-action-dark underline">{{ __('farm.my_orders') }}</a>
            <a href="{{ route('account.credit') }}" class="rounded-full border border-line bg-white px-3 py-1 font-semibold">{{ __('farm.credit_balance') }}: @money($balance)</a>
        </div>
    </div>
    <p class="mt-2 text-slate-600">{{ __('farm.lead') }}</p>
    @include('partials.flash')
    @include('partials.verify_banner')

    <ol class="mt-4 grid gap-2 text-sm text-slate-700 sm:grid-cols-2">
        @foreach(__('farm.start.steps') as $i => $step)
            <li class="flex gap-2 rounded-xl border border-slate-200 bg-white p-3"><span class="font-bold text-action-dark">{{ $i + 1 }}</span><span>{{ $step }}</span></li>
        @endforeach
    </ol>

    <form id="farm-start" method="post" action="{{ route('farm.orders.store') }}" class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
        @csrf
        <input type="hidden" name="file" id="farm-file" value="{{ $file?->uuid }}">
        @if($card)<input type="hidden" name="designer_model" value="{{ $card->id }}">@endif
        @if($inspiration)<input type="hidden" name="catalog_model" value="{{ $inspiration->id }}">@endif

        <div class="text-sm font-semibold text-slate-700">{{ __('farm.start.model') }}</div>
        @if($card)
            {{-- a designer's model: its name and author instead of a file name; the reward is part of the price --}}
            <p class="mt-1 text-sm font-semibold">{{ __('models.farm.card', ['title' => $card->title, 'name' => $card->profile->display_name]) }}</p>
            @if($card->royalty_czk > 0 && $card->profile->user_id !== auth()->id())<p class="text-xs text-slate-500">{{ __('models.farm.reward', ['amount' => \App\Support\Money::price((float) $card->royalty_czk)->format()]) }}</p>@endif
            @unless($previewUrl)
                @if($card->coverUrl(false))<img src="{{ $card->coverUrl(false) }}" alt="" class="mt-2 max-h-64 w-full rounded-xl border border-slate-200 bg-slate-50 object-contain">@endif
                <p class="mt-1 text-xs text-slate-500">{{ __('models.farm.no_preview') }}</p>
            @endunless
        @elseif($file)
            <p class="mt-1 text-sm">{{ $file->original_name }}</p>
        @else
            {{-- a real drop zone: the bare file input looks like a line of text --}}
            <label id="farm-dropzone" for="farm-upload" class="mt-2 flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center transition hover:border-action hover:bg-action-soft">
                <x-icon name="folder-open" class="h-8 w-8 text-muted" />
                <span class="btn-primary pointer-events-none text-sm">{{ __('farm.start.upload') }}</span>
                <span class="text-xs text-slate-500">{{ __('farm.start.drop_hint') }}</span>
            </label>
            <input id="farm-upload" type="file" accept=".stl" class="sr-only" aria-label="{{ __('farm.start.upload') }}">
            <p class="mt-1 text-xs text-slate-500">{{ __('farm.start.upload_hint', ['max' => $settings['max_upload_mb'], 'x' => (int) ($bed?->x ?? 250), 'y' => (int) ($bed?->y ?? 250), 'z' => (int) ($bed?->z ?? 250)]) }}</p>
            <p id="farm-upload-status" class="mt-1 hidden text-sm text-slate-600" role="status"></p>
        @endif
        <div id="farm-preview-box" class="mt-3 {{ $file && ($previewUrl || ! $card) ? '' : 'hidden' }} overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
            <canvas id="farm-preview" class="block h-64 w-full touch-none" data-model="{{ $previewUrl ?? '' }}" data-change="{{ $twoColor ?? '' }}" data-changes="{{ json_encode(array_map(fn ($c) => round((float) $c['z'], 3), $changes ?? [])) }}"></canvas>
        </div>
        @if($inspiration)
            <div class="mt-3 rounded-xl bg-action-soft p-3 text-sm">
                <p class="font-semibold">{{ __('models.farm.source_title') }}</p>
                <p class="text-slate-700">{{ __('models.farm.source_text', ['line' => $inspiration->attribution()]) }}</p>
            </div>
        @endif

        {{-- the colour decides the machine: the order is sliced for the printer that holds this spool --}}
        <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.order.color') }}</div>
        <p class="text-xs text-slate-500">{{ __('farm.start.color_hint') }}</p>
        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="{{ __('farm.order.color') }}">
            @forelse($colors as $c)
                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 bg-white p-2 text-left text-sm has-[:checked]:border-action has-[:checked]:ring-2 has-[:checked]:ring-action {{ $c['enough'] ? '' : 'opacity-50' }}">
                    <input type="radio" name="color" value="{{ $c['id'] }}" class="sr-only" @checked($c['id'] === $preselect) @disabled(! $c['enough']) data-hex="{{ $c['hex'] }}">
                    @if($c['photo'])<img src="{{ $c['photo'] }}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover">@else<span class="h-10 w-10 shrink-0 rounded-lg border border-slate-200" style="background:{{ $c['hex'] }}"></span>@endif
                    <span><span class="font-semibold">{{ $c['name'] }}</span><br><span class="text-xs text-slate-500">{{ $c['kind'] }} · {{ $c['printer'] }}</span></span>
                </label>
            @empty
                <p class="col-span-full text-sm text-slate-600">{{ __('farm.order.no_colors') }}</p>
            @endforelse
        </div>

        @php($one = count($changes ?? []) === 1)
        @foreach($changes ?? [] as $i => $change)
            {{-- a plate with a text, a picture in filament colours: every change of the design takes a spool of the machine of
                 the first colour (its ACE changes the spool). data-want: the colour the design was made in, the nearest spool is ticked --}}
            <div id="{{ $i === 0 ? 'farm-second-start' : 'farm-change-'.$i }}" class="farm-change mt-4" data-change-index="{{ $i }}" data-want="{{ $change['hex'] }}">
                <div class="text-sm font-semibold text-slate-700">{{ $one ? __('farm.start.second_title') : __('farm.start.change_title', ['n' => $i + 2, 'z' => number_format($change['z'] * ($scaleNow ?? 1), 1, ',', ' ')]) }}</div>
                @if($i === 0)<p class="text-xs text-slate-500">{{ $one ? __('farm.start.second_hint') : __('farm.start.changes_hint', ['n' => count($changes) + 1, 'max' => \App\Models\FarmOrder::MAX_COLORS]) }}</p>@endif
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="{{ $one ? __('farm.start.second_title') : __('farm.start.change_title', ['n' => $i + 2, 'z' => number_format($change['z'] * ($scaleNow ?? 1), 1, ',', ' ')]) }}">
                    <label data-second-for="*" class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 bg-white p-2 text-left text-sm has-[:checked]:border-action has-[:checked]:ring-2 has-[:checked]:ring-action">
                        <input type="radio" name="change_color[{{ $i }}]" value="" class="sr-only" data-hex="" @checked(! ($changePreselect[$i] ?? 0))>
                        <span class="h-10 w-10 shrink-0 rounded-lg border border-dashed border-slate-300"></span>
                        <span><span class="font-semibold">{{ $i === 0 ? __('farm.order.second_same') : __('farm.order.change_same') }}</span><br><span class="text-xs text-slate-500">{{ $i === 0 ? __('farm.start.second_same_hint') : __('farm.order.change_same_hint') }}</span></span>
                    </label>
                    @foreach($colors as $c)
                        @foreach($i === 0 ? $c['seconds'] : array_merge([$c + ['own' => true]], $c['seconds']) as $s)
                            <label data-second-for="{{ $c['id'] }}" class="hidden cursor-pointer items-center gap-2 rounded-xl border border-slate-300 bg-white p-2 text-left text-sm has-[:checked]:border-action has-[:checked]:ring-2 has-[:checked]:ring-action">
                                <input type="radio" name="change_color[{{ $i }}]" value="{{ $s['id'] }}" class="sr-only" data-hex="{{ $s['hex'] }}" @checked($s['id'] === ($changePreselect[$i] ?? 0) && $c['id'] === $preselect)>
                                @if($s['photo'])<img src="{{ $s['photo'] }}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover">@else<span class="h-10 w-10 shrink-0 rounded-lg border border-slate-200" style="background:{{ $s['hex'] }}"></span>@endif
                                <span><span class="font-semibold">{{ $s['name'] }}</span><br><span class="text-xs text-slate-500">{{ ! empty($s['own']) ? __('farm.order.change_first') : $s['kind'] }}</span></span>
                            </label>
                        @endforeach
                    @endforeach
                </div>
                <p class="farm-change-none mt-2 hidden text-xs text-amber-800">{{ __('farm.start.second_none') }}</p>
            </div>
        @endforeach

        @if(count($parts ?? []) >= 2)
            {{-- a design of several separately printed parts (a box and its lid, the plates of a layered picture): each part
                 may print from its own spool of the machine of the first colour, one plate after another --}}
            <div id="farm-parts" class="mt-4 rounded-xl border border-slate-200 bg-white p-3">
                <label class="flex cursor-pointer items-start gap-2 text-sm">
                    <input type="checkbox" id="farm-by-parts" name="by_parts" value="1" class="mt-1 h-4 w-4 accent-action" @checked($byParts ?? false)>
                    <span><span class="font-semibold text-slate-700">{{ __('farm.start.parts_title', ['n' => count($parts)]) }}</span><br><span class="text-xs text-slate-500">{{ __('farm.start.parts_hint', ['max' => \App\Models\FarmOrder::MAX_COLORS]) }}</span></span>
                </label>
                <div id="farm-part-rows" class="mt-3 space-y-3 {{ ($byParts ?? false) ? '' : 'hidden' }}">
                    @foreach($parts as $part)
                        <div class="farm-part" data-part="{{ $part['name'] }}" data-want="{{ $part['hex'] ?? '' }}">
                            <div class="text-xs font-semibold text-slate-700">{{ $part['label'] }}</div>
                            <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="{{ $part['label'] }}">
                                <label data-second-for="*" class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-300 bg-white p-2 text-left text-sm has-[:checked]:border-action has-[:checked]:ring-2 has-[:checked]:ring-action">
                                    <input type="radio" name="part_color[{{ $part['name'] }}]" value="" class="sr-only" data-hex="" @checked(! $part['preselect'])>
                                    <span class="h-10 w-10 shrink-0 rounded-lg border border-dashed border-slate-300"></span>
                                    <span><span class="font-semibold">{{ __('farm.start.part_main') }}</span><br><span class="text-xs text-slate-500">{{ __('farm.start.part_main_hint') }}</span></span>
                                </label>
                                @foreach($colors as $c)
                                    @foreach($c['seconds'] as $s)
                                        <label data-second-for="{{ $c['id'] }}" class="hidden cursor-pointer items-center gap-2 rounded-xl border border-slate-300 bg-white p-2 text-left text-sm has-[:checked]:border-action has-[:checked]:ring-2 has-[:checked]:ring-action">
                                            <input type="radio" name="part_color[{{ $part['name'] }}]" value="{{ $s['id'] }}" class="sr-only" data-hex="{{ $s['hex'] }}" @checked($s['id'] === $part['preselect'] && $c['id'] === $preselect)>
                                            @if($s['photo'])<img src="{{ $s['photo'] }}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover">@else<span class="h-10 w-10 shrink-0 rounded-lg border border-slate-200" style="background:{{ $s['hex'] }}"></span>@endif
                                            <span><span class="font-semibold">{{ $s['name'] }}</span><br><span class="text-xs text-slate-500">{{ $s['kind'] }}</span></span>
                                        </label>
                                    @endforeach
                                @endforeach
                            </div>
                            <p class="farm-change-none mt-2 hidden text-xs text-amber-800">{{ __('farm.start.second_none') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- the size as the calculator had it (or as uploaded); one dimension typed scales the whole model --}}
        <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.size.label') }} <span id="farm-size-pct" class="font-normal text-action-dark"></span> <button id="farm-size-reset" type="button" class="hidden text-xs font-semibold text-action-dark underline">{{ __('farm.size.reset') }}</button></div>
        <div id="farm-size" class="mt-2 grid grid-cols-3 gap-2" data-bbox="{{ json_encode($file?->bbox) }}" data-scale="{{ $scale }}" data-max="{{ $maxScale }}">
            @foreach(['x', 'y', 'z'] as $axis)
                <label class="text-xs font-semibold text-slate-600">{{ __('farm.size.'.$axis) }} <span class="font-normal text-slate-500">mm</span>
                    <input data-axis="{{ $axis }}" type="number" inputmode="decimal" min="1" step="any" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
                </label>
            @endforeach
        </div>
        <p class="mt-1 text-xs text-slate-500">{{ __('farm.size.hint') }}</p>
        <input type="hidden" name="scale" id="farm-scale" value="{{ $scale }}">

        <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.quality.label') }}</div>
        <div class="mt-2 grid grid-cols-3 gap-2">
            @foreach($settings['qualities'] as $key => $q)
                <label class="seg block cursor-pointer text-center has-[:checked]:border-action has-[:checked]:bg-action-soft has-[:checked]:text-action-dark has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action"><input type="radio" name="quality" value="{{ $key }}" class="sr-only" @checked($key === $quality || ($loop->first && ! array_key_exists($quality, $settings['qualities'])))>{{ __('farm.quality.'.$key) }}<span class="block text-xs font-normal text-slate-500">{{ $q['layer_mm'] }} mm</span></label>
            @endforeach
        </div>

        <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.strength.label') }}</div>
        <div class="mt-2 grid grid-cols-3 gap-2">
            @foreach($settings['strengths'] as $key => $s)
                <label class="seg block cursor-pointer text-center has-[:checked]:border-action has-[:checked]:bg-action-soft has-[:checked]:text-action-dark has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action"><input type="radio" name="strength" value="{{ $key }}" class="sr-only" @checked($key === $strength || ($loop->first && ! array_key_exists($strength, $settings['strengths'])))>{{ __('farm.strength.'.$key) }}<span class="block text-xs font-normal text-slate-500">{{ __('farm.strength.infill', ['n' => $s['infill']]) }}</span></label>
            @endforeach
        </div>

        <div class="mt-4 text-sm font-semibold text-slate-700">{{ __('farm.supports.label') }}</div>
        <div class="mt-2 grid grid-cols-2 gap-2">
            @foreach(['auto', 'off'] as $key)
                <label class="seg block cursor-pointer text-center has-[:checked]:border-action has-[:checked]:bg-action-soft has-[:checked]:text-action-dark has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action"><input type="radio" name="supports" value="{{ $key }}" class="sr-only" @checked($key === ($supports ?? 'auto'))>{{ __('farm.supports.'.$key) }}<span class="block text-xs font-normal text-slate-500">{{ __('farm.supports.'.$key.'_hint') }}</span></label>
            @endforeach
        </div>

        <label class="mt-4 block text-sm font-semibold text-slate-700">{{ __('farm.copies.label') }}
            <input name="copies" type="number" inputmode="numeric" min="1" max="{{ $maxCopies }}" value="{{ $copies }}" class="mt-1 w-32 rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal">
        </label>
        <p class="mt-1 text-xs text-slate-500">{{ __('farm.copies.hint') }}</p>

        <button id="farm-continue" type="submit" class="mt-4 w-full rounded-xl bg-action px-4 py-3 font-semibold text-white disabled:opacity-50" @disabled(! $file)>{{ __('farm.start.continue') }}</button>
        @if($slicesLeft !== null)<p class="mt-2 text-xs text-slate-500">{{ __('farm.slices_left', ['n' => $slicesLeft]) }}</p>@endif
    </form>
</div>
@endsection
