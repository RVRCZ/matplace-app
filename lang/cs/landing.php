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

    // vstupní stránky session N1 (docs/AP.md): SVG do STL, oprava, kontrola
    'svg_to_stl' => [
        'order' => [
            ['q' => 'Jak vysoko tvar vytáhnout, aby se dal vytisknout a držel?', 'a' => 'Nejméně 0,6 mm, to jsou tři vrstvy po 0,2 mm; štítek nebo nálepka na dveře snese 2 až 3 mm, přívěsek na klíče 4 až 6 mm a tvar, který má stát sám, 20 mm a víc. Čím výš, tím víc gramů a minut; kalkulace to přepočítá po každé změně.'],
            ['q' => 'Jak tenké čáry se vytisknou?', 'a' => 'Tryska 0,4 mm potřebuje dvě linky vedle sebe, tedy aspoň 0,8 mm. Nástroj čáry pod 0,8 mm označí předem; v editoru je zesilte, nebo tvar zvětšete.'],
            ['q' => 'Kolik stojí vytištěný tvar a jak dlouho se tiskne?', 'farm' => true, 'a' => 'Kočka 80 mm vytažená na 6 mm má 9,6 g a tiskne se 17 minut, u nás za 99 Kč, což je nejnižší cena zakázky (PLA, bez dopravy). Větší nebo vyšší tvar ukáže kalkulace v gramech, minutách a korunách dřív, než cokoli objednáte.'],
            ['q' => 'Můžu mít motiv jinou barvou než podklad?', 'a' => 'Vytažený obrys je jedna barva. Když má být motiv na destičce jinou barvou, přepněte provedení na reliéf: barvy pak leží nad sebou a tiskárna je zvládne výměnou filamentu ve výšce destičky, u nás i doma.'],
            ['q' => 'Jak dlouho trvá doručení?', 'farm' => true, 'a' => 'Odesíláme v den, kdy tisk skončí, v pracovní dny. Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů, na výdejní místo za :cz_point, domů za :cz_home; na Slovensko od :sk_point, do EU od :eu_point.'],
            ['q' => 'Můžu výsledek dál upravovat?', 'a' => 'Ano. Vzniklý STL je obyčejný model: v kalkulaci ho zvětšíte nebo zmenšíte, nástroj Puzzle ho rozřeže na dílky, Forma z něj udělá formu na odlévání a Vykrajovátko ze stejného obrysu vyrobí vykrajovátko na těsto.'],
        ],
        'who' => [
            ['t' => 'Logo firmy nebo klubu', 'text' => 'Logo z vektorového souboru jako cedulka na stůl, přívěsek na klíče nebo znak na dveře, 60 až 120 mm, přesně podle grafiky.'],
            ['t' => 'Přesný díl z 2D výkresu', 'text' => 'Rozpěrka, podložka, šablona nebo vložka, kterou máte nakreslenou v CADu nebo v Inkscapu: šířka v milimetrech, otvory zůstanou otvory.'],
            ['t' => 'Kresba a ikona', 'text' => 'Dětská kresba převedená na obrys, ikona z Noun Project nebo silueta z naší knihovny jako ozdoba, magnetka nebo přívěsek.'],
        ],
    ],

    'repair' => [
        'order' => [
            ['q' => 'Jak poznám, že model potřebuje opravu?', 'a' => 'Slicer hlásí non‑manifold, otevřené hrany nebo neuzavřenou síť, v náhledu chybí kousky povrchu, nebo výtisk má díry a vrstvy navíc. Když si nejste jistí, nejdřív model nahrajte do kontroly; ta řekne, co je špatně, a opravu nabídne jedním tlačítkem.'],
            ['q' => 'Jak dlouho oprava trvá a jak velký soubor zvládne?', 'a' => 'Soubor do 120 MB. Běžný model je hotový za pár vteřin, síť s miliony trojúhelníků do minuty; stránka říká, co se právě děje.'],
            ['q' => 'Kolik stojí tisk opraveného modelu?', 'farm' => true, 'a' => 'Oprava nic nestojí. Tisk u nás spočítá kalkulace z gramů a minut; nejnižší cena zakázky je 99 Kč a busta 60 × 52 × 78 mm z příkladu vyjde kolem 110 Kč (PLA, bez dopravy).'],
            ['q' => 'Pomůže oprava i modelu pro pryskyřicový tisk?', 'a' => 'Ano, STL je pro FDM i pryskyřici stejný a uzavřená síť je potřeba v obou. Pro pryskyřici model po opravě vydutíte s odtokovými otvory, ušetříte pryskyřici a díl nepraskne.'],
            ['q' => 'Jak výtisk dostanu?', 'farm' => true, 'a' => 'Pošleme ho Zásilkovnou na výdejní místo (v Česku :cz_point) nebo na adresu v EU. Odesíláme v den, kdy tisk skončí, v pracovní dny; jakmile balík předáme, přijde e‑mail se sledováním.'],
            ['q' => 'Co když slicer po opravě pořád hlásí chybu?', 'a' => 'Ve zprávě stojí, co se nepodařilo: chybějící velký kus povrchu nebo stěnu bez tloušťky nástroj nedoplní. Zbytek je ruční práce v Blenderu nebo Meshmixeru; drobné vady slicer často přejde a výtisk se povede.'],
        ],
        'who' => [
            ['t' => 'Model z internetu', 'text' => 'Držák, krytka nebo figurka z Printables, Thingiverse nebo MakerWorldu, kterou slicer odmítá. Oprava trvá vteřiny a nemění rozměry.'],
            ['t' => 'Sken z telefonu', 'text' => 'Fotogrammetrie a skenery nechávají díry a smítka. Oprava je zacelí a spojí úlomky, tvar skenu zůstane.'],
            ['t' => 'Export z CADu nebo z hry', 'text' => 'Soubor ze SketchUpu, Tinkercadu nebo z herního modelu s převrácenými a zdvojenými plochami. Oprava je otočí a odstraní.'],
        ],
    ],

    'check' => [
        'order' => [
            ['q' => 'Hlídá kontrola i tloušťku stěn?', 'a' => 'Jen nejtenčí rozměr celého modelu: pod 0,8 mm je chyba, protože tryska 0,4 mm takovou stěnu nevytiskne. Tenké místo uvnitř jinak silného modelu kontrola nenajde; to ukáže až slicer.'],
            ['q' => 'Co se stane s modelem, který neprojde?', 'a' => 'Díry a převrácené plochy pošlete jedním tlačítkem do opravy. Model větší než tiskárna zmenšíte v kalkulaci měřítkem, nebo ho rozdělíte na díly s kolíky. Špatné jednotky vyřeší nové uložení v milimetrech.'],
            ['q' => 'Projde-li kontrola, můžu rovnou objednat tisk?', 'farm' => true, 'a' => 'Ano. Tlačítko k ceně otevře model v kalkulaci, kde vidíte gramy, dobu tisku a cenu; nejnižší cena zakázky je 99 Kč. Stejná kontrola běží i v kalkulaci, takže nic nehlásí dvakrát.'],
            ['q' => 'Kontroluje se i STEP, 3MF a OBJ?', 'a' => 'Ano. STEP z CADu se převede na síť a zkontroluje stejně jako STL; u 3MF se kontroluje tvar, barvy a nastavení tisku zůstávají stranou.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Dobu tisku ukáže kalkulace. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Ukládáte můj soubor?', 'a' => 'Soubor slouží ke kontrole, k opravě a ke kalkulaci a nikomu dalšímu ho neposíláme. Kontrola do něj nezasahuje; stažený model je stejný soubor, jaký jste nahráli.'],
        ],
        'who' => [
            ['t' => 'Před tiskem doma', 'text' => 'Stažený model před slicováním: za pár vteřin víte, jestli má správné jednotky, uzavřenou síť a vejde se na podložku.'],
            ['t' => 'Kroužek, škola, FabLab', 'text' => 'Soubory od studentů z Tinkercadu a SketchUpu: zpráva řekne každému, co má opravit, bez instalace programu.'],
            ['t' => 'Před objednávkou u nás', 'text' => 'Stejnou kontrolou projde každý model na naší farmě. Kdo ji udělá předem, ví, že objednávka projde bez dotazů.'],
        ],
    ],

    // vstupní stránky session N1, dávka 2 (docs/AP.md §4): vydutit, rozdělit, G‑kód
    'hollow' => [
        'order' => [
            ['q' => 'Kolik ušetřím na ceně tisku u vás?', 'farm' => true, 'a' => 'Záleží na velikosti. Váleček ⌀ 120 × 200 mm stál plný 910 Kč a dutý se stěnou 2,5 mm 500 Kč (PLA+, bez dopravy); u malého dílu se cena nezmění, protože nejnižší cena zakázky je 99 Kč. Rozdíl ukáže nástroj hned po vydutění a kalkulace ho potvrdí.'],
            ['q' => 'Tiskne se dutý model bez podpěr?', 'a' => 'Zvenku stejně jako plný. Uvnitř má dutina strop, který slicer buď překlene, nebo podepře vnitřními podpěrami; ty po tisku vysypete odtokovými otvory. Pro FDM volte stěnu 2 až 3 mm, slicer ji ještě vyplní podle svého nastavení.'],
            ['q' => 'Jak vydutit model pro pryskyřicový tisk?', 'a' => 'Stěna 2 až 3 mm a aspoň dva otvory 3 až 4 mm ve dně, aby pryskyřice vytekla a nevznikl podtlak. Váleček ⌀ 120 × 200 mm by plný spolkl 2,3 litru pryskyřice, dutý asi čtvrt litru.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Dutý model se tiskne kratší dobu: váleček z příkladu 6 h 36 min místo 8 h 14 min. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Můžu dutinu zatížit pískem?', 'a' => 'Ano. Otvory ve dně nasypete písek nebo broky a zalepíte je tavnou pistolí nebo kouskem plastu. Těžítko, stojan na vlajku nebo figurka tak stojí pevně a model zůstává lehký na tisk.'],
            ['q' => 'Je vydutění zdarma a potřebuji účet?', 'a' => 'Vydutění i stažení dutého modelu jsou zdarma a bez účtu, soubor slouží jen k výpočtu a nikomu ho neposíláme. Účet potřebujete, až když si model necháte vytisknout u nás.'],
        ],
        'who' => [
            ['t' => 'Velká figura nebo busta', 'text' => 'Nad 150 mm ušetří dutina stovky gramů; model nad 250 mm ještě rozdělte na díly, které se vejdou na tiskárnu.'],
            ['t' => 'Pryskyřicový tisk', 'text' => 'Plný díl praská a spotřebuje litry. Dutý se stěnou 2 až 3 mm a odtokovými otvory se tiskne čistě a levně.'],
            ['t' => 'Těžítko a stojan', 'text' => 'Dutina na písek nebo broky: figurka, stojan na vlajku nebo podstavec stojí pevně a tisk zůstane rychlý.'],
        ],
    ],

    'split' => [
        'order' => [
            ['q' => 'Kolik stojí tisk dílů u vás?', 'farm' => true, 'a' => 'Kalkulace sečte všechny díly i kolíky: váleček ⌀ 120 × 320 mm na dva díly vyšel na 1 440 Kč (814 g, PLA+, bez dopravy). Díly objednáte v jedné objednávce a dostanete je pohromadě.'],
            ['q' => 'Kolik řezů bude potřeba?', 'a' => 'Nástroj to řekne po nahrání: figura vysoká 60 cm potřebuje na podložku 250 mm dva řezy, tedy tři díly. Roviny můžete posunout tam, kde se spoj schová, třeba do pasu nebo na hranu podstavce.'],
            ['q' => 'Jaké kolíky použít a kolik jich bude?', 'a' => 'Kolíky mají průměr 6 mm a díry vůli 0,2 mm. Nástroj jich do každého spoje dá tolik, kolik se vejde (váleček z příkladu dostal čtyři) a přidá je do souboru jako samostatný díl; místo nich poslouží dřevěné kolíky Ø 6 mm.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Kalkulace sečte dobu všech dílů; váleček z příkladu se tiskl 13 h 8 min. Díly se tisknou za sebou, odesíláme v den, kdy skončí poslední, v pracovní dny; Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Můžu model zároveň zvětšit?', 'a' => 'Na to je nástroj Životní velikost: zadáte výšku v centimetrech a model se zvětší a rozdělí najednou. Tady se model jen řeže, velikost změníte předem v kalkulaci.'],
            ['q' => 'Je rozdělení zdarma a potřebuji účet?', 'a' => 'Rozdělení i stažení dílů jsou zdarma a bez účtu, soubor slouží jen k výpočtu a nikomu ho neposíláme. Účet potřebujete, až když si díly necháte vytisknout u nás.'],
        ],
        'who' => [
            ['t' => 'Figura v životní velikosti', 'text' => 'Socha, busta nebo postava z hry nad 250 mm: díly s kolíky, číslem v řezu a mapou, jak je složit.'],
            ['t' => 'Cosplay a rekvizity', 'text' => 'Helma, meč nebo štít delší než podložka: rybinové klíče drží bez lepidla a díly jdou převážet rozložené.'],
            ['t' => 'Dlouhý díl', 'text' => 'Lišta, kryt nebo držák delší než 250 mm: jeden řez, dva kolíky, spoj zabroušený a přetřený není vidět.'],
        ],
    ],

    'gcode' => [
        'order' => [
            ['q' => 'Kolik stojí tisk modelu z G‑kódu u vás?', 'farm' => true, 'a' => 'Jako u každého modelu: kalkulace ho nařeže pro naše tiskárny a cenu spočítá z gramů a minut; čas a gramy z hlavičky G‑kódu jsou jen orientační. Nejnižší cena zakázky je 99 Kč (PLA, bez dopravy).'],
            ['q' => 'Nemůžete vytisknout rovnou můj G‑kód?', 'a' => 'Ne. G‑kód je psaný pro jednu tiskárnu a její nastavení; naše farma tiskne z modelu, který nařeže pro svůj stroj a materiál. Převod na model to umožní a navíc můžete změnit velikost nebo barvy.'],
            ['q' => 'Proč je povrch modelu schodovitý?', 'a' => 'Model nese vrstvy výtisku, ze kterého vznikl, třeba 0,2 mm. Při tisku se schodky překryjí novými vrstvami a vidět nejsou; po zvětšení modelu je přebruste, nebo tiskněte jemnější vrstvou.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Dobu tisku ukáže kalkulace. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Mám .gcode.3mf z Bambu Studia nebo .bgcode z Prusy. Co s tím?', 'a' => 'Soubor .gcode.3mf je zip: rozbalte ho a nahrajte .gcode ze složky Metadata. Binární .bgcode nástroj nečte; v PrusaSliceru vypněte binární G‑kód v nastavení tiskárny a exportujte znovu, nebo soubor převeďte nástrojem bgcode od Prusy.'],
            ['q' => 'Je převod zdarma a potřebuji účet?', 'a' => 'Převod i stažení jsou zdarma a bez účtu, G‑kód slouží jen k převodu a kalkulaci a nikomu ho neposíláme. Účet potřebujete, až když si model necháte vytisknout u nás.'],
        ],
        'who' => [
            ['t' => 'Ztracený zdrojový model', 'text' => 'Výtisk se povedl, ale STL je pryč. Z G‑kódu na SD kartě vznikne model, který znovu vytisknete, zvětšíte nebo přebarvíte.'],
            ['t' => 'Soubor od někoho jiného', 'text' => 'Dostali jste jen G‑kód pro cizí tiskárnu. Jako model ho nařežete pro svou, nebo ho necháte vytisknout u nás.'],
            ['t' => 'Archiv na SD kartě', 'text' => 'Staré G‑kódy z tiskárny jako modely do katalogu: vidíte tvar, rozměry a cenu dalšího kusu.'],
        ],
    ],

    // vstupní stránky session N1, dávka 3 (docs/AP.md §5): litofanie, mapa
    'relief' => [
        'order' => [
            ['q' => 'Kolik stojí litofanie u vás?', 'farm' => true, 'a' => 'Panel 100 × 75 mm se stolním stojánkem vyšel na 100 Kč, lampa na objímku E27 o průměru 100 mm a výšce 150 mm na 250 Kč (bez dopravy, kalkulace z 18. 10. 2026). Tiskneme z bílého materiálu; přesnou cenu vaší fotky a velikosti ukáže kalkulace dřív, než cokoli objednáte.'],
            ['q' => 'Jaké světlo dát do lampy?', 'a' => 'LED žárovku E27 nebo E14, nejvýš kolem 10 W; klasickou ani halogenovou ne, PLA měkne už kolem 55 °C. Teplá bílá dá obrazu jemnější odstíny, studená ostřejší kontrast. Do otevřeného dna se vejde LED pásek, do zavřeného LED svíčka.'],
            ['q' => 'Jak litofanii tisknout doma?', 'a' => 'Panel nastojato a plný, lampa nastojato na dně, reliéf naležato bez podpěr; stažený projekt pro slicer má nastavení, které používáme my. Materiál bílý PLA; jiná barva obraz zabarví nebo zatemní.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Panel 100 × 75 mm se tiskne asi 1 h 9 min, lampa 150 mm asi 2 h 29 min. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Stačí fotka z telefonu?', 'a' => 'Ano. Na destičce 100 mm se stejně uplatní jen tolik detailu, kolik dá tryska 0,4 mm; rozhoduje kontrast a jeden hlavní motiv, ne rozlišení. Posuvníky jasu a kontrastu nastavte tak, aby nástroj hlásil co nejvíc odstínů, a náhled proti světlu ukáže výsledek.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne; fotku neukládáme a v modelu zůstává jen tvar. Účet potřebujete, až když si litofanii necháte vytisknout u nás.'],
        ],
        'who' => [
            ['t' => 'Dárek z fotky', 'text' => 'Portrét, svatební nebo rodinná fotka jako panel 100 × 75 mm se stojánkem na poličku nebo k oknu; obraz se objeví, když za ni zasvítí slunce nebo lampa.'],
            ['t' => 'Lampa do dětského pokoje', 'text' => 'Fotka kolem dokola válce s LED žárovkou E27: ve dne bílá lampa, večer rozsvícený obrázek.'],
            ['t' => 'Reliéf na zeď', 'text' => 'Fotka nebo logo jako plastický obraz, který je vidět i bez světla; na památku, do kanceláře nebo jako cedule.'],
        ],
    ],

    'map' => [
        'order' => [
            ['q' => 'Kolik stojí mapa u vás?', 'farm' => true, 'a' => 'Staré Město 500 m na desce 150 mm vyšlo na 240 Kč jednou barvou (85 g, bez dopravy, kalkulace z 18. 10. 2026). Barvy podkladu, silnic a budov vyberete při objednávce; cenu vidíte dřív, než cokoli objednáte.'],
            ['q' => 'Jaký rozsah vybrat?', 'a' => 'Pro centrum nebo čtvrť 500 m, pro menší město 1 km, pro velké město 2 km; na desce 150 mm je u kilometru budova 20 m vysoká 3 mm. Větší území vyberte jako krajinu 2 až 20 km, kde je hlavní reliéf terénu a obce jsou nízké bloky.'],
            ['q' => 'Jak mapu tisknout doma?', 'a' => 'Naplocho, bez podpěr, vrstva 0,2 mm. Projekt 3MF pro OrcaSlicer a PrusaSlicer má v sobě dvě výměny filamentu (silnice, budovy); tiskárna se zastaví, vyměníte cívku a tisk pokračuje. STL je jedno těleso pro tisk jednou barvou.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Mapa 150 mm se tiskne asi 3 h 42 min. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Moje obec nemá v mapě výšky budov. Co s tím?', 'a' => 'Budovy bez údaje dostanou výšku, kterou zadáte, třeba 8 m pro rodinné domy. Náhled oblasti ukáže, které budovy a ulice v OpenStreetMap jsou; co chybí, může kdokoli do OpenStreetMap doplnit a mapa to po aktualizaci dat převezme.'],
            ['q' => 'Co bude na rámu?', 'a' => 'Vyvýšený název, který zadáte: jméno města, ulice nebo věnování. Rám jde i vypnout, deska pak končí hranou mapy.'],
        ],
        'who' => [
            ['t' => 'Dárek z rodného města', 'text' => 'Centrum, kde někdo vyrostl nebo kde byla svatba: 500 m na desce 150 mm se jménem na rámu, ve třech barvách.'],
            ['t' => 'Čtvrť, kampus, areál', 'text' => 'Pro architekty, školy a správce areálů: budovy se skutečnými výškami a ulice podle druhu, do minuty bez kreslení.'],
            ['t' => 'Krajina na výlet', 'text' => 'Kopec, přehrada nebo údolí jako reliéf s převýšením, se silnicemi a vodou na povrchu; 2 až 20 km na jedné desce.'],
        ],
    ],

    // vánoční nástroje session N1 (docs/AP.md §6): perníček, ozdoba, vykrajovátko, jmenovka, zápich, písmeno
    'gingerbread' => [
        'order' => [
            ['q' => 'Do kdy objednat, aby perníčky dorazily do Vánoc?', 'farm' => true, 'a' => 'Perníček 90 mm se tiskne 20 minut a tisk začne, jakmile je tiskárna volná; odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů. V prosinci si nechte rezervu a objednejte aspoň týden předem.'],
            ['q' => 'Jak perníček zavěsit?', 'a' => 'Očkem nahoře s otvorem 3 až 6 mm projde stužka, provázek i háček na ozdoby. Perníček 90 mm váží kolem 11 g, takže drží i na tenké větvi; očko posunete tam, kde má tvar těžiště, třeba na cíp hvězdy.'],
            ['q' => 'Jakou barvu podkladu a polevy zvolit?', 'a' => 'Klasika je hnědý podklad a bílá poleva; dobře vypadá i červená se zlatou, zelená s bílou nebo tmavě modrá se stříbrnou. Matné filamenty skryjí vrstvy tisku lépe než lesklé. Vybíráte z cívek, které máme právě skladem.'],
            ['q' => 'Kolik stojí doprava?', 'farm' => true, 'a' => 'Zásilkovnou na výdejní místo v Česku :cz_point, domů :cz_home, na Slovensko od :sk_point, do dalších zemí EU od :eu_point. Víc perníčků v jedné objednávce jede v jednom balíku.'],
            ['q' => 'Jak perníček tisknout doma?', 'a' => 'Naplocho, lícem nahoru, bez podpěr, z PLA. Stažený projekt pro OrcaSlicer, Bambu Studio nebo PrusaSlicer má v sobě pauzu ve výšce polevy: tiskárna se zastaví, vyměníte cívku a tisk pokračuje bílou. Tiskárna s více cívkami to udělá sama.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si perníčky necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Jmenovky na stromeček pro celou rodinu', 'text' => 'Každý má svůj perníček se jménem: jeden stojí 99 Kč, šest stejných 140 Kč, pro další jména další návrh.'],
            ['t' => 'Dárek pro učitelku a kolegy', 'text' => 'Perníček 60 mm se jménem na stužce místo jmenovky na balíčku; po rozbalení visí na stromečku.'],
            ['t' => 'Školka a kroužek', 'text' => 'Ozdoby se jmény dětí z vlastní tiskárny: projekt s jednou výměnou filamentu, 20 minut na kus.'],
        ],
    ],

    'ornament' => [
        'order' => [
            ['q' => 'Kolik stojí ozdoba u vás a jak dlouho se tiskne?', 'farm' => true, 'a' => 'Perníček 80 mm se tiskne 18 minut a stojí 99 Kč, což je nejnižší cena zakázky; šest kusů vyjde na 160 Kč, tedy 27 Kč za ozdobu (PLA+, bez dopravy, kalkulace z 18. 10. 2026). Cenu vaší ozdoby ukáže kalkulace dřív, než cokoli objednáte.'],
            ['q' => 'Můžu udělat ozdobu z dětské kresby?', 'a' => 'Ano. Kresbu na bílém papíře vyfoťte shora při denním světle, nástroj odstraní pozadí a barvy sloučí do 2 až 4 filamentů; jemné čáry tužkou zesilte fixem. Ozdoba pak kopíruje obrys kresby, nebo ji dáte do kruhu.'],
            ['q' => 'Jak dlouho trvá doručení?', 'farm' => true, 'a' => 'Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point). Před Vánoci objednejte s předstihem.'],
            ['q' => 'Jak ozdobu tisknout doma ve více barvách?', 'a' => 'Naplocho, lícem nahoru, bez podpěr. Stažený projekt má výměny filamentu ve výškách jednotlivých barev; s jednou tryskou cívky vyměníte při pauze, tiskárna s AMS, MMU nebo ACE je vymění sama.'],
            ['q' => 'Vydrží ozdoba venku nebo u krbu?', 'a' => 'Na stromeček v bytě stačí PLA. Blízko topení nebo krbu zvolte PETG, které snese víc tepla; na slunce a mráz venku se tenká ozdoba nehodí, barvy na přímém slunci blednou.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si ozdoby necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Stromeček z dětských kreseb', 'text' => 'Každá kresba jako ozdoba 70 až 90 mm ve třech barvách, s očkem na stužku; z jedné fotky kresby za pár vteřin.'],
            ['t' => 'Firemní dárek s logem', 'text' => 'Logo z SVG jako ozdoba ve firemních barvách, až čtyři barvy v jednom tisku, pro zákazníky i tým.'],
            ['t' => 'Babičce se jmény vnoučat', 'text' => 'Jméno v jednom ze třiceti písem na kruhu nebo hvězdě, pro každé vnouče jiná barva.'],
        ],
    ],

    'cutter' => [
        'order' => [
            ['q' => 'Kolik stojí vykrajovátko u vás?', 'farm' => true, 'a' => 'Vykrajovátko jména Ela (82 × 54 mm) se tiskne 31 minut a stojí 99 Kč, což je nejnižší cena zakázky (PLA+, bez dopravy, kalkulace z 18. 10. 2026). Každý tvar je vlastní návrh a vlastní kalkulace; počet kusů jednoho tvaru zadáte v ní.'],
            ['q' => 'Jak vykrajovat, aby se těsto nelepilo?', 'a' => 'Těsto nechte vychladlé a vykrajovátko před každým tvarem namočte do mouky. Tlačte rovně za obrubu, nekruťte, a tvar vysuňte prstem z hrany; zúžená hrana řeže čistěji než rovná.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Vykrajovátko se tiskne 25 až 35 minut. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point). Na vánoční pečení objednejte s předstihem.'],
            ['q' => 'Jak vykrajovátko tisknout doma?', 'a' => 'Obrubou dolů, bez podpěr, z PLA nebo PETG; stažený projekt má stěnu 1 mm a vrstvu 0,2 mm z kalkulace. Razítko, pokud vzniklo, se tiskne vedle jako samostatný díl.'],
            ['q' => 'Dá se udělat sada písmen na jméno?', 'a' => 'Ano, dvěma způsoby: celé jméno jako jedno vykrajovátko na jeden kus cukroví (dva řádky po 20 znacích), nebo pro každé písmeno vlastní vykrajovátko, které objednáte zvlášť. Na menší písmena zvolte tučné písmo, aby těsto drželo tvar.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si vykrajovátko necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Vánoční cukroví se jménem', 'text' => 'Jméno nebo iniciály v psacím písmu, 80 až 120 mm, na perníčky a linecké pro každého u stolu.'],
            ['t' => 'Narozeninová číslice', 'text' => 'Číslice 70 až 90 mm na sušenky k oslavě; rok nebo věk jako jeden kus.'],
            ['t' => 'Logo na sušenky', 'text' => 'Logo z SVG jako vykrajovátko a razítko s detaily uvnitř, na firemní akci nebo do kavárny.'],
        ],
    ],

    'nameplate' => [
        'order' => [
            ['q' => 'Kolik stojí jmenovky na dárky u vás?', 'farm' => true, 'a' => 'Jmenovka 70 mm s očkem se tiskne 10 minut: jedna stojí 99 Kč, což je nejnižší cena zakázky, deset stejných 120 Kč, tedy 12 Kč za kus (PLA, bez dopravy, kalkulace z 27. 10. 2026). Pro různá jména uděláte návrh pro každé; cenu vidíte dřív, než cokoli objednáte.'],
            ['q' => 'Jak jmenovku pověsit na dárek?', 'a' => 'Zaškrtněte očko: otvor 2 až 8 mm projde stužkou i provázkem. Jmenovka 70 mm váží 5 g, takže drží na mašli i na větvi; bez očka ji přilepíte na balicí papír oboustrannou páskou.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Jmenovka se tiskne 10 až 25 minut podle velikosti. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point). Před Vánoci objednejte s předstihem.'],
            ['q' => 'Jak jmenovku tisknout doma ve více barvách?', 'a' => 'Naplocho, bez podpěr. Stažený projekt má výměnu filamentu ve výšce každé vrstvy; s jednou tryskou vyměníte cívku při pauze, tiskárna s AMS, MMU nebo ACE to udělá sama. STL je jedno těleso pro tisk jednou barvou.'],
            ['q' => 'Vydrží jmenovka na tašce nebo na klíčích?', 'a' => 'Na dárek stačí PLA. Na batoh, klíče nebo obojek zvolte PETG, tloušťku vrstev nechte výchozí a očko aspoň 4 mm; písmo aspoň 6 mm vysoké zůstane čitelné i po roce nošení.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si jmenovky necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Jmenovky na vánoční dárky', 'text' => 'Šestiúhelník nebo hvězda 60 až 80 mm s očkem a jménem ve dvou barvách: deset kusů za 120 Kč.'],
            ['t' => 'Cedulka na dveře pokojíčku', 'text' => 'Mrak nebo domek 120 mm se jménem a oblíbeným zvířetem, tři barvy, na dveře nebo poličku.'],
            ['t' => 'Visačka na batoh a klíče', 'text' => 'Kost, štít nebo stuha z PETG s očkem 4 mm, jméno a telefonní číslo jako dvě vrstvy textu.'],
        ],
    ],

    'topper' => [
        'order' => [
            ['q' => 'Kolik stojí zápich u vás a kdy dorazí?', 'farm' => true, 'a' => 'Zápich s číslem 2 a jménem Olivia (110 mm) se tiskne 55 minut a stojí 99 Kč, což je nejnižší cena zakázky (PLA, bez dopravy, kalkulace z 27. 10. 2026). Odesíláme v den, kdy tisk skončí, v pracovní dny, Zásilkovna doručí do 1 až 2 pracovních dnů; na oslavu objednejte aspoň týden předem.'],
            ['q' => 'Jak zápich zapíchnout, aby držel?', 'a' => 'Hroty zasuňte do dortu až po tvar, nejlépe skrz piškot, ne jen do krému; dva hroty drží lépe než jeden. Obalte je potravinářskou fólií nebo zasuňte do brček a zápich dejte do dortu až před podáváním.'],
            ['q' => 'Kolik stojí doprava?', 'farm' => true, 'a' => 'Zásilkovnou na výdejní místo v Česku :cz_point, domů :cz_home, na Slovensko od :sk_point, do dalších zemí EU od :eu_point.'],
            ['q' => 'Jak zápich tisknout doma?', 'a' => 'Naplocho, lícem nahoru, bez podpěr, z PLA. Stažený projekt má pauzu ve výšce jména pro výměnu cívky; tiskárna s více cívkami to udělá sama. Tloušťka 3 mm stačí, aby se neohýbal.'],
            ['q' => 'Jaké barvy zvolit?', 'a' => 'Zlatá nebo růžová s bílým jménem, černá s bílým, nebo třpytivý a Silk filament, pokud ho máme skladem. Vybíráte při objednávce z cívek, které jsou v tiskárnách; světlé jméno na tmavém tvaru je na fotkách nejčitelnější.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si zápich necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Dětské narozeniny', 'text' => 'Číslo věku 110 mm se jménem psacím písmem, dva hroty 60 mm, za 99 Kč.'],
            ['t' => 'Svatba a výročí', 'text' => 'Srdce 120 mm s nápisem „Ela a Tom“ nebo datem, zlatá s bílou.'],
            ['t' => 'Silvestr a firemní oslava', 'text' => 'Jen nápis bez tvaru: „2027“, „Děkujeme“ nebo název týmu, 180 mm široký, hroty 80 mm.'],
        ],
    ],

    'name_letter' => [
        'order' => [
            ['q' => 'Kolik stojí písmeno u vás?', 'farm' => true, 'a' => 'Písmeno E vysoké 120 mm se jménem Ela se tiskne 40 minut a stojí 99 Kč, což je nejnižší cena zakázky (PLA, bez dopravy, kalkulace z 27. 10. 2026). Písmeno 200 mm je dražší podle gramů a minut; cenu vidíte dřív, než cokoli objednáte.'],
            ['q' => 'Jak písmeno pověsit na dveře?', 'a' => 'Zaškrtněte očko a pověste ho na stužku nebo háček, nebo ho přilepte oboustrannou páskou; písmeno 120 mm váží 27 g. Na poličku stačí tloušťka 10 mm, nebo podstavec s drážkou z téhož souboru.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Písmeno 120 mm se tiskne 40 minut, 200 mm přes hodinu. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Jak písmeno tisknout doma ve dvou barvách?', 'a' => 'Naplocho, lícem nahoru, bez podpěr. Stažený projekt má pauzu ve výšce jména pro výměnu cívky; podstavec se tiskne vedle. Tiskárna s AMS, MMU nebo ACE vymění barvu sama.'],
            ['q' => 'Jaké barvy se hodí?', 'a' => 'Dvě kontrastní: pastelové tělo s bílým nebo tmavým jménem do dětského pokoje, černá se zlatou na stůl. Matné filamenty skryjí vrstvy tisku lépe než lesklé; vybíráte z cívek, které máme skladem.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si písmeno necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Dárek k narození a ke křtinám', 'text' => 'Písmeno 120 až 150 mm se jménem miminka na poličku nad postýlku; k tomu „Ela 2026“ jako datum.'],
            ['t' => 'Dveře dětského pokoje', 'text' => 'Písmeno 150 mm s očkem na stužce nebo na oboustranné pásce, barvy podle pokoje.'],
            ['t' => 'Svatba: iniciály na stůl', 'text' => 'Dvě písmena 150 mm se jmény novomanželů, tloušťka 10 mm, aby stála sama na svatebním stole.'],
        ],
    ],
];
