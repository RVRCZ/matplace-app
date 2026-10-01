@extends('layouts.app', ['title' => __('user.profile.title').' · matplace'])

@section('content')
@php $point = $user->pickup_point; @endphp
<div class="mx-auto max-w-2xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('user.profile.title') }}</h1>
        <a href="{{ route('account') }}" class="text-sm text-action-dark">← {{ __('user.profile.back') }}</a>
    </div>
    @include('partials.flash')
    @include('partials.verify_banner')

    {{-- who you are and where prints go --}}
    <form method="post" action="{{ route('account.profile.update') }}" class="card mt-4 space-y-4 p-5">
        @csrf
        <h2 class="font-bold">{{ __('user.profile.basics') }}</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="lbl">{{ __('auth.name') }}<input name="name" required maxlength="120" value="{{ old('name', $user->name) }}" autocomplete="nickname" class="field"></label>
            <label class="lbl">{{ __('account.phone') }}<input name="phone" type="tel" maxlength="30" value="{{ old('phone', $user->phone) }}" autocomplete="tel" class="field"></label>
        </div>

        <h2 class="pt-2 font-bold">{{ __('user.profile.delivery') }}</h2>
        <p class="hint -mt-3">{{ __('user.profile.delivery_hint') }}</p>
        <label class="lbl">{{ __('user.profile.delivery_name') }}<input name="delivery_name" maxlength="120" value="{{ old('delivery_name', $user->delivery_name) }}" placeholder="{{ $user->name }}" autocomplete="name" class="field"></label>
        <label class="lbl">{{ __('account.street') }}<input name="street" maxlength="160" value="{{ old('street', $user->street) }}" autocomplete="street-address" class="field"></label>
        <div class="grid gap-3 sm:grid-cols-[1fr_8rem]">
            <label class="lbl">{{ __('account.city') }}<input name="city" maxlength="100" value="{{ old('city', $user->city) }}" autocomplete="address-level2" class="field"></label>
            <label class="lbl">{{ __('account.zip') }}<input name="zip" maxlength="10" value="{{ old('zip', $user->zip) }}" autocomplete="postal-code" class="field"></label>
        </div>
        <label class="lbl">{{ __('account.country') }}
            <select name="country" id="profile-country" autocomplete="country" class="field">
                @foreach($countries as $code => $name)<option value="{{ $code }}" @selected(old('country', $user->country ?? 'CZ') === $code)>{{ $name }}</option>@endforeach
            </select>
        </label>

        {{-- favourite pickup point: the Packeta picker fills the hidden fields (resources/js/site/pickup.ts) --}}
        <div id="pickup" data-pickup-box data-key="{{ $packetaKey }}" data-language="{{ app()->getLocale() }}" data-country-field="profile-country" data-none="{{ __('user.profile.pickup_none') }}">
            <div class="lbl">{{ __('user.profile.pickup') }}</div>
            <p class="hint">{{ __('user.profile.pickup_hint') }}</p>
            <input type="hidden" name="pickup_point[id]" data-pickup="id" value="{{ old('pickup_point.id', $point['id'] ?? '') }}">
            <input type="hidden" name="pickup_point[name]" data-pickup="name" value="{{ old('pickup_point.name', $point['name'] ?? '') }}">
            <input type="hidden" name="pickup_point[carrier_id]" data-pickup="carrier_id" value="{{ old('pickup_point.carrier_id', $point['carrier_id'] ?? '') }}">
            <input type="hidden" name="pickup_point[country]" data-pickup="country" value="{{ old('pickup_point.country', $point['country'] ?? '') }}">
            <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                <span data-pickup="label" class="font-semibold">{{ old('pickup_point.name', $point['name'] ?? '') ?: __('user.profile.pickup_none') }}</span>
                @if($packetaKey !== '')
                    <button type="button" data-pickup="choose" class="btn-quiet min-h-0 px-3 py-1.5 text-sm">{{ empty($point['id']) ? __('user.profile.pickup_choose') : __('user.profile.pickup_change') }}</button>
                @endif
                <button type="button" data-pickup="remove" class="text-slate-500 hover:underline {{ empty($point['id']) ? 'hidden' : '' }}">{{ __('user.profile.pickup_remove') }}</button>
            </div>
        </div>

        <label class="lbl pt-2">{{ __('user.profile.mail_language') }}
            <select name="locale" class="field">
                @foreach(\App\Support\Locales::SUPPORTED as $l)<option value="{{ $l }}" @selected(old('locale', $user->locale) === $l)>{{ __('site.languages.'.$l) }}</option>@endforeach
            </select>
            <span class="hint mt-1 block font-normal">{{ __('user.profile.mail_language_hint') }}</span>
        </label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notify_email" value="1" @checked(old('notify_email', $user->notify_email))> {{ __('account.notify_email') }}</label>
        <button class="btn-primary w-full">{{ __('user.profile.save') }}</button>
    </form>

    <div id="security" class="scroll-mt-4">
        {{-- login e-mail: changes only after the new address confirms --}}
        <section class="card mt-4 p-5">
            <h2 class="font-bold">{{ __('user.email.title') }}</h2>
            <p class="mt-1 text-sm">
                {{ __('user.email.current') }}: <strong>{{ $user->email }}</strong>
                <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold {{ $user->hasVerifiedEmail() ? 'bg-ok-soft text-ok' : 'bg-amber-50 text-amber-900' }}">{{ __($user->hasVerifiedEmail() ? 'user.email.verified' : 'user.email.unverified') }}</span>
            </p>
            @if($user->pending_email)
                <div class="note-warn mt-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <span>{{ __('user.email.pending', ['email' => $user->pending_email]) }}</span>
                    <form method="post" action="{{ route('account.email.cancel') }}">@csrf<button class="btn-quiet min-h-0 px-3 py-1.5 text-sm">{{ __('user.email.cancel') }}</button></form>
                </div>
            @endif
            @foreach($errors->email->all() as $e)<p class="note-error mt-3 text-sm">{{ $e }}</p>@endforeach
            <form method="post" action="{{ route('account.email.change') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                @csrf
                <label class="lbl">{{ __('user.email.new') }}<input name="new_email" type="email" required maxlength="190" value="{{ old('new_email') }}" autocomplete="email" class="field"></label>
                @if($user->hasPassword())
                    <label class="lbl">{{ __('user.email.password') }}<input name="current_password" type="password" required autocomplete="current-password" class="field"></label>
                @endif
                <div class="sm:col-span-2"><button class="btn-secondary">{{ __('user.email.change') }}</button></div>
            </form>
        </section>

        <section class="card mt-4 p-5">
            <h2 class="font-bold">{{ __('user.password.title') }}</h2>
            @unless($user->hasPassword())<p class="hint mt-1">{{ __('user.password.none') }}</p>@endunless
            @foreach($errors->password->all() as $e)<p class="note-error mt-3 text-sm">{{ $e }}</p>@endforeach
            <form method="post" action="{{ route('account.password') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                @csrf
                @if($user->hasPassword())
                    <label class="lbl sm:col-span-2">{{ __('user.password.current') }}<input name="current_password" type="password" required autocomplete="current-password" class="field"></label>
                @endif
                <label class="lbl">{{ __('user.password.new') }}<input name="password" type="password" required minlength="8" autocomplete="new-password" class="field"></label>
                <label class="lbl">{{ __('user.password.again') }}<input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="field"></label>
                <div class="sm:col-span-2"><button class="btn-secondary">{{ __($user->hasPassword() ? 'user.password.save' : 'user.password.set') }}</button></div>
            </form>
        </section>

        {{-- Google / Facebook --}}
        @if($providers || $user->oauthIdentities->isNotEmpty())
            <section class="card mt-4 p-5">
                <h2 class="font-bold">{{ __('user.logins.title') }}</h2>
                @php $only = ! $user->hasPassword() && $user->oauthIdentities->count() < 2; @endphp
                <ul class="mt-2 divide-y divide-slate-100 text-sm">
                    @forelse($user->oauthIdentities as $identity)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span><strong>{{ ucfirst($identity->provider) }}</strong> <span class="text-slate-500">{{ __('user.logins.since', ['date' => $identity->created_at?->format('j. n. Y')]) }}</span></span>
                            @if($only)
                                <span class="text-xs text-slate-500">{{ __('user.logins.last') }}</span>
                            @else
                                <form method="post" action="{{ route('account.logins.disconnect', $identity->id) }}">@csrf<button class="text-slate-600 underline hover:text-red-700">{{ __('user.logins.disconnect') }}</button></form>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-slate-500">{{ __('user.logins.none') }}</li>
                    @endforelse
                </ul>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($providers as $p)
                        @unless($user->oauthIdentities->contains('provider', $p))
                            <a href="{{ route('oauth.redirect', [$p, 'link' => 1]) }}" class="btn-quiet min-h-0 px-3 py-1.5 text-sm">{{ __('user.logins.connect', ['provider' => ucfirst($p)]) }}</a>
                        @endunless
                    @endforeach
                </div>
            </section>
        @endif

        {{-- deleting the account --}}
        <section class="card mt-4 p-5">
            <details @if($errors->delete->any()) open @endif>
                <summary class="cursor-pointer font-bold text-red-800">{{ __('user.delete.title') }}</summary>
                <p class="mt-2 text-sm text-slate-600">{{ __('user.delete.lead') }}</p>
                @foreach($errors->delete->all() as $e)<p class="note-error mt-3 text-sm">{{ $e }}</p>@endforeach
                <form method="post" action="{{ route('account.delete') }}" class="mt-3 space-y-3">
                    @csrf
                    @if($balance->isPositive())
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="credit" value="1" required class="mt-1"> <span>{{ __('user.delete.credit', ['amount' => $balance->format()]) }}</span></label>
                    @endif
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="understand" value="1" required class="mt-1"> <span>{{ __('user.delete.understand') }}</span></label>
                    @if($user->hasPassword())
                        <label class="lbl">{{ __('user.delete.password') }}<input name="password" type="password" required autocomplete="current-password" class="field"></label>
                    @endif
                    <button class="rounded-xl border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-800 hover:bg-red-50">{{ __($user->hasPassword() ? 'user.delete.button' : 'user.delete.mail_button') }}</button>
                </form>
            </details>
        </section>
    </div>
</div>
@endsection
