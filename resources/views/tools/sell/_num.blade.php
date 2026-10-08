{{-- one number of a selling tool: $key, $label, $unit, $min, $max, $step, $value; $hint optional, $attrs optional --}}
<label class="block text-sm font-medium text-ink" data-field="{{ $key }}">{{ $label }}
    <span class="tool-unit mt-1 !w-full" data-unit="{{ $unit }}"><input data-param="{{ $key }}" type="number" inputmode="decimal" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $value }}" class="field !mt-0 {{ mb_strlen($unit) > 3 ? '!pr-24' : '' }}" {!! $attrs ?? '' !!}></span>
    @isset($hint)<span class="mt-1 block text-xs font-normal text-muted">{{ $hint }}</span>@endisset
</label>
