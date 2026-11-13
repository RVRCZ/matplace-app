{{-- One filament (docs/AQ.md): a colour of the catalogue = the spool that sits in a printer. Its own values are edited
     here; what belongs to its kind (temperatures, slicer profile, price per gram, manual_only, note) is shown and edited
     at the kind in "Materiály a barvy"; what a machine adds is a row of "Ladění materiálů". Nothing is stored twice. --}}
@extends('layouts.app', ['title' => $color->name.' · '.__('farm.admin.fd.title').' · admin', 'noindex' => true])

@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
    $own = (array) $color->print_overrides;
    $temps = $color->temps();
    $slice = $color->sliceOverrides();
    $deg = fn ($v) => $v ? $v.' °C' : '—';
    $tone = ['untested' => 'bg-slate-100 text-slate-700', 'testing' => 'bg-amber-100 text-amber-900', 'tuned' => 'bg-ok-soft text-ok'];
@endphp

@section('content')
@include('admin.farm.nav')
<datalist id="farm-makers">@foreach($makers as $mk)<option value="{{ $mk }}">@endforeach</datalist>

<div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
    <a href="{{ route('admin.farm.dashboard') }}" class="underline">← {{ __('farm.admin.nav.dashboard') }}</a>
    <a href="{{ route('admin.farm.materials') }}#kind-{{ $kind->id }}" class="underline">{{ __('farm.admin.nav.materials') }}</a>
</div>

<header class="mt-3 flex flex-wrap items-center gap-3">
    @if($color->photoUrl())
        <img src="{{ $color->photoUrl() }}" alt="{{ $color->name }}" data-zoom="{{ $color->photoUrl() }}" class="h-14 w-14 cursor-zoom-in rounded-xl object-cover">
    @else
        <span class="h-14 w-14 rounded-xl border border-slate-300" style="background: {{ $color->hex }}"></span>
    @endif
    <div class="min-w-0">
        <h1 class="break-words text-xl font-bold">{{ $color->fullLabel() }}</h1>
        <p class="text-xs text-slate-500"><span class="font-mono">{{ $color->code }}</span> · {{ $color->enabled ? __('farm.admin.fd.offered') : __('farm.admin.fd.not_offered') }} · {{ $color->in_stock ? __('farm.admin.fd.in_stock') : __('farm.admin.fd.out_of_stock') }} @if($kind->manual_only) · {{ __('farm.admin.manual_only_short') }} @endif</p>
    </div>
</header>

