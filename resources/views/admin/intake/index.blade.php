@extends('layouts.app', ['title' => 'Rozcestník · admin', 'noindex' => true])

@section('content')
@include('admin.nav')
@include('partials.flash')

<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-extrabold text-ink">Rozcestník na hlavní straně</h1>
        <p class="mt-1 text-sm text-slate-500">Co lidé poslali z hlavní strany (fotka, soubor, popis) a kam to asistent poslal. Poptávky člověku čekají na odpověď: otevřených {{ $open }}.</p>
    </div>
    <nav class="flex gap-2 text-sm" aria-label="Filtr">
        <a href="{{ route('admin.intake.index', ['show' => 'requests']) }}" class="chip {{ $filter === 'requests' ? 'chip-on' : '' }}">Poptávky člověku</a>
        <a href="{{ route('admin.intake.index', ['show' => 'all']) }}" class="chip {{ $filter === 'all' ? 'chip-on' : '' }}">Všechna zadání</a>
    </nav>
</div>

@if($intakes->isEmpty())
    <p class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Zatím nic.</p>
@else
<div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-3 py-2">Kdy</th><th class="px-3 py-2">Druh</th><th class="px-3 py-2">Zadání</th><th class="px-3 py-2">Rozhodnutí</th><th class="px-3 py-2">Poptávka</th><th class="px-3 py-2 text-right">Jistota · ms · Kč</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($intakes as $i)
                <tr class="align-top">
                    <td class="whitespace-nowrap px-3 py-2 text-slate-500"><a href="{{ route('admin.intake.show', $i) }}" class="font-medium text-ink underline">{{ $i->created_at->format('j. n. H:i') }}</a><br><span class="text-xs">{{ $i->locale }}</span></td>
                    <td class="px-3 py-2">{{ ['photo' => 'fotka', 'file' => 'soubor', 'text' => 'text'][$i->kind] ?? $i->kind }}@if($i->hasPhoto()) <span class="text-xs text-ok">· s fotkou</span>@endif</td>
                    <td class="max-w-md px-3 py-2">{{ \Illuminate\Support\Str::limit((string) $i->text, 140) ?: '—' }}@if($i->modelFile)<br><span class="text-xs text-slate-500">{{ $i->modelFile->original_name }}</span>@endif</td>
                    <td class="px-3 py-2">
                        <span class="font-medium">{{ ['tool' => 'nástroj', 'human' => 'člověk', 'shape' => 'tvar', 'file' => 'kalkulačka'][$i->route] ?? '—' }}</span>
                        @if($i->tool_key)<br><span class="text-xs text-slate-500">{{ __('tools.'.$i->tool_key.'.title') }}</span>@endif
                        @if($i->wants_shape)<br><span class="text-xs text-warn">chce tvar podle fotky</span>@endif
                        <br><span class="text-xs text-slate-400">{{ ['new' => 'nové', 'opened' => 'nástroj otevřen', 'human' => 'u člověka', 'closed' => 'uzavřeno'][$i->status] ?? $i->status }}</span>
                    </td>
                    <td class="px-3 py-2">
                        @if($i->request)
                            <span class="{{ $i->request->status === 'new' ? 'font-semibold text-action-dark' : 'text-slate-600' }}">{{ ['new' => 'čeká na odpověď', 'answered' => 'odpovězeno', 'closed' => 'uzavřeno'][$i->request->status] ?? $i->request->status }}</span><br><span class="text-xs text-slate-500">{{ $i->request->email }}</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right text-xs text-slate-500">{{ $i->confidence !== null ? number_format((float) $i->confidence, 2) : '—' }} · {{ $i->duration_ms ?? '—' }} · {{ $i->cost_czk !== null ? number_format((float) $i->cost_czk, 2) : '—' }}<br>{{ $i->model ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $intakes->links() }}</div>
@endif
@endsection
