<?php

// Stránka pro dotaz „3d nápis na poličku“ (docs/AP.md §12). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => '3D nápis ze jména nebo slova: stojí sám, k tisku zdarma',
    'heading' => '3D nápis ze jména nebo slova, který stojí sám',
    'description' => 'Napište jméno, slovo nebo vzkaz, vyberte jedno z 30 písem a vznikne nápis ze silných písmen na patce, který stojí sám. Tisk u nás, nebo stažení zdarma.',
    'h1' => 'Jak nápis vzniká, jak velký vyjde a kolik stojí',
    'intro' => [
        'Nástroj udělá z textu nápis, který stojí sám na poličce, na stole nebo ve výloze. Napíšete jeden až tři řádky po 40 znacích, vyberete jedno ze třiceti písem a výšku velkých písmen od 4 do 80 mm. Písmena jsou silná 1,2 až 30 mm a stojí na společné patce, takže drží pohromadě i písmo, jehož písmena se nedotýkají; víc řádků nad sebou spojuje příčka. K textu přidáte siluetu z knihovny nebo vlastní SVG, stojí vedle písmen nebo nad nimi. Nápis se tiskne vleže na zádech bez podpěr, přední strana je proto rovná a hladká. Když je na svou výšku moc tenký a mohl by se převrhnout, nástroj to řekne ještě v náhledu.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): slovo HOME písmem Archivo Black s písmeny 30 mm vysokými a 12 mm silnými má 144 × 34 mm, váží 21,1 g, tiskne se 40 minut a stojí u nás 99 Kč bez dopravy (kalkulace /c/bkdbbtgjavzn). Dvouřádkový nápis KAVÁRNA u Jany písmem Bebas Neue, 20 mm silný, má 55,7 g, tiskne se 1 h 53 min a stojí 130 Kč (kalkulace /c/qxcoca4ayqkb). Cenu vidíte dřív, než cokoli objednáte, i pro víc kusů. Model si stáhnete zdarma jako STL nebo projekt pro svou tiskárnu, bez registrace.',
    ],
    'steps' => [
        ['name' => 'Napište text', 'text' => 'Jeden až tři řádky; první je největší. Symboly pod poli vložíte do textu jedním klepnutím, obrázek přidáte z knihovny nebo nahrajete SVG.'],
        ['name' => 'Vyberte písmo', 'text' => 'Třicet písem vidíte jako dlaždice s názvem vysázeným v daném písmu. Tučná bezpatková stojí nejjistěji, psaná jsou zdobnější.'],
        ['name' => 'Nastavte výšku a tloušťku', 'text' => 'Výška písma určuje velikost nápisu, tloušťka jeho hloubku. Aby nápis stál, má být hluboký aspoň pětinu své výšky; u tenčího nástroj upozorní.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu ukáže kalkulace po „Pokračovat“. Model i projekt pro slicer stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jak velký nápis vyjde?', 'a' => 'Slovo HOME s písmem vysokým 30 mm vyjde 144 mm dlouhé, 34 mm vysoké a 12 mm hluboké. Délka roste s počtem písmen a s výškou písma; na tiskovou plochu se vejde nápis do 250 mm. Delší text rozdělte do dvou řádků, nástroj je spojí příčkou.'],
        ['q' => 'Bude nápis opravdu stát?', 'a' => 'Ano, když je hluboký aspoň pětinu své výšky; u tenčího nástroj upozorní. Jednořádkový nápis vysoký 30 mm stojí už při 8 mm, dvouřádkový potřebuje 15 až 20 mm. Těžší je nápis s vysokým obrázkem nad písmeny, tomu dejte hloubku o třetinu větší.'],
        ['q' => 'Co když se písmena nedotýkají?', 'a' => 'Nevadí. Všechna stojí na patce a řádky nad sebou spojuje příčka. Čárky, háčky a tečky, které by zůstaly ve vzduchu, nástroj přichytí krátkou spojkou, takže i jméno s diakritikou je jeden kus.'],
        ['q' => 'Jaké písmo vybrat?', 'a' => 'Na krátké slovo tučné bezpatkové, třeba Archivo Black nebo Bebas Neue. Na jméno psané, například Pacifico nebo Lobster. Velmi tenká psaná písma volte od výšky 30 mm, jinak nástroj upozorní na čáry tenčí než 0,8 mm, které se netisknou čistě.'],
        ['q' => 'Dá se nápis vytisknout ve dvou barvách?', 'a' => 'Stojící nápis je jeden kus v jedné barvě. Druhou barvu získáte tak, že text rozdělíte do dvou návrhů a každý objednáte v jiné barvě, nebo zvolíte cedulku, kde je písmo jinou barvou než destička.'],
        ['q' => 'Jak nápis tisknout doma?', 'a' => 'Na zádech, přední stranou dolů, bez podpěr, vrstva 0,2 mm, PLA nebo PETG. Stažený projekt pro OrcaSlicer a PrusaSlicer má nápis už položený; z STL ho položte sami na přední plochu. Spodní plocha patky zůstane po sejmutí z podložky hladká.'],
    ],
    'examples' => [
        'Slovo HOME písmem Archivo Black, 144 × 34 mm, hloubka 12 mm: 21,1 g, 40 minut, 99 Kč.',
        'Jméno Ela psacím písmem se srdcem na společné patce, 143 × 48 mm, hloubka 15 mm.',
        'Dvouřádkový nápis KAVÁRNA u Jany písmem Bebas Neue, hloubka 20 mm: 55,7 g, 1 h 53 min, 130 Kč.',
    ],
];
