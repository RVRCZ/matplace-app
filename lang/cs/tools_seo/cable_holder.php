<?php

// Stránka pro dotaz „držák kabelů na stůl“ (docs/AP.md §12). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Držák kabelů na stůl na míru pro 3D tisk: návrh zdarma',
    'heading' => 'Držák kabelů na stůl na míru pro 3D tisk: kabely zacvaknou a nespadnou',
    'description' => 'Zadáte průměr kabelu a počet drážek a vznikne držák, do kterého kabely zacvaknou shora a nespadnou za stůl. Tisk u nás, nebo stažení zdarma.',
    'h1' => 'Jak držák vzniká, jak velký vyjde a kolik stojí',
    'intro' => [
        'Držák kabelů je malý blok s drážkami, který přilepíte oboustrannou páskou nebo přišroubujete na hranu stolu. Kabel do drážky zacvaknete shora a zůstane na místě, i když ho odpojíte od zařízení; štěrbina nad drážkou je o něco užší než kabel, proto nevypadne, a otvor je o 0,6 mm větší, aby se kabel volně posouval. Zadáte počet kabelů 1 až 8 a průměr kabelu 3 až 14 mm, všechny drážky jednoho držáku mají stejný průměr. Hloubku držáku nastavíte od 10 do 80 mm, tloušťku stěn mezi drážkami a zaoblení rohů také; s volbou „S oušky na šrouby“ dostane po stranách dvě ouška s otvory. Hodí se na nabíječky, kabely k monitoru nebo ke sluchátkům.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): držák na čtyři kabely o průměru 6 mm, dlouhý 61 mm a hluboký 45 mm, má 17,1 g, tiskne se 42 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky, bez dopravy (kalkulace /c/mjyodedmxzye); držák na šest tenkých kabelů 4 mm s oušky na šrouby má 24,6 g a tiskne se 56 minut (kalkulace /c/kop5zm2dhbtw). Na každý stůl v kanceláři zadáte počet kusů a cena za kus klesne. Model si stáhnete zdarma jako STL nebo projekt pro svou tiskárnu; tiskne se bez podpěr. Páska ani šrouby nejsou součástí výtisku.',
    ],
    'steps' => [
        ['name' => 'Změřte průměr kabelu', 'text' => 'Změřte průměr kabelu, ne koncovky. Zadat lze průměr od 3 do 14 mm po půl milimetru.'],
        ['name' => 'Zvolte počet kabelů', 'text' => 'Držák může mít 1 až 8 drážek. Všechny drážky mají stejný průměr; na různě silné kabely navrhněte víc držáků.'],
        ['name' => 'Vyberte upevnění', 'text' => 'Bez oušek se držák lepí oboustrannou páskou. S volbou „S oušky na šrouby“ dostane po stranách dvě ouška s otvory.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Zkontrolujte náhled s rozměry a tlačítkem „Pokračovat“ přejděte k přesné ceně. Nebo si model stáhněte zdarma pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Jak silné kabely držák udrží?', 'a' => 'Kabely o průměru od 3 do 14 mm: od tenkého kabelu ke sluchátkům po napájecí kabel monitoru. Otvor je o 0,6 mm větší než kabel, aby se v něm kabel volně posouval, a štěrbina nad ním je užší, aby nevypadl.'],
        ['q' => 'Mohu mít v jednom držáku různě silné kabely?', 'a' => 'Ne, všechny drážky jednoho držáku mají stejný průměr. Pro různě silné kabely navrhněte víc držáků; dva držáky vedle sebe vyjdou na kus levněji než jeden, když je zadáte jako víc kusů.'],
        ['q' => 'Jak se držák připevní a udrží?', 'a' => 'Oboustrannou páskou na hranu nebo spodek desky stolu, nebo dvěma šrouby, když zapnete ouška na šrouby. Pěnová páska drží na lakované i laminátové desce; na dřevo bez laku raději šrouby. Páska ani šrouby nejsou součástí výtisku.'],
        ['q' => 'Jak velký držák bude?', 'a' => 'Délka závisí na počtu a průměru kabelů a na tloušťce stěn; přesné vnější rozměry ukáže náhled. Držák na čtyři kabely o průměru 6 mm je dlouhý asi 61 mm a hluboký 45 mm, na šest kabelů 4 mm asi 105 mm.'],
        ['q' => 'Musí drážkou projít koncovka kabelu?', 'a' => 'Nemusí. Kabel se do drážky zacvakne shora, koncovku nikudy neprovlékáte; stejně snadno ho zase vyndáte.'],
        ['q' => 'Z čeho držák tisknout a jak doma?', 'a' => 'Z PLA do kanceláře a domácnosti, z PETG tam, kde je teplo nebo se kabely často vyndávají a štěrbina má pružit. Tiskne se drážkami nahoru, bez podpěr, vrstva 0,2 mm; projekt pro slicer je připravený.'],
    ],
    'examples' => [
        'Držák na čtyři kabely o průměru 6 mm, délka 61 mm, lepí se páskou: 17,1 g, 42 minut, 99 Kč.',
        'Držák na dva silné kabely o průměru 10 mm, délka 45 mm.',
        'Držák na šest tenkých kabelů 4 mm s oušky na šrouby, délka 105 mm: 24,6 g, 56 minut.',
    ],
];
