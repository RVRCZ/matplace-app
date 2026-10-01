@extends('layouts.app', ['title' => __('designer.profile.title').' · matplace', 'noindex' => true])

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ __('designer.profile.title') }}</h1>
        <a href="{{ route('designer.dashboard') }}" class="text-sm text-action-dark">← {{ __('designer.back') }}</a>
    </div>
    @include('partials.flash')

    <form method="post" action="{{ route('designer.profile.update') }}" enctype="multipart/form-data" class="card mt-4 space-y-4 p-5">
        @csrf
        <label class="lbl">{{ __('designer.profile.name') }}<input name="display_name" required maxlength="120" value="{{ old('display_name', $profile->display_name) }}" class="field"></label>
        <label class="lbl">{{ __('designer.profile.slug') }}
            <span class="mt-1 flex items-center gap-1 text-sm font-normal text-muted"><span class="shrink-0">{{ parse_url(url('/'), PHP_URL_HOST) }}/d/</span><input name="slug" required maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('slug', $profile->slug) }}" class="field mt-0"></span>
            <span class="hint mt-1 block font-normal">{{ __('designer.profile.slug_hint') }}</span>
        </label>
        <label class="lbl">{{ __('designer.profile.bio') }}<textarea name="bio" rows="4" maxlength="2000" class="field">{{ old('bio', $profile->bio) }}</textarea></label>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <div class="lbl">{{ __('designer.profile.avatar') }}</div>
                @if($profile->avatarUrl())<img src="{{ $profile->avatarUrl() }}" alt="" class="mt-1 h-20 w-20 rounded-full object-cover">@endif
                <input type="file" name="avatar" accept="image/*" class="mt-2 block w-full text-sm">
            </div>
            <div>
                <div class="lbl">{{ __('designer.profile.cover') }}</div>
                @if($profile->coverUrl())<img src="{{ $profile->coverUrl() }}" alt="" class="mt-1 h-20 w-full rounded-lg object-cover">@endif
                <input type="file" name="cover" accept="image/*" class="mt-2 block w-full text-sm">
            </div>
        </div>

        <fieldset class="grid gap-3 sm:grid-cols-2">
            <legend class="lbl mb-1">{{ __('designer.profile.links') }}</legend>
            @foreach(\App\Models\DesignerProfile::LINKS as $link)
                <label class="text-sm text-muted">{{ __('designer.profile.link.'.$link) }}<input name="links[{{ $link }}]" type="url" maxlength="300" placeholder="https://" value="{{ old('links.'.$link, $profile->links[$link] ?? '') }}" class="field"></label>
            @endforeach
        </fieldset>

        <label class="lbl">{{ __('designer.profile.default_royalty') }}
            <span class="mt-1 flex items-center gap-2 font-normal"><input name="default_royalty_czk" type="number" min="0" max="{{ \App\Models\DesignerProfile::MAX_ROYALTY_CZK }}" step="1" required value="{{ old('default_royalty_czk', (int) $profile->default_royalty_czk) }}" class="field mt-0 w-32"> {{ \App\Support\Money::symbol('CZK') }}@if(\App\Support\Currency::current() !== 'CZK') <span class="text-xs text-muted">≈ @money((float) $profile->default_royalty_czk)</span>@endif</span>
            <span class="hint mt-1 block font-normal">{{ __('designer.royalty.hint') }}</span>
        </label>

        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="visible" value="1" class="mt-1" @checked(old('visible', $profile->visible))> <span><strong>{{ __('designer.profile.visible') }}</strong><br><span class="text-muted">{{ __('designer.profile.visible_hint') }}</span></span></label>
        <button class="btn-primary w-full">{{ __('user.profile.save') }}</button>
    </form>

    <section class="card mt-4 p-5">
        <h2 class="font-bold">{{ __('designer.verify.accounts') }}</h2>
        <ul class="mt-2 divide-y divide-slate-100 text-sm">
            @foreach(\App\Engines\Import\Sources::KEYS as $source)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span><strong>{{ __('designer.source.'.$source) }}</strong>
                        @if($profile->verifiedOn($source))<span class="ml-1 rounded-full bg-ok-soft px-2 py-0.5 text-xs font-semibold text-ok">{{ __('designer.verify.verified_as', ['handle' => $source === 'printables' ? $profile->printables_username : $profile->makerworld_handle]) }}</span>@endif
                    </span>
                    <a href="{{ route('designer.verify', $source) }}" class="text-action-dark underline">{{ $profile->verifiedOn($source) ? __('designer.verify.again') : __('designer.verify.start') }}</a>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection
