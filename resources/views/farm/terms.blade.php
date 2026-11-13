@extends('layouts.app', ['title' => __('farm.terms.title').' · matplace', 'description' => __('seo.farm_terms.description'), 'crumbs' => [[__('privacy.footer.terms'), route('farm.terms')]]])

@section('content')
<x-crumbs :trail="[[__('privacy.footer.terms'), route('farm.terms')]]" class="mx-auto -mt-2 mb-1 max-w-2xl" />
<article class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6">
    <h1 class="text-2xl font-extrabold">{{ __('farm.terms.title') }}</h1>
    <p class="text-xs text-slate-500">{{ __('farm.terms.version', ['v' => $version]) }}</p>
    <ol class="mt-4 list-decimal space-y-3 pl-5 text-slate-700">
        @foreach(__('farm.terms.items') as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ol>
</article>
@endsection
