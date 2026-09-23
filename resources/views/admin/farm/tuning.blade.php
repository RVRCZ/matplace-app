@extends('layouts.app', ['title' => __('farm.admin.nav.tuning').' · admin', 'noindex' => true])

@php
    $badge = ['untested' => 'bg-slate-100 text-slate-700', 'testing' => 'bg-amber-100 text-amber-900', 'tuned' => 'bg-ok-soft text-ok'];
    $label = ['untested' => 'nevyzkoušeno', 'testing' => 'testuje se', 'tuned' => 'vyladěno'];
    $src = ['generic' => 'jen profil druhu', 'library' => 'z knihovny', 'inherited' => 'převzato', 'test' => 'z testu', 'manual' => 'ručně'];
@endphp

@section('content')
@include('admin.farm.nav')

<p class="mt-4 text-sm text-slate-600">Každý druh materiálu má na každé tiskárně svůj řádek: co stroj k profilu druhu přidává (teploty, chlazení, rychlosti…) a jak moc mu věříme. Nový druh dostane nejlepší známé výchozí hodnoty (vyladěný profil ze stejného modelu tiskárny, jinak knihovna). Testovací tisk jede z založené cívky přes agenta jako každá zakázka, jen bez zákazníka a ceny.</p>
@if(! $generator)<p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Generátor testovacích objektů (Python + manifold3d) není na tomhle serveru k dispozici; testy nejde spustit.</p>@endif

<div class="mt-4 space-y-4">
    @foreach($printers as $p)
        <section class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-bold">{{ $p->name }} <span class="text-xs font-normal text-slate-500">{{ $p->model }}@if(! $p->enabled) · vypnuta @endif</span></h2>
                <span class="text-xs text-slate-500">v slotech: @foreach($p->slots->filter(fn ($s) => $s->color) as $s){{ $s->slot + 1 }}: {{ $s->color->material->label() }} {{ $s->color->name }}@if(! $loop->last), @endif @endforeach</span>
            </div>
            <table class="mt-2 w-full text-sm">
                <thead><tr class="text-left text-xs text-slate-500"><th class="py-1">Materiál</th><th>Stav</th><th>Původ</th><th>Verze</th><th>Testy</th><th>Hodnocení</th><th></th></tr></thead>
                <tbody>
                    @forelse($rows->get($p->id, collect()) as $r)
                        @php $t = $tests->get($r->id, collect()); @endphp
                        <tr class="border-t border-slate-100">
                            <td class="py-1.5 font-semibold">{{ $r->label() }}@if($r->farm_color_id) <span class="text-xs font-normal text-slate-500">(cívka)</span>@endif</td>
                            <td><span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $badge[$r->status] }}">{{ $label[$r->status] }}</span></td>
                            <td class="text-xs text-slate-600">{{ $src[$r->source] ?? $r->source }}</td>
                            <td class="text-xs text-slate-600">v{{ $r->version }}</td>
                            <td class="text-xs text-slate-600">{{ $t->count() }}@if($t->whereIn('status', ['queued', 'printing', 'uploaded'])->count()) · běží @endif</td>
                            <td class="text-xs">{{ $r->score ? str_repeat('★', $r->score) : '—' }}</td>
                            <td class="text-right"><a href="{{ route('admin.farm.tuning.edit', $r) }}" class="underline">otevřít</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-2 text-slate-500">Žádný zapnutý druh materiálu.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($p->slots->filter(fn ($s) => $s->color)->isNotEmpty())
                <form method="post" action="{{ route('admin.farm.tuning.spool') }}" class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    @csrf
                    <input type="hidden" name="farm_printer_id" value="{{ $p->id }}">
                    <span class="text-slate-600">Vlastní nastavení pro jednu cívku:</span>
                    <select name="farm_color_id" class="rounded-lg border border-slate-300 px-2 py-1">
                        @foreach($p->slots->filter(fn ($s) => $s->color) as $s)<option value="{{ $s->color->id }}">{{ $s->slot + 1 }}: {{ $s->color->material->label() }} {{ $s->color->name }}</option>@endforeach
                    </select>
                    <button class="btn-quiet !min-h-0 !py-1 text-xs">založit řádek cívky</button>
                </form>
            @endif
        </section>
    @endforeach
</div>
@endsection
