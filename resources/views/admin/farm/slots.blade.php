@extends('layouts.app', ['title' => __('farm.admin.slots_title').' · admin', 'noindex' => true])

@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
    // a kind that breaks in the changer says so in its group: it goes only into a printer in manual mode
    $groups = $colors->groupBy(fn ($c) => $c->material->label().($c->material->manual_only ? ' · '.__('farm.admin.manual_only_short') : ''));
@endphp

@section('content')
@include('admin.farm.nav')

<h1 class="mt-4 text-xl font-bold">{{ __('farm.admin.slots_title') }}</h1>
<p class="mt-1 text-sm text-slate-600">{{ __('farm.admin.slots_lead') }}</p>

<form method="post" action="{{ route('admin.farm.printers.load.save') }}" id="slots-form" class="mt-4 space-y-3">
    @csrf
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" id="slots-prefill" class="btn-quiet text-sm">{{ __('farm.admin.slots_prefill') }}</button>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="slots-grams-fill" value="1" checked class="h-4 w-4 accent-action"> {{ __('farm.admin.slots_prefill_grams') }}</label>
        <span class="text-xs text-slate-500">{{ __('farm.admin.slots_prefill_hint') }}</span>
    </div>

    @foreach($printers as $p)
        @php $slots = $p->slots->keyBy('slot'); $count = max(4, $slots->count()); $planned = $plan[$p->key] ?? []; @endphp
        <section class="rounded-2xl border border-slate-200 bg-white p-3" data-printer="{{ $p->key }}">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-bold">{{ $p->name }} <span class="text-xs font-normal text-slate-500">{{ $p->key }} · {{ $p->model }} · {{ __('farm.admin.mode.'.$p->mode) }}</span></h2>
                @if($planned)<span class="text-xs text-slate-500">{{ __('farm.admin.slots_plan') }}: {{ collect($planned)->map(fn ($x, $i) => ($i + 1).' '.($x['label'] ?? '? '.$x['want']))->implode(' · ') }}</span>@endif
            </div>
            <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                @for($i = 0; $i < $count; $i++)
                    @php $s = $slots->get($i); $want = $planned[$i] ?? null; @endphp
                    <div class="rounded-xl bg-slate-50 p-2" data-slot="{{ $i }}" data-plan-id="{{ $want['id'] ?? '' }}">
                        <label class="{{ $lb }}">{{ $i + 1 }}
                            <select name="slots[{{ $p->id }}][{{ $i }}][color]" data-color class="{{ $in }}"><option value="">— {{ __('farm.admin.slots_empty') }} —</option>
                                @foreach($groups as $kind => $group)<optgroup label="{{ $kind }}">@foreach($group as $c)<option value="{{ $c->id }}" @selected($s?->farm_color_id === $c->id)>{{ $c->fullLabel() }}</option>@endforeach</optgroup>@endforeach
                            </select>
                        </label>
                        <div class="mt-1 grid grid-cols-[1fr_auto] items-end gap-2">
                            <label class="{{ $lb }}">g<input type="number" step="1" min="0" name="slots[{{ $p->id }}][{{ $i }}][remaining_g]" data-grams value="{{ $s ? round($s->remaining_g) : 0 }}" class="{{ $in }}"></label>
                            <label class="flex items-center gap-1 pb-2 text-xs"><input type="checkbox" name="slots[{{ $p->id }}][{{ $i }}][enabled]" value="1" data-enabled @checked($s?->enabled) class="h-4 w-4 accent-action"> {{ __('farm.admin.slots_offer') }}</label>
                        </div>
                        @if($want && ! $want['id'])<p class="mt-1 text-[11px] text-amber-700">{{ __('farm.admin.slots_plan_unknown', ['want' => $want['want']]) }}</p>@endif
                        @error("slots.{$p->id}.{$i}.color")<p class="mt-1 text-[11px] text-red-700">{{ $message }}</p>@enderror
                    </div>
                @endfor
            </div>
        </section>
    @endforeach

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.farm.printers') }}" class="text-sm text-slate-500 underline">{{ __('farm.admin.slots_back') }}</a>
        <button class="btn-primary" onclick="return confirm(@js(__('farm.admin.slots_confirm')))">{{ __('farm.admin.slots_save') }}</button>
    </div>
</form>

<script>
    // "Předvyplnit podle rozpisu": every slot that the plan knows takes the planned colour, is offered, and gets 1000 g
    // when it holds nothing; a slot the plan does not know stays as it is
    document.getElementById('slots-prefill')?.addEventListener('click', () => {
        const grams = document.getElementById('slots-grams-fill').checked;
        document.querySelectorAll('[data-slot]').forEach((box) => {
            const id = box.dataset.planId;
            if (!id) return;
            const sel = box.querySelector('[data-color]');
            const g = box.querySelector('[data-grams]');
            sel.value = id;
            box.querySelector('[data-enabled]').checked = true;
            if (grams && Number(g.value) <= 0) g.value = '1000';
            box.classList.add('ring-2', 'ring-action');
        });
    });
</script>
@endsection
