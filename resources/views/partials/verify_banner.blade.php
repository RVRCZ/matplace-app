{{-- Shown wherever an unverified account would hit the wall: ordering, credit, the designer profile. --}}
@auth
    @if(! auth()->user()->hasVerifiedEmail())
        <div id="verify-banner" class="note-warn mt-3 flex flex-wrap items-center justify-between gap-3 text-sm" role="alert">
            <span>{{ __('user.verify.banner', ['email' => auth()->user()->email]) }}</span>
            <form method="post" action="{{ route('verification.send') }}">
                @csrf
                <button class="btn-quiet min-h-0 px-3 py-1.5 text-sm">{{ __('user.verify.resend') }}</button>
            </form>
        </div>
    @endif
@endauth
