{{-- Spare-part request: what the customer told us, for the customer's page and for the printer. --}}
@php $d = (array) $inquiry->details; @endphp
<div class="card p-4 text-sm">
    <div class="rounded-lg bg-action-soft px-3 py-2 text-action-dark">{{ __('spare.badge') }}</div>
    @if(!empty($d['photos']))
        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-5">
            @foreach($d['photos'] as $p)
                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($p) }}" target="_blank"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($p) }}" alt="{{ __('spare.photos') }}" loading="lazy" class="aspect-square w-full rounded-lg object-cover"></a>
            @endforeach
        </div>
    @endif
    <dl class="mt-3 space-y-2">
        <div><dt class="text-muted">{{ __('spare.what') }}</dt><dd class="whitespace-pre-line text-ink">{{ $d['what'] ?? '' }}</dd></div>
        @if(!empty($d['use']))<div><dt class="text-muted">{{ __('spare.use') }}</dt><dd class="whitespace-pre-line text-ink">{{ $d['use'] }}</dd></div>@endif
        @if(!empty($d['dims']))<div><dt class="text-muted">{{ __('spare.dims') }}</dt><dd class="font-semibold text-ink">{{ collect($d['dims'])->map(fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', ' '), '0'), ','))->join(' × ') }} mm</dd></div>@endif
        <div><dt class="text-muted">{{ __('spare.load') }}</dt><dd class="text-ink">{{ __('spare.load.'.($d['load'] ?? 'unknown')) }}@if(!empty($d['environment'])) · {{ collect($d['environment'])->map(fn ($e) => __('spare.env.'.$e))->join(', ') }}@endif</dd></div>
        <div><dt class="text-muted">{{ __('calc.material') }}</dt><dd class="text-ink">{{ empty($d['material_known']) ? __('spare.material.unknown') : $inquiry->material_code }} · {{ $inquiry->quantity }} {{ __('inquiry.pcs') }}</dd></div>
        @if(!empty($d['original_available']))<div class="text-ok">✓ {{ __('spare.original') }}</div>@endif
    </dl>
</div>
