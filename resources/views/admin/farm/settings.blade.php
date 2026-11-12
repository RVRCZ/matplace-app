@extends('layouts.app', ['title' => __('farm.admin.nav.settings').' · admin', 'noindex' => true])

@php
    $czk = \App\Support\Money::symbol('CZK');   // the farm's rates are defined in crowns
    $in = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal';
    $lb = 'block text-xs font-semibold text-slate-600';
    $json = fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $num = fn (string $key, string $label, string $step = '1') => ['key' => $key, 'label' => $label, 'step' => $step];
    $groups = [
        'Cena (bez DPH, pokud není uvedeno jinak)' => [
            $num('hourly_rate', 'Hodinová sazba ('.$czk.'/h)', '0.01'), $num('fixed_fee', 'Fixní poplatek za zakázku', '0.01'), $num('min_price', 'Minimální cena zakázky', '0.01'),
            $num('vat_percent', 'DPH (%) – 0 = neplátce', '0.1'), $num('rounding', 'Zaokrouhlení nahoru na násobek (0 = haléře)', '0.01'),
        ],
        'Limity' => [
            $num('max_upload_mb', 'Max. velikost souboru (MB)'), $num('daily_slices_per_user', 'Výpočtů na uživatele za den (0 = bez limitu)'),
            $num('min_model_mm', 'Nejmenší díl (mm)', '0.1'), $num('bed_margin_mm', 'Volný okraj podložky (mm)', '0.1'),
        ],
        'Provoz' => [
            $num('changeover_minutes', 'Výměna mezi tisky pro odhad fronty (min)'), $num('offline_after_seconds', 'Offline po (s) bez heartbeatu'),
            $num('topup_min', 'Nejmenší dobití ('.$czk.')'), $num('topup_max', 'Největší dobití ('.$czk.')'),
            $num('generation_price', 'Generování modelu nad denní limit ('.$czk.' z kreditu, 0 = nelze)', '0.01'),
        ],
    ];
@endphp

@section('content')
@include('admin.farm.nav')

