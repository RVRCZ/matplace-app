<?php

return [
    'title' => 'Forma na odlévání z 3D modelu: vytvořte ji online',
    'description' => 'Z 3D modelu vytvoříte formu k tisku: dělenou ze 2 až 4 dílů, nebo na silikon (objímka, skořepina, vanička, dvoudílná). S odvzdušněním a objemy v ml.',
    'h1' => 'Forma k tisku z vašeho modelu: dělená, nebo na silikon',
    'intro' => [
        'Nahrajete 3D model a nástroj kolem něj postaví formu připravenou k tisku. Jednoduché tvary jdou odlévat přímo do tištěné formy ze dvou až čtyř dílů. Pro sošky, busty a jiné tvary s podřezy je určená forma na silikon ve čtyřech provedeních: objímka, skořepina, která kopíruje model a ušetří přes polovinu silikonu, vanička na reliéfy a dvoudílná forma s dělicí deskou a klíči.',
        'Ještě před stavbou nástroj změří, kterou část povrchu by tuhá forma nepustila, a označí ji na modelu červeně. Spočítá, kolik hmoty je potřeba na jeden odlitek a kolik silikonu na formu. Najde místa, kde by v dutině zůstal vzduch, a vyvede z nich odvzdušňovací kanálky. Dutý odlitek zařídí tištěné jádro. Forma se otevře jako nový soubor: necháte ji vytisknout u nás, nebo si ji stáhnete.',
    ],
    'steps' => [
        ['name' => 'Nahrajte model', 'text' => 'Přetáhněte model do pole nebo klepněte a vyberte soubor. Model musí být uzavřené těleso o velikosti 5 až 400 mm. Nástroj hned označí podřezy červeně a uvede objem odlitku a silikonu.'],
        ['name' => 'Zvolte typ formy', 'text' => 'Klepněte na obrázek typu nahoře, nebo vyberte v nastavení: tištěnou dělenou formu, nebo formu na silikon a její provedení. Odvzdušnění, jádro a tloušťka stěny jsou volitelné; dělení a řez můžete nechat na automatice.'],
        ['name' => 'Vytvořte formu a objednejte tisk', 'text' => 'Klepněte na „Vytvořit formu“. Stavba trvá pár vteřin, u velkých modelů až dvě minuty. Tlačítkem „Cena a tisk formy“ otevřete formu v kalkulaci, kde zjistíte cenu, objednáte tisk nebo si soubor stáhnete.'],
    ],
    'which' => [
        ['shape' => 'Jednoduchý tvar bez podřezů (přívěsek, dlaždice, knoflík, mýdlo)', 'kind' => 'Tištěná dělená forma, 2 díly'],
        ['shape' => 'Hranatý nebo oblý tvar s mírnými podřezy ze stran (váza, hrnek, figurka s nohama)', 'kind' => 'Tištěná dělená forma, 3 nebo 4 díly, případně z TPU'],
        ['shape' => 'Soška, busta, zvíře: podřezy ze všech stran, chcete víc odlitků', 'kind' => 'Silikon: skořepina (nejméně silikonu) nebo objímka (nejjednodušší)'],
        ['shape' => 'Reliéf, cedulka, podtácek, magnetka: jedna strana plochá', 'kind' => 'Silikon: vanička'],
        ['shape' => 'Tvar, který má být ze všech stran čistý a odléváte ho opakovaně', 'kind' => 'Silikon: dvoudílná forma s klíči'],
    ],
    'faq' => [
        ['q' => 'Co je podřez a proč vadí?', 'a' => 'Podřez je místo, za které se forma při rozebírání zachytí. Tuhá forma takový odlitek nepustí bez poškození. Volbou „Vyplnit podřezy“ se ta místa v odlitku zaplní hmotou (v náhledu oranžově), takže tištěná forma půjde rozebrat; jinak zvolte formu na silikon.'],
        ['q' => 'Jaký je rozdíl mezi objímkou, skořepinou, vaničkou a dvoudílnou formou?', 'a' => 'Všechny čtyři jsou tištěné nádoby, do kterých zalijete model silikonem. Objímka je rovná stěna kolem obrysu: nejjednodušší, ale silikonu je hodně. Skořepina kopíruje model v odstupu stěny a ušetří obvykle polovinu silikonu, tiskne se ve dvou půlkách s podpěrami. Vanička je jeden kus pro model s plochou zadní stranou. Dvoudílná forma má dělicí desku s kuželovými klíči: lijete dvě půlky, které do sebe zapadnou, a odlitek je čistý ze všech stran.'],
        ['q' => 'K čemu je odvzdušnění a jak ho nástroj udělá?', 'a' => 'Při lití zůstane vzduch v každé kapse dutiny, která je výš než okolí: v nose busty, pod bradou, v prohlubni. Odlitek tam má bubliny. Nástroj ta místa najde a vyvede z nich kanálky o průměru 2,5 mm ven: u dělené formy v dělicí rovině, u silikonové jako tenké kolíky na modelu, které v silikonu nechají kanálek. Po odlití se nálitky z kanálků odříznou.'],
        ['q' => 'Co je jádro a kdy se hodí?', 'a' => 'Jádro je tištěný model zmenšený o stěnu odlitku se stopkou do nalévacího otvoru. Vložíte ho do silikonové formy a odlitek vznikne jako skořepina zvolené tloušťky: ušetří pryskyřici, vosk nebo mýdlo a velký odlitek je lehčí. Nástroj uvede objem jádra i hmoty na jeden dutý odlitek.'],
        ['q' => 'Kolik silikonu a hmoty budu potřebovat?', 'a' => 'Hned po nahrání modelu nástroj uvede objem odlitku v ml a objem silikonu pro objímku a skořepinu. Po stavbě formy uvede přesné hodnoty pro zvolený typ, včetně úspory skořepiny proti objímce a objemu jádra. Kupujte asi o desetinu víc kvůli ztrátám v kelímku.'],
        ['q' => 'Jaký model nástroj nezpracuje a kolik to stojí?', 'a' => 'Nezpracuje model menší než 5 mm nebo větší než 400 mm a model, ze kterého se nepodaří udělat uzavřené těleso; model s dírami nejdřív opravte v našem nástroji na opravu. Vytvoření formy je zdarma, platíte jen tisk, pokud si ho u nás objednáte. Silikon si pořídíte sami.'],
    ],
    'examples' => [
        'Tištěná dělená forma ze dvou dílů s kulovými klíči, nálevkou a odvzdušněním v dělicí rovině',
        'Tištěná forma ze čtyř klínů pro tvar s podřezy ze stran',
        'Forma na silikon: skořepina ve dvou půlkách kopíruje bustu a ušetří přes polovinu silikonu',
        'Forma na silikon: vanička v jednom kuse pro model s plochou zadní stranou',
        'Forma na silikon: dvoudílná s dělicí deskou, kuželovými klíči a boxem',
    ],
];
