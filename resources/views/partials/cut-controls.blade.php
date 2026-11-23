{{-- The height of the chest cut and the lean of a bust (PedestalChanger::CUT_SHIFTS, PITCHES): rows of big step buttons, no sliders, a thumb on a phone hits them.
     Used by the calculator's base card and by the bust page; resources/js/calc/cut_controls.ts marks the chosen step and shows the row when the model can take it. --}}
<div data-cut-controls class="hidden {{ $class ?? '' }}">
    <div id="pedestal-cut" role="group" aria-labelledby="pedestal-cut-label">
        <div id="pedestal-cut-label" class="text-xs font-semibold text-slate-600">{{ __('pedestal.cut') }}</div>
        <div class="mt-1 grid grid-cols-5 gap-1.5">
            @foreach(\App\Domain\Generation\PedestalChanger::CUT_SHIFTS as $cs)<button type="button" data-step="{{ $cs }}" aria-pressed="false" class="min-h-12 rounded-lg border border-slate-300 bg-white px-1 text-sm font-semibold text-slate-700">{{ $cs > 0 ? '+' : ($cs < 0 ? '−' : '') }}{{ abs($cs) }}&nbsp;%</button>@endforeach
        </div>
        <p class="mt-1 text-xs text-slate-500">{{ __('pedestal.cut.hint') }}</p>
    </div>
    <div id="pedestal-pitch" role="group" aria-labelledby="pedestal-pitch-label" class="mt-3">
        <div id="pedestal-pitch-label" class="text-xs font-semibold text-slate-600">{{ __('pedestal.pitch') }}</div>
        <div class="mt-1 grid grid-cols-4 gap-1.5 sm:grid-cols-7">
            @foreach(\App\Domain\Generation\PedestalChanger::PITCHES as $pt)<button type="button" data-step="{{ $pt }}" aria-pressed="false" class="min-h-12 rounded-lg border border-slate-300 bg-white px-1 text-sm font-semibold text-slate-700">{{ $pt > 0 ? '+' : ($pt < 0 ? '−' : '') }}{{ abs($pt) }}°</button>@endforeach
        </div>
        <p class="mt-1 text-xs text-slate-500">{{ __('pedestal.pitch.hint') }}</p>
    </div>
</div>