<div class="mt-4 grid gap-4 lg:grid-cols-2 lg:items-start">
<div class="space-y-4">
    {{-- where the spool sits --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4" data-filament-slots>
        <h2 class="font-bold">{{ __('farm.admin.fd.loaded_in') }}</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @forelse($color->slots as $s)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 {{ $s->enabled ? '' : 'opacity-60' }}">
                    <a href="{{ route('admin.farm.printers.show', $s->printer) }}#slots" class="font-semibold underline">{{ $s->placeLabel() }}</a>
                    <span class="text-xs text-slate-600"><span class="font-mono">{{ $s->code() }}</span> · {{ round($s->remaining_g) }} g · {{ $s->enabled ? __('farm.admin.fd.offered') : __('farm.admin.fd.not_offered') }}</span>
                </li>
            @empty
                <li class="text-slate-500">{{ __('farm.admin.fd.loaded_nowhere') }}</li>
            @endforelse
        </ul>
    </section>

    {{-- the spool's own values: the same save as a colour's row in "Materiály a barvy" (FarmCatalogController::saveColor) --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">{{ __('farm.admin.fd.this') }}</h2>
        <form method="post" enctype="multipart/form-data" action="{{ route('admin.farm.colors.update', $color) }}" class="mt-2 space-y-3" data-filament-form>
            @csrf
            <input type="hidden" name="back" value="filament">
            <input type="hidden" name="code" value="{{ $color->code }}">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="{{ $lb }}">{{ __('farm.admin.fd.name') }}<input name="name" required value="{{ old('name', $color->name) }}" class="{{ $in }}"></label>
                <label class="{{ $lb }}">{{ __('farm.admin.fd.name_en') }}<input name="name_en" value="{{ old('name_en', $color->name_en) }}" class="{{ $in }}"></label>
                <label class="{{ $lb }}">{{ __('farm.admin.fd.kind') }}
                    <select name="farm_material_id" class="{{ $in }}">
                        @foreach($materials as $mm)
                            <option value="{{ $mm->id }}" @selected($mm->id === $color->farm_material_id)>{{ $mm->label() }}{{ $mm->enabled ? '' : ' ('.__('farm.admin.fd.kind_off').')' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="{{ $lb }}">{{ __('farm.admin.maker') }} <span class="font-normal">({{ __('farm.admin.maker_hint') }})</span><input name="manufacturer" list="farm-makers" value="{{ old('manufacturer', $color->manufacturer) }}" placeholder="{{ $kind->maker() }}" class="{{ $in }}"></label>
                <label class="{{ $lb }}">Hex<input type="color" name="hex" value="{{ old('hex', $color->hex ?: '#cccccc') }}" class="mt-1 h-10 w-full rounded-lg border border-slate-300"></label>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 pt-4 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" name="in_stock" value="1" @checked($color->in_stock) class="h-4 w-4 accent-action"> {{ __('farm.admin.fd.in_stock') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="enabled" value="1" @checked($color->enabled) class="h-4 w-4 accent-action"> {{ __('farm.admin.fd.offer') }}</label>
                </div>
            </div>

            {{-- temperatures of this spool alone; an empty field = the kind's value (the placeholder) --}}
            <fieldset class="rounded-xl bg-slate-50 p-3">
                <legend class="px-1 text-xs font-semibold text-slate-600">{{ __('farm.admin.fd.own_temps') }}</legend>
                <div class="grid grid-cols-3 gap-2">
                    <label class="{{ $lb }}">{{ __('farm.admin.fd.nozzle') }}<input type="number" name="temps[nozzle_temp]" min="150" max="350" value="{{ old('temps.nozzle_temp', $own['nozzle_temp'] ?? '') }}" placeholder="{{ $kind->nozzle_temp }}" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">{{ __('farm.admin.fd.nozzle_first') }}<input type="number" name="temps[nozzle_temp_first]" min="150" max="350" value="{{ old('temps.nozzle_temp_first', $own['nozzle_temp_first'] ?? '') }}" placeholder="{{ $kind->nozzle_temp_first }}" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">{{ __('farm.admin.fd.bed') }}<input type="number" name="temps[bed_temp]" min="0" max="150" value="{{ old('temps.bed_temp', $own['bed_temp'] ?? '') }}" placeholder="{{ $kind->bed_temp }}" class="{{ $in }}"></label>
                </div>
                <p class="mt-2 text-xs text-slate-600" data-effective-temps>{{ $temps ? __('farm.admin.fd.effective', ['nozzle' => $temps['nozzle'], 'first' => $temps['nozzle_first'] ?: '—', 'bed' => $temps['bed']]) : __('farm.admin.fd.effective_profile') }} {{ __('farm.admin.fd.effective_note') }}</p>
            </fieldset>

            <label class="{{ $lb }}">{{ __('farm.admin.fd.own_slice') }}<input name="print_overrides" value="{{ old('print_overrides', $slice ? json_encode($slice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') }}" placeholder='{"filament": {"filament_max_volumetric_speed": ["12"]}}' class="{{ $in }} font-mono text-xs"></label>
            @error('print_overrides')
                <p class="text-xs text-red-700">{{ $message }}</p>
            @enderror
            <label class="{{ $lb }}">{{ __('farm.admin.fd.test_notes') }}<textarea name="test_notes" rows="2" maxlength="2000" class="{{ $in }}">{{ old('test_notes', $color->test_notes) }}</textarea></label>
            <label class="{{ $lb }}">{{ __('farm.admin.shop.field.source_url') }}<input type="url" name="source_url" value="{{ old('source_url', $color->source_url) }}" placeholder="https://…" class="{{ $in }} text-xs"></label>
            @error('source_url')
                <p class="text-xs text-red-700">{{ $message }}</p>
            @enderror
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="{{ $lb }}">{{ __('farm.admin.fd.photo') }}<input type="file" name="photo" accept="image/*" class="mt-1 w-full text-xs"></label>
                @if($color->photo_path)
                    <label class="flex items-center gap-2 pt-4 text-sm"><input type="checkbox" name="remove_photo" value="1" class="h-4 w-4 accent-action"> {{ __('farm.admin.fd.remove_photo') }}</label>
                @endif
            </div>
            <div class="flex justify-end"><button class="btn-primary text-sm">{{ __('farm.admin.fd.save') }}</button></div>
        </form>
    </section>
</div>

<div class="space-y-4">
    {{-- what the kind gives every one of its colours: read here, edited at the kind --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4" data-filament-kind="{{ $kind->id }}">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-bold">{{ __('farm.admin.fd.kind_title', ['kind' => $kind->label()]) }}</h2>
            <a href="{{ route('admin.farm.materials') }}#kind-{{ $kind->id }}" class="text-sm font-semibold underline" data-kind-link>{{ __('farm.admin.fd.kind_edit') }} →</a>
        </div>
        <p class="mt-1 text-xs text-slate-500">{{ __('farm.admin.fd.kind_lead', ['n' => $kindColors]) }}</p>
        <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
            <div><dt class="text-xs text-slate-500">{{ __('farm.admin.fd.nozzle') }}</dt><dd class="font-semibold">{{ $deg($kind->nozzle_temp) }}</dd></div>
            <div><dt class="text-xs text-slate-500">{{ __('farm.admin.fd.nozzle_first') }}</dt><dd class="font-semibold">{{ $deg($kind->nozzle_temp_first) }}</dd></div>
            <div><dt class="text-xs text-slate-500">{{ __('farm.admin.fd.bed') }}</dt><dd class="font-semibold">{{ $deg($kind->bed_temp) }}</dd></div>
            <div><dt class="text-xs text-slate-500">{{ __('farm.admin.fd.price') }}</dt><dd class="font-semibold">{{ rtrim(rtrim(number_format((float) $kind->price_per_gram, 4, ',', ' '), '0'), ',') }} Kč/g</dd></div>
            <div><dt class="text-xs text-slate-500">{{ __('farm.admin.fd.density') }}</dt><dd class="font-semibold">{{ $kind->density }} g/cm³</dd></div>
            <div><dt class="text-xs text-slate-500">{{ __('farm.admin.fd.kind_state') }}</dt><dd class="font-semibold">{{ $kind->enabled ? __('farm.admin.fd.offered') : __('farm.admin.fd.kind_off') }}</dd></div>
            <div class="col-span-2 sm:col-span-3"><dt class="text-xs text-slate-500">{{ __('farm.admin.fd.profile') }}</dt><dd class="break-all font-mono text-xs">{{ $kind->filament_profile }}</dd></div>
            @if($kind->filament_overrides)
                <div class="col-span-2 sm:col-span-3"><dt class="text-xs text-slate-500">{{ __('farm.admin.fd.profile_overrides') }}</dt><dd class="break-all font-mono text-xs">{{ json_encode($kind->filament_overrides, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</dd></div>
            @endif
            <div class="col-span-2 sm:col-span-3"><dt class="text-xs text-slate-500">{{ __('farm.admin.manual_only') }}</dt><dd class="font-semibold">{{ $kind->manual_only ? __('farm.admin.fd.yes') : __('farm.admin.fd.no') }}</dd></div>
            @if($kind->notes)
                <div class="col-span-2 sm:col-span-3"><dt class="text-xs text-slate-500">{{ __('farm.admin.fd.kind_notes') }}</dt><dd>{{ $kind->notes }}</dd></div>
            @endif
        </dl>
    </section>

    {{-- what each machine adds to the kind (rows of "Ladění materiálů"); a row of this very spool wins over the kind's row --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4" data-filament-tuning>
        <h2 class="font-bold">{{ __('farm.admin.fd.tuning_title') }}</h2>
        <p class="mt-1 text-xs text-slate-500">{{ __('farm.admin.fd.tuning_lead') }}</p>
        <ul class="mt-2 space-y-1 text-sm">
            @forelse($rows as $r)
                @php $rt = $r->temps(); @endphp
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                    <span class="min-w-0 break-words"><span class="font-semibold">{{ $r->printer?->name }}</span> <span class="text-xs text-slate-500">{{ $r->farm_color_id ? __('farm.admin.fd.row_spool') : __('farm.admin.fd.row_kind') }} · v{{ $r->version }} @if($rt) · {{ collect($rt)->implode(' / ') }} °C @endif</span></span>
                    <span class="flex items-center gap-2"><span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $tone[$r->status] ?? '' }}">{{ __('farm.admin.fd.tune.'.$r->status) }}</span><a href="{{ route('admin.farm.tuning.edit', $r) }}" class="underline">{{ __('farm.admin.fd.tuning_open') }}</a></span>
                </li>
            @empty
                <li class="text-slate-500">{{ __('farm.admin.fd.tuning_none') }}</li>
            @endforelse
        </ul>
        @foreach($spoolless as $sp)
            <form method="post" action="{{ route('admin.farm.tuning.spool') }}" class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                @csrf
                <input type="hidden" name="farm_printer_id" value="{{ $sp->id }}">
                <input type="hidden" name="farm_color_id" value="{{ $color->id }}">
                <span class="text-slate-600">{{ $sp->name }}:</span>
                <button class="btn-quiet !min-h-0 !py-1 text-xs">{{ __('farm.admin.fd.tuning_spool') }}</button>
            </form>
        @endforeach
    </section>
</div>
</div>
@endsection
