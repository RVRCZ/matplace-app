@extends('layouts.app', ['title' => __('privacy.title').' · matplace'])

@section('content')
<article class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 text-slate-700">
    <h1 class="text-2xl font-extrabold text-ink">{{ __('privacy.title') }}</h1>
    <p class="text-xs text-slate-500">{{ __('privacy.effective') }}</p>
    @foreach(__('privacy.sections') as $section)
        <section class="mt-5" @if(str_contains($section['h'], 'YouTube')) id="youtube" @endif>
            <h2 class="font-bold text-ink">{{ $section['h'] }}</h2>
            @foreach($section['p'] ?? [] as $p)
                {{-- plain text; bare URLs become links --}}
                <p class="mt-2">{!! preg_replace('~(https://[^\s)]+)~', '<a href="$1" target="_blank" rel="noopener" class="text-action-dark underline">$1</a>', e($p)) !!}</p>
            @endforeach
            @if(! empty($section['li']))
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($section['li'] as $li)<li>{{ $li }}</li>@endforeach
                </ul>
            @endif
        </section>
    @endforeach
</article>
@endsection
