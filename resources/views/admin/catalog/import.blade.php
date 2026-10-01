@extends('layouts.app', ['title' => 'Import · admin', 'noindex' => true])

@section('content')
@include('admin.catalog._tabs')

<h1 class="mt-3 text-xl font-extrabold">Import z {{ $import->created_at->format('j. n. Y H:i') }}</h1>
<p class="text-sm text-slate-600">Celkem {{ $import->total }}, přidáno nebo přeskočeno {{ $import->done }}, selhalo {{ $import->failed }}.</p>

<div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Adresa</th><th class="px-3 py-2">Výsledek</th><th class="px-3 py-2">Model</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach((array) $import->items as $item)
                @php $model = isset($item['model_id']) ? ($models[$item['model_id']] ?? null) : null; @endphp
                <tr>
                    <td class="max-w-md truncate px-3 py-2"><a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="underline">{{ $item['url'] }}</a></td>
                    <td class="px-3 py-2">{{ ['imported' => 'přidáno', 'skipped' => 'už v katalogu', 'failed' => 'selhalo', 'waiting' => 'čeká'][$item['state']] ?? $item['state'] }}@if(! empty($item['note']) && $item['state'] === 'failed')<span class="block text-xs text-red-700">{{ $item['note'] }}</span>@endif</td>
                    <td class="px-3 py-2">@if($model)<a href="{{ route('admin.catalog.edit', $model->id) }}" class="underline">{{ $model->title }}</a>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
