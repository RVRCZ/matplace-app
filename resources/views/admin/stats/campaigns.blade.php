@extends('layouts.app', ['title' => 'Kampaně · admin', 'noindex' => true])

@php
    $steps = [
        'visit' => ['Návštěvy', 'Každý příchod z odkazu reklamy, bez robotů a bez nás.'],
        'page' => ['Stránka zobrazena', 'Prohlížeč stránku opravdu ukázal. Další sloupce se počítají jen z těchto lidí.'],
        'scroll' => ['Sjel níž', 'Posunul stránku aspoň do poloviny.'],
        'pick' => ['Otevřel výběr fotky', 'Klepl na „Přidat fotky“.'],
        'photo' => ['Vybral fotku', 'Fotku opravdu vložil.'],
        'create' => ['Odeslal', 'Stiskl „Vytvořit figurku“ s fotkou a souhlasem.'],
        'generate' => ['Výroba začala', 'Server fotku přijal a model se začal vyrábět.'],
        'model' => ['Model hotový', 'Model se vyrobil nebo ukázal na obrazovce.'],
        'to_print' => ['Přešel k tisku', 'Stiskl „Pokračovat k barvám a ceně“, vznikla zakázka.'],
        'price' => ['Viděl přesnou cenu', 'Vybral barvu a nechal si spočítat přesnou cenu.'],
        'checkout' => ['Šel platit', 'Otevřel platbu kartou.'],
        'paid' => ['Zaplatil', 'Banka platbu potvrdila.'],
        'download' => ['Stáhl model', 'Stáhl si model pro vlastní tiskárnu (STL nebo projekt 3MF). Není to krok k objednávce.'],
    ];
    $stops = [
        'no_consent' => ['Bez souhlasu', 'Zkusil vytvořit figurku bez zaškrtnutého souhlasu.'],
        'no_photo' => ['Bez fotky', 'Zkusil vytvořit figurku bez fotky.'],
        'refused' => ['Odmítnuto', 'Denní limit, odmítnutá fotka nebo chyba.'],
        'wait_left' => ['Odešel při čekání', 'Opustil stránku, když se model vyráběl.'],
    ];
    $before = [
        'thumb' => ['Náhled fotky', 'Náhled vybrané fotky se na stránce opravdu ukázal.'],
        'create_seen' => ['Viděl tlačítko', 'Tlačítko „Vytvořit“ měl na obrazovce.'],
        'consent_seen' => ['Viděl souhlas', 'Zaškrtávátko souhlasu měl na obrazovce.'],
        'consent_on' => ['Zaškrtl souhlas', 'Souhlas se zpracováním fotky zaškrtl.'],
        'photo_off' => ['Fotku odebral', 'Vybranou fotku zase odebral křížkem.'],
    ];
    $after = [
        'model_touch' => ['Pohnul modelem', 'Model otočil nebo přiblížil.'],
        'cta_seen' => ['Viděl „Přejít k tisku“', 'Tlačítko k tisku měl na obrazovce.'],
        'price_seen' => ['Viděl cenu', 'Orientační cenu u modelu měl na obrazovce.'],
        'again' => ['Zkusil znovu', 'Stiskl „Vytvořit znovu“ nebo úpravu podstavce.'],
        'dl_click' => ['Klepl na stažení', 'Klepl na stažení modelu.'],
        'model_left' => ['Odešel s modelem', 'Opustil stránku s hotovým modelem, aniž přešel k tisku.'],
    ];
    $window = [
        'img_seen' => ['Okno ukázáno', 'Okno pro nahrání obrázku se na nástroji ukázalo.'],
        'img_pick' => ['Vybral soubor', 'Vybral nebo přetáhl soubor, nebo vložil obrázek ze schránky.'],
        'img_camera' => ['Vyfotil', 'Použil foťák telefonu.'],
        'img_close' => ['Okno zavřel', 'Zavřel okno bez obrázku a šel dál s výchozím návrhem.'],
        'img_qty' => ['Změnil počet kusů', 'Změnil počet kusů přímo v okně.'],
    ];
    $order = [
        'o_color' => ['Vybral barvu', 'Klepl na dlaždici s barvou.'],
        'o_setting' => ['Měnil nastavení', 'Změnil něco v podrobném nastavení tisku.'],
        'o_slice' => ['Chtěl přesnou cenu', 'Stiskl „Spočítat přesnou cenu“ nebo přepočet.'],
        'o_delivery' => ['Vybral dopravu', 'Klepl na způsob doručení.'],
        'o_email' => ['Psal e-mail', 'Začal vyplňovat e-mail (nepřihlášený zákazník).'],
        'o_terms' => ['Zaškrtl podmínky', 'Zaškrtl souhlas s obchodními podmínkami.'],
        'o_blocked' => ['Platba: něco chybělo', 'Stiskl „Zaplatit“, ale něco nebylo vyplněné; stránka ho navedla.'],
        'o_pay' => ['Odeslal platbu', 'Stiskl „Zaplatit“ se vším vyplněným.'],
        'o_left' => ['Odešel bez platby', 'Zavřel nebo opustil stránku nezaplacené zakázky.'],
    ];
    $pct = fn (int $k, int $n) => $n > 0 ? str_replace('.', ',', (string) round(100 * $k / $n)).' %' : '–';
    $sourceName = ['facebook' => 'Facebook', 'google' => 'Google', 'seznam' => 'Seznam', 'tiktok' => 'TikTok', 'instagram' => 'Instagram'];
