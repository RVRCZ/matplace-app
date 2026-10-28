<?php

// Stránka pro dotaz „držák na zeď na míru 3d tisk“ (docs/AP.md §9). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Držák na zeď na míru k 3D tisku: kolébka, kapsa, hák, klip',
    'heading' => 'Držák na zeď na míru k 3D tisku',
    'description' => 'Změříte věc, vyberete, jak má držet, a držák je na míru: kolébka, kapsa, hák nebo klip na násadu, na šrouby nebo pásku. Model zdarma, nebo tisk u nás.',
    'h1' => 'Jaké držáky nástroj umí, jak měřit a kolik stojí tisk',
    'intro' => [
        'Nástroj vytvoří držák podle rozměrů věci, kterou chcete uklidit na zeď: dálkový ovladač, lahev, nářadí, sluchátka, koště. Vyberete jeden ze čtyř typů: kolébku s nízkou přední stranou, uzavřenou kapsu, hák na vše s uchem nebo popruhem, nebo klip, který sevře kulatou násadu do průměru 60 mm. Zadáváte skutečné rozměry věci (šířka 10 až 300 mm, tloušťka 5 až 150 mm) a vůli 0,8 mm kolem ní přidá nástroj sám; klip je naopak o trochu těsnější. Držák se připevní šrouby 4 mm s hmoždinkou, zavěšením na šroub ve zdi, nebo oboustrannou páskou bez otvorů. Náhled ukáže tvar i s otvory a rozměry, model se tiskne bez podpěr.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): kolébka na dálkový ovladač 50 × 22 mm, vysoká 70 mm, má 27 g, tiskne se 1 h 12 min a stojí u nás 100 Kč; hák na sluchátka má 11 g, tiskne se 27 minut a stojí 99 Kč; klip na násadu koštěte o průměru 24 mm má 8 g, tiskne se 21 minut a stojí 99 Kč, vše bez dopravy. Za 90 dní prošlo kalkulací 11 držáků. Na lehké věci stačí PLA a páska, na těžší zvolte PETG a šrouby; šrouby, hmoždinky ani páska nejsou součástí výtisku. Model si stáhnete zdarma jako STL nebo projekt pro svou tiskárnu.',
    ],
    'steps' => [
        ['name' => 'Změřte věc', 'text' => 'Změřte šířku a tloušťku věci, u násady její průměr. Zadávejte skutečné rozměry bez rezervy, vůli přidá nástroj.'],
        ['name' => 'Vyberte, jak má držet', 'text' => 'Kolébka a kapsa věc obejmou, hák je na vše s uchem nebo popruhem a klip sevře kulatou násadu. Můžete začít od předvolby, například „Dálkový ovladač“ nebo „Sluchátka“.'],
        ['name' => 'Zvolte upevnění', 'text' => 'Nechte zapnuté otvory na šrouby do zdi, nebo je vypněte a držák přilepte oboustrannou páskou. Otvory mohou být kulaté, nebo pro zavěšení na šroub ve zdi.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Zkontrolujte náhled a tlačítkem „Pokračovat“ přejděte k přesné ceně. Nebo si model stáhněte zdarma pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Jak velkou věc držák pojme?', 'a' => 'Šířka věci může být od 10 do 300 mm a tloušťka od 5 do 150 mm. Klip je jen pro kulaté násady do průměru 60 mm. Držák nad 250 mm se nevejde na podložku naší farmy; kalkulace to řekne.'],
        ['q' => 'Kolik držák unese?', 'a' => 'Nosnost neuvádíme, záleží na materiálu, šroubech a zdi. Na těžší věci zvolte pevný plast (PETG) a šrouby do hmoždinek. Oboustranná páska unese jen lehké věci, třeba ovladač nebo sluchátka.'],
        ['q' => 'Jaké šrouby potřebuji?', 'a' => 'Otvory jsou na šrouby o průměru 4 mm s hmoždinkou. Šrouby ani hmoždinky nejsou součástí výtisku.'],
        ['q' => 'Co znamená zavěšení na šroub ve zdi?', 'a' => 'Hlava šroubu, který už je ve zdi, projde širokým otvorem, držák sjede dolů a visí v úzké drážce. Sundáte ho tak, že ho nadzvednete. Hodí se tam, kde nechcete vrtat nové díry.'],
        ['q' => 'Mám k rozměrům přidat vůli?', 'a' => 'Ne. Nástroj přidá kolem věci vůli 0,8 mm, kterou můžete změnit od 0,3 do 2 mm. Klip je naopak o trochu těsnější, aby násadu sevřel; u násady změřte průměr posuvným měřítkem, ne odhadem.'],
        ['q' => 'Vydrží držák v koupelně nebo venku?', 'a' => 'V koupelně a garáži zvolte PETG, které snáší vlhko a teplo; venku na slunci ASA. PLA stačí do pokoje a kanceláře. Materiál vyberete v kalkulaci v porovnání materiálů.'],
    ],
    'examples' => [
        'Kolébka na dálkový ovladač 50 × 22 mm, vysoká 70 mm, na šrouby: 27 g, 1 h 12 min, 100 Kč.',
        'Hák na sluchátka, šířka 35 mm, hloubka 45 mm, výška háku 25 mm: 11 g, 27 minut, 99 Kč.',
        'Klip na násadu koštěte o průměru 24 mm, výška 25 mm, na šrouby: 8 g, 21 minut, 99 Kč.',
    ],
];
