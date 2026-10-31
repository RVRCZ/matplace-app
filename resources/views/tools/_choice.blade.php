{{-- One chip of a choice of the tool page. A plate's shape with an icon of its own shows the icon above its name. --}}
@php($shape = $key === 'body' && in_array($o, \App\Domain\Tools\ParametricGenerator::SHAPE_ICONS, true))
<label @class(['tool-choice', 'flex w-[4.75rem] flex-col items-center gap-0.5 px-1 text-center text-[11px] leading-tight' => $shape]) @if($shape) title="{{ $tr('o', $o) }}" @endif>
    <input type="radio" name="c-{{ $key }}" data-choice="{{ $key }}" value="{{ $o }}" class="sr-only" @checked($i === 0) @if($shape) aria-label="{{ $tr('o', $o) }}" @endif>@if($shape)<x-icon :name="'shape-'.$o" class="h-6 w-6" /><span>{{ $tr('o', $o) }}</span>@else{{ $tr('o', $o) }}@endif
</label>