@endphp

@section('content')
@include('admin.nav')
<nav class="mb-2 flex flex-wrap gap-2 text-sm" aria-label="Statistiky">
    <a href="{{ route('admin.stats.funnel') }}" class="chip">Cesty návštěvníků</a>
    <a href="{{ route('admin.stats.search') }}" class="chip">Vyhledávání</a>
    <a href="{{ route('admin.ai.index') }}" class="chip">AI aktivita</a>
    <a href="{{ route('admin.stats.speed') }}" class="chip">Rychlost</a>
    <a href="{{ route('admin.stats.tools') }}" class="chip">Nástroje týdně</a>
    <a href="{{ route('admin.stats.campaigns') }}" class="chip chip-on">Kampaně</a>
    <a href="{{ route('admin.search_console.index') }}" class="chip">Search Console</a>
</nav>

<h2 class="mt-3 font-bold">Kde odcházejí návštěvníci z reklam</h2>
<p class="mt-1 text-xs text-slate-500">Lidé, kteří přišli odkazem s označením kampaně (utm_campaign), krok za krokem od příchodu po zaplacení. Řádek je kanál a reklama (utm_source a utm_content). Číslo je počet návštěvníků, kteří krok udělali; procento je podíl z těch, kterým se stránka zobrazila. Bez robotů a bez nás.</p>

<div class="mt-2 flex flex-wrap gap-1 text-sm" aria-label="Období">
    @foreach(\App\Domain\Stats\Funnel::PERIODS as $d)
        <a href="{{ route('admin.stats.campaigns', ['days' => $d, 'campaign' => $stats['campaign']]) }}" class="chip {{ $stats['days'] === $d ? 'chip-on' : '' }}">{{ $d }} dní</a>
    @endforeach
</div>

@if(! $stats['campaigns'])
    <p class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm" data-campaigns-empty>V tomto období nepřišel nikdo odkazem s označením kampaně.</p>
