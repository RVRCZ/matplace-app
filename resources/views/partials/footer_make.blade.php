{{--
    The footer's row "what we make" (docs/AP.md §14): the services and the tools that bring customers, linked from every
    page, so the pages search engines visit most (the catalogue, the blog) lead to them. Tools from config/seo.php
    `footer_tools`, only those on offer with a page of their own; nothing is shown twice and nothing hidden is linked.
--}}
@php
    $footerTools = array_values(array_intersect((array) config('seo.footer_tools', []), \App\Support\ToolSeo::tools()));
    $footerLink = 'inline-flex min-h-11 min-w-11 items-center justify-center underline hover:text-slate-900';
@endphp
<nav id="footer-make" class="mt-2 flex flex-wrap items-center justify-center gap-x-5" aria-label="{{ __('footer.make_nav') }}">
    <a href="{{ route('tools') }}" class="{{ $footerLink }}">{{ __('footer.all_tools') }}</a>
    <a href="{{ route('tools.gifts') }}" class="{{ $footerLink }}">{{ __('footer.gifts') }}</a>
    @foreach($footerTools as $footerTool)
        <a href="{{ route(config('tools')[$footerTool]['route']) }}" class="{{ $footerLink }}">{{ __('tools.'.$footerTool.'.title') }}</a>
    @endforeach
    @if(\Illuminate\Support\Facades\Lang::has('pages.praha.title'))
        <a href="{{ route('pages.praha') }}" class="{{ $footerLink }}">{{ __('footer.praha') }}</a>
    @endif
    @if(\Illuminate\Support\Facades\Lang::has('pages.business.title'))
        <a href="{{ route('tools.business') }}" class="{{ $footerLink }}">{{ __('footer.business') }}</a>
    @endif
</nav>
