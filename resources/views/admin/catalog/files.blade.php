@extends('layouts.app', ['title' => 'Soubory modelů · admin', 'noindex' => true])

@php
    $dur = fn (int $m) => $m >= 60 ? intdiv($m, 60).' h '.($m % 60).' min' : $m.' min';
    $fileState = ['uploaded' => 'čeká na zpracování', 'processing' => 'zpracovává se', 'ready' => 'připraven', 'failed' => 'zpracování selhalo', 'deleted' => 'smazán'];
@endphp

@section('content')
@include('admin.catalog._tabs')

<section class="rounded-2xl border border-slate-200 bg-white p-4">
    <h2 class="font-bold">Top {{ count($top) }} modelů bez souboru</h2>
    <p class="mt-1 text-xs text-slate-500">
        @if($hasConsole)Podle zobrazení stránek /model/… v Search Console za {{ $consoleDays }} dní, doplněno nejnavštěvovanějšími.@else Podle návštěvnosti (Search Console zatím nemá řádky pro /model/…).@endif
        Postup: otevřít zdroj, stáhnout STL nebo 3MF (u sady největší či hlavní díl), nahrát sem. Soubor zůstává skrytý (nikde ke stažení),
        zpracuje se a rozřeže ve frontě; stránka modelu pak ukáže cenu ve třech materiálech a dobu tisku (docs/AN.md §13, §15).
    </p>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Model</th><th class="px-3 py-2">Zdroj</th><th class="px-3 py-2">Licence</th><th class="px-3 py-2 text-right">Zobrazení SC</th><th class="px-3 py-2 text-right">Návštěvy</th><th class="px-3 py-2">Soubor</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($top as $row)
                    @php $m = $row['model']; @endphp
                    <tr data-top-model="{{ $m->slug }}">
                        <td class="px-3 py-2"><a href="{{ route('admin.catalog.edit', $m->id) }}" class="font-semibold underline">{{ $m->title }}</a>@if($m->slug)<span class="block text-xs text-slate-500">/model/{{ $m->slug }}</span>@endif</td>
                        <td class="px-3 py-2 text-xs">@if($m->hasWebLink())<a href="{{ $m->external_url }}" target="_blank" rel="noopener" class="underline">{{ $m->source }} ↗</a>@else{{ $m->source }}@endif</td>
                        <td class="px-3 py-2 text-xs">{{ $m->license ?: '—' }}<span class="block {{ $m->license_restricted ? 'text-slate-500' : 'text-ok' }}">{{ $m->license_restricted ? 'jen pro sebe' : 'i na prodej' }}</span></td>
                        <td class="px-3 py-2 text-right">{{ $row['impressions'] !== null ? number_format($row['impressions'], 0, ',', ' ') : '–' }}</td>
                        <td class="px-3 py-2 text-right">{{ $m->view_count }}</td>
                        <td class="px-3 py-2">@include('admin.catalog._file_form', ['model' => $m, 'formats' => $formats])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-4 text-center text-slate-500">Každý zobrazený model už má soubor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
    <h2 class="font-bold">Modely se souborem ({{ $withFile->count() }})</h2>
    <p class="mt-1 text-xs text-slate-500">Stav zpracování a řezu; „Rozřezat znovu“ dá řez do fronty (asi minutu), noční <code>catalog:slice</code> bere modely bez řezu sám.</p>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Model</th><th class="px-3 py-2">Soubor</th><th class="px-3 py-2">Řez</th><th class="px-3 py-2"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($withFile as $m)
                    <tr data-file-model="{{ $m->id }}">
                        <td class="px-3 py-2"><a href="{{ route('admin.catalog.edit', $m->id) }}" class="font-semibold underline">{{ $m->title }}</a>@if($m->slug)<span class="block text-xs text-slate-500"><a href="{{ route('catalog.show', $m->slug) }}" target="_blank" class="underline">/model/{{ $m->slug }}</a></span>@endif</td>
                        <td class="px-3 py-2 text-xs">{{ $m->modelFile?->original_name ?? '—' }}<span class="block text-slate-500">{{ $fileState[$m->modelFile?->status ?? ''] ?? ($m->modelFile?->status ?? 'chybí') }}@if($m->modelFile?->error) · {{ $m->modelFile->error }}@endif</span></td>
                        <td class="px-3 py-2 text-xs">
                            @if($m->sliced_at)<span class="font-semibold text-ok">{{ $m->sliced_at->format('j. n. Y') }}</span><span class="block text-slate-500">{{ $dur((int) $m->slice_minutes) }}, {{ $m->slice_grams }} g{{ $m->sliceCalculation ? ' · ' : '' }}@if($m->sliceCalculation)<a href="{{ url('/c/'.$m->sliceCalculation->token) }}" target="_blank" class="underline">kalkulace</a>@endif</span>
                            @elseif($m->slice_error)<span class="text-amber-700">selhal: {{ $m->slice_error }}</span>
                            @else<span class="text-slate-500">čeká</span>@endif
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="post" action="{{ route('admin.catalog.slice', $m->id) }}">@csrf<button class="btn-quiet !min-h-0 py-1.5 text-xs">Rozřezat znovu</button></form>
                                @include('admin.catalog._file_form', ['model' => $m, 'formats' => $formats])
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-4 text-center text-slate-500">Zatím žádný model nemá soubor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
