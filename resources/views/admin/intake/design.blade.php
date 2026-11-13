@extends('layouts.app', ['title' => 'Volný návrh z úvodní strany · admin', 'noindex' => true])

{{--
    /admin/intake/design (docs/AN.md §24.4): the free design of the home page. A wish no tool has a template for is made
    by the model generator from the words; every run costs money, so it is off until it is switched on here, and it has
    a count per visitor and day and a count for the whole site and day. The confidence a tool opens directly from is
    here too. Stored in the farm's settings under `home.design`, no deploy.
--}}
@php
    $in = 'mt-1 w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-sm font-semibold text-ink';
@endphp

@section('content')
@include('admin.nav')
@include('partials.flash')

<a href="{{ route('admin.intake.index') }}" class="inline-flex min-h-11 items-center gap-1 text-sm text-slate-500 hover:text-ink"><x-icon name="arrow-left" class="h-4 w-4" />Rozcestník</a>
<h1 class="text-2xl font-extrabold text-ink">Volný návrh z úvodní strany</h1>
<p class="mt-1 max-w-3xl text-sm text-slate-500">Přání, na které nemáme nástroj (nebo si jím asistent není jistý), dostane rovnou 3D návrh od generátoru modelů podle textu. Každé generování stojí peníze: proto je vypnuté, dokud ho tady nezapnete, a má počty na návštěvníka i denní strop. Jedno použití = jedno spuštění generátoru (první návrh nebo úprava slovy); kopie dřívějšího stejného výsledku, nepovedené a placené běhy se nepočítají. Návštěvníka bez účtu poznáme podle cookie prohlížeče a podle IP adresy za posledních {{ \App\Domain\Intake\IntakeDesign::IP_DAYS }} dní; po přihlášení se k účtu počítá i to, co udělal v témže prohlížeči předtím. Správce se do počtů nepočítá.</p>

@if($errors->any())
    <p class="mt-3 rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">{{ $errors->first() }}</p>
@endif

<section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm" id="design-now">
    <h2 class="font-bold">Stav</h2>
    <ul class="mt-2 space-y-1">
        <li>Generátor modelů: <strong>{{ $generator }}</strong> @if(! $ready)<span class="text-danger">(není nastavený: návrhy se nespustí, ani když je přepínač zapnutý)</span>@endif</li>
        <li>Dnes spuštěno volných návrhů: <strong>{{ $today }}</strong> z {{ $settings['per_day'] }}</li>
        <li>Odhad ceny jednoho návrhu z textu: <strong>{{ $costCzk !== null ? number_format($costCzk, 1, ',', ' ').' Kč' : 'neznámá' }}</strong> (podle ceníku v config/ai.php; skutečné částky jsou v <a class="underline" href="{{ url('/admin/ai') }}">/admin/ai</a>)</li>
    </ul>
</section>

<form method="post" action="{{ route('admin.intake.design.save') }}" class="mt-4 grid max-w-3xl gap-4 rounded-2xl border border-slate-200 bg-white p-4" id="design-form">
    @csrf
    <label class="flex items-start gap-3 text-sm text-ink">
        <input type="hidden" name="enabled" value="0">
        <input type="checkbox" name="enabled" value="1" class="mt-0.5 h-5 w-5 shrink-0 accent-action" @checked(old('enabled', $settings['enabled']))>
        <span><span class="font-semibold">Volný návrh zapnutý</span><br><span class="text-slate-500">Vypnuto: přání bez nástroje dostane větu a nabídku poptávky návrháři jako dosud; nejistý nástroj se otevře s poznámkou, že si asistent není jistý.</span></span>
    </label>
    <label class="{{ $lb }}">Zdarma bez účtu: návrhů a úprav slovy na návštěvníka (celkem, ne za den)
        <input type="number" name="free_guest" min="0" max="1000" value="{{ old('free_guest', $settings['free_guest']) }}" class="{{ $in }}"></label>
    <label class="{{ $lb }}">Dalších zdarma po přihlášení
        <input type="number" name="free_user" min="0" max="1000" value="{{ old('free_user', $settings['free_user']) }}" class="{{ $in }}"></label>
    <label class="{{ $lb }}">Cena dalšího návrhu nebo úpravy z kreditu (Kč s DPH; prázdné = zatím se neprodává)
        <input type="number" name="price" min="0" max="10000" step="0.01" value="{{ old('price', $settings['price']) }}" class="{{ $in }}">
        <span class="mt-1 block text-xs font-normal text-slate-500">Dokud je pole prázdné, placená volba se nikde neukáže: po vyčerpání volných návrhů dostane přihlášený větu, že další zatím nenabízíme, a nabídku nástroje nebo návrháře.</span></label>
    <label class="{{ $lb }}">Návrhů celkem za den (strop nákladů)
        <input type="number" name="per_day" min="0" max="5000" value="{{ old('per_day', $settings['per_day']) }}" class="{{ $in }}"></label>
    <label class="{{ $lb }}">Jistota, od které se nástroj otevře bez váhání (0–1)
        <input type="number" name="direct_confidence" min="0" max="1" step="0.05" value="{{ old('direct_confidence', $settings['direct_confidence']) }}" class="{{ $in }}">
        <span class="mt-1 block text-xs font-normal text-slate-500">Pod ní: je-li volný návrh zapnutý, vygeneruje se návrh; jinak se nástroj otevře s poznámkou. Výchozí {{ \App\Domain\Intake\IntakeRouter::DIRECT_CONFIDENCE }}.</span></label>
    <div><button class="btn-primary">Uložit</button></div>
</form>
@endsection
