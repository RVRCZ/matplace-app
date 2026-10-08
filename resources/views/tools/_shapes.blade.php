{{-- The shape of a sign's plate: a tile per shape with its outline (public/img/shapes/<name>.svg, drawn by
     engines/shapes/_draw.py), its name for the pointer and for a screen reader. --}}
<fieldset {!! $whenOf($key) !!}>
    <legend class="lbl">{{ $tr('c', $key) }}</legend>
    <div class="mt-2 grid grid-cols-5 gap-1.5" role="radiogroup">
        @foreach($options as $i => $o)
            <label class="shape-tile" title="{{ $tr('o', $o) }}">
                <input type="radio" name="c-{{ $key }}" data-choice="{{ $key }}" value="{{ $o }}" class="sr-only" @checked($i === 0)>
                <img src="{{ asset('img/shapes/'.$o.'.svg') }}" alt="{{ $tr('o', $o) }}" loading="lazy" width="40" height="24">
            </label>
        @endforeach
    </div>
</fieldset>
