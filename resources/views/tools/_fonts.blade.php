{{-- The typeface of a tool's text: every face drawn in itself (public/img/fonts/<key>.svg, the outlines the model is
     made of), grouped by kind. The group of the face the tool opens with comes first, so nothing has to be scrolled to. --}}
@php
    $registry = \App\Domain\Tools\ParametricGenerator::FONTS;
    $first = $registry[$options[0]][2];
    $groups = collect($options)->groupBy(fn ($o) => $registry[$o][2])->sortBy(fn ($faces, $group) => $group === $first ? -1 : array_search($group, ['plain', 'serif', 'hand', 'fun', 'tech'], true));
@endphp
<fieldset {!! $whenOf($key) !!}>
    <legend class="lbl">{{ $tr('c', $key) }} <span class="font-normal text-muted">({{ count($options) }})</span></legend>
    <div class="font-picker mt-2" role="radiogroup" data-font-picker>
        @foreach($groups as $group => $faces)
            <p class="font-group">{{ __('param.fonts.'.$group) }}</p>
            <div class="grid grid-cols-2 gap-1.5">
                @foreach($faces as $o)
                    <label class="font-tile" title="{{ $registry[$o][1] }}">
                        <input type="radio" name="c-{{ $key }}" data-choice="{{ $key }}" value="{{ $o }}" class="sr-only" @checked($o === $options[0])>
                        <img src="{{ asset('img/fonts/'.$o.'.svg') }}" alt="{{ $registry[$o][1] }}" loading="lazy" width="120" height="24">
                    </label>
                @endforeach
            </div>
        @endforeach
    </div>
</fieldset>
