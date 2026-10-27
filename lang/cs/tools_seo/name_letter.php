<?php

// Stránka pro dotaz „písmeno se jménem dárek“ (docs/AP.md §6). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Velké písmeno se jménem: 3D dekorace a dárek na míru, zdarma',
    'heading' => 'Velké písmeno se jménem jako dárek',
    'description' => 'Napíšete jméno a vznikne jeho velké první písmeno s celým jménem druhou barvou. Vytiskneme ho ve dvou barvách od 99 Kč, nebo si model stáhnete zdarma.',
    'h1' => 'Jak písmeno se jménem vzniká, kdy stojí samo a kolik stojí',
    'intro' => [
        'Nástroj vytvoří velké první písmeno jména a na něj napíše celé jméno. Stačí zadat jméno do 20 znaků: písmeno se vybere samo, nebo zvolíte jiné, třeba iniciálu příjmení. Výšku nastavíte od 60 do 200 mm a tloušťku od 3 do 15 mm; od 10 mm většina písmen sama stojí na poličce a kterékoli lze postavit do podstavce s drážkou, který se vytiskne vedle něj. Velké písmeno má tři tučná písma, jméno třicet včetně psaných rukou. Jméno si nástroj umístí sám tam, kde má písmeno nejvíc místa: napříč vodorovným tahem, nebo nastojato podél svislého; vystupuje o 0,4 až 2 mm a tiskne se druhou barvou. Dvě barvy leží nad sebou, takže tisk stačí jednou zastavit a vyměnit filament; na naší farmě písmeno vytiskneme najednou a obě barvy si vyberete při objednávce.',
        'Příklad z kalkulace na produkci (27. 10. 2026, PLA): písmeno E vysoké 120 mm a silné 8 mm se jménem Ela psacím písmem má 27 g, tiskne se 40 minut a stojí u nás 99 Kč, což je nejnižší cena zakázky (kalkulace /c/vhfifzrzy5in, jedna barva, druhou vyberete při objednávce). Při 8 mm ho postavíte do podstavce z téhož souboru, od 10 mm stojí samo. Tiskne se naplocho bez podpěr. Hodí se na dveře dětského pokoje, na poličku nebo jako dárek k narození a ke jmeninám; model i projekt s výměnou filamentu stáhnete zdarma a bez registrace.',
    ],
    'steps' => [
        ['name' => 'Napište jméno', 'text' => 'Velké písmeno je první písmeno jména. Když chcete jiné, třeba iniciálu příjmení, napište ho do druhého pole.'],
        ['name' => 'Vyberte písma', 'text' => 'Pro velké písmeno bezpatkové, patkové nebo strojové, vždy tučné. Pro jméno kterékoli ze třiceti písem.'],
        ['name' => 'Nastavte výšku, tloušťku a barvy', 'text' => 'Náhled ukazuje, kam se jméno vešlo a jak je velké. Písmenu i jménu přiřadíte filament z těch, které máme skladem.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu a dobu tisku ukáže kalkulace po „Pokračovat“. Model i projekt s výměnou filamentu stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Kam se jméno na písmeno napíše?', 'a' => 'Tam, kde má písmeno nejvíc místa. U písmen se svislým tahem (E, T, L, I, K) jde jméno nastojato podél něj, u širokých vodorovných tahů napříč. Místo najde nástroj sám a náhled ho hned ukáže.'],
        ['q' => 'Co když se jméno nevejde?', 'a' => 'U oblých a úzkých písmen (O, S, C) vychází jméno menší. Když by bylo nižší než 4 mm, nástroj na to upozorní: pomůže větší výška písmene, kratší jméno nebo jiné písmo velkého písmene.'],
        ['q' => 'Můžu místo jména napsat datum nebo slovo?', 'a' => 'Ano, jakýkoli text do 20 znaků: „Ela 2026“, „Novákovi“ nebo „Vánoce“. Velké písmeno pak zvolíte ručně v druhém poli, aby odpovídalo tomu, co má dekorace říkat.'],
        ['q' => 'Bude písmeno stát?', 'a' => 'Od tloušťky 10 mm většina písmen stojí sama na rovné ploše; tenčí postavíte do podstavce s drážkou, který je v souboru. Písmena s úzkou patou, jako P nebo F, je jistější opřít nebo přilepit oboustrannou páskou na dveře.'],
        ['q' => 'Z čeho se tiskne a kolik barev má?', 'a' => 'Doporučujeme PLA. Písmeno má dvě barvy: tělo a jméno, které leží nad ním. Stačí jedna výměna filamentu, takže ho vytiskne každá tiskárna; u nás si obě barvy vyberete při objednávce.'],
        ['q' => 'Jak velké písmeno můžete vytisknout?', 'a' => 'Až 200 mm na výšku, podložka naší farmy má 250 × 250 mm. Náhled u rozměrů ukazuje, jestli se písmeno na podložku vejde; široká písmena jako M a W jsou při stejné výšce širší.'],
    ],
    'examples' => [
        'Písmeno E vysoké 120 mm se jménem Ela psacím písmem podél svislého tahu: 27 g, 40 minut, 99 Kč.',
        'Patkové písmeno T vysoké 150 mm se jménem Tomáš.',
        'Písmeno I vysoké 100 mm a silné 12 mm se jménem Ivana, stojí samo.',
    ],
];
