@php $providers = array_filter(['google' => config('services.google.client_id'), 'facebook' => config('services.facebook.client_id')]); @endphp
@if($providers)
{{-- the buttons in the colours people know: Google white with its "G", Facebook in its blue with the "f" (both brands' guidelines) --}}
<div class="mt-4 grid gap-2">
    @foreach(array_keys($providers) as $p)
        @if($p === 'google')
            <a href="{{ route('oauth.redirect', $p) }}" class="flex items-center justify-center gap-3 rounded-xl border border-[#dadce0] bg-white px-4 py-2.5 text-sm font-semibold text-[#3c4043] hover:bg-[#f8f9fa]">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                {{ __('auth.continue_with', ['provider' => 'Google']) }}
            </a>
        @else
            <a href="{{ route('oauth.redirect', $p) }}" class="flex items-center justify-center gap-3 rounded-xl bg-[#1877F2] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#166fe5]">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true"><path fill="#fff" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.88v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                {{ __('auth.continue_with', ['provider' => 'Facebook']) }}
            </a>
        @endif
    @endforeach
</div>
<div class="my-3 flex items-center gap-3 text-xs text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>{{ __('auth.or') }}<span class="h-px flex-1 bg-slate-200"></span></div>
@endif
