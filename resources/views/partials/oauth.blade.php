@php $providers = array_filter(['google' => config('services.google.client_id'), 'facebook' => config('services.facebook.client_id')]); @endphp
@if($providers)
<div class="mt-4 grid gap-2">
    @foreach(array_keys($providers) as $p)
        <a href="{{ route('oauth.redirect', $p) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
            {{ __('auth.continue_with', ['provider' => ucfirst($p)]) }}
        </a>
    @endforeach
</div>
<div class="my-3 flex items-center gap-3 text-xs text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>{{ __('auth.or') }}<span class="h-px flex-1 bg-slate-200"></span></div>
@endif
