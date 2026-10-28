<?php

// Stránka pro dotaz „podtácek s fotkou 3d tisk“ (docs/AP.md §12). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Podtácek s fotkou nebo kresbou pro 3D tisk: návrh zdarma',
    'heading' => 'Podtácek s vlastní fotkou nebo kresbou pro 3D tisk',
    'description' => 'Kulatý, čtvercový nebo šestihranný podtácek s fotkou, kresbou nebo jménem v barvách filamentů, s drážkami proti klouzání. Tisk u nás, nebo stažení zdarma.',
    'h1' => 'Jak podtácek vzniká, kolik barev unese a kolik stojí',
    'intro' => [
        'Nástroj vytvoří podtácek s vaším obrázkem: kruh, čtverec nebo šestiúhelník o šířce 80 až 120 mm a tloušťce 3 až 6 mm. Nahrajete PNG, JPG, WebP či SVG, vyberete motiv z knihovny, nebo napíšete jméno. Celá fotka vyplní podtácek až k okraji, vyříznutý motiv sedí uprostřed. Obrázek se převede na 1 až 8 barev filamentů, které máme na farmě skladem, a barvy vystupují nad podklad po stupních, ve výchozím nastavení po 0,4 mm, takže sklenice stojí bez potíží. Zespodu přidáte drážky, aby podtácek na mokrém stole neklouzal, a kolem obrázku okraj do 4 mm nebo zkosenou hranu. Úplně rovný vršek dává volba „Barvy zarovno s povrchem“, která potřebuje tiskárnu s výměnou filamentu.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): kulatý podtácek 100 mm se sněhulákem z knihovny, 4 mm silný, s drážkami zespodu, má 20,4 g, tiskne se 40 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky, bez dopravy (kalkulace /c/xqbu3qsmyqm0); sada čtyř stejných stojí 170 Kč (kalkulace /c/57k7ndo2ibxo), protože příprava zakázky se platí jednou. Na farmě vytiskneme až čtyři barvy najednou, podklad a tři další; cívku každé barvy potvrdíte při objednávce. Model i projekt pro slicer s výměnami barev si stáhnete zdarma a bez registrace.',
    ],
    'steps' => [
        ['name' => 'Nahrajte fotku nebo kresbu', 'text' => 'Obrázek nahrajete, vyberete z knihovny, nebo použijete dříve nahraný. Pro celou fotku vypněte odstranění pozadí; když ho necháte zapnuté, zůstane jen motiv uprostřed.'],
        ['name' => 'Vyberte tvar a rozměry', 'text' => 'Kruh, čtverec nebo šestiúhelník. Nastavíte šířku, tloušťku a okraj kolem obrázku do 4 mm, případně zkosenou hranu a drážky zespodu.'],
        ['name' => 'Dolaďte barvy', 'text' => 'Zvolíte počet barev a v okně barev každé přiřadíte filament. Určíte, o kolik je každá barva výš (0,2 až 0,8 mm), a dvě barvy můžete sloučit v jednu.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu a dobu tisku ukáže kalkulace po „Pokračovat“. Pro sadu stačí nastavit počet kusů. Model i projekt pro slicer stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Bude na podtácku sklenice stát rovně?', 'a' => 'Ano. Barvy jsou ve výchozím nastavení odstupňované po 0,4 mm a sklenice na nich stojí bez potíží. Úplně rovný vršek získáte volbou „Barvy zarovno s povrchem“, kterou vytiskne jen tiskárna, která mění filament sama (AMS, MMU, ACE).'],
        ['q' => 'Z čeho se podtácek tiskne a snese horký hrnek?', 'a' => 'Doporučujeme PLA: snese studené i teplé nápoje, kolem 55 °C ale měkne. Pro hrnek přímo z plotny zvolte PETG. Podtácek se tiskne naplocho a bez podpěr.'],
        ['q' => 'Jaká fotka na podtácek vyjde?', 'a' => 'Nejlépe kresba, logo nebo fotka s několika plnými barvami; z portrétu zůstane po převodu na 4 až 8 barev plakátový obraz. Pro celou fotku vypněte odstranění pozadí, jinak zůstane jen motiv uprostřed. Drobné detaily pod 0,8 mm se slijí.'],
        ['q' => 'K čemu jsou drážky zespodu?', 'a' => 'Podtácek díky nim na mokrém stole neklouže a kapka pod ním neudělá přísavku. Drážky jsou volitelné a nepřidají čas tisku.'],
        ['q' => 'Jak objednám sadu podtácků?', 'a' => 'Sada je stejný podtácek ve více kusech: stačí nastavit počet kusů, cena za kus klesne, protože příprava zakázky se platí jednou. Podtácky s různými motivy jsou různé návrhy, každý se objedná zvlášť.'],
        ['q' => 'Kolik barev vytisknete u vás a jak doma?', 'a' => 'U nás až čtyři v jednom tisku: podklad a tři barvy nad ním, cívku každé potvrdíte při objednávce. Doma vytiskne návrh s více barvami jakákoli tiskárna: barvy leží nad sebou a projekt pro OrcaSlicer, Bambu Studio a PrusaSlicer má výměny filamentu ve správných výškách.'],
    ],
    'examples' => [
        'Kulatý podtácek 100 mm se sněhulákem a drážkami zespodu: 20,4 g, 40 minut, 99 Kč; sada čtyř 170 Kč.',
        'Šestihranný podtácek 95 mm s tlapkou, okraj 2 mm, čtyři barvy.',
        'Čtvercový podtácek 100 mm s fotkou přes celou plochu, 6 barev, zkosená hrana.',
    ],
];
