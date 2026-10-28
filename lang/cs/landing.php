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

    // dalších 10 nástrojů, dávka A (docs/AP.md §9): stojánek, cedulka, klíčenka
    'phone_stand' => [
        'order' => [
            ['q' => 'Kolik stojí stojánek u vás a jak dlouho se tiskne?', 'farm' => true, 'a' => 'Nízký klín 80 mm se tiskne 1 h 8 min a stojí 120 Kč, A‑rám s poličkou 75 mm 1 h 45 min a 170 Kč (PLA+, bez dopravy, kalkulace z 18. 10. 2026). Cenu vašeho stojánku ukáže kalkulace dřív, než cokoli objednáte.'],
            ['q' => 'Jak změřit telefon?', 'a' => 'Tloušťku změřte i s obalem v nejsilnějším místě, u obalu s držákem na prst včetně něj; nástroj bere 7 až 20 mm a rezervu přidá sám. Šířku změřte přes obal; u stolních typů rozhoduje, jak široká bude opěrka.'],
            ['q' => 'Jak dlouho trvá doručení?', 'farm' => true, 'a' => 'Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point, domů :cz_home).'],
            ['q' => 'Jak stojánek tisknout doma?', 'a' => 'Stažený projekt pro OrcaSlicer, Bambu Studio nebo PrusaSlicer má orientaci i nastavení z kalkulace, stačí vybrat tiskárnu; STL si natočíte sami. Na stůl stačí PLA s výplní 15 %, matný filament skryje vrstvy.'],
            ['q' => 'Vydrží stojánek v autě na slunci?', 'a' => 'Klip do auta a stojánek na palubní desku tiskněte z PETG: PLA za sklem měkne už kolem 55 °C a klip povolí. Na stůl a na zeď PLA stačí.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si stojánek necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Na pracovní stůl', 'text' => 'A‑rám s poličkou, sklon 65°, výřez na kabel: telefon nabíjí a je vidět na hovory i upozornění.'],
            ['t' => 'Do kuchyně na recepty', 'text' => 'Nízký klín nebo široký A‑rám do 260 mm pro tablet, sklon 60°, aby šel displej číst od desky.'],
            ['t' => 'Do auta', 'text' => 'Klip na mřížku ventilace podle tloušťky lamely 1 až 4 mm, z PETG, s výřezem na kabel.'],
        ],
    ],

    'sign' => [
        'order' => [
            ['q' => 'Kolik stojí cedulky u vás?', 'farm' => true, 'a' => 'Klíčenka se jménem 99 Kč a deset stejných dohromady 99 Kč, cedulka na dveře 173 × 69 mm 99 Kč (PLA+, bez dopravy, kalkulace z 18. 10. 2026). Větší cedulka nebo víc jmen se počítá z gramů a minut; cenu vidíte dřív, než cokoli objednáte.'],
            ['q' => 'Jaký tvar a písmo zvolit na dveře?', 'a' => 'Na dveře bytu nebo kanceláře bezpatkové nebo patkové písmo s výškou aspoň 15 mm a rámeček, aby se cedulka četla z chodby; psaná písma nechte na jména a klíčenky. Vystouplé písmo druhou barvou je čitelnější než zapuštěné.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Klíčenka se tiskne 5 minut, cedulka na dveře 46 minut. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Jak cedulku tisknout doma ve dvou barvách?', 'a' => 'Naplocho, bez podpěr. Stažený projekt má pauzu ve výšce, kde písmo začíná: tiskárna se zastaví, vyměníte cívku a tisk pokračuje; tiskárna s AMS, MMU nebo ACE barvu vymění sama. U dvoubarevné varianty stáhnete desku a písmo i zvlášť.'],
            ['q' => 'Vydrží cedulka venku?', 'a' => 'Do bytu a na vnitřní dveře stačí PLA. Na venkovní dveře nebo plot zvolte ASA, které snáší slunce a mráz; PETG vydrží ve stínu. Materiál vyberete v kalkulaci v porovnání materiálů.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si cedulky necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Jmenovky pro třídu a tým', 'text' => 'Klíčenky se jmény 46 × 22 mm: deset kusů za 99 Kč, každé jméno vlastní návrh se stejným rozložením.'],
            ['t' => 'Cedulka na dveře bytu a kanceláře', 'text' => '„Novákovi 12“ s vystouplým písmem a rámečkem, 173 × 69 mm, přilepená oboustrannou páskou.'],
            ['t' => 'Jméno na dětský pokoj', 'text' => 'Jen jméno psacím písmem bez destičky, s očkem na stužku, nebo bez něj na dveře.'],
        ],
    ],

    'keychain' => [
        'order' => [
            ['q' => 'Kolik stojí klíčenky u vás?', 'farm' => true, 'a' => 'Kost 70 mm se jménem ve dvou barvách stojí 99 Kč, což je nejnižší cena zakázky, deset stejných 100 Kč, tedy 10 Kč za kus (PLA, bez dopravy, kalkulace z 28. 10. 2026). Barvy vyberete při objednávce z cívek, které máme skladem.'],
            ['q' => 'Jak udělat klíčenky s různými jmény pro tým?', 'a' => 'Návrh zůstává na stránce: přepíšete jméno, necháte tvar a obrázek a pokračujete do kalkulace pro každé jméno zvlášť; počet kusů jednoho jména zadáte v ní. Všechny klíčenky se tisknou vedle sebe na jedné podložce.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Klíčenka se tiskne 10 minut. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Jak klíčenku tisknout doma ve více barvách?', 'a' => 'Naplocho, bez podpěr. Stažený projekt má výměnu filamentu ve výšce každé vrstvy; s jednou tryskou vyměníte cívku při pauze, tiskárna s AMS, MMU nebo ACE to udělá sama. STL je jedno těleso pro tisk jednou barvou.'],
            ['q' => 'Hodí se jako firemní dárek s logem?', 'a' => 'Ano. Logo nahrajete jako SVG a zůstane z něj silueta v jedné barvě na tvaru v barvě firmy; vícebarevné logo zpracuje rychlý formulář. Deset klíčenek vyjde na 100 Kč, cenu větší série ukáže kalkulace.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si klíčenky necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Klíčenky pro tým nebo svatbu', 'text' => 'Jedno rozložení, různá jména, deset kusů za 100 Kč; na svatbu srdce se jmény a datem.'],
            ['t' => 'Visačka na batoh a klíče', 'text' => 'Štítek 80 mm z PETG se jménem a telefonem ve dvou řádcích, očko 5 mm na karabinu.'],
            ['t' => 'Dárek s logem', 'text' => 'Logo z SVG jako silueta na kruhu v barvě firmy, až čtyři barvy v jednom tisku.'],
        ],
    ],

    // dávka B (docs/AP.md §9): medaile, váza (krabička má bloky z AH.md §11)
    'medallion' => [
        'order' => [
            ['q' => 'Kolik stojí sada medailí pro závod?', 'farm' => true, 'a' => 'Medaile 60 mm s řetězem ze 40 článků stojí 130 Kč (PLA+, bez dopravy, kalkulace z 22. 10. 2026). Pro 1. až 3. místo uděláte tři návrhy, každý má vlastní kalkulaci, a počet kusů jednoho místa zadáte v ní; cenu celé sady vidíte dřív, než cokoli objednáte.'],
            ['q' => 'Jak medaili pověsit bez řetězu?', 'a' => 'Nastavte počet článků na nulu: medaile má nahoře očko s otvorem 6 mm, kterým projde stužka do 15 mm nebo šňůrka. Řetěz z článků se tiskne jen tehdy, když ho chcete.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Medaile 60 mm s řetězem se tiskne 1 h 42 min, sada pro tři místa tedy zabere tiskárnu na půl dne. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point); na závod objednejte aspoň týden předem.'],
            ['q' => 'Jak medaili tisknout doma?', 'a' => 'Zarovnaný motiv střídá dvě cívky v týchž vrstvách, na to je potřeba tiskárna s měničem (AMS, MMU, ACE). Bez měniče zvolte vystouplý motiv: projekt má jednu výměnu filamentu ve výšce motivu. Řetěz se tiskne naplocho bez podpěr; z PLA Silk vypadá kovově.'],
            ['q' => 'Můžu na medaili dát logo klubu nebo jméno?', 'a' => 'Ano. Místo číslice napíšete jméno do dvou řádků po 16 znacích, nebo nahrajete obrázek, ze kterého zůstane silueta v jedné barvě; tvar destičky vyberete ze sedmi. Logo s drobnými detaily zvětšete motivem na 60 až 70 % průměru.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si medaile necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Dětský závod a turnaj', 'text' => 'Sada pro 1. až 3. místo s řetězy 28 až 32 článků pro děti, každá medaile 130 Kč.'],
            ['t' => 'Školní soutěž a dětský den', 'text' => 'Medaile 60 mm se jménem nebo obrázkem na stužce, bez řetězu, pro každého účastníka.'],
            ['t' => 'Firemní turnaj', 'text' => 'Štít nebo rozeta 80 mm s logem firmy a řetězem 40 článků, zlatá PLA Silk.'],
        ],
    ],

    'vase' => [
        'order' => [
            ['q' => 'Kolik stojí váza u vás?', 'farm' => true, 'a' => 'Hladká váza 160 mm 190 Kč, spirálová 180 mm 280 Kč, žebrovaný obal na květináč 120 mm 260 Kč (PLA+, bez dopravy, kalkulace z 18. 10. 2026). Cenu vaší vázy ukáže kalkulace dřív, než cokoli objednáte; rozhodují gramy a minuty, u spirál hlavně minuty.'],
            ['q' => 'Jaký filament na vázu?', 'a' => 'Na spirálové a žebrované vázy lesklé PLA Silk, na kterém žebra hrají světlem; na hladké matný filament, který skryje vrstvy. Obal na květináč, který bývá mokrý, zvolte z PETG. Barvu vyberete z cívek, které máme právě skladem.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Hladká váza 160 mm se tiskne necelé 2 hodiny, spirálová 180 mm 5 hodin. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Jak vázu tisknout doma?', 'a' => 'Nastojato, bez podpěr; stažený projekt má stěnu a dno podle návrhu. STL je plné těleso, takže ho můžete ve sliceru přepnout do vázového režimu jednou stěnou, tisk je pak rychlejší a tišší, ale hůř drží vodu. Vysoké vázy tiskněte s límcem, aby se od podložky neodlepily.'],
            ['q' => 'Jak změřit květináč pro obal?', 'a' => 'Změřte vnější průměr květináče u horního okraje a jeho výšku, k průměru přidejte 5 až 10 mm na vůli a zadejte ho jako horní průměr obalu; spodní průměr o něco menší podle tvaru květináče. Podmiska se dopočítá sama.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si vázu necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Sušené květiny na stůl', 'text' => 'Spirálová váza 180 mm s úzkým hrdlem z PLA Silk, na sušené květiny nemusí držet vodu.'],
            ['t' => 'Obal na květináč do bytu', 'text' => 'Žebrovaný obal 120 mm s drenáží a podmiskou z PETG, průměr podle květináče plus 5 mm.'],
            ['t' => 'Sada tří váz', 'text' => 'Hladké vázy 120, 160 a 200 mm stejného tvaru v jedné barvě na poličku nebo jako dárek.'],
        ],
    ],

    // dávka C (docs/AP.md §9): logo, držák, razítko, světelný nápis
    'logo' => [
        'order' => [
            ['q' => 'Kolik stojí logo u vás?', 'farm' => true, 'a' => 'Reliéf na destičce široký 100 mm stojí 99 Kč, což je nejnižší cena zakázky (PLA, bez dopravy, kalkulace z 28. 10. 2026). Větší cedule nebo stojící logo se počítá z gramů a minut; cenu vidíte dřív, než cokoli objednáte, i pro víc kusů.'],
            ['q' => 'Jak připravit SVG loga?', 'a' => 'Jednou barvou, s výplněmi místo čar (Inkscape: Stroke to Path) a textem převedeným na křivky; přechody a masky nástroj nečte. Logo s drobným textem zvětšete aspoň na 100 mm, aby čáry nebyly pod 0,8 mm.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Reliéf 100 mm se tiskne 43 minut, cedule 250 mm přes dvě hodiny. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Jak logo tisknout doma ve dvou barvách?', 'a' => 'Naplocho, bez podpěr. U reliéfu na destičce má stažený projekt pauzu ve výšce, kde motiv začíná: tiskárna se zastaví, vyměníte cívku a tisk pokračuje; tiskárna s AMS, MMU nebo ACE to udělá sama. Stojící logo a podstavec se tisknou jako dva díly.'],
            ['q' => 'Vydrží logo venku na fasádě?', 'a' => 'Venku na slunci a v mrazu zvolte ASA, do interiéru stačí PLA, ve stínu PETG. Logo nad 250 mm se nevejde na podložku, rozdělte ho na díly nebo ho nechte nástrojem Rozdělit model rozřezat s kolíky.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si logo necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Cedule na dveře kanceláře', 'text' => 'Reliéf loga nebo názvu firmy 120 až 150 mm ve dvou barvách, přilepený oboustrannou páskou.'],
            ['t' => 'Logo na recepci a pult', 'text' => 'Stojící logo 150 až 200 mm v podstavci s drážkou, tloušťka od 2,4 mm, tiskne se ze dvou dílů.'],
            ['t' => 'Drobnosti s logem pro tým', 'text' => 'Vyříznutý tvar loga 60 mm jako magnetka nebo přívěsek; na klíčenky se jmény je nástroj Klíčenka.'],
        ],
    ],

    'holder' => [
        'order' => [
            ['q' => 'Kolik stojí držák u vás?', 'farm' => true, 'a' => 'Kolébka na ovladač 100 Kč, hák na sluchátka 99 Kč, klip na koště 99 Kč (PLA+, bez dopravy, kalkulace z 18. 10. 2026); 99 Kč je nejnižší cena zakázky. Víc držáků v jedné objednávce vyjde na kus levněji; cenu vidíte dřív, než cokoli objednáte.'],
            ['q' => 'Jaký typ držáku na co?', 'a' => 'Kolébka na ovladač, telefon nebo brýle, kapsa na lahev, nářadí a tužky, hák na sluchátka, tašku nebo klíče s uchem, klip na násadu koštěte, mopu nebo hrábí. Předvolby nastaví rozměry běžných věcí, vlastní rozměry je přepíšou.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Klip se tiskne 21 minut, hák 27 minut, kolébka 1 h 12 min. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Jak držák připevnit bez vrtání?', 'a' => 'Vypněte otvory a držák přilepte oboustrannou páskou; unese jen lehké věci jako ovladač nebo sluchátka. Tam, kde už ve zdi šroub je, zvolte zavěšení na šroub: hlava projde širokým otvorem a držák sjede do drážky.'],
            ['q' => 'Jak držák tisknout doma?', 'a' => 'Naplocho zadní stranou na podložku, bez podpěr; stažený projekt má nastavení z kalkulace. Na těžší věci zvyšte ve sliceru počet stěn nebo výplň a tiskněte z PETG.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si držák necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Ovladače u gauče', 'text' => 'Kolébka 50 × 22 mm na bok police nebo stolku, přilepená páskou; na každý ovladač jedna.'],
            ['t' => 'Nářadí v dílně a garáži', 'text' => 'Klipy na násady a kapsy na kladiva z PETG na šrouby, rozměry podle každého kusu.'],
            ['t' => 'Sluchátka u stolu', 'text' => 'Hák 35 mm pod deskou stolu nebo na boku monitoru, sluchátka nepřekáží na stole.'],
        ],
    ],

    'stamp' => [
        'order' => [
            ['q' => 'Kolik stojí razítko u vás?', 'farm' => true, 'a' => 'Razítko 50 mm s držadlem stojí 99 Kč, což je nejnižší cena zakázky (PLA, bez dopravy, kalkulace z 28. 10. 2026). Větší razítko nebo víc kusů se počítá z gramů a minut; cenu vidíte dřív, než cokoli objednáte.'],
            ['q' => 'Jak udělat značku autora na dno hrnků?', 'a' => 'Nahrajte logo jako SVG nebo napište iniciály, zvolte vystouplý motiv, šířku 20 až 40 mm a výšku motivu 1 až 1,5 mm; otisk do kožené hlíny je pak čistý. S držadlem se razítko drží lépe, bez držadla ho otisknete palcem.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Razítko 50 mm se tiskne 28 minut. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Hodí se razítko na mýdlo a těsto?', 'a' => 'Ano, na vše měkké, co otisk podrží: mýdlo, fondán, linecké těsto, polymerovou hmotu. Pro vystouplé logo na mýdle zvolte zahloubený motiv. Tištěný plast je určený pro krátký dotyk s potravinou; razítko myjte ručně, do myčky nepatří.'],
            ['q' => 'Jak velké razítko zvolit?', 'a' => 'Značka autora 20 až 40 mm, dekorační razítko na keramiku 50 až 80 mm, nejvíc 120 mm. Čím větší razítko, tím víc tlaku potřebuje; velký motiv raději otiskněte válečkem přes destičku bez držadla.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si razítko necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Keramická dílna', 'text' => 'Značka autora 30 mm na dno hrnků a misek, vystouplý motiv, s držadlem.'],
            ['t' => 'Výroba mýdla', 'text' => 'Zahloubený motiv 50 mm, ze kterého na mýdle vystoupí logo; razítko z PETG snese vlhko.'],
            ['t' => 'Pečení a fondán', 'text' => 'Razítko „Hand made“ nebo jméno do těsta a fondánu, z PLA, ručně mýt.'],
        ],
    ],

    'lightbox' => [
        'order' => [
            ['q' => 'Kolik stojí díly u vás?', 'farm' => true, 'a' => 'Nápis OPEN 180 mm, čtyři díly, stojí 180 Kč (PLA, bez dopravy, kalkulace z 28. 10. 2026). Barvy dílů vyberete při objednávce v jedné zakázce, tmavé tělo a bílý difuzor; cenu vidíte dřív, než cokoli objednáte.'],
            ['q' => 'Co k nápisu koupit?', 'a' => 'LED pásek na 5 V široký 8 nebo 10 mm v délce, kterou nástroj vypíše pod náhledem, USB kabel s napájením (stačí nabíječka telefonu) a oboustrannou pásku nebo lepidlo. Pásek s dálkovým ovladačem volte do rozměru 18 × 8 mm.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Čtyři díly nápisu 180 mm se tisknou 2 h 17 min. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Jak nápis tisknout doma?', 'a' => 'Díly se tisknou naplocho; stažený projekt má nastavení z kalkulace. Tělo, masku a zadní kryt z tmavého PLA nebo PETG, difuzor z bílého nebo průsvitného PLA; masku s můstky uvnitř písmen tiskněte pomaleji, aby můstky drželi.'],
            ['q' => 'Jak jasný nápis bude?', 'a' => 'Jas dává pásek, ne díly; difuzor světlo rozptýlí tak, aby nebyly vidět jednotlivé diody. Pásek na 5 V má spotřebu v řádu jednotek wattů, takže stačí USB nabíječka a nápis může svítit celý večer.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si díly necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Kavárna a obchod', 'text' => 'OPEN nebo název podniku 240 mm do výlohy, obdélník s LED páskem, z PETG na slunce.'],
            ['t' => 'Domácí bar a herna', 'text' => 'Kulatý nápis 160 mm s rovnou patou na polici, jedno slovo nebo jméno.'],
            ['t' => 'Noční světlo se jménem', 'text' => 'Jméno dítěte 120 mm s LED modulem a ovladačem, tlumené světlo do pokojíčku.'],
        ],
    ],
    // 3D nápis, který stojí (docs/AP.md §12, dávka D)
    'text' => [
        'order' => [
            ['q' => 'Kolik stojí nápis u vás?', 'farm' => true, 'a' => 'Slovo HOME 144 mm stojí 99 Kč (PLA, bez dopravy, kalkulace z 28. 10. 2026), dvouřádkový nápis KAVÁRNA u Jany 130 Kč. Cena roste s počtem písmen, výškou a hloubkou; vidíte ji dřív, než cokoli objednáte, i pro víc kusů.'],
            ['q' => 'Jaký text a písmo se hodí?', 'a' => 'Krátké slovo nebo jméno v tučném bezpatkovém písmu stojí nejjistěji; psaná písma volte od výšky 30 mm. Diakritiku nástroj přichytí spojkou, symboly pod poli vložíte jedním klepnutím.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'HOME se tiskne 40 minut, dvouřádkový nápis 1 h 53 min. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Dá se nápis přilepit na stěnu?', 'a' => 'Ano. Nápis se tiskne na zádech, takže zadní strana je rovná a hladká; na stěnu nebo na dveře ho přilepíte oboustrannou páskou. Na zavěšení je lepší cedulka s očkem, nápis očko nemá.'],
            ['q' => 'Vydrží nápis ve výloze na slunci?', 'a' => 'Do interiéru stačí PLA. Do výlohy, kam svítí slunce, zvolte PETG nebo ASA; PLA u teplého skla měkne. Nápis delší než 250 mm rozdělte do dvou řádků nebo na dvě slova.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si nápis necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Jméno na poličku dětského pokoje', 'text' => 'Psané písmo 40 mm vysoké se srdcem nebo hvězdou na společné patce, hloubka 15 mm.'],
            ['t' => 'Slovo na stůl v kavárně nebo kanceláři', 'text' => 'OPEN, HOME nebo název podniku v Bebas Neue 30 až 50 mm, hloubka pětina výšky.'],
            ['t' => 'Jmenovky na svatební stůl', 'text' => 'Jména hostů ve stejném písmu, každé jako samostatný návrh; na cedulku s destičkou je nástroj Cedulka.'],
        ],
    ],
    // obraz z filamentu (docs/AP.md §12, dávka D)
    'filament_art' => [
        'order' => [
            ['q' => 'Kolik stojí obraz u vás?', 'farm' => true, 'a' => 'Sněhulák ze čtyř desek v kulatém rámu 120 mm stojí 200 Kč, perníček jako jeden tisk 120 mm 99 Kč (PLA, bez dopravy, kalkulace z 28. 10. 2026). Cena roste se šířkou a počtem desek; vidíte ji dřív, než cokoli objednáte.'],
            ['q' => 'Jaký obrázek nahrát?', 'a' => 'Kresbu nebo logo s několika plnými barvami, PNG nebo JPG s jasným pozadím, nebo SVG. Fotku nástroj zjednoduší na hlavní barvy v režimu „malba“; portrét ve stupních šedi dejte raději do litofanie.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Sněhulák se tiskne 2 h 46 min, perníček 26 minut. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Jak obraz tisknout doma?', 'a' => 'Naplocho, bez podpěr, vrstva 0,2 mm, PLA. Každou desku ze své cívky; u jednoho tisku má projekt výměny filamentu ve výškách barev, tiskárna se zastaví a vy cívku vyměníte.'],
            ['q' => 'Jak se obraz skládá a věší?', 'a' => 'Desky lepíte podle návodu od zadní k přední vteřinovým lepidlem, sloupky zapadnou pod desku před nimi. Rám má v zadní stěně drážku na hřebík; s místem na LED pásek je dole výřez na kabel.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si obraz necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Dětská kresba na zeď pokoje', 'text' => 'Vyfocená kresba jako vrstvený obraz 150 až 200 mm ze 4 až 6 desek v rámu; hrubé linky kresby vrstvám svědčí.'],
            ['t' => 'Logo firmy na recepci', 'text' => 'SVG loga jako desky v čtvercovém rámu 250 mm s místem na LED pásek; barvy vyberete z našich cívek podle firemních.'],
            ['t' => 'Dárek z fotky nebo motivu', 'text' => 'Plakátový obraz z fotky v režimu „malba“, 180 mm, nebo motiv z knihovny jako jeden tisk bez lepení.'],
        ],
    ],
    // modulární organizér z misek (docs/AP.md §12, dávka D)
    'modular' => [
        'order' => [
            ['q' => 'Kolik stojí sada misek u vás?', 'farm' => true, 'a' => 'Čtyři misky tří velikostí pro zásuvku 300 × 150 mm, 40 mm vysoké, stojí 310 Kč (PLA, bez dopravy, kalkulace z 28. 10. 2026). Cena roste s velikostí, výškou a počtem misek; vidíte ji dřív, než cokoli objednáte.'],
            ['q' => 'Jak změřit zásuvku?', 'a' => 'Vnitřní šířku a hloubku na dně zásuvky, od obou odečtěte 1 až 2 mm. Výšku misek zvolte aspoň o 5 mm nižší, než je světlá výška zásuvky, aby se dala zavřít.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Čtyři misky se tisknou 3 h 45 min, větší sada úměrně déle. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Jak misky tisknout doma?', 'a' => 'Dnem dolů, bez podpěr, vrstva 0,2 mm, PLA nebo PETG. Každou misku ze své cívky, nebo několik najednou na jedné podložce; projekt pro slicer má misky rozložené vedle sebe.'],
            ['q' => 'Vydrží misky v kuchyni a v dílně?', 'a' => 'Na příbory, kosmetiku a kancelář stačí PLA. Do dílny na šroubky a nářadí nebo k teplu zvolte PETG. Misky myjte rukou, ne v myčce.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si sadu necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Zásuvka s kosmetikou nebo šperky', 'text' => 'Šest až osm misek 30 mm vysokých v různých barvách, jedna dlouhá na štětce.'],
            ['t' => 'Dílna a šroubky', 'text' => 'Stejné čtvercové misky 40 mm vysoké na mřížce 8 × 4, každá druhá v jiné barvě, aby se obsah našel.'],
            ['t' => 'Stůl v kanceláři', 'text' => 'Čtyři misky s vnějším tácem 240 × 120 mm na tužky, sponky a kabely; tác drží sadu pohromadě.'],
        ],
    ],
    // víčko, zátka nebo krytka na míru (docs/AP.md §12, dávka E)
    'cap' => [
        'order' => [
            ['q' => 'Kolik stojí víčko u vás?', 'farm' => true, 'a' => 'Víčko na PET lahev stojí 99 Kč, což je nejnižší cena zakázky, nasazovací víčko na sklenici 70 mm 99 Kč (PLA, bez dopravy, kalkulace z 28. 10. 2026). Víc kusů stejného víčka zadáte jako počet kusů a cena za kus klesne.'],
            ['q' => 'Co když neznám stoupání závitu?', 'a' => 'Změřte délku deseti závitů a vydělte ji deseti, nebo zkuste předvolbu: PET lahve mají 2,7 mm, metrické závity stoupání podle řady M6 až M30. U neznámého závitu vytiskněte jeden kus a teprve potom objednejte víc.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Víčko na PET lahev se tiskne 22 minut, víčko na sklenici 39 minut. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Co když víčko nepasuje?', 'a' => 'Zkontrolujte, že jste zadali skutečný rozměr bez vůle; vůli přidává nástroj. Když jde díl ztuha, zvětšete vůli o 0,1 mm, když je volný, zmenšete ji, a nový kus objednáte ze stejného návrhu.'],
            ['q' => 'Vydrží víčko venku a v teple?', 'a' => 'Na zahradní nádrž, sud nebo do auta zvolte ASA nebo PETG; PLA na slunci a nad 55 °C měkne a závit povolí. Do interiéru stačí PLA.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si víčko necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Ztracené víčko od lahve nebo kanystru', 'text' => 'Šroubovací víčko na PET lahev 28 mm, nebo vlastní závit podle změřeného průměru a stoupání, s těsnicím kroužkem.'],
            ['t' => 'Krytky profilů a trubek', 'text' => 'Zátky do nábytkových trubek a krytky hliníkových profilů podle vnitřního rozměru, po pěti i deseti kusech.'],
            ['t' => 'Víčko na sklenici nebo dózu', 'text' => 'Nasazovací víčko na sklenici s hrdlem 70 mm s lůžkem pro vložku, z PETG do spíže.'],
        ],
    ],
    // podtácek s obrázkem (docs/AP.md §12, dávka E)
    'coaster' => [
        'order' => [
            ['q' => 'Kolik stojí podtácek u vás?', 'farm' => true, 'a' => 'Kulatý podtácek 100 mm stojí 99 Kč, což je nejnižší cena zakázky, sada čtyř 170 Kč (PLA, bez dopravy, kalkulace z 28. 10. 2026). Cena roste s tloušťkou a počtem kusů; vidíte ji dřív, než cokoli objednáte.'],
            ['q' => 'Jaký tvar a velikost zvolit?', 'a' => 'Na sklenici a hrnek stačí kruh 90 až 100 mm, na půllitr čtverec 100 až 110 mm; šestiúhelníky se skládají vedle sebe do sady. Tloušťka 4 mm je dost, 6 mm působí masivněji.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Jeden podtácek se tiskne 40 minut, sada čtyř úměrně déle. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Jak podtácek čistit?', 'a' => 'Otřít vlhkým hadrem nebo opláchnout vlažnou vodou; do myčky ne, PLA by se při 60 °C zkroutilo. Barvy jsou plast, nesmývají se ani neblednou.'],
            ['q' => 'Hodí se jako firemní dárek s logem?', 'a' => 'Ano. Logo nahrajete jako SVG v jedné až čtyřech barvách, zvolíte tvar a počet kusů; cena za kus s počtem klesá. Různé motivy v jedné sadě jsou různé návrhy, každý se objedná zvlášť.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si podtácky necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Sada na stůl nebo do kavárny', 'text' => 'Čtyři až šest podtácků 100 mm se stejným motivem a drážkami zespodu, PLA.'],
            ['t' => 'Dárek s fotkou', 'text' => 'Kulatý podtácek s fotkou přes celou plochu, 6 až 8 barev, zkosená hrana.'],
            ['t' => 'Firemní logo', 'text' => 'Čtvercový podtácek s logem z SVG ve dvou barvách, 20 kusů do zasedačky.'],
        ],
    ],
    // magnetka na lednici (docs/AP.md §12, dávka E)
    'magnet' => [
        'order' => [
            ['q' => 'Kolik stojí magnetka u vás?', 'farm' => true, 'a' => 'Tlapka 60 mm s kapsou na magnet stojí 99 Kč, což je nejnižší cena zakázky (PLA, bez dopravy, kalkulace z 28. 10. 2026). Víc kusů stejného návrhu zadáte jako počet kusů a cena za kus klesne; magnet není v ceně.'],
            ['q' => 'Kde koupit magnety?', 'a' => 'Neodymové kulaté magnety Ø 10 × 2 nebo Ø 12 × 3 mm prodává železářství i e-shopy po desítkách kusů; na magnetku do 60 mm stačí jeden, na 100 mm zvolte větší, třeba Ø 20 × 3 mm. Zadejte rozměr magnetu, který opravdu máte.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Magnetka 60 mm se tiskne 14 minut. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Udrží magnetka na lednici papír?', 'a' => 'Neodymový magnet Ø 10 × 2 mm v magnetce 60 mm udrží několik listů papíru; na tlustší svazek zvolte Ø 12 × 3 nebo Ø 15 × 3 mm. Na nerezových dveřích bez železné vrstvy magnet nedrží, vyzkoušejte je obyčejným magnetem.'],
            ['q' => 'Hodí se jako dárek nebo firemní drobnost?', 'a' => 'Ano: fotka dítěte pro babičku, logo firmy pro zákazníky nebo svatební magnetky pro hosty; stejný návrh ve 20 kusech vyjde na kus levněji. Různé fotky jsou různé návrhy.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si magnetky necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Fotka na lednici', 'text' => 'Obdélník 80 mm s fotkou přes celou plochu, 6 až 8 barev, magnet Ø 12 × 3 mm vlepený.'],
            ['t' => 'Svatební nebo firemní magnetky', 'text' => 'Logo nebo jména ve dvou barvách, 50 mm, 20 až 50 kusů stejného návrhu.'],
            ['t' => 'Kresba dítěte', 'text' => 'Vyfocená kresba jako magnetka kopírující obrys, 60 mm, 4 barvy.'],
        ],
    ],
    // náušnice z obrázku (docs/AP.md §12, dávka E)
    'earrings' => [
        'order' => [
            ['q' => 'Kolik stojí náušnice u vás?', 'farm' => true, 'a' => 'Pár srdcí 28 mm stojí 99 Kč, což je nejnižší cena zakázky (PLA, bez dopravy, kalkulace z 28. 10. 2026). Víc párů stejného návrhu zadáte jako počet kusů a cena za pár klesne; háčky nejsou v ceně.'],
            ['q' => 'Jaké háčky a kroužky koupit?', 'a' => 'Afroháčky nebo puzety z chirurgické oceli a spojovací kroužky 4 až 5 mm z galanterie. Kroužek rozevřete dvěma kleštičkami do strany, navléknete do očka a zavřete; očko nastavte na 2 mm.'],
            ['q' => 'Jak dlouho trvá tisk a doručení?', 'farm' => true, 'a' => 'Pár náušnic se tiskne 6 minut. Odesíláme v den, kdy tisk skončí, v pracovní dny, a Zásilkovna v Česku obvykle doručí do 1 až 2 pracovních dnů (na výdejní místo :cz_point).'],
            ['q' => 'Dotýká se plast ucha?', 'a' => 'Ne, v uchu je jen háček nebo puzeta; plastová část visí pod ním. Háčky volte z chirurgické oceli, titanu nebo stříbra. PLA neobsahuje nikl.'],
            ['q' => 'Hodí se na dárek nebo na malou sérii na trh?', 'a' => 'Ano. Stejný návrh zadáte v deseti párech a cena za pár klesne; pro další sérii stačí vyměnit barvy nebo obrázek. Na trhy se hodí jednoduché siluety ve dvou barvách.'],
            ['q' => 'Potřebuji účet?', 'a' => 'Na návrh a stažení ne. Účet potřebujete, až když si náušnice necháte vytisknout u nás; platí se z kreditu dobitého kartou.'],
        ],
        'who' => [
            ['t' => 'Dárek k narozeninám', 'text' => 'Pár srdcí nebo hvězd 28 až 32 mm ve dvou barvách, háčky z chirurgické oceli zvlášť.'],
            ['t' => 'Iniciály nebo jméno', 'text' => 'Krátký nápis místo obrázku, 25 mm, zrcadlení vypnuté, aby se text četl.'],
            ['t' => 'Malá série na trh', 'text' => 'Deset párů stejné siluety, každý pár v jiné kombinaci barev z našich cívek.'],
        ],
    ],
];
