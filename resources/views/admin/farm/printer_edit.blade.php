@extends('layouts.app', ['title' => ($printer->name ?: __('farm.admin.nav.printers')).' · admin', 'noindex' => true])

@php
    $json = fn ($v) => $v ? json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    $slots = $printer->exists ? $printer->slots->keyBy('slot') : collect();
    $slotCount = max(4, $slots->count());
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
@endphp

@section('content')
@include('admin.farm.nav')

<form method="post" action="{{ $printer->exists ? route('admin.farm.printers.update', $printer) : route('admin.farm.printers.create') }}" class="mt-4 space-y-4">
    @csrf
    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="{{ $lb }}">Název<input name="name" required value="{{ old('name', $printer->name) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">Model<input name="model" required value="{{ old('model', $printer->model) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">Klíč (v konfiguraci agenta)<input name="key" required value="{{ old('key', $printer->key) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">Režim
                <select name="mode" class="{{ $in }}">@foreach(['manual', 'agent'] as $m)<option value="{{ $m }}" @selected(old('mode', $printer->mode) === $m)>{{ __('farm.admin.mode.'.$m) }}</option>@endforeach</select>
            </label>
            <label class="{{ $lb }}">Agent
                <select name="farm_agent_id" class="{{ $in }}"><option value="">—</option>@foreach($agents as $a)<option value="{{ $a->id }}" @selected((int) old('farm_agent_id', $printer->farm_agent_id) === $a->id)>{{ $a->name }}</option>@endforeach</select>
            </label>
            <label class="mt-5 flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $printer->enabled)) class="h-4 w-4 accent-action"> V provozu</label>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Profil pro slicer</h2>
        <div class="mt-2 grid gap-3 sm:grid-cols-4">
            <label class="{{ $lb }}">Plocha X (mm)<input type="number" step="0.1" name="bed_x" required value="{{ old('bed_x', $printer->bed_x) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">Plocha Y (mm)<input type="number" step="0.1" name="bed_y" required value="{{ old('bed_y', $printer->bed_y) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">Výška Z (mm)<input type="number" step="0.1" name="bed_z" required value="{{ old('bed_z', $printer->bed_z) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">Tryska (mm)<input type="number" step="0.05" name="nozzle_mm" required value="{{ old('nozzle_mm', $printer->nozzle_mm) }}" class="{{ $in }}"></label>
        </div>
        <label class="{{ $lb }} mt-3">Soubor profilu stroje <span class="font-normal">(engines/orca/profiles nebo storage/app/farm/profiles)</span><input name="machine_profile" required value="{{ old('machine_profile', $printer->machine_profile) }}" class="{{ $in }}"></label>
        <div class="mt-3 grid gap-3 lg:grid-cols-3">
            <label class="{{ $lb }}">Procesní profily podle kvality (JSON)<textarea name="process_profiles" rows="6" required class="{{ $in }} font-mono text-xs">{{ old('process_profiles', $json($printer->process_profiles)) }}</textarea></label>
            <label class="{{ $lb }}">Přepisy profilu stroje (JSON)<textarea name="machine_overrides" rows="6" class="{{ $in }} font-mono text-xs">{{ old('machine_overrides', $json($printer->machine_overrides)) }}</textarea></label>
            <label class="{{ $lb }}">Přepisy procesu (JSON)<textarea name="process_overrides" rows="6" class="{{ $in }} font-mono text-xs">{{ old('process_overrides', $json($printer->process_overrides)) }}</textarea></label>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Cena a kalibrace</h2>
        <div class="mt-2 grid gap-3 sm:grid-cols-3">
            <label class="{{ $lb }}">Korekce času<input type="number" step="0.001" name="time_factor" required value="{{ old('time_factor', $printer->time_factor) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">Korekce hmotnosti<input type="number" step="0.001" name="weight_factor" required value="{{ old('weight_factor', $printer->weight_factor) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">Hodinová sazba (prázdné = výchozí farmy)<input type="number" step="0.01" name="hourly_rate" value="{{ old('hourly_rate', $printer->hourly_rate) }}" class="{{ $in }}"></label>
        </div>
        <p class="mt-2 text-xs text-slate-500">{{ $calibration ? __('farm.admin.calibration', ['n' => $calibration['n'], 'time' => $calibration['time'] ?? '—', 'weight' => $calibration['weight'] ?? '—']) : __('farm.admin.calibration_none') }}</p>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Sloty (cívky)</h2>
        <p class="text-xs text-slate-500">Zákazník vidí jen barvy v zapnutých slotech. Číslo slotu je číslo nástroje v G-code (slot 1 = T0).</p>
        <div class="mt-2 space-y-2">
            @for($i = 0; $i < $slotCount; $i++)
                @php $s = $slots->get($i); @endphp
                <div class="grid items-end gap-2 sm:grid-cols-[3rem_1fr_8rem_6rem]">
                    <span class="pb-2 text-sm font-bold">{{ $i + 1 }}</span>
                    <label class="{{ $lb }}">Barva
                        <select name="slots[{{ $i }}][color]" class="{{ $in }}"><option value="">— prázdný —</option>
                            @foreach($colors->groupBy(fn ($c) => $c->material->label()) as $kind => $group)<optgroup label="{{ $kind }}">@foreach($group as $c)<option value="{{ $c->id }}" @selected($s?->farm_color_id === $c->id)>{{ $c->name }}@if($c->name_en) / {{ $c->name_en }}@endif</option>@endforeach</optgroup>@endforeach
                        </select>
                    </label>
                    <label class="{{ $lb }}">Zbývá (g)<input type="number" step="1" min="0" name="slots[{{ $i }}][remaining_g]" value="{{ $s ? round($s->remaining_g) : 0 }}" class="{{ $in }}"></label>
                    <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="slots[{{ $i }}][enabled]" value="1" @checked($s?->enabled) class="h-4 w-4 accent-action"> nabízet</label>
                </div>
            @endfor
        </div>
    </section>

    <button class="btn-primary">Uložit</button>
</form>
@endsection
