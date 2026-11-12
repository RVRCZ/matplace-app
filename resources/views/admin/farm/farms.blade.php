{{-- Farms (docs/AQ.md): the places the printers stand at, each with its address. The overview groups printers by them;
     a printer gets its farm in its settings. A pickup point would hang on a farm later. --}}
@extends('layouts.app', ['title' => __('farm.admin.nav.farms').' · admin', 'noindex' => true])

@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
@endphp

@section('content')
@include('admin.farm.nav')

<p class="mt-4 text-sm text-slate-600">{{ __('farm.admin.farms.lead') }}</p>

<div class="mt-4 space-y-4">
    @foreach($farms->concat([new \App\Models\Farm]) as $f)
        <section class="rounded-2xl border border-slate-200 bg-white p-4" data-farm="{{ $f->code }}">
            <form method="post" action="{{ $f->exists ? route('admin.farm.farms.update', $f) : route('admin.farm.farms.create') }}">
                @csrf
                <h2 class="font-bold">{{ $f->exists ? $f->label() : '+ '.__('farm.admin.farms.new') }} @if($f->exists) <span class="text-xs font-normal text-slate-500">{{ __('farm.admin.ov.printers', ['n' => $f->printers_count]) }}</span> @endif</h2>
                <div class="mt-2 grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <label class="{{ $lb }}">{{ __('farm.admin.farms.code') }}<input name="code" required maxlength="8" value="{{ $f->code }}" placeholder="S" class="{{ $in }} font-mono uppercase"></label>
                    <label class="{{ $lb }} lg:col-span-2">{{ __('farm.admin.farms.name') }}<input name="name" required maxlength="80" value="{{ $f->name }}" placeholder="Farma S" class="{{ $in }}"></label>
                    <label class="{{ $lb }} lg:col-span-3">{{ __('farm.admin.farms.street') }}<input name="street" maxlength="120" value="{{ $f->street }}" class="{{ $in }}"></label>
                    <label class="{{ $lb }} lg:col-span-2">{{ __('farm.admin.farms.city') }}<input name="city" maxlength="80" value="{{ $f->city }}" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">{{ __('farm.admin.farms.zip') }}<input name="zip" maxlength="16" value="{{ $f->zip }}" class="{{ $in }}"></label>
                    <label class="{{ $lb }}">{{ __('farm.admin.farms.country') }}<input name="country" maxlength="2" value="{{ $f->country ?: 'CZ' }}" class="{{ $in }} uppercase"></label>
                    <label class="{{ $lb }}">{{ __('farm.admin.farms.sort') }}<input type="number" name="sort" min="0" max="1000" value="{{ $f->sort }}" class="{{ $in }}"></label>
                    <label class="flex items-center gap-2 pt-5 text-sm"><input type="checkbox" name="enabled" value="1" @checked($f->enabled) class="h-4 w-4 accent-action"> {{ __('farm.admin.farms.enabled') }}</label>
                    <label class="{{ $lb }} sm:col-span-3 lg:col-span-6">{{ __('farm.admin.farms.note') }}<input name="note" maxlength="500" value="{{ $f->note }}" class="{{ $in }}"></label>
                </div>
                <div class="mt-2 flex justify-end"><button class="btn-quiet text-sm">{{ __('farm.admin.farms.save') }}</button></div>
            </form>
        </section>
    @endforeach
</div>
@endsection
