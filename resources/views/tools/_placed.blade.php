{{--
    What a parametric tool placed in one step of its page (ParametricGenerator::PLACE), in the order it is written there:
    sizes with a slider, ticks, choices. $step = the step's id. Used by tools with steps of their own (SECTIONS).
--}}
@foreach($place as $key => $where)
    @continue($where !== $step)
    @if(isset($fields[$key]))
        @include('tools._num', ['key' => $key, 'f' => $fields[$key], 'label' => $label($key), 'unit' => $unit($key), 'when' => $whenOf($key)])
        @if(\Illuminate\Support\Facades\Lang::has('param.f.'.$kind.'.'.$key.'.hint'))<p class="hint !mt-1 !text-xs" {!! $whenOf($key) !!}>{{ __('param.f.'.$kind.'.'.$key.'.hint') }}</p>@endif
    @elseif(in_array($key, $flags, true))
        <label class="flex items-start gap-3 text-sm text-ink" {!! $whenOf($key) !!}>
            <input data-flag="{{ $key }}" type="checkbox" class="mt-0.5 h-5 w-5 accent-ink" @checked(in_array($key, $flagsOn, true))>
            <span><span class="font-medium">{{ $tr('flag', $key) }}</span><br><span class="text-muted" data-flag-hint="{{ $key }}">{{ $tr('flag', $key.'.hint') }}</span></span>
        </label>
    @elseif(isset($plainChoices[$key]))
        <fieldset {!! $whenOf($key) !!}>
            <legend class="lbl">{{ $tr('c', $key) }}</legend>
            <div class="mt-2 flex flex-wrap gap-1.5" role="radiogroup">
                @foreach($plainChoices[$key] as $i => $o)
                    <label class="tool-choice">
                        <input type="radio" name="c-{{ $key }}" data-choice="{{ $key }}" value="{{ $o }}" class="sr-only" @checked($i === 0)>{{ $tr('o', $o) }}
                    </label>
                @endforeach
            </div>
            @if(\Illuminate\Support\Facades\Lang::has('param.c.'.$kind.'.'.$key.'.hint'))<p class="hint mt-1 !text-xs">{{ __('param.c.'.$kind.'.'.$key.'.hint') }}</p>@endif
        </fieldset>
    @endif
@endforeach
