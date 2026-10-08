<?php

return [
    'title' => '3D nápis, který stojí: jméno nebo slovo na poličku',
    'description' => 'Napíšete jméno, slovo nebo krátký vzkaz a vznikne nápis ze silných písmen na patce, který stojí sám. Vytiskneme ho, nebo si model zdarma stáhnete.',
    'h1' => 'Stojící 3D nápis ze jména nebo slova',
    'intro' => [
        'Nástroj udělá z textu nápis, který stojí sám na poličce, na stole nebo ve výloze. Písmena jsou silná až 30 mm a stojí na společné patce, takže drží pohromadě i písmo, jehož písmena se nedotýkají. Napíšete jeden až tři řádky po 40 znacích, vyberete jedno ze třiceti písem a výšku velkých písmen od 4 do 80 mm. Delší text se rozloží do řádků nad sebou a mezi řádky vznikne příčka, která je spojí.',
        'K textu můžete přidat obrázek: siluetu z knihovny nebo vlastní SVG. Stojí vedle písmen na stejné patce, nebo nad nimi. Nápis se tiskne vleže na zádech, bez podpěr, a přední strana je proto rovná a čistá. Když je na svou výšku příliš tenký a mohl by se převrhnout, nástroj to řekne. Hotový návrh objednáte jako výtisk v barvě, která je právě založená v tiskárnách, nebo si model stáhnete.',
    ],
    'steps' => [
        ['name' => 'Napište text', 'text' => 'Jeden až tři řádky; první je největší. Symboly pod poli vložíte do textu jedním klepnutím.'],
        ['name' => 'Vyberte písmo', 'text' => 'Třicet písem vidíte jako dlaždice s názvem vysázeným v daném písmu. Tučná bezpatková stojí nejjistěji, psaná jsou zdobnější.'],
        ['name' => 'Nastavte výšku a tloušťku', 'text' => 'Výška písma určuje velikost nápisu, tloušťka jeho hloubku. Aby nápis stál, má být hluboký aspoň pětinu své výšky.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry a orientační cenu, přesnou cenu o krok dál. Model i projekt pro slicer stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jak velký nápis vyjde?', 'a' => 'Slovo HOME s písmem vysokým 30 mm vyjde 144 mm dlouhé, 34 mm vysoké a 12 mm hluboké. Délka roste s počtem písmen; na tiskovou plochu se vejde nápis do 250 mm.'],
        ['q' => 'Bude nápis opravdu stát?', 'a' => 'Ano, když je hluboký aspoň pětinu své výšky; u tenčího nástroj upozorní. Jednořádkový nápis vysoký 30 mm stojí už při 8 mm, dvouřádkový potřebuje 15 až 20 mm.'],
        ['q' => 'Co když se písmena nedotýkají?', 'a' => 'Nevadí. Všechna stojí na patce a řádky nad sebou spojuje příčka. Čárky, háčky a tečky, které by zůstaly ve vzduchu, nástroj přichytí krátkou spojkou.'],
        ['q' => 'Jaké písmo vybrat?', 'a' => 'Na krátké slovo tučné bezpatkové, třeba Archivo Black nebo Bebas Neue. Na jméno psané, například Pacifico nebo Lobster. Velmi tenká psaná písma volte od výšky 30 mm, jinak nástroj upozorní na tenké čáry.'],
        ['q' => 'Dá se nápis vytisknout ve dvou barvách?', 'a' => 'Nápis je jeden kus v jedné barvě. Druhou barvu získáte tak, že text rozdělíte do dvou návrhů, nebo zvolíte cedulku, kde je písmo jinou barvou než destička.'],
        ['q' => 'Jak zaplatím a jak nápis dostanu?', 'a' => 'Platíte z předplaceného kreditu, který dobijete kartou; ceny vidíte v korunách nebo v eurech. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [
        'Slovo HOME písmem Archivo Black, 144 × 34 mm, hloubka 12 mm.',
        'Jméno Ela psacím písmem se srdcem na společné patce, 143 × 48 mm, hloubka 15 mm.',
        'Dvouřádkový nápis KAVÁRNA u Jany písmem Bebas Neue, 144 × 87 mm, hloubka 20 mm.',
    ],
];
