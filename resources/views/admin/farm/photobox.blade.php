@extends('layouts.app', ['title' => __('farm.admin.nav.photobox').' · admin', 'noindex' => true])

{{-- The photo box next to the farm: three fixed cameras on this PC (top, left, right). The browser remembers which
     camera is which; one click takes all three at full resolution and stores them with the chosen test.
     Logic in resources/js/calc/photobox.ts. --}}
@php $views = ['top' => 'Shora', 'left' => 'Zleva', 'right' => 'Zprava']; @endphp

@section('content')
@include('admin.farm.nav')
<div class="mx-auto max-w-5xl space-y-4 px-4 py-4">
    <h1 class="text-xl font-bold">{{ __('farm.admin.nav.photobox') }}</h1>

    <form method="get" class="flex flex-wrap items-end gap-2 text-sm">
        <label class="block text-xs font-semibold text-slate-600">Test
            <select name="order" onchange="this.form.submit()" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal">
                @foreach($tests as $x)
                    <option value="{{ $x->token }}" @selected($order && $order->id === $x->id)>{{ $x->number }} · {{ $x->printer?->name }} · {{ $x->material?->name }} {{ $x->color?->name }} · {{ __('farm.status.'.$x->status) }}</option>
                @endforeach
            </select>
        </label>
        @if($order && $order->farm_printer_material_id)
            <a class="underline" href="{{ route('admin.farm.tuning.edit', $order->farm_printer_material_id) }}#test-{{ $order->id }}">zpět na test a vyhodnocení</a>
        @endif
    </form>

    @if(! $order)
        <p class="text-sm text-slate-600">Zatím není žádný vytištěný test.</p>
    @else
        <section id="photobox" data-upload="{{ route('admin.farm.photos.store', $order) }}" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <p class="text-slate-600">Položte test na vyznačené místo, stejně natočený jako vždy. Kamery vyberte jen poprvé, prohlížeč si je zapamatuje.</p>
            <div class="mt-3 grid gap-3 md:grid-cols-3">
                @foreach($views as $key => $label)
                    <div>
                        <label class="block text-xs font-semibold text-slate-600">{{ $label }}
                            <select data-camera="{{ $key }}" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1 text-xs font-normal"><option value="">— bez kamery —</option></select>
                        </label>
                        <video data-preview="{{ $key }}" autoplay muted playsinline class="mt-2 aspect-video w-full rounded-lg bg-slate-900"></video>
                        <p data-res="{{ $key }}" class="mt-1 text-[11px] text-slate-500"></p>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button id="photobox-shoot" type="button" class="btn-primary text-sm">📷 Vyfotit a uložit k {{ $order->number }}</button>
                <button id="photobox-start" type="button" class="btn-quiet text-sm">Povolit kamery</button>
                <span id="photobox-msg" role="status" class="text-slate-700"></span>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
            <h2 class="font-bold">Fotky u {{ $order->number }} ({{ count($photos) }})</h2>
            <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-6">
                @foreach($photos as $i => $p)
                    <a href="{{ route('admin.farm.photos.show', [$order, $i]) }}" target="_blank" rel="noopener">
                        <img src="{{ route('admin.farm.photos.show', [$order, $i]) }}?thumb=1" alt="fotka {{ $i + 1 }}" loading="lazy" class="aspect-square w-full rounded-md object-cover">
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
