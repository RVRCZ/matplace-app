@extends('layouts.app', ['title' => 'Příklady pod polem · admin', 'noindex' => true])

{{--
    /admin/intake/hints (docs/AN.md §23.3): the calendar of the examples under the first screen's field. Every event can
    be switched off, its dates changed (day and month, every year) and its examples rewritten per language, without a
    deploy; an empty date or box means what config/home.php and the dictionary say.
--}}
@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $day = fn ($d) => $d ? $d->format('j. n. Y') : '';
    $mine = fn (string $md) => $md === '' ? '' : ((int) substr($md, 3, 2)).'.'.((int) substr($md, 0, 2)).'.';
@endphp

@section('content')
@include('admin.nav')
@include('partials.flash')

<a href="{{ route('admin.intake.index') }}" class="inline-flex min-h-11 items-center gap-1 text-sm text-slate-500 hover:text-ink"><x-icon name="arrow-left" class="h-4 w-4" />Rozcestník</a>
<h1 class="text-2xl font-extrabold text-ink">Příklady pod polem „Co chcete vyrobit?“</h1>
<p class="mt-1 max-w-3xl text-sm text-slate-500">Štítek je krátký název, vzorové přání se po kliknutí vepíše do pole a zákazník ho upraví. Ukáže se nejvýš {{ (int) config('home.intake.hints.seasonal') }} sezonních a zbytek do {{ (int) config('home.intake.hints.total') }} stálých. Událost se nabízí s předstihem a končí pár dní před svým dnem, aby se stihla výroba a doprava.</p>

@if($errors->any())
    <p class="mt-3 rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">{{ $errors->first() }}</p>
@endif

<section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm" id="hints-now">
    <h2 class="font-bold">Dnes na hlavní straně</h2>
    @foreach($now as $locale => $chips)
        <p class="mt-2"><span class="mr-2 inline-block w-6 font-mono text-xs text-slate-500">{{ $locale }}</span>
            @foreach($chips as $chip)
                <span class="chip mb-1 {{ $chip['seasonal'] ? 'border-action text-action-dark' : '' }}" title="{{ $chip['fill'] }}">{{ $chip['label'] }}</span>
            @endforeach
        </p>
    @endforeach
</section>

<form method="post" action="{{ route('admin.intake.hints.save') }}" class="mt-4 space-y-4">
    @csrf
    @foreach($events as $key => $e)
        <section class="rounded-2xl border border-slate-200 bg-white p-4" data-event="{{ $key }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-base font-bold text-ink">
                    <input type="checkbox" name="events[{{ $key }}][on]" value="1" class="h-5 w-5 accent-action" @checked(old('events.'.$key) !== null ? old('events.'.$key.'.on') : ! $e['off'])>
                    {{ $e['name'] }}
                </label>
                <p class="text-xs text-slate-500">
                    @if($e['always'])
                        celý rok
                    @else
                        @foreach($e['windows'] as $locale => $w)
                            <span class="mr-3 {{ $w && $w['open'] ? 'font-semibold text-ok' : '' }}">{{ $locale }}: {{ $w ? $day($w['from']).' – '.$day($w['to']).($w['open'] ? ' (teď)' : '') : '—' }}</span>
                        @endforeach
                    @endif
                </p>
            </div>
            @unless($e['always'])
                <div class="mt-3 grid max-w-md grid-cols-2 gap-3">
                    <label class="block text-sm text-slate-600">Nabízet od (den.měsíc., prázdné = podle kalendáře)<input name="events[{{ $key }}][from]" value="{{ old('events.'.$key.'.from', $mine($e['from'])) }}" placeholder="5.11." class="{{ $in }}"></label>
                    <label class="block text-sm text-slate-600">Nabízet do (den.měsíc.)<input name="events[{{ $key }}][to]" value="{{ old('events.'.$key.'.to', $mine($e['to'])) }}" placeholder="1.12." class="{{ $in }}"></label>
                </div>
            @endunless
            <div class="mt-3 grid gap-3 lg:grid-cols-3">
                @foreach($e['texts'] as $locale => $t)
                    <label class="block text-sm text-slate-600">{{ $locale }}@if(! in_array($locale, $e['locales'], true)) <span class="text-xs text-slate-400">(v tomto jazyce se událost nenabízí)</span>@elseif($t['own']) <span class="text-xs text-action-dark">(upraveno)</span>@endif
                        <textarea name="events[{{ $key }}][texts][{{ $locale }}]" rows="4" class="{{ $in }} font-mono text-xs leading-relaxed" spellcheck="false">{{ old('events.'.$key.'.texts.'.$locale, $t['lines']) }}</textarea>
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-slate-400">Řádek = Štítek | Vzorové přání, nejvýš {{ \App\Domain\Intake\IntakeHints::MAX_EXAMPLES }} řádky. Prázdné pole vrátí původní texty. <code>:year</code> se nahradí rokem události. Původní příklady vedou: {{ implode(', ', array_map(fn ($x) => $x === 'human' ? 'člověk' : $x, $e['expect'])) }}; po přepsání to ověřte vlastním zadáním.</p>
        </section>
    @endforeach
    <button class="btn-primary">Uložit</button>
</form>
@endsection
