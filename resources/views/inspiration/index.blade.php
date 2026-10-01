@extends('layouts.app', [
    'title' => ($category ? $category->label().' · ' : '').__('models.inspiration.title').' · matplace',
    'description' => __('models.inspiration.description'),
    // search results and deep pages of a list are not pages of their own for search engines
    'noindex' => $q !== '',
])

@section('content')
<div class="mx-auto max-w-5xl">
    <h1 class="text-2xl font-extrabold sm:text-3xl">{{ $category ? $category->label() : __('models.inspiration.title') }}</h1>
    <p class="mt-1 max-w-2xl text-slate-600">{{ __('models.inspiration.lead') }} <a href="{{ route('models.index') }}" class="text-action-dark underline">{{ __('models.inspiration.printable_link') }}</a></p>

    <form method="get" action="{{ $category ? route('catalog.category', $category->slug) : route('catalog.index') }}" class="mt-4 flex gap-2" role="search">
        <input name="q" type="search" value="{{ $q }}" maxlength="100" placeholder="{{ __('models.inspiration.search') }}" aria-label="{{ __('models.inspiration.search') }}" class="field mt-0 flex-1">
        <button class="btn-primary min-h-0 px-4 py-2">{{ __('models.inspiration.search_button') }}</button>
    </form>

    <nav class="mt-4 flex flex-wrap gap-2 text-sm" aria-label="{{ __('models.category') }}">
        <a href="{{ route('catalog.index') }}" class="chip {{ $category ? '' : 'chip-on' }}">{{ __('models.all_categories') }}</a>
        @foreach($categories as $top)
            @php $open = $category && ($category->id === $top->id || $category->parent_id === $top->id); @endphp
            <a href="{{ route('catalog.category', $top->slug) }}" class="chip {{ $open ? 'chip-on' : '' }}">{{ $top->label() }}</a>
        @endforeach
    </nav>
    @foreach($categories as $top)
        @if($category && ($category->id === $top->id || $category->parent_id === $top->id) && $top->children->isNotEmpty())
            <nav class="mt-2 flex flex-wrap gap-2 text-sm" aria-label="{{ $top->label() }}">
                @foreach($top->children as $child)
                    <a href="{{ route('catalog.category', $child->slug) }}" class="rounded-full px-3 py-1 {{ $category->id === $child->id ? 'bg-ink text-white' : 'bg-white text-slate-700 hover:bg-slate-100' }}">{{ $child->label() }}</a>
                @endforeach
            </nav>
        @endif
    @endforeach

    @if($models->isEmpty())
        <p class="card mt-6 p-8 text-center text-slate-600">{{ $q !== '' ? __('models.inspiration.nothing_found', ['q' => $q]) : __('models.inspiration.empty') }}</p>
    @else
        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($models as $model)
                @include('inspiration._tile', ['model' => $model])
            @endforeach
        </div>
        <div class="mt-5">{{ $models->links() }}</div>
    @endif
</div>
@endsection
