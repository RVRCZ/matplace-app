{{-- One chip of a choice of the tool page. A value with an icon of its own (ParametricGenerator::CHOICE_ICONS) shows the icon above its name. --}}
@php($icon = in_array($o, \App\Domain\Tools\ParametricGenerator::CHOICE_ICONS[$key] ?? [], true) ? ($key === 'body' ? 'shape-' : $key.'-').$o : null)
<label @class(['tool-choice', 'flex w-[4.75rem] flex-col items-center gap-0.5 px-1 text-center text-[11px] leading-tight' => $icon]) @if($icon) title="{{ $tr('o', $o) }}" @endif>
    <input type="radio" name="c-{{ $key }}" data-choice="{{ $key }}" value="{{ $o }}" class="sr-only" @checked($i === 0) @if($icon) aria-label="{{ $tr('o', $o) }}" @endif>@if($icon)<x-icon :name="$icon" class="h-6 w-6" /><span>{{ $tr('o', $o) }}</span>@else{{ $tr('o', $o) }}@endif
</label>
