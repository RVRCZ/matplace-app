@extends('layouts.app', ['title' => __('farm.admin.nav.materials').' · admin', 'noindex' => true])

@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
    $json = fn ($v) => $v ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
@endphp

@section('content')
@include('admin.farm.nav')
<datalist id="farm-makers">@foreach($makers as $mk)<option value="{{ $mk }}">@endforeach</datalist>

<p class="mt-4 text-sm text-slate-600">Druh materiálu nese teploty a slicer profil; barva je jeden filament toho druhu. Zákazník vidí barvy, které jsou <strong>zapnuté</strong> a založené v některém slotu tiskárny. Vypnutý druh (bez ověřeného profilu) se nenabízí, i když má barvy.</p>

{{-- the routine of a catalogue of 250 spools: hex from the photos, English names by translation, the list from a spreadsheet --}}
@php
    $allColors = $materials->flatMap->colors;
    $noHex = $allColors->filter(fn ($c) => \App\Domain\Farm\ColorCatalog::hexMissing($c))->count();
    $noEn = $allColors->filter(fn ($c) => trim((string) $c->name_en) === '')->count();
@endphp
<section class="mt-4 rounded-xl border border-slate-200 bg-white p-4 text-sm">
    <h2 class="font-bold">Katalog barev: {{ $allColors->count() }} cívek</h2>
    <p class="mt-1 text-slate-600">Chybí hex: <strong class="{{ $noHex ? 'text-amber-700' : '' }}">{{ $noHex }}</strong> · chybí anglický název: <strong class="{{ $noEn ? 'text-amber-700' : '' }}">{{ $noEn }}</strong>. Hex se čte z fotky výtisku (bez pozadí, střed vzorku), anglický název se přeloží z českého. Duhové, dřevěné a svítící filamenty dostanou převládající barvu a poznámku „zkontrolovat“.</p>
    <div class="mt-3 flex flex-wrap items-end gap-4">
        <form method="post" action="{{ route('admin.farm.colors.fill') }}">@csrf<button class="btn-quiet text-sm">Doplnit hex a anglické názvy</button></form>
        <form method="post" enctype="multipart/form-data" action="{{ route('admin.farm.colors.import') }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <label class="{{ $lb }}">Import z tabulky (CSV, středník, UTF-8)<input type="file" name="csv" accept=".csv,text/csv" required class="mt-1 block w-full text-xs"></label>
            <button name="preview" value="1" class="btn-quiet text-sm">Náhled změn</button>
            <button class="btn-quiet text-sm">Importovat</button>
        </form>
    </div>
    <p class="mt-2 text-xs text-slate-500">Sloupce: <code>code;material;name;name_en;hex;in_stock;sort</code> (první řádek jsou názvy sloupců; <code>name_en</code>, <code>hex</code>, <code>in_stock</code> a <code>sort</code> mohou zůstat prázdné). Materiál pište jako „PLA+“, „PLA matte“, „PLA silk“. Fotky: <code>php artisan farm:import-photos &lt;složka&gt;</code>, složky pojmenované kódem.</p>
    @if(session('color_report'))
        <details class="mt-3" open><summary class="cursor-pointer font-semibold">Co se stalo ({{ count(session('color_report')) }})</summary><ul class="mt-1 max-h-64 list-disc overflow-y-auto pl-5 text-xs text-slate-700">@foreach(session('color_report') as $line)<li>{{ $line }}</li>@endforeach</ul></details>
    @endif
</section>

