<?php

// Stránka pro dotaz „modulární organizér do zásuvky“ (docs/AP.md §12). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Modulární organizér do zásuvky na míru: misky k tisku zdarma',
    'heading' => 'Modulární organizér do zásuvky na míru z volných misek',
    'description' => 'Zadáte vnitřek zásuvky, rozdělíte ho na mřížku a naskládáte misky různých velikostí. Každá se tiskne zvlášť a jde vyndat. Tisk u nás, nebo stažení zdarma.',
    'h1' => 'Jak sadu misek navrhnout, kolik stojí a jak se tiskne',
    'intro' => [
        'Modulární organizér se skládá ze samostatných misek, které vedle sebe přesně vyplní zásuvku. Zadáte vnitřní šířku a hloubku zásuvky od 60 do 600 mm a výšku misek 15 až 120 mm, rozdělíte plochu na mřížku až 12 × 12 polí a do polí naskládáte až 24 misek různých velikostí; jedna miska může zabrat víc polí. Každá miska se tiskne zvlášť, takže ji kdykoli vyndáte, přeskládáte nebo umyjete, a každá může mít jinou barvu. Mezi miskami je vůle 0,6 mm, rohy jsou zaoblené. Když misky nepatří do zásuvky, ale na stůl nebo na polici, přidáte k nim vnější tác, který je drží pohromadě. Na rozdíl od organizéru z jednoho kusu se sada hodí do velké zásuvky: misky se vejdou na každou tiskárnu.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): čtyři misky tří velikostí pro zásuvku 300 × 150 mm, 40 mm vysoké, mají dohromady 188 g, tisknou se 3 h 45 min a stojí u nás 310 Kč bez dopravy (kalkulace /c/fsgkqtoncl9f); barvu každé misky vyberete při objednávce v jedné zakázce. Nástroj ukáže rozložení v náhledu a pod ním seznam „Co se vytiskne“ s rozměry každé misky; přesnou cenu celé sady spočítá kalkulace o krok dál. Máte-li tiskárnu, stáhnete si zdarma jednotlivé misky jako STL nebo celou sadu jako projekt pro svou tiskárnu.',
    ],
    'steps' => [
        ['name' => 'Změřte vnitřek zásuvky', 'text' => 'Zadejte šířku a hloubku uvnitř zásuvky a výšku misek. Od změřených rozměrů odečtěte 1 až 2 mm, stěny zásuvky nebývají přesně rovné.'],
        ['name' => 'Rozdělte ji na mřížku', 'text' => 'Zvolte počet sloupců a řad. Nástroj ukáže, kolik milimetrů měří jedno pole mřížky; musí mít aspoň 15 mm.'],
        ['name' => 'Naskládejte misky', 'text' => 'Klepněte na prázdné pole a potom na protější roh misky. Klepnutím na hotovou misku ji vyberete a změníte jí barvu nebo ji odeberete.'],
        ['name' => 'Zkontrolujte seznam', 'text' => 'Pod náhledem je seznam „Co se vytiskne“ s rozměry jednotlivých misek. Přesná cena celé sady je o krok dál, v kalkulaci.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Pokračujte k přesné ceně a sadu objednejte u nás. Nebo si pod „Mám tiskárnu, stáhnout model“ stáhněte jednotlivé díly nebo projekt.'],
    ],
    'faq' => [
        ['q' => 'Jak velkou zásuvku nástroj zvládne?', 'a' => 'Vnitřek zásuvky může mít na šířku i hloubku od 60 do 600 mm a misky mohou být vysoké 15 až 120 mm. Každá miska se tiskne zvlášť, takže i velká zásuvka se skládá z menších dílů, které se vejdou na běžnou tiskárnu.'],
        ['q' => 'Kolik misek může sada mít?', 'a' => 'Nejvýše 24 misek v jedné sadě. Mřížka může mít až 12 sloupců a 12 řad a jedna miska může zabrat více polí; pole musí měřit aspoň 15 mm.'],
        ['q' => 'K čemu je vnější tác?', 'a' => 'Tác drží misky pohromadě, když nestojí v zásuvce, ale na stole nebo na polici. Velký tác se nemusí vejít na tiskárnu, kalkulace to řekne; misky fungují i bez něj.'],
        ['q' => 'Půjdou misky ze zásuvky snadno vyndat?', 'a' => 'Ano. Mezi miskami je vůle, ve výchozím nastavení 0,6 mm, a rohy misek jsou zaoblené. Vůli, tloušťku stěn a dna změníte pod volbou „Tloušťka stěn a další“.'],
        ['q' => 'Může mít každá miska jinou barvu?', 'a' => 'Ano. Každé misce zvolíte barvu v návrhu a při objednávce u nás vyberete barvu každé misky z cívek, které jsou právě založené v tiskárnách, v jedné zakázce. Doma vytisknete každou misku zvlášť ze své cívky.'],
        ['q' => 'Kdy zvolit organizér z jednoho kusu?', 'a' => 'Když má zásuvka do 250 mm, takže se organizér z jednoho kusu vejde na běžnou podložku, a chcete přihrádky pevně na místě, zvolte nástroj Organizér do šuplíku: je to jeden díl bez vůlí. Sada misek se hodí do větší zásuvky a tam, kde se obsah mění: misky přeskládáte nebo jednu doplníte.'],
    ],
    'examples' => [
        'Čtyři misky tří velikostí pro zásuvku 300 × 150 mm, výška 40 mm: 188 g, 3 h 45 min, 310 Kč.',
        'Čtyři stejné čtvercové misky pro zásuvku 160 × 160 mm, výška 30 mm.',
        'Čtyři misky s vnějším tácem na stůl, vnitřek 240 × 120 mm, výška 45 mm.',
    ],
];
