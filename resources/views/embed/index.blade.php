@extends('layouts.app', ['title' => __('embed.title').' · matplace', 'description' => __('embed.description')])

@section('content')
<article class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 text-slate-700">
    <h1 class="text-2xl font-extrabold text-ink">{{ __('embed.h1') }}</h1>
    <p class="mt-3 text-lg leading-relaxed">{{ __('embed.lead') }}</p>

    <h2 class="mt-5 font-bold text-ink">{{ __('embed.code_title') }}</h2>
    <p class="mt-2 leading-relaxed">{{ __('embed.code_hint') }}</p>
    {{-- the code as text to copy; the button copies it, the textarea stays for a browser without the clipboard --}}
    <textarea id="embed-code" readonly rows="3" class="mt-2 w-full rounded-xl border border-slate-300 bg-slate-50 p-3 font-mono text-xs text-ink" aria-label="{{ __('embed.code_title') }}">{{ $code }}</textarea>
    <button type="button" id="embed-copy" class="btn-secondary mt-2 text-sm" data-copied="{{ __('embed.copied') }}">{{ __('embed.copy') }}</button>

    <h2 class="mt-5 font-bold text-ink">{{ __('embed.preview_title') }}</h2>
    <div class="mt-2 overflow-hidden rounded-xl border border-line bg-page">{!! $code !!}</div>

    <h2 class="mt-5 font-bold text-ink">{{ __('embed.rules_title') }}</h2>
    <ul class="mt-2 list-disc space-y-1 pl-5">
        @foreach((array) __('embed.rules') as $rule)
            <li>{{ str_replace([':formats'], [strtoupper(implode(', ', $formats))], $rule) }}</li>
        @endforeach
    </ul>
    <p class="mt-4 text-sm text-slate-500">{{ __('embed.contact') }} <a href="mailto:info@matplace.com" class="text-action-dark underline">info@matplace.com</a></p>
</article>
<script>
    (function () {
        var b = document.getElementById('embed-copy'); var code = document.getElementById('embed-code');
        if (!b || !code) return;
        b.addEventListener('click', function () {
            var done = function () { var old = b.textContent; b.textContent = b.dataset.copied; setTimeout(function () { b.textContent = old; }, 1500); };
            if (navigator.clipboard) navigator.clipboard.writeText(code.value).then(done, function () { code.select(); });
            else { code.select(); done(); }
        });
    })();
</script>
@endsection
