{{-- Photos of one test print and what the judge read from them (tuning page). Expects $t, $photoList, $ai, $evaluated. --}}
@php
    $views = ['top' => 'shora', 'left' => 'zleva', 'right' => 'zprava', 'phone' => 'mobil'];
    $conf = ['high' => 'jisté', 'medium' => 'spíš', 'low' => 'nejisté'];
    $labels = ['stringing' => 'Stringing', 'overhang_ok' => 'Převis', 'bridge' => 'Most', 'elephant' => 'Sloní noha', 'corners' => 'Rohy', 'ironing' => 'Žehlená plocha', 'top' => 'Vrchní plocha', 'wall' => 'Tenká stěna', 'bond' => 'Spojení vrstev', 'warp' => 'Podložka'];
    $status = $ai['status'] ?? null;
@endphp
<div class="mt-2 rounded-lg border border-slate-200 bg-white p-2 text-xs">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="font-semibold">Fotky testu ({{ count($photoList) }})</p>
        <a class="underline" href="{{ route('admin.farm.photobox', ['order' => $t->token]) }}">Vyfotit ve foto-boxu</a>
    </div>
    @if($photoList)
        <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-6">
            @foreach($photoList as $i => $p)
                <figure class="relative">
                    <a href="{{ route('admin.farm.photos.show', [$t, $i]) }}" target="_blank" rel="noopener">
                        <img src="{{ route('admin.farm.photos.show', [$t, $i]) }}?thumb=1" alt="{{ $views[$p['view']] ?? $p['view'] }}" loading="lazy" class="aspect-square w-full rounded-md object-cover">
                    </a>
                    <figcaption class="mt-0.5 flex items-center justify-between gap-1 text-[11px] text-slate-600">
                        <span>{{ $i + 1 }} · {{ $views[$p['view']] ?? $p['view'] }}</span>
                        <form method="post" action="{{ route('admin.farm.photos.destroy', [$t, $i]) }}" onsubmit="return confirm('Smazat fotku?')">@csrf<button class="text-red-700" aria-label="Smazat fotku {{ $i + 1 }}">✕</button></form>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    @endif
    <form method="post" enctype="multipart/form-data" action="{{ route('admin.farm.photos.store', $t) }}" class="mt-2 flex flex-wrap items-center gap-2">
        @csrf
        <input type="file" name="photos[]" multiple accept="image/*,.heic,.heif" class="max-w-full text-xs">
        <button class="btn-quiet !min-h-0 !py-1 text-xs">Nahrát fotky</button>
    </form>

    <div class="mt-2 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-2">
        <form method="post" action="{{ route('admin.farm.photos.judge', $t) }}">
            @csrf
            <button class="btn-primary !min-h-0 !py-1 text-xs" @disabled(! $photoList || in_array($status, ['queued'], true))>{{ $status === 'done' ? 'Vyhodnotit z fotek znovu' : 'Vyhodnotit z fotek (AI)' }}</button>
        </form>
        @if($status === 'queued')
            <span class="text-slate-600">Vyhodnocuje se, obvykle 1–3 minuty. Obnovte stránku.</span>
        @elseif($status === 'failed')
            <span class="text-red-700">Vyhodnocení selhalo: {{ $ai['error'] ?? '' }}</span>
        @elseif($status === 'done')
            <span class="text-slate-600">AI {{ \Illuminate\Support\Carbon::parse($ai['at'])->format('j. n. H:i') }} · {{ $ai['photos'] ?? '?' }} fotek, {{ $ai['crops'] ?? 0 }} přiblížení{{ $evaluated ? ' · formulář už je odeslaný, AI ho nepřepisuje' : ' · formulář níže je předvyplněný' }}</span>
        @endif
    </div>

    @if($status === 'done')
        <div class="mt-2 grid gap-1">
            @foreach($ai['fields'] ?? [] as $k => $f)
                @continue(($f['value'] ?? null) === null && ($f['reason'] ?? '') === '')
                <p><strong>{{ $labels[$k] ?? $k }}:</strong> {{ $f['value'] ?? 'nelze posoudit' }} <span class="text-slate-500">({{ $conf[$f['confidence']] ?? $f['confidence'] }})</span> · {{ $f['reason'] }}</p>
            @endforeach
            @if(! empty($ai['note']))<p class="mt-1"><strong>Celkem {{ $ai['score'] ?? '—' }}/5:</strong> {{ $ai['note'] }}</p>@endif
            @if(! empty($ai['better_photos']))<p class="text-amber-900">📷 {{ $ai['better_photos'] }}</p>@endif
        </div>
    @endif
</div>
