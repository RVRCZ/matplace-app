@extends('layouts.app', ['title' => $row->label().' · '.$row->printer->name.' · admin', 'noindex' => true])

@php
    $json = fn ($v) => $v ? json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
    $o = (array) $row->overrides;
    $m = $row->material;
    $label = ['untested' => 'nevyzkoušeno', 'testing' => 'testuje se', 'tuned' => 'vyladěno'];
@endphp

@section('content')
@include('admin.farm.nav')

<div class="mt-4 flex flex-wrap items-center justify-between gap-2">
    <h1 class="text-xl font-extrabold">{{ $row->label() }} <span class="text-base font-normal text-slate-500">na {{ $row->printer->name }}</span></h1>
    <a href="{{ route('admin.farm.tuning') }}" class="text-sm underline">← {{ __('farm.admin.nav.tuning') }}</a>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-[1.1fr_1fr]">
    <div class="space-y-4">
        <form method="post" action="{{ route('admin.farm.tuning.save', $row) }}" class="rounded-2xl border border-slate-200 bg-white p-4">
            @csrf
            <h2 class="font-bold">Co tenhle stroj k profilu druhu přidává <span class="text-xs font-normal text-slate-500">verze {{ $row->version }} · {{ $row->source }}</span></h2>
            <p class="mt-1 text-xs text-slate-500">Druh {{ $m->label() }} sám říká: tryska {{ $m->nozzle_temp ?? '—' }} / {{ $m->nozzle_temp_first ?? '—' }} °C, podložka {{ $m->bed_temp ?? '—' }} °C, profil {{ $m->filament_profile }}. Prázdné pole = platí hodnota druhu.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="{{ $lb }}">Tryska (°C)<input type="number" name="nozzle_temp" value="{{ old('nozzle_temp', $o['nozzle_temp'] ?? '') }}" placeholder="{{ $m->nozzle_temp }}" class="{{ $in }}"></label>
                <label class="{{ $lb }}">Tryska 1. vrstva (°C)<input type="number" name="nozzle_temp_first" value="{{ old('nozzle_temp_first', $o['nozzle_temp_first'] ?? '') }}" placeholder="{{ $m->nozzle_temp_first }}" class="{{ $in }}"></label>
                <label class="{{ $lb }}">Podložka (°C)<input type="number" name="bed_temp" value="{{ old('bed_temp', $o['bed_temp'] ?? '') }}" placeholder="{{ $m->bed_temp }}" class="{{ $in }}"></label>
            </div>
            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                <label class="{{ $lb }}">Přepisy filamentu (JSON, hodnoty jako pole: {"fan_max_speed": ["100"]})<textarea name="filament" rows="7" class="{{ $in }} font-mono text-xs">{{ old('filament', $json($o['filament'] ?? null)) }}</textarea></label>
                <label class="{{ $lb }}">Přepisy procesu (JSON, hodnoty jako text: {"outer_wall_speed": "80"})<textarea name="process" rows="7" class="{{ $in }} font-mono text-xs">{{ old('process', $json($o['process'] ?? null)) }}</textarea></label>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="{{ $lb }}">Stav
                    <select name="status" class="{{ $in }}">@foreach($label as $k => $v)<option value="{{ $k }}" @selected(old('status', $row->status) === $k)>{{ $v }}</option>@endforeach</select>
                </label>
                <label class="{{ $lb }}">Hodnocení (1–5)<input type="number" name="score" min="1" max="5" value="{{ old('score', $row->score) }}" class="{{ $in }}"></label>
                <label class="{{ $lb }}">Poznámka ke změně<input name="change_note" maxlength="300" class="{{ $in }}"></label>
            </div>
            <label class="{{ $lb }} mt-3">Poznámky (co testy ukázaly)<textarea name="notes" rows="3" maxlength="2000" class="{{ $in }}">{{ old('notes', $row->notes) }}</textarea></label>
            <button class="btn-primary mt-3 text-sm">Uložit jako novou verzi</button>
        </form>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <h2 class="font-bold">Výsledné nastavení tisku <span class="text-xs font-normal text-slate-500">vrstvy: {{ implode(' → ', $effective->layers) }}</span></h2>
            <p class="mt-1 text-xs text-slate-600">Teploty do G-code: tryska {{ $effective->temps['nozzle'] ?? '—' }} / {{ $effective->temps['nozzle_first'] ?? '—' }} °C, podložka {{ $effective->temps['bed'] ?? '—' }} °C</p>
            @php $printerProcess = (array) $row->printer->process_overrides; @endphp
            <p class="mt-1 text-xs text-slate-600">Podpěry: pod plochy plošší než {{ $effective->process['support_threshold_angle'] ?? $printerProcess['support_threshold_angle'] ?? 30 }}° od vodorovné roviny, mosty bez podpěr do {{ $effective->process['max_bridge_length'] ?? $printerProcess['max_bridge_length'] ?? 10 }} mm
                <span class="text-slate-400">— přebírá se z vyhodnocení testu (převis čistý do, most)</span></p>
            <details class="mt-2 text-xs"><summary class="cursor-pointer">filament ({{ count($effective->filament) }} klíčů) · proces ({{ count($effective->process) }} klíčů)</summary>
                <pre class="mt-2 overflow-x-auto whitespace-pre-wrap">{{ $json(['filament_profile' => $effective->filamentProfile, 'filament' => $effective->filament, 'process' => $effective->process]) }}</pre>
            </details>
        </section>

        @if($row->history)
            <details class="rounded-2xl border border-slate-200 bg-white p-4 text-xs">
                <summary class="cursor-pointer text-sm font-bold">Historie verzí ({{ count($row->history) }})</summary>
                <ul class="mt-2 space-y-2">
                    @foreach(array_reverse($row->history) as $h)
                        <li><strong>v{{ $h['version'] }}</strong> · {{ $h['source'] }} · {{ $h['at'] }}@if($h['note'] ?? null) · {{ $h['note'] }}@endif<pre class="mt-1 overflow-x-auto whitespace-pre-wrap text-slate-600">{{ $json($h['overrides']) ?: '(nic)' }}</pre></li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>

    <div class="space-y-4">
        <form method="post" action="{{ route('admin.farm.tuning.test', $row) }}" class="rounded-2xl border border-action bg-white p-4">
            @csrf
            <h2 class="font-bold">Vytisknout test</h2>
            @if($slots->isEmpty())
                <p class="mt-1 text-sm text-slate-600">V {{ $row->printer->name }} teď není založená žádná cívka tohoto druhu. Založte ji ve slotu tiskárny a vraťte se sem.</p>
            @elseif(! $generator)
                <p class="mt-1 text-sm text-amber-900">Generátor testovacích objektů není k dispozici.</p>
            @else
                <p class="mt-1 text-xs text-slate-500">Tiskne se s výsledným nastavením výše; pole níže ho pro tenhle test přepíší. Zakázka jde rovnou do fronty tiskárny (podložka musí být potvrzená jako volná).</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="{{ $lb }}">Cívka (slot)
                        <select name="slot" class="{{ $in }}">@foreach($slots as $s)<option value="{{ $s->id }}">{{ $s->slot + 1 }}: {{ $s->color->name }} ({{ round($s->remaining_g) }} g)</option>@endforeach</select>
                    </label>
                    <label class="{{ $lb }}">Objekt
                        <select name="object" id="test-object" class="{{ $in }}">@foreach($objects as $k => $spec)<option value="{{ $k }}" @selected(old('object') === $k)>{{ __('farm.test.object.'.$k) }} · ~{{ $spec['minutes'] }} min</option>@endforeach</select>
                    </label>
                    <label class="{{ $lb }}">Tryska (°C)<input type="number" name="t_nozzle_temp" value="{{ old('t_nozzle_temp') }}" placeholder="{{ $effective->temps['nozzle'] ?? '' }}" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">Podložka (°C)<input type="number" name="t_bed_temp" value="{{ old('t_bed_temp') }}" placeholder="{{ $effective->temps['bed'] ?? '' }}" class="{{ $in }}"></label>
                </div>
                <label id="ironing-field" class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="t_ironing" value="1" @checked(old('t_ironing', true)) class="h-4 w-4 accent-action"> Žehlit plošinu (ironing) – 30 × 30 mm ukáže, jak žehlení s tímto filamentem dopadá</label>
                <label id="scarf-field" class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="t_scarf" value="1" @checked(old('t_scarf')) class="h-4 w-4 accent-action"> Šikmý šev (scarf joint) – bez zaškrtnutí se tiskne šev podle řádku; pro srovnání vytiskněte oba</label>
                <div id="tower-fields" class="mt-3 grid gap-3 sm:grid-cols-3">
                    <label class="{{ $lb }}">Pater<input type="number" name="floors" min="3" max="10" value="{{ old('floors', 5) }}" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">Spodní patro (°C)<input type="number" name="start" value="{{ old('start') }}" placeholder="auto" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">Krok (°C)<input type="number" name="step" value="{{ old('step', -5) }}" class="{{ $in }}"></label>
                    <p class="text-xs text-slate-500 sm:col-span-3">Auto: střed = tryska výše, patra jdou od nejteplejšího dole. Každé patro má 10 mm, most a převis 45°.</p>
                </div>
                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <label class="{{ $lb }}">Filament navíc (JSON)<textarea name="t_filament" rows="3" class="{{ $in }} font-mono text-xs">{{ old('t_filament') }}</textarea></label>
                    <label class="{{ $lb }}">Proces navíc (JSON)<textarea name="t_process" rows="3" class="{{ $in }} font-mono text-xs">{{ old('t_process') }}</textarea></label>
                </div>
                <button class="btn-primary mt-3 w-full text-sm">Vytisknout test</button>
                <script>
                    (function () { const s = document.getElementById('test-object'), t = document.getElementById('tower-fields'); const i = document.getElementById('ironing-field'); const c = document.getElementById('scarf-field'); const f = () => { t.style.display = s.value === 'temp_tower' ? '' : 'none'; i.style.display = s.value === 'ironing' ? '' : 'none'; c.style.display = s.value === 'seam' ? '' : 'none'; }; s.addEventListener('change', f); f(); })();
                </script>
            @endif
        </form>

        <section id="testy" class="scroll-mt-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-bold">Testy</h2>
                @if($showHidden)
                    <a class="text-xs underline" href="{{ route('admin.farm.tuning.edit', $row) }}#testy">Schovat skryté testy</a>
                @elseif($hiddenTests)
                    <a class="text-xs underline" href="{{ route('admin.farm.tuning.edit', [$row, 'skryte' => 1]) }}#testy">Zobrazit skryté testy ({{ $hiddenTests }})</a>
                @endif
            </div>
            <ol class="mt-2 list-decimal space-y-1 rounded-lg bg-slate-50 p-2 pl-6 text-xs text-slate-600">
                <li><strong>Uložit hodnocení</strong> – zapíšete, co jste na výtisku viděli, a poradce z toho navrhne úpravy. Tisky se tím nemění.</li>
                <li><strong>Zkusit navržené úpravy</strong> – úpravy se zapíšou do řádku jako nová verze (stav Testuje se) a <em>tisky už pojedou s nimi</em>. Potom vytiskněte další test.</li>
                <li><strong>Test je dobrý – označit jako vyladěné</strong> – řádek dostane přesně to nastavení, se kterým se test tiskl, a stav Vyladěno. Platí jen pro tento materiál na této tiskárně; doporučené úpravy se nepřevezmou.</li>
            </ol>
            @forelse($tests as $t)
                @php $c = (array) ($t->test_params['candidate'] ?? []); $temps = $t->test_params['temps'] ?? null; @endphp
                @php $isHidden = ! empty($t->test_params['hidden']); $running = in_array($t->status, ['uploaded', 'sliced', 'queued', 'printing'], true); @endphp
                <div id="test-{{ $t->id }}" class="mt-2 scroll-mt-4 rounded-xl border border-slate-200 p-3 target:border-action target:ring-2 target:ring-action/40 {{ $isHidden ? 'opacity-60' : '' }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <a class="font-semibold underline" href="{{ route('admin.farm.orders.show', $t) }}">{{ $t->number }}</a>
                        <span class="flex flex-wrap items-center gap-2 text-xs">
                            {{ __('farm.test.object.'.($t->test_params['object'] ?? 'quick')) }} · {{ __('farm.status.'.$t->status) }} · {{ $t->created_at->format('j. n. H:i') }}
                            @if($isHidden)
                                <form method="post" action="{{ route('admin.farm.tuning.hide', [$row, $t]) }}">@csrf<input type="hidden" name="show" value="1"><button class="underline" title="Test se vrátí do seznamu">vrátit do seznamu</button></form>
                            @elseif(! $running)
                                <form method="post" action="{{ route('admin.farm.tuning.hide', [$row, $t]) }}" data-confirm="Skrýt {{ $t->number }} ze seznamu? Nic se nemaže – fotky i hodnocení zůstanou a test jde kdykoli vrátit.">@csrf<button class="rounded border border-slate-300 px-1.5 text-slate-600 hover:bg-slate-100" title="Zmizí ze seznamu, nic se nemaže">skrýt</button></form>
                            @endif
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-600">tryska {{ $c['nozzle_temp'] ?? '—' }} °C · podložka {{ $c['bed_temp'] ?? '—' }} °C · verze řádku {{ $t->test_params['row_version'] ?? '?' }}@if(! empty($t->test_params['ironing'])) · ironing {{ $c['process']['ironing_flow'] ?? '' }} / {{ $c['process']['ironing_speed'] ?? '' }} mm/s / {{ $c['process']['ironing_spacing'] ?? '' }} mm @endif @if(($t->test_params['object'] ?? '') === 'seam') · šev: {{ in_array($c['process']['seam_slope_type'] ?? 'none', ['external', 'all'], true) ? 'scarf '.$c['process']['seam_slope_type'].', délka '.($c['process']['seam_slope_min_length'] ?? '?').' mm' : 'bez scarfu' }}@isset($c['process']['seam_gap']), mezera {{ $c['process']['seam_gap'] }}@endisset @endif @if($t->quality_rating) · {{ str_repeat('★', $t->quality_rating) }}@endif</p>
                    @if($temps)<p class="mt-1 text-xs text-slate-600">patra zdola: {{ implode(' · ', array_map(fn ($i, $v) => ($i + 1).': '.$v.' °C', array_keys($temps), $temps)) }}</p>@endif
                    @if(in_array($t->status, ['done', 'handed_over']))
                        @php $cubeMm = \App\Domain\Farm\TuningAdvisor::cubeMm((string) ($t->test_params['object'] ?? 'quick')); $res = (array) ($t->test_params['result'] ?? []); $adv = $t->test_params['advice'] ?? null; $sel = 'rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs';
                            // the judge's reading prefills whatever the operator has not answered yet; saved only when the form is sent
                            $ai = (array) ($t->test_params['ai'] ?? []); $aiF = ($ai['status'] ?? null) === 'done' ? (array) ($ai['fields'] ?? []) : [];
                            if ($aiF) { foreach ($aiF as $k => $f) { if (! array_key_exists($k, $res) && ($f['value'] ?? null) !== null) { $res[$k] = $f['value']; } } if (! isset($res['score']) && ! empty($ai['score'])) { $res['score'] = $ai['score']; } if (! isset($res['note']) && ! empty($ai['note'])) { $res['note'] = $ai['note']; } }
                            $photoList = (array) ($t->test_params['photos'] ?? []); $isSeam = ($t->test_params['object'] ?? '') === 'seam'; @endphp
                        @include('admin.farm.partials.test_photos', ['t' => $t, 'photoList' => $photoList, 'ai' => $ai, 'evaluated' => ! empty($t->test_params['result'])])
                        <details class="mt-2 rounded-lg bg-slate-50 p-2 text-xs" @if(! $adv) open @endif>
                            <summary class="cursor-pointer font-semibold">Vyhodnocení {{ $res ? '✓' : '' }}</summary>
                            <form method="post" action="{{ route('admin.farm.tuning.evaluate', [$row, $t]) }}" class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @csrf
                                @if($temps)
                                    <label class="{{ $lb }}">Nejlepší patro<select name="best_floor" class="{{ $sel }} w-full"><option value="">—</option>@foreach($temps as $i => $v)<option value="{{ $i + 1 }}" @selected(($res['best_floor'] ?? null) == $i + 1)>{{ $i + 1 }}: {{ $v }} °C</option>@endforeach</select></label>
                                @else
                                    <label class="{{ $lb }}">Rozměr X (mm)<input type="number" step="0.01" name="cube_x" value="{{ $res['cube_x'] ?? '' }}" placeholder="{{ $cubeMm }}" class="{{ $sel }} w-full"></label>
                                    <label class="{{ $lb }}">Rozměr Y (mm)<input type="number" step="0.01" name="cube_y" value="{{ $res['cube_y'] ?? '' }}" placeholder="{{ $cubeMm }}" class="{{ $sel }} w-full"></label>
                                    <label class="{{ $lb }}">Výška Z (mm)<input type="number" step="0.01" name="cube_z" value="{{ $res['cube_z'] ?? '' }}" placeholder="{{ $cubeMm }}" class="{{ $sel }} w-full"></label>
                                    @if($isSeam)
                                    <label class="{{ $lb }}">Šev na válci a hranolu<select name="seam" class="{{ $sel }} w-full"><option value="">—</option>@foreach([0 => 'není vidět', 1 => 'slabá linka', 2 => 'zřetelná linka', 3 => 'hrubý, ruší'] as $v => $l)<option value="{{ $v }}" @selected(isset($res['seam']) && $res['seam'] == $v)>{{ $l }}</option>@endforeach</select></label>
                                    <label class="{{ $lb }}">Vada na švu<select name="seam_fault" class="{{ $sel }} w-full"><option value="">—</option>@foreach(['none' => 'žádná', 'bulge' => 'boule, hrbolky', 'gap' => 'díry, propad'] as $v => $l)<option value="{{ $v }}" @selected(($res['seam_fault'] ?? null) === $v)>{{ $l }}</option>@endforeach</select></label>
                                    @else
                                    <label class="{{ $lb }}">Otvor (mm)<input type="number" step="0.01" name="hole" value="{{ $res['hole'] ?? '' }}" placeholder="8" class="{{ $sel }} w-full"></label>
                                    <label class="{{ $lb }}">Převis čistý do<select name="overhang_ok" class="{{ $sel }} w-full"><option value="">—</option>@foreach([70, 60, 50, 40, 30, 0] as $v)<option value="{{ $v }}" @selected(isset($res['overhang_ok']) && $res['overhang_ok'] == $v)>{{ $v ? $v.'°' : 'žádný' }}</option>@endforeach</select></label>
                                    <label class="{{ $lb }}">Sloní noha<select name="elephant" class="{{ $sel }} w-full"><option value="">—</option>@foreach([0 => 'žádná', 1 => 'mírná', 2 => 'silná'] as $v => $l)<option value="{{ $v }}" @selected(isset($res['elephant']) && $res['elephant'] == $v)>{{ $l }}</option>@endforeach</select></label>
                                    @endif
                                    <label class="{{ $lb }}">Rohy<select name="corners" class="{{ $sel }} w-full"><option value="">—</option>@foreach(['ok' => 'ostré', 'bulge' => 'vyboulené', 'round' => 'zaoblené', 'gaps' => 'mezery ve stěně'] as $v => $l)<option value="{{ $v }}" @selected(($res['corners'] ?? null) === $v)>{{ $l }}</option>@endforeach</select></label>
                                    @if(! $isSeam)
                                    <label class="{{ $lb }}">Vrchní plocha<select name="top" class="{{ $sel }} w-full"><option value="">—</option>@foreach(['ok' => 'hladká', 'pillow' => 'zvlněná', 'gaps' => 'děravá'] as $v => $l)<option value="{{ $v }}" @selected(($res['top'] ?? null) === $v)>{{ $l }}</option>@endforeach</select></label>
                                    @if(($t->test_params['object'] ?? '') === 'detailed')
                                        <label class="{{ $lb }}">Žehlená plocha{{ ! empty($t->test_params['ironing']) ? '' : ' (bez ironingu)' }}<select name="ironing" class="{{ $sel }} w-full"><option value="">—</option>@foreach(['ok' => 'hladká, lesklá', 'lines' => 'viditelné čáry', 'bumps' => 'hrbolky, přebytek', 'rough' => 'hrubá, matná'] as $v => $l)<option value="{{ $v }}" @selected(($res['ironing'] ?? null) === $v)>{{ $l }}</option>@endforeach</select></label>
                                    @endif
                                    <label class="{{ $lb }}">Tenká stěna<select name="wall" class="{{ $sel }} w-full"><option value="">—</option>@foreach(['ok' => 'celistvá', 'gaps' => 'děravá', 'missing' => 'chybí'] as $v => $l)<option value="{{ $v }}" @selected(($res['wall'] ?? null) === $v)>{{ $l }}</option>@endforeach</select></label>
                                    @endif
                                @endif
                                @if(! $isSeam)
                                <label class="{{ $lb }}">Stringing<select name="stringing" class="{{ $sel }} w-full"><option value="">—</option>@foreach([0 => 'žádný', 1 => 'vlásky', 2 => 'zřetelný', 3 => 'silný'] as $v => $l)<option value="{{ $v }}" @selected(isset($res['stringing']) && $res['stringing'] == $v)>{{ $l }}</option>@endforeach</select></label>
                                <label class="{{ $lb }}">Most<select name="bridge" class="{{ $sel }} w-full"><option value="">—</option>@foreach(['ok' => 'rovný', 'sag' => 'prověšený', 'fail' => 'spadl'] as $v => $l)<option value="{{ $v }}" @selected(($res['bridge'] ?? null) === $v)>{{ $l }}</option>@endforeach</select></label>
                                <label class="{{ $lb }}">Spojení vrstev<select name="bond" class="{{ $sel }} w-full"><option value="">—</option>@foreach(['ok' => 'pevné', 'weak' => 'slabé, loupe se'] as $v => $l)<option value="{{ $v }}" @selected(($res['bond'] ?? null) === $v)>{{ $l }}</option>@endforeach</select></label>
                                <label class="{{ $lb }}">Podložka<select name="warp" class="{{ $sel }} w-full"><option value="">—</option>@foreach(['ok' => 'drží', 'lift' => 'rohy se zvedly'] as $v => $l)<option value="{{ $v }}" @selected(($res['warp'] ?? null) === $v)>{{ $l }}</option>@endforeach</select></label>
                                @endif
                                <label class="{{ $lb }}">Celkem (1–5)<select name="score" class="{{ $sel }} w-full"><option value="">—</option>@foreach([5, 4, 3, 2, 1] as $q)<option value="{{ $q }}" @selected(($res['score'] ?? null) == $q)>{{ $q }}</option>@endforeach</select></label>
                                <label class="{{ $lb }} col-span-2 sm:col-span-3">Poznámka<input name="note" maxlength="500" value="{{ $res['note'] ?? '' }}" class="{{ $sel }} w-full"></label>
                                @if($aiF && empty($t->test_params['result']))
                                    <p class="col-span-2 rounded-lg bg-amber-50 p-2 text-amber-900 sm:col-span-3">Předvyplněno podle AI z fotek. Zkontrolujte, opravte a teprve pak odešlete.</p>
                                @endif
                                <div class="col-span-2 sm:col-span-3">
                                    <button class="btn-quiet !min-h-0 !py-1 text-xs">1 · Uložit hodnocení</button>
                                    <p class="mt-1 text-slate-500">Zapíše hodnocení k testu a poradce navrhne úpravy. Na tisky zatím nic nemění.</p>
                                </div>
                            </form>
                            @if(is_array($adv))
                                <div class="mt-3 rounded-lg border border-slate-200 bg-white p-2">
                                    <p class="font-semibold">Návrh úprav</p>
                                    @forelse($adv['advice'] ?? [] as $x)
                                        <p class="mt-1"><code>{{ $x['setting'] }}</code>: {{ $x['from'] ?? '—' }} → <strong>{{ $x['to'] }}</strong> <span class="text-slate-500">· {{ $x['reason'] }}</span></p>
                                    @empty
                                        <p class="mt-1 text-slate-500">Podle vyhodnocení není co měnit.</p>
                                    @endforelse
                                    @foreach($adv['notes'] ?? [] as $n)<p class="mt-1 text-amber-900">⚠ {{ $n }}</p>@endforeach
                                    @if(! empty($adv['advice']))
                                        <form method="post" action="{{ route('admin.farm.tuning.apply', [$row, $t]) }}" class="mt-2" data-confirm="Zapsat navržené úpravy do řádku? Tisky {{ $row->label() }} na {{ $row->printer->name }} s nimi hned pojedou.">@csrf
                                            <button class="btn-primary !min-h-0 !py-1 text-xs">2 · Zkusit navržené úpravy</button>
                                            <p class="mt-1 text-slate-500">Úpravy se zapíšou do řádku jako nová verze (stav Testuje se) a tisky tohoto materiálu na této tiskárně už pojedou s nimi. Potom vytiskněte další test.</p>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </details>
                        @if($t->quality_rating && $t->quality_rating < 4)
                            <p class="mt-2 text-xs text-slate-500">Test má hodnocení {{ $t->quality_rating }}/5, jako vyladěné ho označit nejde. Použijte krok 2 · Zkusit navržené úpravy.</p>
                        @else
                        <form method="post" action="{{ route('admin.farm.tuning.adopt', [$row, $t]) }}" class="mt-2 flex flex-wrap items-end gap-2 text-xs" data-confirm="{{ $t->number }} tiskl dobře? Řádek {{ $row->label() }} na {{ $row->printer->name }} dostane nastavení tohoto testu a stav Vyladěno.">
                            @csrf
                            @if($temps)
                                <label class="{{ $lb }}">Nejlepší patro
                                    <select name="nozzle_temp" class="rounded-lg border border-slate-300 px-2 py-1">@foreach($temps as $i => $v)<option value="{{ $v }}">{{ $i + 1 }}: {{ $v }} °C</option>@endforeach</select>
                                </label>
                            @endif
                            <label class="{{ $lb }}">Hodnocení<select name="score" class="rounded-lg border border-slate-300 px-2 py-1"><option value="">—</option>@foreach([5, 4] as $q)<option value="{{ $q }}" @selected($t->quality_rating == $q)>{{ $q }}</option>@endforeach</select></label>
                            <input name="note" maxlength="300" placeholder="co test ukázal" class="rounded-lg border border-slate-300 px-2 py-1">
                            <button class="btn-quiet !min-h-0 !py-1 text-xs">3 · Test je dobrý – označit jako vyladěné</button>
                            <p class="w-full text-slate-500">Řádek dostane přesně to nastavení, se kterým se {{ $t->number }} tiskl, a stav Vyladěno. Platí pro {{ $row->label() }} na {{ $row->printer->name }}; jiné tiskárny se nemění a doporučené úpravy se nepřevezmou.</p>
                        </form>
                        @endif
                    @endif
                </div>
            @empty
                <p class="mt-1 text-slate-500">Zatím žádný test.</p>
            @endforelse
        </section>
        <script>
            document.querySelectorAll('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => { if (! confirm(f.dataset.confirm)) e.preventDefault(); }));
            // the anchor jump happens before the test photos have loaded; they push the card down and the screen ended
            // up on another test (29 Sep 2026) - once everything is in, go to the card again
            window.addEventListener('load', () => { const t = location.hash && document.getElementById(location.hash.slice(1)); if (t) t.scrollIntoView({ block: 'start' }); });
        </script>
    </div>
</div>
@endsection
