@extends('layouts.app', ['title' => __('farm.admin.nav.settings').' · admin', 'noindex' => true])

@php
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
    $json = fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $num = fn (string $key, string $label, string $step = '1') => ['key' => $key, 'label' => $label, 'step' => $step];
    $groups = [
        'Cena (bez DPH, pokud není uvedeno jinak)' => [
            $num('hourly_rate', 'Hodinová sazba (Kč/h)', '0.01'), $num('fixed_fee', 'Fixní poplatek za zakázku', '0.01'), $num('min_price', 'Minimální cena zakázky', '0.01'),
            $num('vat_percent', 'DPH (%) – 0 = neplátce', '0.1'), $num('rounding', 'Zaokrouhlení nahoru na násobek (0 = haléře)', '0.01'), $num('shipping_price', 'Doprava (s DPH)', '0.01'),
        ],
        'Limity' => [
            $num('max_upload_mb', 'Max. velikost souboru (MB)'), $num('daily_slices_per_user', 'Výpočtů na uživatele za den (0 = bez limitu)'),
            $num('min_model_mm', 'Nejmenší díl (mm)', '0.1'), $num('bed_margin_mm', 'Volný okraj podložky (mm)', '0.1'),
        ],
        'Provoz' => [
            $num('changeover_minutes', 'Výměna mezi tisky pro odhad fronty (min)'), $num('offline_after_seconds', 'Offline po (s) bez heartbeatu'),
            $num('topup_min', 'Nejmenší dobití (Kč)'), $num('topup_max', 'Největší dobití (Kč)'),
            $num('generation_price', 'Generování modelu nad denní limit (Kč z kreditu, 0 = nelze)', '0.01'),
        ],
    ];
@endphp

@section('content')
@include('admin.farm.nav')

<form method="post" action="{{ route('admin.farm.settings.save') }}" class="mt-4 space-y-4">
    @csrf
    @foreach($groups as $title => $fields)
        <section class="rounded-2xl border border-slate-200 bg-white p-4">
            <h2 class="font-bold">{{ $title }}</h2>
            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                @foreach($fields as $f)
                    <label class="{{ $lb }}">{{ $f['label'] }}<input type="number" step="{{ $f['step'] }}" name="{{ $f['key'] }}" required value="{{ old($f['key'], $settings[$f['key']]) }}" class="{{ $in }}"></label>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
        <h2 class="font-bold">Přepínače webu</h2>
        <div class="mt-2 grid gap-3 text-sm">
            <label class="flex items-start gap-2"><input type="checkbox" name="farm_open" value="1" @checked($settings['farm_open']) class="mt-1 h-4 w-4 accent-action"><span><strong>Farma přijímá zakázky</strong><br><span class="text-xs text-slate-600">Vypnuto: tlačítko „Pronajmout tiskárnu“ zmizí a nové zakázky nejdou založit; rozjeté tisky doběhnou, kredit zůstává.</span></span></label>
            <label class="flex items-start gap-2"><input type="checkbox" name="marketplace" value="1" @checked($settings['marketplace']) class="mt-1 h-4 w-4 accent-action"><span><strong>Tržiště tiskařů</strong><br><span class="text-xs text-slate-600">Zapnuto: ceny tiskařů v kalkulaci, poptávky „Chci to vyrobit“, nabídky, veřejné stránky a role tiskaře. Vypnuto: kalkulace ukazuje jen údaje o tisku a jedinou cenu má farma.</span></span></label>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Ostatní</h2>
        <div class="mt-2 grid gap-3 sm:grid-cols-3">
            <label class="{{ $lb }}">Nabízené částky dobití (Kč, oddělené čárkou)<input name="topup_amounts" required value="{{ old('topup_amounts', implode(', ', $settings['topup_amounts'])) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">Verze podmínek<input name="terms_version" required value="{{ old('terms_version', $settings['terms_version']) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">E-mail obsluhy (upozornění)<input type="email" name="admin_email" value="{{ old('admin_email', $settings['admin_email']) }}" class="{{ $in }}"></label>
        </div>
        <div class="mt-3 flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="require_approval" value="1" @checked($settings['require_approval']) class="h-4 w-4 accent-action"> Každou zaplacenou zakázku před tiskem ručně schválit</label>
            @foreach(['pickup', 'shipping'] as $mode)
                <label class="flex items-center gap-2"><input type="checkbox" name="delivery_modes[]" value="{{ $mode }}" @checked(in_array($mode, $settings['delivery_modes'])) class="h-4 w-4 accent-action"> {{ $mode === 'pickup' ? 'Osobní odběr' : 'Odeslání' }}</label>
            @endforeach
        </div>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="{{ $lb }}">Předvolby kvality (JSON: klíč → layer_mm; klíč = procesní profil tiskárny)<textarea name="qualities" rows="6" required class="{{ $in }} font-mono text-xs">{{ old('qualities', $json($settings['qualities'])) }}</textarea></label>
            <label class="{{ $lb }}">Předvolby pevnosti (JSON: klíč → infill %)<textarea name="strengths" rows="6" required class="{{ $in }} font-mono text-xs">{{ old('strengths', $json($settings['strengths'])) }}</textarea></label>
        </div>
    </section>

    <button class="btn-primary">Uložit</button>
</form>
@endsection
