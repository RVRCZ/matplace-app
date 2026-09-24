@extends('layouts.app', ['title' => __('farm.admin.nav.printers').' · admin', 'noindex' => true])

@section('content')
@include('admin.farm.nav')

<div class="mt-4 flex justify-end"><a href="{{ route('admin.farm.printers.new') }}" class="btn-primary text-sm">+ {{ __('farm.admin.nav.printers') }}</a></div>

<div class="mt-3 space-y-3">
    @foreach($printers as $p)
        @php $cal = $calibration[$p->id] ?? null; @endphp
        <a href="{{ route('admin.farm.printers.edit', $p) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 hover:border-action">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-bold">{{ $p->name }} <span class="text-xs font-normal text-slate-500">{{ $p->key }} · {{ $p->model }}</span></span>
                <span class="text-xs">{{ __('farm.admin.state.'.$p->displayState()) }} · {{ __('farm.admin.mode.'.$p->mode) }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-600">{{ (int) $p->bed_x }} × {{ (int) $p->bed_y }} × {{ (int) $p->bed_z }} mm · {{ $p->nozzle_mm }} mm · podložka {{ $p->bedTypeLabel() }} · čas × {{ $p->time_factor }} · hmotnost × {{ $p->weight_factor }}@if($p->hourly_rate) · {{ $p->hourly_rate }} Kč/h @endif</p>
            <p class="mt-1 text-xs text-slate-500">{{ $cal ? __('farm.admin.calibration', ['n' => $cal['n'], 'time' => $cal['time'] ?? '—', 'weight' => $cal['weight'] ?? '—']) : __('farm.admin.calibration_none') }}</p>
        </a>
    @endforeach
</div>
@endsection
