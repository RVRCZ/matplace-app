@extends('layouts.app', ['title' => __('printer.nav.quotes').' · matplace'])

@section('content')
<div class="mx-auto max-w-4xl">
    @include('printer.nav')
    @include('partials.flash')
    <div class="mt-5 flex items-center justify-between">
        <h1 class="text-2xl font-extrabold">{{ __('printer.nav.quotes') }}</h1>
        <form method="post" action="{{ route('printer.quotes.store') }}">@csrf<button class="rounded-full bg-teal-600 px-4 py-2 text-sm font-semibold text-white">+ {{ __('quote.new_empty') }}</button></form>
    </div>
    @include('printer.quote_list', ['quotes' => $quotes])
    <div class="mt-4">{{ $quotes->links() }}</div>
</div>
@endsection
