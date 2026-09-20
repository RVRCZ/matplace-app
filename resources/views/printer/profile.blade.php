@extends('layouts.app', ['title' => __('printer.profile.title').' · matplace'])

@section('content')
<div class="mx-auto max-w-3xl">
    @include('printer.nav')
    @include('partials.flash')

    <form method="post" action="{{ route('printer.profile.update') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
        @csrf

        {{-- The five fields --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h1 class="text-xl font-extrabold">{{ __('printer.profile.pricing') }}</h1>
            <p class="text-sm text-slate-500">{{ __('printer.profile.pricing_hint') }}</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-semibold">{{ __('printer.f.hourly_rate') }} <span class="font-normal text-slate-500">Kč/h</span><input name="hourly_rate" type="number" step="1" min="0" required value="{{ old('hourly_rate', $pricing->hourly_rate) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.price_per_gram') }} <span class="font-normal text-slate-500">Kč/g</span><input name="price_per_gram" type="number" step="0.1" min="0" required value="{{ old('price_per_gram', $pricing->price_per_gram) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.setup_fee') }} <span class="font-normal text-slate-500">Kč</span><input name="setup_fee" type="number" step="1" min="0" value="{{ old('setup_fee', $pricing->setup_fee) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.margin_pct') }} <span class="font-normal text-slate-500">%</span><input name="margin_pct" type="number" step="1" min="0" value="{{ old('margin_pct', $pricing->margin_pct) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.lead_time_days') }} <span class="font-normal text-slate-500">{{ __('quote.days_short') }}</span><input name="lead_time_days" type="number" min="0" required value="{{ old('lead_time_days', $pricing->lead_time_days ?: $profile->lead_time_days) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
            </div>

            <div class="mt-4 text-sm font-semibold">{{ __('printer.f.materials') }}</div>
            <p class="text-xs text-slate-500">{{ __('printer.f.materials_hint') }}</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                @foreach($materials as $m)
                    @php $pm = $profile->materials->firstWhere('material_code', $m['code']); @endphp
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" name="materials[]" value="{{ $m['code'] }}" @checked(in_array($m['code'], old('materials', $profile->materials->pluck('material_code')->all())))>
                        <span class="flex-1"><strong>{{ $m['code'] }}</strong> <span class="text-slate-500">{{ __('materials.'.$m['code'].'.label') }}</span></span>
                        <input name="material_price[{{ $m['code'] }}]" type="number" step="0.1" min="0" placeholder="Kč/g" value="{{ old('material_price.'.$m['code'], $pm?->price_per_gram) }}" class="w-20 rounded border border-slate-200 px-2 py-1 text-right text-xs">
                    </label>
                @endforeach
            </div>
        </div>

        {{-- More --}}
        <details class="rounded-2xl border border-slate-200 bg-white p-5" @if($errors->any()) open @endif>
            <summary class="cursor-pointer text-lg font-bold">{{ __('printer.profile.more') }}</summary>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-semibold">{{ __('printer.f.min_price') }} <span class="font-normal text-slate-500">Kč</span><input name="min_price" type="number" step="1" min="0" value="{{ old('min_price', $pricing->min_price) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.express_pct') }} <span class="font-normal text-slate-500">%</span><input name="express_pct" type="number" step="1" min="0" value="{{ old('express_pct', $pricing->express_pct) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold sm:col-span-2">{{ __('printer.f.speed') }} <span class="font-normal text-slate-500">{{ __('printer.f.speed_hint') }}</span>
                    <select name="time_factor" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal">
                        @foreach (['1' => 'fast', '1.3' => 'medium', '1.8' => 'slow', '2.5' => 'very_slow'] as $v => $k)
                            <option value="{{ $v }}" @selected(abs((float) old('time_factor', $pricing->time_factor ?: 1) - (float) $v) < 0.01)>{{ __('printer.f.speed.'.$k) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold sm:col-span-2">{{ __('printer.f.qty_discounts') }} <span class="font-normal text-slate-500">{{ __('printer.f.qty_discounts_hint') }}</span><input name="qty_discounts" value="{{ old('qty_discounts', \App\Http\Controllers\Printer\PrinterController::discountsToString($pricing->qty_discounts)) }}" placeholder="5:10, 20:20" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
            </div>

            <h3 class="mt-6 font-bold">{{ __('printer.profile.identity') }}</h3>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-semibold">{{ __('printer.f.display_name') }}<input name="display_name" required value="{{ old('display_name', $profile->display_name) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.company') }}<input name="company" value="{{ old('company', $profile->company) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">IČO @if($profile->ico_verified_at)<span class="font-normal text-teal-700">✓ {{ $profile->ico_subject_name }}</span>@endif<input name="ico" value="{{ old('ico', $profile->ico) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.logo') }}<input name="logo" type="file" accept="image/*" class="mt-1 w-full text-sm font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.contact_email') }}<input name="contact_email" type="email" value="{{ old('contact_email', $profile->contact_email) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.contact_phone') }}<input name="contact_phone" value="{{ old('contact_phone', $profile->contact_phone) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('account.zip') }}<input name="zip" value="{{ old('zip', $user->zip) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('account.city') }}<input name="city" value="{{ old('city', $user->city) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold sm:col-span-2">{{ __('printer.f.pickup_address') }}<input name="pickup_address" value="{{ old('pickup_address', $profile->pickup_address) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.cover') }} <span class="font-normal text-slate-500">{{ __('printer.f.cover_hint') }}</span><input name="cover" type="file" accept="image/*" class="mt-1 w-full text-sm font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.video_url') }} <span class="font-normal text-slate-500">YouTube / Vimeo</span><input name="video_url" type="url" value="{{ old('video_url', $profile->video_url) }}" placeholder="https://youtu.be/…" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold sm:col-span-2">{{ __('printer.f.bio') }}<textarea name="bio" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal">{{ old('bio', $profile->bio) }}</textarea></label>
            </div>

            <div class="mt-4 text-sm font-semibold">{{ __('printer.f.languages') }}</div>
            <div class="mt-1 flex flex-wrap gap-3 text-sm">
                @foreach(\App\Models\PrinterProfile::LANGUAGES as $l)
                    <label class="flex items-center gap-1"><input type="checkbox" name="languages[]" value="{{ $l }}" @checked(in_array($l, old('languages', $profile->languages ?? [])))> {{ __('printer.lang.'.$l) }}</label>
                @endforeach
            </div>

            <div class="mt-4 text-sm font-semibold">{{ __('printer.f.services') }}</div>
            <div class="mt-1 flex flex-wrap gap-3 text-sm">
                @foreach(\App\Models\PrinterProfile::SERVICES as $sv)
                    <label class="flex items-center gap-1"><input type="checkbox" name="services[]" value="{{ $sv }}" @checked(in_array($sv, old('services', $profile->services ?? [])))> {{ __('printer.service.'.$sv) }}</label>
                @endforeach
            </div>

            <div class="mt-4 text-sm font-semibold">{{ __('printer.f.delivery') }}</div>
            <div class="mt-1 flex flex-wrap gap-3 text-sm">
                @foreach(['pickup', 'zasilkovna', 'courier'] as $d)
                    <label class="flex items-center gap-1"><input type="checkbox" name="delivery_options[]" value="{{ $d }}" @checked(in_array($d, old('delivery_options', $profile->delivery_options ?? [])))> {{ __('printer.delivery.'.$d) }}</label>
                @endforeach
            </div>

            <div class="mt-4 text-sm font-semibold">{{ __('printer.f.capacity') }}</div>
            <div class="mt-1 flex flex-wrap gap-3 text-sm">
                @foreach(['open', 'busy', 'paused'] as $c)
                    <label class="flex items-center gap-1"><input type="radio" name="capacity" value="{{ $c }}" @checked(old('capacity', $profile->capacity) === $c)> {{ __('printer.capacity.'.$c) }}</label>
                @endforeach
            </div>

            <h3 class="mt-6 font-bold">{{ __('printer.profile.machine') }}</h3>
            @php $mc = $profile->machines->first(); @endphp
            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                <label class="text-sm font-semibold sm:col-span-2">{{ __('printer.f.machine_name') }}<input name="machine_name" value="{{ old('machine_name', $mc?->name) }}" placeholder="Prusa MK4, Bambu P1S…" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.machine_count') }}<input name="machine_count" type="number" min="1" value="{{ old('machine_count', $mc?->count ?? 1) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
                <label class="text-sm font-semibold">{{ __('printer.f.technology') }}<select name="machine_technology" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-normal"><option value="fdm" @selected(old('machine_technology', $mc?->technology) !== 'resin')>FDM</option><option value="resin" @selected(old('machine_technology', $mc?->technology) === 'resin')>Resin</option></select></label>
                <label class="text-sm font-semibold sm:col-span-2">{{ __('printer.f.bed') }} <span class="font-normal text-slate-500">mm</span>
                    <div class="mt-1 grid grid-cols-3 gap-2 font-normal">
                        <input name="machine_bed_x" type="number" min="1" placeholder="X" value="{{ old('machine_bed_x', $mc?->bed_x) }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        <input name="machine_bed_y" type="number" min="1" placeholder="Y" value="{{ old('machine_bed_y', $mc?->bed_y) }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        <input name="machine_bed_z" type="number" min="1" placeholder="Z" value="{{ old('machine_bed_z', $mc?->bed_z) }}" class="rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                </label>
            </div>

            <h3 class="mt-6 font-bold">{{ __('printer.profile.portfolio') }}</h3>
            <p class="text-sm text-slate-500">{{ __('printer.f.portfolio_hint') }}</p>
            <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5">
                @foreach($profile->portfolioItems as $item)
                    <label class="relative block cursor-pointer">
                        <img src="{{ $profile->mediaUrl($item->photo_path) }}" alt="" loading="lazy" class="aspect-square w-full rounded-lg object-cover">
                        <span class="absolute left-1 top-1 flex items-center gap-1 rounded bg-white/90 px-1 text-xs"><input type="checkbox" name="portfolio_delete[]" value="{{ $item->id }}"> {{ __('printer.f.portfolio_delete') }}</span>
                    </label>
                @endforeach
            </div>
            <input name="portfolio[]" type="file" accept="image/*" multiple class="mt-2 w-full text-sm">

            @if($profile->visible)<p class="mt-4 text-sm"><a class="text-teal-700 underline" href="{{ route('printers.show', $profile->slug) }}" target="_blank">{{ __('printer.profile.view_public') }}</a></p>@endif
            <label class="mt-5 flex items-center gap-2 text-sm"><input type="checkbox" name="visible" value="1" @checked(old('visible', $profile->visible))> {{ __('printer.f.visible') }}</label>
        </details>

        <button class="w-full rounded-xl bg-teal-600 px-4 py-3 font-semibold text-white">{{ __('printer.profile.save') }}</button>
    </form>
</div>
@endsection
