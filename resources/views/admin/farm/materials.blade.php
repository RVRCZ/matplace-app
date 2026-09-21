@extends('layouts.app', ['title' => __('farm.admin.nav.materials').' · admin', 'noindex' => true])

@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
    $json = fn ($v) => $v ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
@endphp

@section('content')
@include('admin.farm.nav')

<div class="mt-4 space-y-4">
    @foreach($materials->concat([new \App\Models\FarmMaterial(['density' => 1.24, 'enabled' => true])]) as $m)
        <section class="rounded-2xl border border-slate-200 bg-white p-4">
            <form method="post" action="{{ $m->exists ? route('admin.farm.materials.update', $m) : route('admin.farm.materials.create') }}">
                @csrf
                <h2 class="font-bold">{{ $m->exists ? $m->name : '+ Nový materiál' }}</h2>
                <div class="mt-2 grid gap-3 sm:grid-cols-5">
                    <label class="{{ $lb }}">Kód<input name="code" required value="{{ $m->code }}" placeholder="PETG" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">Název<input name="name" required value="{{ $m->name }}" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">Profil filamentu<input name="filament_profile" required value="{{ $m->filament_profile }}" placeholder="filament_petg.json" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">Hustota (g/cm³)<input type="number" step="0.001" name="density" required value="{{ $m->density }}" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">Cena za gram (bez DPH)<input type="number" step="0.0001" name="price_per_gram" required value="{{ $m->price_per_gram }}" class="{{ $in }}"></label>
                </div>
                <label class="{{ $lb }} mt-2">Přepisy profilu filamentu (JSON)<input name="filament_overrides" value="{{ $json($m->filament_overrides) }}" class="{{ $in }} font-mono text-xs"></label>
                <div class="mt-2 flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked($m->enabled) class="h-4 w-4 accent-action"> Nabízet</label>
                    <button class="btn-quiet text-sm">Uložit</button>
                </div>
            </form>

            @if($m->exists)
                <h3 class="mt-4 text-sm font-bold">Barvy</h3>
                <div class="mt-2 space-y-2">
                    @foreach($m->colors->concat([new \App\Models\FarmColor(['hex' => '#cccccc', 'enabled' => true])]) as $c)
                        <form method="post" enctype="multipart/form-data" action="{{ $c->exists ? route('admin.farm.colors.update', $c) : route('admin.farm.colors.create') }}" class="grid items-end gap-2 rounded-xl bg-slate-50 p-2 sm:grid-cols-[3rem_1fr_6rem_1fr_6rem_5rem]">
                            @csrf
                            <input type="hidden" name="farm_material_id" value="{{ $m->id }}">
                            @if($c->photoUrl())<img src="{{ $c->photoUrl() }}" alt="" class="h-10 w-10 rounded-lg object-cover">@else<span class="h-10 w-10 rounded-lg border border-slate-300" style="background: {{ $c->hex }}"></span>@endif
                            <label class="{{ $lb }}">{{ $c->exists ? 'Název' : '+ Nová barva' }}<input name="name" required value="{{ $c->name }}" class="{{ $in }}"></label>
                            <label class="{{ $lb }}">Hex<input type="color" name="hex" value="{{ $c->hex }}" class="mt-1 h-9 w-full rounded-lg border border-slate-300"></label>
                            <label class="{{ $lb }}">Fotka výtisku<input type="file" name="photo" accept="image/*" class="mt-1 w-full text-xs"></label>
                            <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked($c->enabled) class="h-4 w-4 accent-action"> nabízet</label>
                            <button class="btn-quiet text-sm">Uložit</button>
                        </form>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
</div>
@endsection
