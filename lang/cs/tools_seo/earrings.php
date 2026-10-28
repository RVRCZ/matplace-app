<?php

// Stránka pro dotaz „náušnice 3d tisk vlastní“ (docs/AP.md §12). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Náušnice pro 3D tisk z vlastního obrázku: pár zdarma',
    'heading' => 'Náušnice z vlastního obrázku nebo nápisu pro 3D tisk',
    'description' => 'Z obrázku, motivu z knihovny nebo krátkého nápisu vznikne pár náušnic, stejných nebo zrcadlových, v barvách filamentů. Tisk u nás, nebo stažení zdarma.',
    'h1' => 'Jak pár náušnic vzniká, co váží a kolik stojí',
    'intro' => [
        'Nástroj vytvoří z jednoho obrázku pár náušnic. Obě jsou stejné, nebo je druhá zrcadlově obrácená, aby se motiv na obou uších díval stejným směrem. Nahrajete PNG, JPG, WebP nebo SVG, vyberete motiv z knihovny, nebo napíšete krátký text do 12 znaků na řádek. Jedna náušnice je široká 10 až 60 mm a silná 1,6 až 3 mm; kopíruje obrys obrázku, nebo má tvar kruhu, kolem obrázku je okraj do 2 mm. Očko s otvorem 1,5 až 3 mm umístíte kamkoli na obrys, posuvníkem nebo tažením v náhledu. Obrázek se převede na 1 až 8 barev filamentů, které máme skladem; barvy leží nad sebou, každá o 0,2 až 0,8 mm výš. Háčky a kroužky součástí nejsou, koupíte je v galanterii.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): pár červených srdcí z knihovny, každé široké 28 mm a 2,4 mm silné, má dohromady 2,2 g, tiskne se 6 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky, bez dopravy (kalkulace /c/5xbjn5tfg5gn). Jedna náušnice váží kolem gramu, takže netahá. Víc párů stejného návrhu zadáte jako počet kusů a cena za pár klesne. Na farmě vytiskneme až čtyři barvy najednou, podklad a tři další; cívku každé barvy potvrdíte při objednávce. STL i projekt pro slicer s výměnami barev si stáhnete zdarma a bez registrace.',
    ],
    'steps' => [
        ['name' => 'Nahrajte obrázek nebo napište text', 'text' => 'Obrázek nahrajete, vyberete z knihovny, nebo použijete dříve nahraný. Pozadí se odstraní samo; posuvníkem určíte, kolik ho zmizí.'],
        ['name' => 'Zvolte pár a velikost', 'text' => 'Vyberete stejné, nebo zrcadlové náušnice a tvar podle obrázku či kruh. Nastavíte šířku jedné náušnice, tloušťku a okraj kolem obrázku do 2 mm.'],
        ['name' => 'Umístěte očko a vyberte barvy', 'text' => 'Očko posunete posuvníkem nebo tažením v náhledu. Každé barvě můžete v okně barev dát jiný filament, změnit pořadí barev nebo dvě barvy sloučit.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Náhled ukazuje obě náušnice v barvách filamentů a rozměry v milimetrech. Přesnou cenu a dobu tisku uvidíte o krok dál, v kalkulaci. STL i projekt pro slicer stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jsou háčky součástí náušnic?', 'a' => 'Ne. Dostanete jen plastové díly s očkem. Háčky, spojovací kroužky nebo puzety koupíte v galanterii a do očka je navléknete sami; na otvor očka 2 mm stačí kroužek 4 až 5 mm.'],
        ['q' => 'K čemu je zrcadlový pár?', 'a' => 'Pravá náušnice je stranově obrácená, takže se motiv na obou uších dívá stejným směrem. U souměrného motivu, třeba srdce, stačí pár stejných; u nápisu zrcadlení nepoužívejte, text by byl pozpátku.'],
        ['q' => 'Kolik náušnice váží a vydrží?', 'a' => 'PLA je lehké: náušnice široká 32 mm a 2,4 mm silná váží zhruba 1 až 2 gramy, takže ucho netahá. Očko je spojené s tělem náušnice; při tloušťce 2,4 mm a stěně očka 1,6 mm vydrží běžné nošení, na tenké motivy pod 0,8 mm nástroj upozorní.'],
        ['q' => 'Jaký obrázek na náušnice vyjde?', 'a' => 'Jednoduchá silueta nebo motiv s 2 až 4 barvami: srdce, hvězda, list, písmeno. Na 30 mm se drobné detaily fotky slijí, proto portréty nechte raději na magnetku nebo podtácek. Krátké jméno nebo iniciály napíšete místo obrázku.'],
        ['q' => 'Z čeho se náušnice tisknou a jak doma?', 'a' => 'Doporučujeme PLA; obě náušnice se tisknou najednou, naplocho, lícem nahoru a bez podpěr, vrstva 0,2 mm nebo jemnější. Projekt pro OrcaSlicer, Bambu Studio a PrusaSlicer má výměny filamentu ve výškách barev. Barvy zarovno s povrchem potřebují tiskárnu, která mění filament sama (AMS, MMU, ACE).'],
        ['q' => 'Kolik barev vytisknete u vás?', 'a' => 'Až čtyři v jednom tisku: podklad a tři barvy nad ním. Cívku pro každou barvu potvrdíte při objednávce, ty z návrhu jsou předvybrané. Návrh s více barvami si stáhnete a vytisknete na své tiskárně.'],
    ],
    'examples' => [
        'Pár červených srdcí, každé široké 28 mm: 2,2 g, 6 minut, 99 Kč.',
        'Zrcadlový pár duchů, každý široký 32 mm, očko na špičce.',
        'Kulaté náušnice 30 mm s usměvavou hvězdou, tři barvy.',
    ],
];
