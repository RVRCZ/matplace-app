<?php

// Vstupní stránky (docs/AH.md §11): bloky pod nástrojem. „order“ = Než objednáte (FAQ, 'farm' => true jen když se
// tisk u nás nabízí), „who“ = Pro koho. Zástupné :cz_point, :cz_home, :sk_point, :eu_point (doprava z config/farm.php),
// :guest, :account (denní limit modelů z fotky) doplní App\Support\LandingPage.
return [
    'titles' => [
        'result' => 'Takhle to dopadlo',
        'result_lead' => 'Skutečné výtisky z naší farmy s rozměry, materiálem, cenou a dobou tisku.',
        'order' => 'Než objednáte',
        'who' => 'Pro koho to je',
        'next' => 'Kam dál',
        'next_tools' => 'Podobné nástroje',
        'next_blog' => 'Ke čtení',
    ],

    'calc' => [
        'order' => [
            ['q' => 'Jak dlouho trvá, než výtisk dorazí?', 'farm' => true, 'a' => 'Dobu tisku ukáže kalkulace hned po nahrání. Tisk začne, jakmile je tiskárna volná. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů. Všechny časy jsou odhad.'],
            ['q' => 'Kolik stojí doprava?', 'farm' => true, 'a' => 'Zásilkovnou na výdejní místo v Česku :cz_point, domů :cz_home, na Slovensko od :sk_point, do dalších zemí EU od :eu_point. Přesnou částku ukáže objednávka podle země a hmotnosti balíku.'],
            ['q' => 'Jak přesná je velikost a cena?', 'a' => 'Kalkulace počítá s vaším souborem tak, jak je: rozměry v milimetrech, které můžete změnit, a přesný výpočet sliceru pro dobu tisku a spotřebu materiálu. Standardní kvalita tiskne po vrstvách 0,2 mm. Kontrola modelu upozorní na příliš tenké stěny a díly, které se nevejdou na tiskárnu.'],
            ['q' => 'Mohu model jen stáhnout a vytisknout doma?', 'a' => 'Ano. „Stáhnout model“ dá soubor STL, nebo hotový projekt pro vaši tiskárnu (OrcaSlicer, Bambu Studio, PrusaSlicer) s nastavením z kalkulace.'],
            ['q' => 'Co když je model poškozený?', 'a' => 'Kalkulace ho přesto zkusí spočítat a kontrola modelu řekne, co je špatně (díry, převrácené nebo zdvojené plochy). Opravit ho můžete zdarma nástrojem Oprava modelu a pokračovat s opraveným souborem.'],
            ['q' => 'Co když se mi výtisk nelíbí?', 'farm' => true, 'a' => 'Před objednáním vidíte 3D náhled, rozměry, dobu tisku i cenu. Výtisk vyrábíme na zakázku, proto ho nejde bez důvodu vrátit do 14 dnů. Vadu (praskliny, chybějící vrstvy, jiný rozměr, než jste objednali) reklamujete podle reklamačního řádu.'],
        ],
        'who' => [
            ['t' => 'Náhradní díl z internetu', 'text' => 'Našli jste držák, krytku nebo kolečko na Printables či Thingiverse. Nahrajete STL, uvidíte rozměry a dobu tisku a rozhodnete se, jestli ho vytisknout u nás, nebo doma.'],
            ['t' => 'Vlastní model z CADu', 'text' => 'Navrhli jste díl ve Fusion, Onshape nebo FreeCADu. Kalkulace zkontroluje stěny a velikost, ukáže spotřebu materiálu a zvládne i STEP.'],
            ['t' => 'Máte vlastní tiskárnu', 'text' => 'Stáhnete projekt přímo pro svůj stroj s nastavením z kalkulace. U dvoubarevných modelů je v něm i výměna filamentu.'],
        ],
    ],

    'figure' => [
        'order' => [
            ['q' => 'Kolik stojí vytištěná busta a jak bude velká?', 'farm' => true, 'a' => 'Busta vysoká 80 mm vyjde u nás na 99 Kč, 120 mm na 130 Kč a 150 mm na 200 Kč (PLA+, jeden kus, bez dopravy; kalkulace z produkce 17. 10. 2026). Velikost změníte v kalkulaci a cena se přepočítá.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Model vzniká 2 až 4 minuty. Tisk busty 80 mm trvá asi hodinu, 150 mm necelé tři hodiny. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů.'],
            ['q' => 'Z čeho a v jaké barvě se busta tiskne?', 'a' => 'Z jedné barvy plastu PLA nebo PLA+. Na figurky se hodí matné odstíny, na kterých jsou vrstvy tisku méně vidět.'],
            ['q' => 'Co když se mi model nelíbí?', 'a' => 'Model si nejdřív prohlédnete ve 3D. V rámci denního limitu (:guest bez účtu, :account s účtem) můžete zkusit jinou fotku; podstavec, jméno a věnování změníte i bez nového generování.'],
            ['q' => 'Mohu model stáhnout a vytisknout sám?', 'a' => 'Ano, jako STL nebo projekt pro svou tiskárnu. Busty, které jsme tiskli, podpěry nepotřebovaly; kontrola v kalkulaci to u vašeho modelu ověří.'],
            ['q' => 'Jak výtisk dostanu?', 'farm' => true, 'a' => 'Pošleme ho Zásilkovnou na výdejní místo nebo na adresu v EU (v Česku od :cz_point). Jakmile balík předáme, přijde e-mail s odkazem na sledování zásilky.'],
        ],
        'who' => [
            ['t' => 'Dárek s osobním věnováním', 'text' => 'Busta 120 mm se jménem a věnováním na podstavci, k narozeninám, k výročí nebo jako poděkování. Vejde se na poličku i na pracovní stůl.'],
            ['t' => 'Kolega, trenér, učitelka', 'text' => 'Na rozlučku nebo konec sezóny: jedna fotka zepředu stačí, podpis týmu se vejde na podstavec.'],
            ['t' => 'Sami pro sebe', 'text' => 'Vlastní figurka 80 mm na stůl nebo jako předloha pro malování akrylovými barvami.'],
        ],
    ],

    'pet' => [
        'order' => [
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Model vzniká 2 až 4 minuty, tisk figurky podle velikosti hodinu až tři. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů.'],
            ['q' => 'Z čeho a v jaké barvě se figurka tiskne?', 'a' => 'Z jedné barvy plastu PLA nebo PLA+. Srst a detaily nese tvar, ne barva; matné odstíny je ukážou nejlépe.'],
            ['q' => 'Co když se mi model nelíbí?', 'a' => 'Model si nejdřív prohlédnete ve 3D. V rámci denního limitu (:guest bez účtu, :account s účtem) můžete zkusit jinou fotku nebo jiný styl; podstavec změníte i bez nového generování.'],
            ['q' => 'Mohu model stáhnout a vytisknout sám?', 'a' => 'Ano, jako STL nebo projekt pro svou tiskárnu. Kontrola v kalkulaci upozorní na tenké nohy, uši nebo ocas, které by se tiskly špatně.'],
            ['q' => 'Jak výtisk dostanu?', 'farm' => true, 'a' => 'Pošleme ho Zásilkovnou na výdejní místo nebo na adresu v EU (v Česku od :cz_point). Jakmile balík předáme, přijde e-mail s odkazem na sledování zásilky.'],
        ],
        'who' => [
            ['t' => 'Dárek pro pejskaře a kočičkáře', 'text' => 'Figurka 80 až 100 mm se jménem zvířete na podstavci, na poličku nebo vedle misky.'],
            ['t' => 'Na památku', 'text' => 'Z oblíbené fotky mazlíčka, který už není s vámi. Stačí jedna ostrá fotka celé postavy z boku nebo zepředu.'],
            ['t' => 'Do auta nebo na stůl', 'text' => 'Menší figurka kolem 60 mm, která se vejde k monitoru nebo na palubní desku.'],
        ],
    ],

    'qr' => [
        'order' => [
            ['q' => 'Jak velká má cedulka být, aby šla načíst?', 'a' => 'Na stůl nebo pult stačí výchozích 70 mm, jedno pole kódu má pak kolem 2 mm. Na zeď nebo ke vchodu, kde se čte z větší dálky, zvolte větší cedulku. Nástroj u každé velikosti ukáže velikost pole a hlídá, aby šel kód přečíst.'],
            ['q' => 'Kolik kusů mohu objednat najednou?', 'farm' => true, 'a' => 'Až 64 v jedné objednávce. Kalkulace ukáže, kolik se jich vejde na jednu podložku (výchozí cedulka 6 kusů na 250 × 250 mm), další podložky se tisknou za sebou.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Výchozí cedulka 70 mm se tiskne asi 25 minut. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Vydrží cedulka venku?', 'a' => 'Na stůl a dovnitř stačí PLA. Na přímé slunce a déšť zvolte materiál, který je v kalkulaci v porovnání materiálů označený pro venkovní použití.'],
            ['q' => 'Co když kód nepůjde načíst?', 'a' => 'Odkaz si vyzkoušejte telefonem z náhledu na obrazovce ještě před objednáním. Nástroj hlídá velikost pole a kontrast; když výtisk kvůli vadě tisku načíst nejde, reklamujete ho.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete až na objednávku tisku u nás.'],
        ],
        'who' => [
            ['t' => 'Kavárna a restaurace', 'text' => 'Menu nebo objednávka u stolu: cedulka 70 mm se stojánkem na každý stůl, kód vede na váš jídelní lístek.'],
            ['t' => 'Ubytování a Airbnb', 'text' => 'Wi‑Fi pro hosty bez přepisování hesla: cedulka 100 mm na poličku u vchodu, host se připojí jedním naskenováním.'],
            ['t' => 'Obchod, stánek, salon', 'text' => 'Odkaz na e‑shop, recenze na Googlu nebo rezervace: cedulka u pokladny vedle terminálu.'],
        ],
    ],

    'organizer' => [
        'order' => [
            ['q' => 'Jak správně změřit zásuvku?', 'a' => 'Změřte vnitřní šířku a hloubku v nejužším místě (u dna bývají rohy zaoblené) a odečtěte 2 až 3 mm, aby šel organizér vložit i vyndat. Výšku zvolte o pár milimetrů nižší, než je zásuvka, jinak se nezavře.'],
            ['q' => 'Jak přesně rozměry sedí?', 'a' => 'Tiskárna drží rozměr v řádu desetin milimetru a velký díl se po vychladnutí může nepatrně smrštit. Proto počítejte s vůlí 2 až 3 mm kolem dokola.'],
            ['q' => 'Kolik stojí a jak dlouho se tiskne?', 'farm' => true, 'a' => 'Záleží hlavně na velikosti a výšce. Organizér 200 × 120 × 40 mm se 3 × 2 přihrádkami se tiskne asi dvě a půl hodiny a vyjde na 250 Kč (PLA+, bez dopravy). Kalkulace ukáže přesnou dobu a cenu dřív, než cokoli objednáte.'],
            ['q' => 'Vejde se velký organizér na tiskárnu?', 'a' => 'Kalkulace hned ukáže, jestli se vejde na tiskárnu. Když je moc velký, rozdělte zásuvku na dva organizéry, nebo použijte modulární misky.'],
            ['q' => 'Z jakého materiálu?', 'a' => 'Do zásuvky v kanceláři nebo dílně stačí PLA. Do koupelny nebo blízko trouby zvolte PETG, které lépe snáší teplo a vlhko.'],
            ['q' => 'Co když nepasuje?', 'farm' => true, 'a' => 'Výtisk vyrábíme na zakázku podle vašich rozměrů, proto ho nejde bez důvodu vrátit. Když neodpovídá rozměrům z objednávky, je to vada a reklamujete ji.'],
        ],
        'who' => [
            ['t' => 'Dílna', 'text' => 'Bity, šroubky a hmoždinky v zásuvce ponku: mřížka 4 × 3 přihrádky, výška kolem 40 mm.'],
            ['t' => 'Kuchyň', 'text' => 'Příbory, koření a drobné náčiní: organizér přesně na šířku zásuvky, z PETG.'],
            ['t' => 'Psací stůl', 'text' => 'Tužky, nabíječky a kabely: mělký organizér 20 až 30 mm, aby se zásuvka dobře zavírala.'],
        ],
    ],

    'box' => [
        'order' => [
            ['q' => 'Jak změřit, co se má vejít dovnitř?', 'a' => 'Změřte věc v nejširším místě a přidejte 1 až 2 mm na každou stranu. Nástroj zadává vnitřní rozměry, vnější dopočítá podle stěn a dna.'],
            ['q' => 'Jak přesně krabička sedí?', 'a' => 'Tiskárna drží rozměr v řádu desetin milimetru. Víčko má vůli 0,25 mm, kterou můžete změnit; u první krabičky z nové sady ji vyzkoušejte.'],
            ['q' => 'Kolik stojí a jak dlouho se tiskne?', 'farm' => true, 'a' => 'Krabička 84 × 54 × 32 mm s víčkem se tiskne asi 35 minut a vyjde na 99 Kč (PLA+, bez dopravy). Pro jiné rozměry ukáže cenu a dobu kalkulace dřív, než cokoli objednáte. Odesíláme v den, kdy tisk skončí, v pracovní dny.'],
            ['q' => 'Z jakého materiálu?', 'a' => 'Na drobnosti a elektroniku uvnitř stačí PLA. Do tepla nebo vlhka zvolte PETG.'],
            ['q' => 'Kolik stojí doprava?', 'farm' => true, 'a' => 'Zásilkovnou na výdejní místo v Česku :cz_point, domů :cz_home, na Slovensko od :sk_point, do dalších zemí EU od :eu_point.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete až na objednávku tisku u nás.'],
        ],
        'who' => [
            ['t' => 'Elektronika', 'text' => 'Krabička na Arduino, ESP32 nebo zdroj s otvory na kabely a výřezem na konektor.'],
            ['t' => 'Drobnosti', 'text' => 'Náušnice, SD karty, náhradní knoflíky: malá krabička s víčkem, přesně na velikost.'],
            ['t' => 'Dárkové balení', 'text' => 'Krabička na míru dárku, v barvě, kterou si vyberete, třeba s výřezem na stuhu.'],
        ],
    ],
];
