<?php

// Stránka pro dotaz „krabička na míru 3d tisk“ (docs/AP.md §9). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Krabička na míru k 3D tisku: s víčkem a otvory, model zdarma',
    'heading' => 'Krabička na míru k 3D tisku',
    'description' => 'Zadáte vnitřní rozměry, přidáte víčko, otvory ve stěnách nebo výřez na kabel a hned vidíte náhled s vnějšími rozměry. Model zdarma, nebo tisk u nás.',
    'h1' => 'Co krabička na míru umí, jak sedí víčko a kolik stojí tisk',
    'intro' => [
        'Nástroj vytvoří krabičku podle vnitřních rozměrů, tedy podle věci, která se do ní má vejít: šířka a hloubka 10 až 300 mm, výška 8 až 200 mm, stěny 1,2 až 5 mm. Vnější rozměry dopočítá sám a ukáže je pod náhledem. Můžete přidat nasazovací víčko s lemem (vůle 0,25 mm, jde změnit), až osm kruhových nebo obdélníkových otvorů do přední, zadní, levé nebo pravé stěny, zaoblené rohy a výřez na kabel od horního okraje široký 3 až 30 mm. Krabička i víčko se tisknou vedle sebe bez podpěr. Poslouží na elektroniku, drobné součástky, SD karty i jako obal na dárek.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): krabička s nasazovacím víčkem, uvnitř 80 × 50 × 30 mm (vně 84 × 54 × 32 mm), má 28 g, tiskne se 34 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky (kalkulace /c/nku29wz4vieb); s víčkem a výřezem na kabel má 41 g, tiskne se 51 minut a stojí 110 Kč, obojí bez dopravy. Za 90 dní prošlo kalkulací 13 krabiček. Do zásuvky stačí PLA, do koupelny nebo k troubě zvolte PETG. Model si stáhnete zdarma jako STL nebo projekt pro svou tiskárnu; krabičku i víčko také jako samostatné díly.',
    ],
    'steps' => [
        ['name' => 'Změřte, co se má vejít', 'text' => 'Zadejte šířku, hloubku a výšku uvnitř krabičky. Kolem věci si nechte 1 až 2 mm rezervy na každé straně.'],
        ['name' => 'Zvolte víčko a zaoblení', 'text' => 'Zaškrtněte „S nasazovacím víčkem“, pokud víčko chcete, a nastavte zaoblení rohů. Pro kabel přidejte volbu „S výřezem na kabel“.'],
        ['name' => 'Přidejte otvory ve stěnách', 'text' => 'U každého otvoru vyberete stěnu, tvar, velikost a polohu středu. Poloha se měří od levého dolního rohu stěny při pohledu zvenku.'],
        ['name' => 'Zkontrolujte náhled', 'text' => 'Náhled ukáže krabičku, víčko a rozměry. Když otvor zasahuje mimo stěnu nebo se dva překrývají, nástroj to ohlásí.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Pokračujte k přesné ceně a tisk objednejte u nás. Nebo si krabičku a víčko stáhněte zdarma pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Zadávám vnitřní, nebo vnější rozměry?', 'a' => 'Vnitřní. Vnější rozměry nástroj dopočítá podle tloušťky stěn a dna a ukáže je pod náhledem; u krabičky 80 × 50 × 30 mm se stěnou 2 mm vyjdou 84 × 54 × 32 mm.'],
        ['q' => 'Jak velká může krabička být?', 'a' => 'Uvnitř od 10 do 300 mm na šířku i hloubku a od 8 do 200 mm na výšku. Stěny mohou být silné 1,2 až 5 mm. Krabička nad 250 mm se nevejde na každou tiskárnu; kalkulace to řekne.'],
        ['q' => 'Jak drží víčko?', 'a' => 'Víčko má lem, který zapadne dovnitř krabičky. Nemá panty, zámek ani šrouby. Když jde ztuha nebo volně, upravte vůli víčka; výchozí je 0,25 mm. U první krabičky z nové sady vůli vyzkoušejte.'],
        ['q' => 'Kolik otvorů mohu přidat a kam?', 'a' => 'Nejvýše osm, do přední, zadní, levé nebo pravé stěny. Otvor musí mít aspoň 2 mm, být 2 mm od okraje a 1,5 mm od dalšího otvoru. Do dna ani do víčka otvory přidat nejde.'],
        ['q' => 'K čemu je výřez na kabel?', 'a' => 'Je to drážka v pravé stěně otevřená k hornímu okraji. Kabel do ní položíte i s koncovkou a víčko se nad ním zavře. Šířku výřezu zvolíte od 3 do 30 mm; na USB‑C stačí 10 mm, na napájecí kabel se zástrčkou 15 mm.'],
        ['q' => 'Hodí se krabička na elektroniku, třeba na Arduino nebo ESP32?', 'a' => 'Ano: zadáte rozměry desky plus 2 mm na každé straně a výšku podle nejvyšší součástky, otvory na konektory umístíte podle polohy od rohu stěny. Větrací otvory přidáte jako řadu kruhových otvorů; na teplo uvnitř zvolte PETG.'],
    ],
    'examples' => [
        'Krabička s nasazovacím víčkem na drobnosti, uvnitř 80 × 50 × 30 mm: 28 g, 34 minut, 99 Kč.',
        'Krabička s víčkem a výřezem na kabel, uvnitř 120 × 80 × 40 mm.',
        'Otevřená krabička bez víčka se zaoblenými rohy, uvnitř 40 × 40 × 20 mm.',
    ],
];
