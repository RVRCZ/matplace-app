<?php

// Stránka pro dotaz „kontrola stl před tiskem“ (docs/AP.md §1, §3). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Kontrola STL před tiskem online zdarma: rozměry, síť, stěny',
    'heading' => 'Kontrola STL souboru před tiskem',
    'description' => 'Nahrajete model a za chvíli víte, jestli má správné jednotky, uzavřenou síť, otočené plochy a vejde se na tiskárnu. U každého nálezu je, co znamená při tisku.',
    'h1' => 'Co kontrola hlídá, co nehodnotí a jak číst zprávu',
    'intro' => [
        'Kontrola je pro chvíli, kdy máte STL, 3MF, OBJ nebo STEP a nevíte, jestli ho tiskárna vezme. Nahrajete soubor a do pár vteřin dostanete zprávu ve třech skupinách: chyby, doporučení a co je v pořádku, u každého nálezu s tím, co udělá při tisku a jak to napravit. Hlídá jednotky (model menší než 1 mm je skoro jistě uložený v metrech nebo palcích, větší než 2 m naopak), nejtenčí rozměr pod 0,8 mm, který tryska 0,4 mm nevytiskne, uzavřenost sítě, převrácené plochy, počet samostatných těles, síť nad 1,5 milionu trojúhelníků, která zdržuje slicer, a síť pod 40 trojúhelníků, která dá hranatý výtisk. Řekne také, jestli se model vejde na tiskárnu s prostorem 250 × 250 × 250 mm; u sady dílů měří největší díl. Převisy, pevnost stěn ani přesnost nehodnotí, protože závisí na tiskárně, materiálu a natočení modelu. Soubor jen čte a nic v něm nemění.',
        'Příklad z 27. 10. 2026: busta 60 × 52 × 78 mm, do které jsme vyřízli tři díry a část ploch otočili. Kontrola vrátila tři řádky: rozměr 60 × 52 × 78 mm v pořádku, chyba „síť není uzavřená“ a doporučení „51 samostatných těles“. Jedním tlačítkem jsme ji poslali do opravy; po ní je síť uzavřená, těleso jedno a kontrola čistá. Zdravý model jde rovnou dál: tlačítkem k ceně se otevře v kalkulaci, kde vidíte gramy, dobu tisku a cenu u nás, nebo si stáhnete projekt pro svou tiskárnu. Kontrola je zdarma, bez účtu a bez limitu souborů.',
    ],
    'steps' => [
        ['name' => 'Nahrajte model', 'text' => 'Soubor STL, 3MF, OBJ nebo STEP vyberte nebo přetáhněte do pole. Největší velikost souboru je napsaná přímo u něj.'],
        ['name' => 'Přečtěte si zprávu', 'text' => 'Vedle otočného náhledu jsou nálezy ve třech skupinách: „Chyby“, „Doporučení“ a „V pořádku“, každý s vysvětlením pro tisk.'],
        ['name' => 'Opravte, nebo pokračujte k ceně', 'text' => 'Díry a převrácené plochy pošlete jedním tlačítkem do opravy. Zdravý model otevřete v kalkulaci, kde ho objednáte, nebo si ho stáhnete.'],
    ],
    'faq' => [
        ['q' => 'Co přesně kontrola hlídá?', 'a' => 'Jednotky (pod 1 mm a nad 2 m je chyba, pod 5 mm upozornění), nejtenčí rozměr pod 0,8 mm, díry v síti, převrácené plochy, víc samostatných těles, síť nad 1,5 milionu nebo pod 40 trojúhelníků a to, jestli se model vejde na 250 × 250 × 250 mm.'],
        ['q' => 'Zaručí kontrola, že se model vytiskne dobře?', 'a' => 'Ne. Převisy, pevnost stěn a přesnost závisí na tiskárně, materiálu a natočení modelu a kontrola je neposuzuje. Říká jen, co se dá spolehlivě zjistit ze souboru.'],
        ['q' => 'Kontrola našla díry v síti nebo převrácené plochy. Co dál?', 'a' => 'Tlačítko vedle nálezu pošle model do opravy. Ta díry zacelí, plochy otočí a opravený STL si stáhnete zdarma; zpráva opravy ukáže čísla před a po.'],
        ['q' => 'Proč kontrola hlásí, že model měří jen zlomek milimetru?', 'a' => 'Byl uložený v metrech nebo palcích místo milimetrů. Uložte ho znovu v milimetrech, nebo ho v kalkulaci zvětšete měřítkem 1000 (metry) či 25,4 (palce).'],
        ['q' => 'Model se nevejde na tiskárnu. Co s ním?', 'a' => 'Kontrola napíše, který rozměr je větší než 250 mm. Buď ho v kalkulaci zmenšíte měřítkem, nebo ho nástrojem Rozdělit model rozřežete na díly s kolíky, které se vejdou.'],
        ['q' => 'Kontrola hlásí víc samostatných těles. Je to chyba?', 'a' => 'U krabičky s víčkem nebo u sady dílů je to záměr a kontrola to u našich nástrojů ví. U staženého modelu to bývají úlomky rozbité sítě nebo smítka; oprava je spojí, nebo odstraní.'],
    ],
    'examples' => [],
];
