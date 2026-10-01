<?php

return [
    'title' => 'Logo na 3D tisk z SVG nebo obrázku',
    'description' => 'Nahrajete SVG nebo jednoduchý obrázek, případně napíšete text. Vznikne reliéf na destičce, vyříznutý tvar nebo stojící logo a cenu vidíte hned.',
    'h1' => 'Logo nebo nápis jako reliéf, vyříznutý tvar či stojící cedule',
    'intro' => [
        'Nástroj převede logo, obrázek nebo text na model pro 3D tisk. Nahrát můžete SVG s vyplněnými tvary nebo jednoduchý kontrastní obrázek PNG, JPG či WebP, případně napíšete až dva řádky textu. Vyberete jedno ze čtyř provedení: reliéf na destičce, plastický reliéf z obrázku, vyříznutý tvar, nebo logo stojící na podstavci. Šířku motivu nastavíte od 20 do 250 mm.',
        'V náhledu vidíte přesně ten tvar, který se vytiskne, s vnějšími rozměry a orientační cenou. Nástroj upozorní na čáry tenčí než 0,8 mm, které by tiskárna nevytiskla čistě, a na části, které by nedržely pohromadě. Model si objednáte jako výtisk z naší tiskové farmy, nebo si ho zdarma stáhnete pro svou tiskárnu. U reliéfu na destičce lze motiv vytisknout druhou barvou.',
    ],
    'steps' => [
        ['name' => 'Nahrajte předlohu nebo text', 'text' => 'Nahrajete SVG nebo obrázek, nebo napíšete text do jednoho či dvou řádků po 30 znacích. Nahraná předloha má přednost před textem.'],
        ['name' => 'Vyberte provedení', 'text' => 'Zvolíte reliéf na destičce, plastický reliéf z obrázku, vyříznutý tvar, nebo stojící logo. U destičky vyberete tvar: zaoblená, pravoúhlá, nebo kruhová.'],
        ['name' => 'Nastavte velikost', 'text' => 'Zadáte šířku motivu a jeho tloušťku. Výška se dopočítá podle poměru stran předlohy.'],
        ['name' => 'Zkontrolujte náhled a upozornění', 'text' => 'Pod náhledem čtete rozměry, orientační cenu a případná upozornění na tenké čáry nebo samostatné kousky. Podle nich motiv zvětšíte, nebo změníte provedení.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Tisk objednáte z naší tiskové farmy a zaplatíte z předplaceného kreditu. Nebo si model zdarma stáhnete jako soubor STL či hotový projekt pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Jaký obrázek nástroj zpracuje?', 'a' => 'Nejlépe SVG s vyplněnými tvary do 400 kB, nebo čistý obrázek s tmavým motivem na světlém pozadí do 5 MB. Obrysy bez výplně nástroj v SVG vynechá. Obrázek se převádí v mřížce nejvýše 360 bodů na delší straně, takže velmi jemné detaily se ztratí.'],
        ['q' => 'Mohu nahrát fotografii?', 'a' => 'Jen v provedení „Plastický reliéf z obrázku“, kde výšku povrchu určují odstíny: tmavá místa vystoupí nejvýš. V ostatních provedeních nástroj fotografii odmítne a odkáže na nástroj Reliéf a litofanie.'],
        ['q' => 'Proč se vyříznuté logo rozpadá na kousky?', 'a' => 'Vyříznutý tvar nemá destičku, takže jednotlivá písmena nebo oddělené části loga nic nespojuje. Nástroj napíše, z kolika kousků se tvar skládá. Mají-li držet pohromadě, zvolte reliéf na destičce.'],
        ['q' => 'Jak funguje stojící logo?', 'a' => 'Logo a podstavec se tisknou jako dva díly a logo se zasune do drážky v podstavci. Kvůli pevnosti má stojící logo tloušťku nejméně 2,4 mm. Části, které nedosáhnou do podstavce, například tečky a háčky, by nedržely a nástroj na ně upozorní.'],
        ['q' => 'Lze logo vytisknout ve dvou barvách?', 'a' => 'U reliéfu na destičce ano: při tisku u nás vyberete druhou barvu a tiskárna ji vymění ve výšce, kde motiv začíná. Barvy vybíráte z těch, které jsou právě založené v tiskárnách, přesný firemní odstín proto zaručit neumíme.'],
        ['q' => 'Jak zaplatím a jak výtisk dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Nápis ATELIER jako reliéf na zaoblené destičce 110 × 26 mm, třeba na dveře.',
        'Stojící nápis OPEN šířky 120 mm v podstavci, celková výška 43 mm.',
        'Písmeno M jako vyříznutý tvar 80 × 72 mm, tloušťka 2 mm.',
    ],
];