@else
    <div class="mt-2 flex flex-wrap gap-1 text-sm" aria-label="Kampaň">
        @foreach($stats['campaigns'] as $name => $n)
            <a href="{{ route('admin.stats.campaigns', ['days' => $stats['days'], 'campaign' => $name]) }}" class="chip {{ $stats['campaign'] === $name ? 'chip-on' : '' }}">{{ $name }} <span class="text-slate-500">({{ $n }})</span></a>
        @endforeach
    </div>

    <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm" data-campaign-table>
            <thead class="bg-slate-50 text-xs text-slate-500">
                <tr>
                    <th class="px-3 py-2">Kanál a reklama</th>
                    @foreach($steps as [$label, $hint])<th class="px-2 py-2 text-right align-bottom" title="{{ $hint }}">{{ $label }}</th>@endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach(array_merge($stats['rows'], [['source' => 'Celkem', 'content' => ''] + $stats['total']]) as $row)
                    @php $last = $loop->last; $shown = $row['steps']['page']; @endphp
                    <tr class="{{ $last ? 'bg-slate-50 font-semibold' : '' }}">
                        <td class="whitespace-nowrap px-3 py-2">{{ $sourceName[$row['source']] ?? $row['source'] }}@if($row['content'] !== '') <span class="text-slate-500">· {{ $row['content'] }}</span>@endif</td>
                        @foreach(array_keys($steps) as $key)
                            <td class="px-2 py-2 text-right" data-step="{{ $key }}">{{ $row['steps'][$key] }}@if(! in_array($key, ['visit', 'page'], true))<div class="text-xs font-normal text-slate-500">{{ $pct($row['steps'][$key], $shown) }}</div>@endif</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="mt-6 font-bold">Co je zastavilo</h2>
    <p class="mt-1 text-xs text-slate-500">Nejsou to kroky cesty, ale místa, kde se návštěvník zasekl. U odchodu při čekání je i to, jak dlouho typicky vydržel (medián).</p>
    <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm" data-campaign-stops>
            <thead class="bg-slate-50 text-xs text-slate-500">
                <tr>
                    <th class="px-3 py-2">Kanál a reklama</th>
                    @foreach($stops as [$label, $hint])<th class="px-2 py-2 text-right" title="{{ $hint }}">{{ $label }}</th>@endforeach
                    <th class="px-2 py-2 text-right" title="Medián doby, po které lidé odešli během výroby modelu.">Vydržel čekat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach(array_merge($stats['rows'], [['source' => 'Celkem', 'content' => ''] + $stats['total']]) as $row)
                    <tr class="{{ $loop->last ? 'bg-slate-50 font-semibold' : '' }}">
                        <td class="whitespace-nowrap px-3 py-2">{{ $sourceName[$row['source']] ?? $row['source'] }}@if($row['content'] !== '') <span class="text-slate-500">· {{ $row['content'] }}</span>@endif</td>
                        @foreach(array_keys($stops) as $key)<td class="px-2 py-2 text-right" data-stop="{{ $key }}">{{ $row['stops'][$key] }}</td>@endforeach
                        <td class="px-2 py-2 text-right">{{ $row['wait_s'] !== null ? $row['wait_s'].' s' : '–' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="mt-6 font-bold">Mezi fotkou a tlačítkem</h2>
    <p class="mt-1 text-xs text-slate-500">Co viděli a udělali ti, kdo vybrali fotku, než stiskli „Vytvořit“. Zapisuje se od 20. 11. 2026.</p>
    <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm" data-campaign-before>
            <thead class="bg-slate-50 text-xs text-slate-500">
                <tr>
                    <th class="px-3 py-2">Kanál a reklama</th>
                    <th class="px-2 py-2 text-right">Vybral fotku</th>
                    @foreach($before as [$label, $hint])<th class="px-2 py-2 text-right align-bottom" title="{{ $hint }}">{{ $label }}</th>@endforeach
                    <th class="px-2 py-2 text-right">Odeslal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach(array_merge($stats['rows'], [['source' => 'Celkem', 'content' => ''] + $stats['total']]) as $row)
                    @continue(! $loop->last && $row['steps']['photo'] === 0)
                    <tr class="{{ $loop->last ? 'bg-slate-50 font-semibold' : '' }}">
                        <td class="whitespace-nowrap px-3 py-2">{{ $sourceName[$row['source']] ?? $row['source'] }}@if($row['content'] !== '') <span class="text-slate-500">· {{ $row['content'] }}</span>@endif</td>
                        <td class="px-2 py-2 text-right">{{ $row['steps']['photo'] }}</td>
                        @foreach(array_keys($before) as $key)<td class="px-2 py-2 text-right" data-before="{{ $key }}">{{ $row['before'][$key] }}</td>@endforeach
                        <td class="px-2 py-2 text-right">{{ $row['steps']['create'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="mt-6 font-bold">Po hotovém modelu</h2>
    <p class="mt-1 text-xs text-slate-500">Co udělali ti, kdo model viděli. Zapisuje se od 20. 11. 2026.</p>
    <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm" data-campaign-after>
            <thead class="bg-slate-50 text-xs text-slate-500">
                <tr>
                    <th class="px-3 py-2">Kanál a reklama</th>
                    <th class="px-2 py-2 text-right">Model hotový</th>
                    @foreach($after as [$label, $hint])<th class="px-2 py-2 text-right align-bottom" title="{{ $hint }}">{{ $label }}</th>@endforeach
                    <th class="px-2 py-2 text-right">Přešel k tisku</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach(array_merge($stats['rows'], [['source' => 'Celkem', 'content' => ''] + $stats['total']]) as $row)
                    @continue(! $loop->last && $row['steps']['model'] === 0)
                    <tr class="{{ $loop->last ? 'bg-slate-50 font-semibold' : '' }}">
                        <td class="whitespace-nowrap px-3 py-2">{{ $sourceName[$row['source']] ?? $row['source'] }}@if($row['content'] !== '') <span class="text-slate-500">· {{ $row['content'] }}</span>@endif</td>
                        <td class="px-2 py-2 text-right">{{ $row['steps']['model'] }}</td>
                        @foreach(array_keys($after) as $key)<td class="px-2 py-2 text-right" data-after="{{ $key }}">{{ $row['after'][$key] }}</td>@endforeach
                        <td class="px-2 py-2 text-right">{{ $row['steps']['to_print'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="mt-6 font-bold">Okno pro nahrání obrázku</h2>
    <p class="mt-1 text-xs text-slate-500">Co udělali návštěvníci nástrojů s obrázkem (přívěsek, podtácek, klíčenka, ozdoba, reliéf, obraz z filamentu), když se jim hned po otevření ukázalo okno. Zapisuje se od 21. 11. 2026.</p>
    <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm" data-campaign-window>
            <thead class="bg-slate-50 text-xs text-slate-500">
                <tr>
                    <th class="px-3 py-2">Kanál a reklama</th>
                    @foreach($window as [$label, $hint])<th class="px-2 py-2 text-right align-bottom" title="{{ $hint }}">{{ $label }}</th>@endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach(array_merge($stats['rows'], [['source' => 'Celkem', 'content' => ''] + $stats['total']]) as $row)
                    @continue(! $loop->last && $row['window']['img_seen'] === 0)
                    <tr class="{{ $loop->last ? 'bg-slate-50 font-semibold' : '' }}">
                        <td class="whitespace-nowrap px-3 py-2">{{ $sourceName[$row['source']] ?? $row['source'] }}@if($row['content'] !== '') <span class="text-slate-500">· {{ $row['content'] }}</span>@endif</td>
                        @foreach(array_keys($window) as $key)<td class="px-2 py-2 text-right" data-window="{{ $key }}">{{ $row['window'][$key] }}</td>@endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="mt-6 font-bold">Na stránce zakázky</h2>
    <p class="mt-1 text-xs text-slate-500">Co udělali ti, kdo přešli k tisku, než zaplatili nebo odešli. Zapisuje se od 20. 11. 2026.</p>
    <div class="mt-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm" data-campaign-order>
            <thead class="bg-slate-50 text-xs text-slate-500">
                <tr>
                    <th class="px-3 py-2">Kanál a reklama</th>
                    <th class="px-2 py-2 text-right" title="Stiskl „Přejít k tisku“, vznikla zakázka.">Přešel k tisku</th>
                    @foreach($order as [$label, $hint])<th class="px-2 py-2 text-right align-bottom" title="{{ $hint }}">{{ $label }}</th>@endforeach
                    <th class="px-2 py-2 text-right" title="Banka platbu potvrdila.">Zaplatil</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach(array_merge($stats['rows'], [['source' => 'Celkem', 'content' => ''] + $stats['total']]) as $row)
                    @continue(! $loop->last && $row['steps']['to_print'] === 0)
                    <tr class="{{ $loop->last ? 'bg-slate-50 font-semibold' : '' }}">
                        <td class="whitespace-nowrap px-3 py-2">{{ $sourceName[$row['source']] ?? $row['source'] }}@if($row['content'] !== '') <span class="text-slate-500">· {{ $row['content'] }}</span>@endif</td>
                        <td class="px-2 py-2 text-right">{{ $row['steps']['to_print'] }}</td>
                        @foreach(array_keys($order) as $key)<td class="px-2 py-2 text-right" data-order="{{ $key }}">{{ $row['order'][$key] }}</td>@endforeach
                        <td class="px-2 py-2 text-right">{{ $row['steps']['paid'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-slate-500">Kroky „Sjel níž“ až „Odeslal“ a „Co je zastavilo“ se zapisují od 17. 11. 2026 na stránkách figurky, busty a figurky mazlíčka; u starších návštěv a jiných nástrojů jsou nuly. Útratu a cenu za návštěvu ukazují reklamní účty (Meta, Google Ads, Sklik).</p>
@endif
@endsection
