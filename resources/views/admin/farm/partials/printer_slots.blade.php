{{-- The filaments of one printer on its detail (docs/AQ.md): which spool sits in which position, how much is left,
     whether it is offered. The same save as "Založení cívek" (FarmCatalogController::saveSlots), for this machine only;
     every loaded filament leads to its own detail. A spool the catalogue does not know and the names of the positions
     stay in the printer's settings. --}}
@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
    $slots = $printer->slots->keyBy('slot');
    $count = max($printer->positionRows(), $slots->count());
    $groups = $colors->groupBy(fn ($c) => $c->material->label().($c->material->manual_only ? ' · '.__('farm.admin.manual_only_short') : ''));
@endphp
<section id="slots" class="rounded-2xl border border-slate-200 bg-white p-4">
    <h2 class="font-bold">{{ __('farm.admin.pd.slots_title') }}</h2>
    <p class="mt-1 text-xs text-slate-500">{{ __('farm.admin.pd.slots_lead') }} <a class="underline" href="{{ route('admin.farm.printers.edit', $printer) }}">{{ __('farm.admin.pd.settings') }}</a></p>
    {{-- the changer (ACE) is on the machine / taken off it (docs/AL.md §24): one click, the rows of the positions stay as they are --}}
    @if($printer->hasDetachableChanger() || $printer->withoutChanger())
        @php
            $noChanger = $printer->withoutChanger();
            $reported = $printer->reportedChanger();
            $mismatch = $printer->changerMismatch();
            $waiting = $printer->ordersWaitingForChanger();
        @endphp
        <div class="mt-2 rounded-xl {{ $noChanger ? 'bg-amber-50' : 'bg-slate-50' }} p-3 text-sm" data-changer="{{ $noChanger ? 'off' : 'on' }}">
            <form method="post" action="{{ route('admin.farm.printers.changer', $printer) }}" class="flex flex-wrap items-center justify-between gap-2">
                @csrf
                <span class="font-semibold">{{ __($noChanger ? 'farm.admin.changer.off' : 'farm.admin.changer.on') }}</span>
                <button name="attached" value="{{ $noChanger ? 1 : 0 }}" class="btn-quiet text-sm">{{ __($noChanger ? 'farm.admin.changer.attach' : 'farm.admin.changer.detach') }}</button>
            </form>
            <p class="mt-1 text-xs text-slate-600">{{ __($noChanger ? 'farm.admin.changer.off_hint' : 'farm.admin.changer.on_hint') }}</p>
            @if($reported !== null)
                <p class="mt-1 text-xs {{ $mismatch ? 'font-semibold text-red-800' : 'text-slate-500' }}" data-changer-reported="{{ $reported ? 'yes' : 'no' }}" @if($mismatch) data-changer-mismatch @endif>{{ __($reported ? 'farm.admin.changer.reported_yes' : 'farm.admin.changer.reported_no') }}@if($mismatch) {{ __('farm.admin.changer.mismatch') }}@endif</p>
            @endif
            @if($waiting->isNotEmpty())
                <p class="mt-1 text-xs font-semibold text-red-800" data-changer-waiting>{{ __('farm.admin.changer.orders_wait', ['orders' => $waiting->map(fn ($o) => $o->number ?: '#'.$o->id)->implode(', ')]) }}</p>
            @endif
        </div>
    @endif
    <form method="post" action="{{ route('admin.farm.printers.load.save') }}" class="mt-2 space-y-2">
        @csrf
        <input type="hidden" name="back_printer" value="{{ $printer->id }}">
        @for($i = 0; $i < $count; $i++)
            @php
                $s = $slots->get($i);
                $c = $s?->color;
                // a position of the changer that is off the machine: greyed, not sent with the form, so its row stays as it was
                $detached = $printer->withoutChanger() && $i !== \App\Models\FarmPrinter::DIRECT_SLOT;
            @endphp
            <div class="rounded-xl bg-slate-50 p-2 {{ $detached ? 'opacity-50' : '' }}" data-slot="{{ $i }}" @if($detached) data-detached @endif>
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                    <span class="font-semibold text-slate-600"><span class="mr-1 inline-block h-3 w-3 rounded-full border border-slate-300 align-middle" style="background: {{ $c?->hex ?? '#fff' }}"></span>{{ trim((string) $s?->label) !== '' ? $s->label : \App\Domain\Farm\SpoolPositions::name($printer, $i, $count) }} <span class="font-mono font-normal text-slate-500">{{ $s?->code() }}</span></span>
                    @if($c)
                        <a href="{{ route('admin.farm.colors.show', $c) }}" class="font-semibold underline" data-filament-link="{{ $c->id }}">{{ __('farm.admin.pd.filament_detail') }} →</a>
                    @endif
                </div>
                <div class="mt-1 grid grid-cols-[minmax(0,1fr)_5.5rem] items-end gap-2 sm:grid-cols-[minmax(0,1fr)_6rem_auto]">
                    <label class="{{ $lb }} col-span-2 sm:col-span-1">{{ __('farm.admin.pd.slot_color') }}
                        <select name="slots[{{ $printer->id }}][{{ $i }}][color]" class="{{ $in }}" @disabled($detached)><option value="">— {{ __('farm.admin.slots_empty') }} —</option>
                            @foreach($groups as $kind => $group)
                                <optgroup label="{{ $kind }}">
                                    @foreach($group as $o)
                                        <option value="{{ $o->id }}" @selected($s?->farm_color_id === $o->id)>{{ $o->fullLabel() }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                            {{-- a colour switched off in the catalogue that still sits here stays selectable, or saving would empty the slot --}}
                            @if($c && ! $colors->contains('id', $c->id))
                                <option value="{{ $c->id }}" selected>{{ $c->fullLabel() }}</option>
                            @endif
                        </select>
                    </label>
                    <label class="{{ $lb }}">{{ __('farm.admin.pd.slot_grams') }}<input type="number" step="1" min="0" name="slots[{{ $printer->id }}][{{ $i }}][remaining_g]" value="{{ $s ? round($s->remaining_g) : 0 }}" class="{{ $in }}" @disabled($detached)></label>
                    <label class="flex items-center gap-1 pb-2 text-xs"><input type="checkbox" name="slots[{{ $printer->id }}][{{ $i }}][enabled]" value="1" @checked($s?->enabled) @disabled($detached) class="h-4 w-4 accent-action"> {{ __('farm.admin.slots_offer') }}</label>
                </div>
                @if($detached)
                    <p class="mt-1 text-[11px] text-slate-600">{{ __('farm.admin.changer.detached_row') }}</p>
                @endif
                @error("slots.{$printer->id}.{$i}.color")
                    <p class="mt-1 text-[11px] text-red-700">{{ $message }}</p>
                @enderror
            </div>
        @endfor
        <div class="flex justify-end"><button class="btn-quiet text-sm">{{ __('farm.admin.pd.slots_save') }}</button></div>
    </form>
</section>
