{{-- one size of a parametric tool: a slider and its number with the unit. $key, $f = [min, max, default, step], $label, $unit, $when (attribute or '') --}}
<div class="tool-num" {!! $when !!} data-field="{{ $key }}">
    <label for="p-{{ $key }}" class="text-sm font-medium text-ink">{{ $label }}</label>
    <div class="mt-1 flex items-center gap-3">
        <input type="range" data-range="{{ $key }}" min="{{ $f[0] }}" max="{{ $f[1] }}" step="{{ $f[3] }}" value="{{ $f[2] }}" class="min-w-0 flex-1 accent-ink" aria-label="{{ $label }}" tabindex="-1">
        <span class="tool-unit" data-unit="{{ $unit }}">
            <input id="p-{{ $key }}" data-param="{{ $key }}" type="number" inputmode="decimal" min="{{ $f[0] }}" max="{{ $f[1] }}" step="{{ $f[3] }}" value="{{ $f[2] }}" class="field !mt-0" aria-describedby="range-{{ $key }}">
        </span>
    </div>
    <span id="range-{{ $key }}" class="sr-only">{{ $f[0] }}–{{ $f[1] }} {{ $unit }}</span>
</div>