<form method="post" action="{{ route('admin.farm.settings.save') }}" class="mt-4 space-y-4">
    @csrf
    {{-- the state of the whole service, one choice of three (App\Domain\Farm\FarmState, docs/AL.md §23); `farm_open` is written with it --}}
    @php
        $farmState = old('farm_state', \App\Domain\Farm\FarmState::current());
        $pauseNote = (array) ($settings['farm_pause_note'] ?? []);
        $states = [
            'open' => ['Otevřeno', 'Běžný provoz: ceny, objednávky a tisk jako vždy.'],
            'paused' => ['Odstávka', 'Krátká pauza kvůli úpravám a opravám. Ceny zůstávají, objednat a zaplatit jde. Místo slibů rychlosti web říká „Farma má teď odstávku. Tisk začne nejdřív …“. Zaplacené zakázky zákazníků čekají ve frontě a žádná se nespustí, dokud nepřepnete na Otevřeno; vaše zkušební zakázky a tisky farmy se tisknou dál.'],
            'closed' => ['Zavřeno', 'Delší přestávka. Tlačítko „VYTISKNOUT“, ceny tisku a texty o tisku u nás zmizí, nové zakázky nejdou založit; nástroje, náhled a stažení modelu zůstávají. Vy jako administrátor objednáte dál. Pozor: zakázky, které už ve frontě čekají, se při Zavřeno tisknou (jako dosud); mají-li čekat, nechte Odstávku.'],
        ];
    @endphp
    <section id="farm-state" class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-4">
        <h2 class="font-bold">Stav farmy</h2>
        <div class="mt-2 grid gap-3 text-sm sm:grid-cols-3">
            @foreach($states as $value => [$name, $what])
                <label class="flex items-start gap-2 rounded-xl border border-amber-200 bg-white p-3"><input type="radio" name="farm_state" value="{{ $value }}" @checked($farmState === $value) class="mt-1 h-4 w-4 accent-action"><span><strong>{{ $name }}</strong><br><span class="text-xs text-slate-600">{{ $what }}</span></span></label>
            @endforeach
        </div>
        <div class="mt-3 grid gap-3 sm:grid-cols-4">
            <label class="{{ $lb }}">Odstávka: tisk začne nejdřív (nepovinné; bez data web říká „jakmile farmu znovu spustíme“)<input type="date" name="farm_pause_until" value="{{ old('farm_pause_until', $settings['farm_pause_until'] ?? '') }}" class="{{ $in }}"></label>
            @foreach(['cs' => 'česky', 'en' => 'anglicky', 'es' => 'španělsky'] as $l => $lang)
                <label class="{{ $lb }}">Poznámka pro zákazníky {{ $lang }} (nepovinná, za větou o odstávce)<input name="farm_pause_note[{{ $l }}]" maxlength="300" value="{{ old('farm_pause_note.'.$l, $pauseNote[$l] ?? '') }}" class="{{ $in }}"></label>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-slate-600">Poznámka se ukáže jen v jazyce, ve kterém je vyplněná; ostatní jazyky mají standardní větu. Datum, které už uplynulo, web neukazuje. Farma se sama neotevře: stav je vidět nahoře na všech stránkách správy farmy.</p>
        <p class="mt-2 text-sm font-semibold text-amber-900" data-ads-warning>Při odstávce nebo zavření zvažte pozastavení reklamy.</p>
    </section>

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
            {{-- "Farma přijímá zakázky" (farm_open) is the state above now: Zavřeno = off --}}
            <label class="flex items-start gap-2"><input type="checkbox" name="farm_public" value="1" @checked($settings['farm_public'] ?? false) class="mt-1 h-4 w-4 accent-action"><span><strong>Farma je veřejná</strong><br><span class="text-xs text-slate-600">Vypnuto: tlačítko „VYTISKNOUT“ v kalkulaci a nástrojích vidí jen administrátor.</span></span></label>
            <label class="flex items-start gap-2"><input type="checkbox" name="marketplace" value="1" @checked($settings['marketplace']) class="mt-1 h-4 w-4 accent-action"><span><strong>Tržiště tiskařů</strong><br><span class="text-xs text-slate-600">Zapnuto: ceny tiskařů v kalkulaci, poptávky „Chci to vyrobit“, nabídky, veřejné stránky a role tiskaře. Vypnuto: kalkulace ukazuje jen údaje o tisku a jedinou cenu má farma.</span></span></label>
            <label class="flex items-start gap-2"><input type="checkbox" name="video_prices" value="1" @checked($settings['video_prices']) class="mt-1 h-4 w-4 accent-action"><span><strong>Ceny ve videích</strong><br><span class="text-xs text-slate-600">Vypnuto: Short výtisku končí bez ceny, komentář a popis o ní nemluví a Short „Kolik stojí 3D tisk?“ se nestaví. Platí pro nově postavená videa; čekající přestavíte v /admin/youtube tlačítkem „Postavit znovu“, zveřejněná se nemění.</span></span></label>
            <label class="flex items-start gap-2"><input type="checkbox" name="eshop_photos_public" value="1" @checked($settings['eshop_photos_public'] ?? true) class="mt-1 h-4 w-4 accent-action"><span><strong>Fotky filamentů z e‑shopu i pro zákazníky.</strong> Barva bez vlastní fotky výtisku ukáže zákazníkům produktovou fotku z e‑shopu; vypnuto = jen v administraci, jak to bylo do 9. 11. 2026.</span></label>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Ostatní</h2>
        <div class="mt-2 grid gap-3 sm:grid-cols-3">
            <label class="{{ $lb }}">Nabízené částky dobití ({{ $czk }}, oddělené čárkou)<input name="topup_amounts" required value="{{ old('topup_amounts', implode(', ', $settings['topup_amounts'])) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">Verze podmínek<input name="terms_version" required value="{{ old('terms_version', $settings['terms_version']) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">E-mail obsluhy (upozornění)<input type="email" name="admin_email" value="{{ old('admin_email', $settings['admin_email']) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">{{ __('youtube.settings.shorts_hour') }}<input type="time" name="shorts_publish_hour" required value="{{ old('shorts_publish_hour', $settings['shorts_publish_hour']) }}" class="{{ $in }}"></label>
        </div>
        {{-- the home page's router (docs/AN.md §22): the Christmas promise and the reply time are the operator's numbers, not the code's --}}
        <div class="mt-3 grid gap-3 sm:grid-cols-3" id="farm-router">
            <label class="{{ $lb }}">Do Vánoc stihneme objednávky přijaté do (prázdné = hlavní strana o Vánocích mlčí)<input type="date" name="christmas_deadline" value="{{ old('christmas_deadline', $settings['christmas_deadline']) }}" class="{{ $in }}"></label>
            <label class="{{ $lb }}">Poptávka z rozcestníku: ozveme se do (hodin)<input type="number" name="intake_reply_hours" min="1" max="168" value="{{ old('intake_reply_hours', $settings['intake_reply_hours']) }}" class="{{ $in }}"></label>
        </div>
        {{-- what goes to that address: one mail per event of an order (OrderFlow::alertAdmin); problems also cover a printer offline or a start that failed --}}
        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm" id="farm-notify">
            <span class="text-xs font-semibold text-slate-600">E-maily obsluze:</span>
            @foreach(['paid' => 'zaplaceno (nová zakázka)', 'printing' => 'tiskne se', 'done' => 'dotištěno (se snímkem z kamery)', 'problem' => 'problémy (selhání, zrušení, tiskárna offline)', 'paused' => 'pauza tiskárny (s hláškou, jeden e‑mail na pauzu)', 'inquiry' => 'poptávka z rozcestníku (fotka a popis pro modeláře)'] as $kind => $label)
                <label class="flex items-center gap-2"><input type="checkbox" name="notify_{{ $kind }}" value="1" @checked($settings['notify_'.$kind]) class="h-4 w-4 accent-action"> {{ $label }}</label>
            @endforeach
            <span class="text-xs text-slate-500">Jeden e-mail na událost; bez e-mailu obsluhy nechodí nic.</span>
        </div>
        <div class="mt-3 flex flex-wrap gap-4 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="require_approval" value="1" @checked($settings['require_approval']) class="h-4 w-4 accent-action"> Každou zaplacenou zakázku před tiskem ručně schválit</label>
            @foreach(['packeta_point', 'packeta_home', 'pickup'] as $mode)
                <label class="flex items-center gap-2"><input type="checkbox" name="delivery_modes[]" value="{{ $mode }}" @checked(in_array($mode, $settings['delivery_modes'])) class="h-4 w-4 accent-action"> {{ __('farm.delivery.'.$mode) }}</label>
            @endforeach
            <span class="text-xs text-slate-500">Osobní odběr zapněte, až bude kde výtisky vydávat. Ceny zásilek podle zemí jsou v config/farm.php (shipping).</span>
        </div>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label class="{{ $lb }}">Předvolby kvality (JSON: klíč → layer_mm; klíč = procesní profil tiskárny)<textarea name="qualities" rows="6" required class="{{ $in }} font-mono text-xs">{{ old('qualities', $json($settings['qualities'])) }}</textarea></label>
            <label class="{{ $lb }}">Předvolby pevnosti (JSON: klíč → infill %)<textarea name="strengths" rows="6" required class="{{ $in }} font-mono text-xs">{{ old('strengths', $json($settings['strengths'])) }}</textarea></label>
        </div>
    </section>

    <button class="btn-primary">Uložit</button>
</form>
@endsection
