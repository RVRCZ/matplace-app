@extends('layouts.app', ['title' => __('designer.import.progress').' · matplace', 'noindex' => true])

@section('content')
@php
    $p = $import->progress();
    $states = (array) __('designer.import.state');
    $notes = (array) __('designer.import.error');
@endphp
<div class="mx-auto max-w-2xl" data-import-status="{{ route('designer.imports.status', $import->id) }}" data-done="{{ $p['status'] === 'done' ? 1 : 0 }}"
     data-states="{{ json_encode($states) }}" data-notes="{{ json_encode($notes) }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('designer.import.progress') }}</h1>
        <a href="{{ route('designer.dashboard') }}" class="text-sm text-action-dark">← {{ __('designer.back') }}</a>
    </div>
    <div class="card mt-4 p-5">
        <div class="flex items-center justify-between text-sm font-semibold"><span>{{ __('designer.source.'.$import->source) }}</span><span data-import-numbers>{{ $p['done'] + $p['failed'] }} / {{ $p['total'] }}</span></div>
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div data-import-bar class="h-full rounded-full bg-action transition-all" style="width: {{ $p['total'] ? round(100 * ($p['done'] + $p['failed']) / $p['total']) : 100 }}%"></div></div>
        <p data-import-finished class="note-ok mt-4 text-sm {{ $p['status'] === 'done' ? '' : 'hidden' }}">{{ __('designer.import.finished') }}
            <a href="{{ route('designer.dashboard', ['show' => 'nofile']) }}" class="font-semibold underline">{{ __('designer.import.next') }} →</a>
        </p>
        <ul data-import-items class="mt-4 divide-y divide-slate-100 text-sm">
            @foreach($p['items'] as $item)
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="min-w-0 truncate">{{ $item['title'] ?? $item['url'] ?? '…' }}</span>
                    <span class="shrink-0 text-xs font-semibold {{ ['waiting' => 'text-muted', 'imported' => 'text-ok', 'skipped' => 'text-slate-600', 'failed' => 'text-red-700'][$item['state']] }}">
                        {{ __('designer.import.state.'.$item['state']) }}@if($item['note'] ?? null) · {{ __('designer.import.error.'.$item['note']) }}@endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
