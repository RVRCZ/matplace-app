<?php

return [
    'title' => 'Šablona na malování a stříkání z vlastního textu',
    'description' => 'Napíšete text nebo nahrajete motiv a nástroj z něj vyřízne šablonu. Vnitřky písmen drží můstky, takže nic nevypadne. Vytiskneme ji, nebo si ji stáhnete.',
    'h1' => 'Šablona s textem nebo motivem, která drží pohromadě',
    'intro' => [
        'Nástroj vyřízne text nebo motiv do tenké destičky a vznikne šablona na malování, tupování nebo stříkání barvou. Text může mít dva řádky po 24 znacích, místo něj lze nahrát SVG nebo jednoduchý obrázek. Vnitřní části písmen, jako je střed O nebo A, by ze šablony vypadly. Nástroj je proto sám spojí s rámem úzkými můstky a napíše, kolik jich přidal.',
        'Šířku motivu nastavíte od 30 do 250 mm, okraj kolem něj od 5 do 40 mm a tloušťku šablony od 0,8 do 3 mm. Náhled ukazuje přesně tu šablonu, kterou dostanete, i s můstky. Šablonu si objednáte jako výtisk z naší tiskové farmy, nebo si model zdarma stáhnete pro svou tiskárnu. Na opakované použití doporučujeme pevný plast PETG, je pružnější a barva se z něj lépe omývá.',
    ],
    'steps' => [
        ['name' => 'Napište text nebo nahrajte motiv', 'text' => 'Text napíšete do jednoho nebo dvou řádků. Místo textu můžete nahrát SVG s vyplněnými tvary nebo obrázek s tmavým motivem na světlém pozadí.'],
        ['name' => 'Nastavte velikost a okraj', 'text' => 'Zadáte šířku motivu a okraj kolem něj. Výška šablony se dopočítá podle motivu.'],
        ['name' => 'Zkontrolujte můstky v náhledu', 'text' => 'V náhledu vidíte, kudy můstky vedou, a pod ním jejich počet. Šířku můstků změníte v části „Tloušťka stěn a další“, od 0,8 do 3 mm.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Tisk objednáte z naší tiskové farmy a zaplatíte z předplaceného kreditu. Nebo si model zdarma stáhnete jako soubor STL či hotový projekt pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Proč jsou v písmenech můstky?', 'a' => 'Uzavřená písmena jako O, A nebo B mají vnitřek, který by po vyříznutí vypadl. Můstek ho drží u rámu. Na natřené ploše po něm zůstane tenký proužek, který můžete dotáhnout štětcem.'],
        ['q' => 'Jak velkou šablonu lze udělat?', 'a' => 'Motiv může být široký 30 až 250 mm a vysoký nejvýše 300 mm, k tomu se přičte okraj. Velká šablona se nemusí vejít na tiskovou podložku, na to upozorní kalkulace v dalším kroku.'],
        ['q' => 'Z jakého materiálu šablonu tisknout?', 'a' => 'Na opakované použití doporučujeme PETG: je pružnější než běžný plast PLA a barva se z něj lépe omývá. Šablona se tiskne naležato a bez podpěr.'],
        ['q' => 'Jaký motiv mohu nahrát?', 'a' => 'SVG s vyplněnými tvary do 400 kB, nebo obrázek PNG, JPG či WebP s tmavým motivem na světlém pozadí. Vyřízne se to, co je v obrázku tmavé; volbou „Obrátit světlé a tmavé“ to otočíte. Fotografii s plynulými odstíny nástroj nepřijme.'],
        ['q' => 'Lze změnit písmo šablony?', 'a' => 'Ne, šablona používá jedno tučné bezpatkové písmo. Jiné písmo připravte v grafickém programu, převeďte ho na křivky s výplní a nahrajte jako SVG.'],
        ['q' => 'Jak zaplatím a jak výtisk dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Šablona BOA 8 na označení beden, 144 × 52 mm, vnitřky písmen drží můstky.',
        'Šablona FRAGILE na krabice a bedny, 184 × 50 mm, tloušťka 1,2 mm.',
        'Dvouřádková šablona No. 27, například na číslo domu, 124 × 130 mm.',
    ],
];
