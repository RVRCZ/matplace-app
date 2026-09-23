@extends('layouts.app', ['title' => __('farm.admin.nav.materials').' · admin', 'noindex' => true])

@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
    $json = fn ($v) => $v ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
@endphp

@section('content')
@include('admin.farm.nav')

<p class="mt-4 text-sm text-slate-600">Druh materiálu nese teploty a slicer profil; barva je jeden filament toho druhu. Zákazník vidí barvy, které jsou <strong>zapnuté</strong> a založené v některém slotu tiskárny. Vypnutý druh (bez ověřeného profilu) se nenabízí, i když má barvy.</p>

<div class="mt-4 space-y-4">
    @foreach($materials->concat([new \App\Models\FarmMaterial(['density' => 1.24, 'enabled' => true, 'finish' => 'solid'])]) as $m)
        <section class="rounded-2xl border {{ $m->exists && ! $m->enabled ? 'border-slate-200 bg-slate-50' : 'border-slate-200 bg-white' }} p-4">
            <form method="post" action="{{ $m->exists ? route('admin.farm.materials.update', $m) : route('admin.farm.materials.create') }}">
                @csrf
                <details @if(! $m->exists) @else open @endif>
                    <summary class="cursor-pointer font-bold">{{ $m->exists ? $m->label().' · '.$m->colors->count().' barev'.($m->enabled ? '' : ' · vypnuto') : '+ Nový druh materiálu' }}</summary>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        <label class="{{ $lb }}">Kód<input name="code" required value="{{ $m->code }}" placeholder="PETG" class="{{ $in }}"></label>
                        <label class="{{ $lb }}">Povrch
                            <select name="finish" class="{{ $in }}">@foreach(\App\Models\FarmMaterial::FINISHES as $f)<option value="{{ $f }}" @selected($m->finish === $f)>{{ $f }}{{ __('farm.finish.'.$f) ? ' – '.__('farm.finish.'.$f) : '' }}</option>@endforeach</select>
                        </label>
                        <label class="{{ $lb }}">Název<input name="name" required value="{{ $m->name }}" class="{{ $in }}"></label>
                        <label class="{{ $lb }}">Profil filamentu<input name="filament_profile" required value="{{ $m->filament_profile }}" placeholder="filament_petg.json" class="{{ $in }}"></label>
                        <label class="{{ $lb }}">Hustota (g/cm³)<input type="number" step="0.001" name="density" required value="{{ $m->density }}" class="{{ $in }}"></label>
                        <label class="{{ $lb }}">Cena za gram (bez DPH)<input type="number" step="0.0001" name="price_per_gram" required value="{{ $m->price_per_gram }}" class="{{ $in }}"></label>
                        <label class="{{ $lb }}">Tryska (°C)<input type="number" name="nozzle_temp" value="{{ $m->nozzle_temp }}" class="{{ $in }}"></label>
                        <label class="{{ $lb }}">Tryska 1. vrstva (°C)<input type="number" name="nozzle_temp_first" value="{{ $m->nozzle_temp_first }}" placeholder="+5" class="{{ $in }}"></label>
                        <label class="{{ $lb }}">Podložka (°C)<input type="number" name="bed_temp" value="{{ $m->bed_temp }}" class="{{ $in }}"></label>
                        <label class="{{ $lb }}">Pořadí<input type="number" name="sort" value="{{ $m->sort }}" class="{{ $in }}"></label>
                        <label class="{{ $lb }} sm:col-span-2">Další přepisy profilu (JSON)<input name="filament_overrides" value="{{ $json($m->filament_overrides) }}" class="{{ $in }} font-mono text-xs"></label>
                        <label class="{{ $lb }} sm:col-span-3 lg:col-span-6">Poznámka pro obsluhu<input name="notes" value="{{ $m->notes }}" maxlength="500" class="{{ $in }}"></label>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked($m->enabled) class="h-4 w-4 accent-action"> Nabízet zákazníkům (profil je ověřený)</label>
                        <button class="btn-quiet text-sm">Uložit</button>
                    </div>
                </details>
            </form>

            @if($m->exists)
                <details class="mt-3" @if($m->colors->count() <= 8) open @endif>
                    <summary class="cursor-pointer text-sm font-bold">Barvy ({{ $m->colors->count() }})</summary>
                    <div class="mt-2 space-y-2">
                        @foreach($m->colors->concat([new \App\Models\FarmColor(['hex' => '#cccccc', 'enabled' => true, 'in_stock' => true])]) as $c)
                            <form method="post" enctype="multipart/form-data" action="{{ $c->exists ? route('admin.farm.colors.update', $c) : route('admin.farm.colors.create') }}" class="grid items-end gap-2 rounded-xl bg-slate-50 p-2 sm:grid-cols-[3rem_1fr_1fr_5rem_1fr_5rem_5rem_5rem]">
                                @csrf
                                <input type="hidden" name="farm_material_id" value="{{ $m->id }}">
                                @if($c->photoUrl())<img src="{{ $c->photoUrl() }}" alt="" class="h-10 w-10 rounded-lg object-cover">@else<span class="h-10 w-10 rounded-lg border border-slate-300" style="background: {{ $c->hex }}"></span>@endif
                                <label class="{{ $lb }}">{{ $c->exists ? 'Název' : '+ Nová barva' }}<input name="name" required value="{{ $c->name }}" class="{{ $in }}"></label>
                                <label class="{{ $lb }}">Anglicky<input name="name_en" value="{{ $c->name_en }}" class="{{ $in }}"></label>
                                <label class="{{ $lb }}">Hex<input type="color" name="hex" value="{{ $c->hex }}" class="mt-1 h-9 w-full rounded-lg border border-slate-300"></label>
                                <label class="{{ $lb }}">Fotka výtisku<input type="file" name="photo" accept="image/*" class="mt-1 w-full text-xs"></label>
                                <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="in_stock" value="1" @checked($c->in_stock) class="h-4 w-4 accent-action"> skladem</label>
                                <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked($c->enabled) class="h-4 w-4 accent-action"> nabízet</label>
                                <button class="btn-quiet text-sm">Uložit</button>
                                @if($c->code)<input type="hidden" name="code" value="{{ $c->code }}">@endif
                                <details class="sm:col-span-8 text-xs">
                                    <summary class="cursor-pointer text-slate-500">{{ $c->code ?? 'nastavení' }}@if($c->drive_folder) · <a class="underline" target="_blank" href="https://drive.google.com/drive/folders/{{ $c->drive_folder }}">fotky na Drive</a>@endif · vlastní nastavení tisku {{ $c->print_overrides ? '✓' : '–' }}</summary>
                                    <div class="mt-1 grid gap-2 sm:grid-cols-2">
                                        <label class="{{ $lb }}">Vlastní nastavení tisku (JSON; nozzle_temp, nozzle_temp_first, bed_temp = do hotového G-code, "process"/"filament" = nové slicování)<input name="print_overrides" value="{{ $c->print_overrides ? json_encode($c->print_overrides, JSON_UNESCAPED_UNICODE) : '' }}" placeholder='{"nozzle_temp": 220, "bed_temp": 60}' class="{{ $in }} font-mono text-xs"></label>
                                        <label class="{{ $lb }}">Výsledky testů (co ukázal testovací objekt)<input name="test_notes" value="{{ $c->test_notes }}" maxlength="2000" class="{{ $in }}"></label>
                                    </div>
                                </details>
                            </form>
                        @endforeach
                    </div>
                </details>
            @endif
        </section>
    @endforeach
</div>
@endsection