<div class="mt-4 space-y-4">
    @foreach($materials->concat([$template ?? new \App\Models\FarmMaterial(['density' => 1.24, 'enabled' => true, 'finish' => 'solid'])]) as $m)
        <section id="{{ $m->exists ? 'kind-'.$m->id : 'kind-new' }}" class="rounded-2xl border {{ $m->exists && ! $m->enabled ? 'border-slate-200 bg-slate-50' : 'border-slate-200 bg-white' }} p-4">
            <form method="post" action="{{ $m->exists ? route('admin.farm.materials.update', $m) : route('admin.farm.materials.create') }}">
                @csrf
                <details @if($m->exists || $template) open @endif>
                    <summary class="cursor-pointer font-bold">{{ $m->exists ? $m->label().' · '.$m->colors->count().' barev'.($m->enabled ? '' : ' · vypnuto') : '+ Nový druh materiálu' }}</summary>
                    @if(! $m->exists && $template)<p class="mt-1 text-xs text-slate-500">{{ __('farm.admin.copy_kind_hint', ['kind' => $template->label()]) }}</p>@endif
                    <div class="mt-3 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        <label class="{{ $lb }}">Kód<input name="code" required value="{{ $m->code }}" placeholder="PETG" class="{{ $in }}"></label>
                        <label class="{{ $lb }}">Povrch
                            <select name="finish" class="{{ $in }}">@foreach(\App\Models\FarmMaterial::FINISHES as $f)<option value="{{ $f }}" @selected($m->finish === $f)>{{ $f }}{{ __('farm.finish.'.$f) ? ' – '.__('farm.finish.'.$f) : '' }}</option>@endforeach</select>
                        </label>
                        <label class="{{ $lb }}">Název<input name="name" required value="{{ $m->name }}" class="{{ $in }}"></label>
                        <label class="{{ $lb }}">{{ __('farm.admin.maker') }}<input name="manufacturer" list="farm-makers" value="{{ $m->manufacturer }}" placeholder="{{ \App\Models\FarmMaterial::DEFAULT_MAKER }}" class="{{ $in }}"></label>
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
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked($m->enabled) class="h-4 w-4 accent-action"> Nabízet zákazníkům (profil je ověřený)</label>
                        <span class="flex items-center gap-3 text-sm">
                            @if($m->exists)<a href="{{ route('admin.farm.materials', ['copy' => $m->id]) }}#kind-new" class="text-xs text-slate-500 underline">{{ __('farm.admin.copy_kind') }}</a>@endif
                            <button class="btn-quiet text-sm">Uložit</button>
                        </span>
                    </div>
                </details>
            </form>
            @if($m->exists)
                @php $kindUsage = array_filter(['colors' => $m->colors->count(), 'orders' => isset($usedKinds[$m->id]) ? 1 : 0]); @endphp
                @if($kindUsage)
                    <p class="mt-1 text-right text-xs text-slate-400">{{ __('farm.admin.kind_in_use', ['kind' => $m->label(), 'where' => collect($kindUsage)->map(fn ($n, $k) => __('farm.admin.usage.'.$k, ['n' => $n]))->implode(', ')]) }}</p>
                @else
                    <form method="post" action="{{ route('admin.farm.materials.delete', $m) }}" class="mt-1 text-right" onsubmit="return confirm(@js(__('farm.admin.delete_confirm', ['what' => $m->label()])))">@csrf<button class="text-xs text-red-700 underline">{{ __('farm.admin.delete') }}</button></form>
                @endif
            @endif

            @if($m->exists)
                <details class="mt-3" @if($m->colors->count() <= 8) open @endif>
                    <summary class="cursor-pointer text-sm font-bold">Barvy ({{ $m->colors->count() }})</summary>
                    <div class="mt-2 space-y-2">
                        @foreach($m->colors->concat([new \App\Models\FarmColor(['hex' => '#cccccc', 'enabled' => true, 'in_stock' => true])]) as $c)
                            <form method="post" enctype="multipart/form-data" action="{{ $c->exists ? route('admin.farm.colors.update', $c) : route('admin.farm.colors.create') }}" class="grid items-end gap-2 rounded-xl bg-slate-50 p-2 sm:grid-cols-[3rem_1fr_1fr_5rem_1fr_5rem_5rem_5rem]">
                                @csrf
                                @if(! $c->exists)<input type="hidden" name="farm_material_id" value="{{ $m->id }}">@endif
                                @if($c->photoUrl())<img src="{{ $c->photoUrl() }}" alt="{{ $c->name }} · {{ $m->label() }}" data-zoom="{{ $c->photoUrl() }}" title="{{ __('farm.order.photo') }}" class="h-10 w-10 cursor-zoom-in rounded-lg object-cover">@else<span class="h-10 w-10 rounded-lg border border-slate-300" style="background: {{ $c->hex }}"></span>@endif
                                <label class="{{ $lb }}">{{ $c->exists ? 'Název' : '+ Nová barva' }}<input name="name" required value="{{ $c->name }}" class="{{ $in }}"></label>
                                {{-- neither is required: what is missing is marked and "Doplnit hex a anglické názvy" fills it --}}
                                <label class="{{ $lb }}">Anglicky<input name="name_en" value="{{ $c->name_en }}" placeholder="{{ $c->exists ? 'doplní se' : '' }}" class="{{ $in }} {{ $c->exists && trim((string) $c->name_en) === '' ? 'border-amber-500 bg-amber-50' : '' }}"></label>
                                <label class="{{ $lb }}">Hex @if($c->exists && \App\Domain\Farm\ColorCatalog::hexMissing($c))<span class="font-normal text-amber-700">chybí</span>@endif<input type="color" name="hex" value="{{ $c->hex ?: '#cccccc' }}" class="mt-1 h-9 w-full rounded-lg border {{ $c->exists && \App\Domain\Farm\ColorCatalog::hexMissing($c) ? 'border-amber-500' : 'border-slate-300' }}"></label>
                                <label class="{{ $lb }}">Fotka výtisku<input type="file" name="photo" accept="image/*" class="mt-1 w-full text-xs"></label>
                                <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="in_stock" value="1" @checked($c->in_stock) class="h-4 w-4 accent-action"> skladem</label>
                                <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked($c->enabled) class="h-4 w-4 accent-action"> nabízet</label>
                                <button class="btn-quiet text-sm">Uložit</button>
                                @if($c->code)<input type="hidden" name="code" value="{{ $c->code }}">@endif
                                <details class="sm:col-span-8 text-xs">
                                    <summary class="cursor-pointer text-slate-500">{{ $c->code ?? 'nastavení' }}@if($c->exists && $c->manufacturer) · {{ $c->manufacturer }}@endif {{ '' }}@if($c->drive_folder) · <a class="underline" target="_blank" href="https://drive.google.com/drive/folders/{{ $c->drive_folder }}">fotky na Drive</a>@endif · vlastní nastavení tisku {{ $c->print_overrides ? '✓' : '–' }}@if($c->exists) · přeřadit / fotka @endif</summary>
                                    <div class="mt-1 grid gap-2 sm:grid-cols-3">
                                        @if($c->exists)
                                            <label class="{{ $lb }}">Druh materiálu (přeřazení špatně zařazené barvy)
                                                <select name="farm_material_id" class="{{ $in }}">@foreach($materials as $mm)<option value="{{ $mm->id }}" @selected($mm->id === $c->farm_material_id)>{{ $mm->label() }}{{ $mm->enabled ? '' : ' (vypnuto)' }}</option>@endforeach</select>
                                            </label>
                                        @endif
                                        <label class="{{ $lb }}">{{ __('farm.admin.maker') }} <span class="font-normal">({{ __('farm.admin.maker_hint') }})</span><input name="manufacturer" list="farm-makers" value="{{ $c->manufacturer }}" placeholder="{{ $m->maker() }}" class="{{ $in }}"></label>
                                        @if($c->exists)
                                            <label class="flex items-center gap-2 pt-5 text-sm"><input type="checkbox" name="remove_photo" value="1" class="h-4 w-4 accent-action"> smazat současnou fotku (nová se nahraje polem „Fotka výtisku“)</label>
                                        @endif
                                    </div>
                                    <div class="mt-1 grid gap-2 sm:grid-cols-2">
                                        <label class="{{ $lb }}">Vlastní nastavení tisku (JSON; nozzle_temp, nozzle_temp_first, bed_temp = do hotového G-code, "process"/"filament" = nové slicování)<input name="print_overrides" value="{{ $c->print_overrides ? json_encode($c->print_overrides, JSON_UNESCAPED_UNICODE) : '' }}" placeholder='{"nozzle_temp": 220, "bed_temp": 60}' class="{{ $in }} font-mono text-xs"></label>
                                        <label class="{{ $lb }}">Výsledky testů (co ukázal testovací objekt)<input name="test_notes" value="{{ $c->test_notes }}" maxlength="2000" class="{{ $in }}"></label>
                                    </div>
                                </details>
                            </form>
                            @if($c->exists)
                                @if(isset($usedColors[$c->id]))
                                    <p class="-mt-1 px-2 text-right text-[11px] text-slate-400">{{ __('farm.admin.color_in_use', ['color' => $c->name, 'where' => __('farm.admin.usage.slots_or_orders')]) }}</p>
                                @else
                                    <form method="post" action="{{ route('admin.farm.colors.delete', $c) }}" class="-mt-1 px-2 text-right" onsubmit="return confirm(@js(__('farm.admin.delete_confirm', ['what' => $c->name])))">@csrf<button class="text-[11px] text-red-700 underline">{{ __('farm.admin.delete') }} {{ $c->name }}</button></form>
                                @endif
                            @endif
                        @endforeach
                    </div>
                </details>
            @endif
        </section>
    @endforeach
</div>
@endsection
