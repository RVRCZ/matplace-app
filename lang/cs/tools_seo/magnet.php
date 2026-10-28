<?php

// Stránka pro dotaz „magnetka z fotky“ (docs/AP.md §12). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Magnetka na lednici z fotky nebo obrázku: 3D tisk zdarma',
    'heading' => 'Magnetka na lednici z fotky nebo obrázku pro 3D tisk',
    'description' => 'Z fotky, obrázku nebo jména vznikne magnetka v barvách filamentů s kapsou přesně na váš magnet. Vytiskneme ji, nebo si model stáhnete zdarma.',
    'h1' => 'Jak magnetka vzniká, jaký magnet do ní patří a kolik stojí',
    'intro' => [
        'Nástroj udělá z obrázku magnetku na lednici. Nahrajete PNG, JPG, WebP nebo SVG, vyberete motiv z knihovny, nebo napíšete jméno. Obrázek se převede na 1 až 8 barev filamentů, které máme na farmě skladem; pozadí fotky se odstraní samo a magnetka pak kopíruje obrys motivu, nebo je to kruh či obdélník s celou fotkou. Šířka 40 až 100 mm, tloušťka 3 až 6 mm, okraj kolem obrázku do 3 mm, případně zkosená hrana. Na zadní straně je uprostřed kapsa na magnet: vyberete běžný kulatý magnet od Ø 6 × 2 do Ø 20 × 3 mm, nebo zadáte vlastní rozměr, a magnet vlepíte, nalisujete, nebo necháte otvor skrz; pro samolepicí magnetickou fólii zvolíte magnetku bez kapsy. Když je magnet na zvolenou tloušťku vysoký, nástroj podklad zesílí, aby nad ním zůstaly čtyři vrstvy plastu. Magnety součástí nejsou.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): tlapka z knihovny v kruhu 60 mm, 3 mm silná, s kapsou na magnet Ø 10 × 2 mm, má 7 g, tiskne se 14 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky, bez dopravy (kalkulace /c/gob4imtsfmor). Víc kusů stejného návrhu zadáte jako počet kusů a cena za kus klesne; magnetky z fotek celé rodiny jsou různé návrhy. Na farmě vytiskneme až čtyři barvy najednou, podklad a tři další; cívku každé barvy potvrdíte při objednávce. STL, ZIP s jedním STL pro každou barvu i projekty pro slicer si stáhnete zdarma a bez registrace.',
    ],
    'steps' => [
        ['name' => 'Nahrajte obrázek nebo fotku', 'text' => 'Pozadí se odstraní samo a tvar pak kopíruje obrys motivu. Když má být vidět celá fotka, odstranění pozadí vypnete. Kontrast, jas a sytost fotky doladíte posuvníky.'],
        ['name' => 'Zvolte tvar a rozměry', 'text' => 'Tvar je podle obrázku, kruh nebo obdélník. Nastavíte šířku, tloušťku, okraj kolem obrázku do 3 mm a případně zkosenou hranu.'],
        ['name' => 'Vyberte magnet a uchycení', 'text' => 'Zvolíte rozměr magnetu a způsob uchycení: kapsu na lepení s vůlí 0,1 až 0,3 mm, kapsu na nalisování, otvor skrz, nebo žádnou kapsu pro magnetickou fólii.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu a dobu tisku ukáže kalkulace po „Pokračovat“. STL, ZIP s jedním STL pro každou barvu a projekty pro slicer stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jaké magnety do kapsy pasují?', 'a' => 'Předvolby jsou pro běžné kulaté magnety Ø 6 × 2, 8 × 3, 10 × 2, 12 × 3, 15 × 3 a 20 × 3 mm. Vlastní rozměr zadáte v rozsahu průměru 4 až 30 mm a výšky 1 až 6 mm. Magnety nejsou součástí výtisku; na magnetku 60 mm stačí neodymový Ø 10 × 2 mm.'],
        ['q' => 'Jak magnet v magnetce drží?', 'a' => 'Do kapsy na lepení ho vlepíte, například vteřinovým lepidlem; vůli kolem magnetu nastavíte od 0,1 do 0,3 mm. Do kapsy na nalisování se magnet zatlačí. Otvor skrz prochází celou magnetkou, magnet pak může být zepředu vidět.'],
        ['q' => 'Jaká fotka na magnetku vyjde?', 'a' => 'Kresba, logo nebo fotka s jasným motivem na klidném pozadí; z ní nástroj pozadí odstraní a magnetka kopíruje obrys. Portrét nechte jako kruh nebo obdélník s celou fotkou a 6 až 8 barvami, vyjde plakátově. Detaily pod 0,8 mm se slijí.'],
        ['q' => 'Co když je magnet na zvolenou tloušťku moc vysoký?', 'a' => 'Nástroj podklad sám zesílí tak, aby nad magnetem zůstaly čtyři vrstvy plastu, a napíše novou tloušťku. Když se magnet do tvaru nevejde, upozorní vás, ať zvětšíte šířku nebo zvolíte menší magnet.'],
        ['q' => 'Z čeho se magnetka tiskne a jak doma?', 'a' => 'Doporučujeme PLA. Tiskne se lícem nahoru, kapsou dolů a bez podpěr, vrstva 0,2 mm; kapsa je v podkladu, takže nad ní tiskárna přemostí pár milimetrů. Pro místa, kde se magnetka zahřívá, zvolte PETG.'],
        ['q' => 'Vytisknete magnetku barevně?', 'a' => 'Ano, až ve čtyřech barvách v jednom tisku: podklad a tři barvy nad ním. Cívku pro každou barvu potvrdíte při objednávce, ty z návrhu jsou předvybrané. Návrh s více barvami si stáhnete; barvy leží nad sebou, takže ho vytiskne jakákoli tiskárna výměnou filamentu ve výškách, které projekt obsahuje.'],
    ],
    'examples' => [
        'Tlapka v kruhu široká 60 mm, vzadu kapsa na magnet Ø 10 × 2 mm: 7 g, 14 minut, 99 Kč.',
        'Červené srdce široké 50 mm s nalisovaným magnetem Ø 8 × 3 mm.',
        'Fotka na obdélníku širokém 80 mm, 6 barev, otvor skrz na magnet Ø 12 × 3 mm.',
    ],
];
