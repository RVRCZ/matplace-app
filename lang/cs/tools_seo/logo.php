<?php

// Stránka pro dotaz „logo 3d tisk“ (docs/AP.md §9). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Logo na 3D tisk z SVG nebo obrázku: reliéf, výřez, stojící',
    'heading' => 'Logo na 3D tisk z SVG, obrázku nebo textu',
    'description' => 'Nahrajete SVG nebo obrázek loga, nebo napíšete text, a vznikne reliéf na destičce, vyříznutý tvar nebo logo stojící v podstavci. Model zdarma.',
    'h1' => 'Jaká provedení logo umí, co si pohlídat v předloze a kolik stojí tisk',
    'intro' => [
        'Nástroj převede logo, obrázek nebo text na model pro 3D tisk. Nahrát můžete SVG s vyplněnými tvary do 400 kB nebo jednoduchý kontrastní obrázek PNG, JPG či WebP do 5 MB, případně napíšete až dva řádky textu po 30 znacích. Vyberete jedno ze čtyř provedení: reliéf na destičce (zaoblené, pravoúhlé nebo kruhové), plastický reliéf z obrázku, kde odstíny určují výšku, vyříznutý tvar bez destičky, nebo logo stojící v podstavci s drážkou. Šířku motivu nastavíte od 20 do 250 mm a výška se dopočítá podle poměru stran. Nástroj upozorní na čáry tenčí než 0,8 mm, které tiskárna nevytiskne čistě, a na části, které by nedržely pohromadě. U reliéfu na destičce se motiv tiskne druhou barvou: tiskárna ji vymění ve výšce, kde motiv začíná.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): reliéf na zaoblené destičce široké 100 mm s nápisem LOGO a siluetou kočky má 27,6 g, tiskne se 43 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky, bez dopravy (kalkulace /c/11vhrvmivr3e). Větší cedule na dveře nebo na pult vyjde podle gramů a minut, cenu vidíte dřív, než cokoli objednáte. Barvy vybíráte z cívek, které máme právě skladem, přesný firemní odstín proto zaručit neumíme. Model si stáhnete zdarma jako STL nebo projekt pro svou tiskárnu, u reliéfu i destičku a motiv jako samostatné díly.',
    ],
    'steps' => [
        ['name' => 'Nahrajte předlohu nebo text', 'text' => 'Nahrajete SVG nebo obrázek, nebo napíšete text do jednoho či dvou řádků po 30 znacích. Nahraná předloha má přednost před textem.'],
        ['name' => 'Vyberte provedení', 'text' => 'Zvolíte reliéf na destičce, plastický reliéf z obrázku, vyříznutý tvar, nebo stojící logo. U destičky vyberete tvar: zaoblená, pravoúhlá, nebo kruhová.'],
        ['name' => 'Nastavte velikost', 'text' => 'Zadáte šířku motivu a jeho tloušťku. Výška se dopočítá podle poměru stran předlohy.'],
        ['name' => 'Zkontrolujte náhled a upozornění', 'text' => 'Pod náhledem čtete rozměry a případná upozornění na tenké čáry nebo samostatné kousky. Podle nich motiv zvětšíte, nebo změníte provedení.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Tisk objednáte z naší farmy a barvy vyberete při objednávce. Nebo si model zdarma stáhnete jako STL či hotový projekt pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Jaký obrázek nástroj zpracuje?', 'a' => 'Nejlépe SVG s vyplněnými tvary do 400 kB, nebo čistý obrázek s tmavým motivem na světlém pozadí do 5 MB. Obrysy bez výplně nástroj v SVG vynechá. Obrázek se převádí v mřížce nejvýše 360 bodů na delší straně, takže velmi jemné detaily se ztratí; logo s drobným textem pošlete jako SVG.'],
        ['q' => 'Mohu nahrát fotografii?', 'a' => 'Jen v provedení „Plastický reliéf z obrázku“, kde výšku povrchu určují odstíny: tmavá místa vystoupí nejvýš. V ostatních provedeních nástroj fotografii odmítne a odkáže na nástroj Reliéf a litofanie.'],
        ['q' => 'Proč se vyříznuté logo rozpadá na kousky?', 'a' => 'Vyříznutý tvar nemá destičku, takže jednotlivá písmena nebo oddělené části loga nic nespojuje. Nástroj napíše, z kolika kousků se tvar skládá. Mají-li držet pohromadě, zvolte reliéf na destičce, nebo kousky v editoru spojte.'],
        ['q' => 'Jak funguje stojící logo?', 'a' => 'Logo a podstavec se tisknou jako dva díly a logo se zasune do drážky v podstavci. Kvůli pevnosti má stojící logo tloušťku nejméně 2,4 mm. Části, které nedosáhnou do podstavce, například tečky a háčky, by nedržely a nástroj na ně upozorní.'],
        ['q' => 'Lze logo vytisknout ve dvou barvách?', 'a' => 'U reliéfu na destičce ano: při tisku u nás vyberete druhou barvu a tiskárna ji vymění ve výšce, kde motiv začíná; stažený projekt má výměnu v sobě. Barvy vybíráte z těch, které jsou právě založené v tiskárnách, přesný firemní odstín proto zaručit neumíme.'],
        ['q' => 'Jak velké logo na dveře nebo na recepci?', 'a' => 'Na dveře kanceláře stačí reliéf 100 až 150 mm, na recepci nebo výlohu 200 až 250 mm; nad 250 mm se logo nevejde na podložku a rozdělíte ho na díly. Stojící logo na pult vychází nejlépe mezi 120 a 200 mm; pro osvětlený nápis je samostatný nástroj Světelný nápis.'],
    ],
    'examples' => [
        'Nápis ATELIER jako reliéf na zaoblené destičce 110 × 26 mm, třeba na dveře.',
        'Stojící nápis OPEN šířky 120 mm v podstavci, celková výška 43 mm.',
        'Písmeno M jako vyříznutý tvar 80 × 72 mm, tloušťka 2 mm.',
    ],
];
