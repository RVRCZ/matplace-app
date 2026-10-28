<?php

// Stránka pro dotaz „váza 3d tisk“ (docs/AP.md §9). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Váza na 3D tisk na míru: spirálová, žebrovaná, květináč',
    'heading' => 'Váza na 3D tisk podle vašich rozměrů',
    'description' => 'Zvolíte výšku, průměry, tvar a povrch: hladký, žebrovaný nebo spirálový; obal na květináč má drenáž a podmisku. Model zdarma, nebo vázu vytiskneme.',
    'h1' => 'Jaké tvary váza umí, kolik stojí tisk a jestli udrží vodu',
    'intro' => [
        'Nástroj vytvoří vázu nebo obal na květináč podle výšky a průměrů, které zadáte: výška 40 až 300 mm, horní i spodní průměr 30 až 250 mm. Vyberete jeden ze čtyř tvarů (úzké hrdlo, vypouklý, rovný kužel nebo tulipán) a povrch hladký, žebrovaný nebo spirálový; u žebrovaného a spirálového nastavíte počet žeber, hloubku žlábků a zkroucení a zvolíte vlnky ostré, nebo kulaté. U tvaru s úzkým hrdlem posunete zúžení výš nebo níž a můžete mít až tři nad sebou. Obal na květináč může mít drenážní otvory ve dně a samostatnou podmisku. Stěna a dno mají výchozích 1,6 mm (stěna 0,8 až 4 mm, dno 0,8 až 5 mm). Náhled ukazuje skutečné vnější rozměry, protože vypouklé tvary a žebra jsou širší než zadané průměry.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): hladká váza s úzkým hrdlem vysoká 160 mm má 76 g, tiskne se 1 h 57 min a stojí u nás 190 Kč; spirálová váza 180 mm s 18 žebry má 80 g, tiskne se 5 h 7 min a stojí 280 Kč; žebrovaný obal na květináč 120 mm s drenáží má 99 g, tiskne se 3 h 45 min a stojí 260 Kč bez podmisky, vše bez dopravy. Obal s podmiskou vedle sebe měří 287 mm, takže ho kalkulace tiskne na dvě podložky nebo ho zmenšíte. Vytištěná váza nemusí být vodotěsná; na řezané květiny vložte dovnitř sklenici. Model si stáhnete zdarma jako STL nebo projekt pro svou tiskárnu.',
    ],
    'steps' => [
        ['name' => 'Zvolte účel a tvar', 'text' => 'Vyberte vázu, nebo obal na květináč, a jeden ze čtyř tvarů. Můžete začít od předvolby „Spirálová“, „Žebrovaná“, „Hladká“ nebo „Květináč“.'],
        ['name' => 'Zadejte rozměry', 'text' => 'Nastavte výšku, horní průměr a spodní průměr. U obalu změřte květináč, který do něj vložíte, a přidejte rezervu.'],
        ['name' => 'Dolaďte povrch', 'text' => 'U žebrovaného a spirálového povrchu nastavte počet žeber, hloubku žlábků a zkroucení. Nástroj upozorní, když by stěna mezi žlábky vyšla příliš tenká.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Zkontrolujte náhled a tlačítkem „Pokračovat“ přejděte k přesné ceně. Nebo si model stáhněte zdarma pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Udrží vytištěná váza vodu?', 'a' => 'Nemusí. Mezi vrstvami mohou zůstat mikroskopické netěsnosti. Na řezané květiny vložte do vázy sklenici nebo zkumavku, nebo vnitřek utěsněte lakem; na sušené květiny a jako obal na květináč to řešit nemusíte.'],
        ['q' => 'Jak velkou vázu mohu navrhnout?', 'a' => 'Výška může být od 40 do 300 mm, horní i spodní průměr od 30 do 250 mm. Vysoká nebo široká váza se nemusí vejít na každou tiskárnu; podložka naší farmy má 250 × 250 mm a kalkulace to řekne.'],
        ['q' => 'Proč spirálová váza stojí víc než hladká?', 'a' => 'Žebra a zkroucení přidávají plochu a tiskárna jede po klikaté dráze: spirálová váza 180 mm se tiskla 5 h 7 min, hladká 160 mm 1 h 57 min při podobné hmotnosti. Cena se počítá z gramů a minut, proto je v tom rozdíl 90 Kč.'],
        ['q' => 'K čemu jsou drenážní otvory a podmiska?', 'a' => 'U obalu na květináč odteče otvory ve dně přebytečná voda a podmiska ji zachytí. Podmiska je samostatný díl a její průměr nástroj dopočítá podle dna.'],
        ['q' => 'Proč je váza širší než zadané průměry?', 'a' => 'Průměry platí pro horní okraj a dno. Tvar s úzkým hrdlem a vypouklý tvar se mezi nimi rozšiřují a žebra přidají další milimetry. Skutečné vnější rozměry ukazuje náhled.'],
        ['q' => 'Jak silná je stěna a dá se tisknout ve vázovém režimu?', 'a' => 'Výchozí stěna i dno mají 1,6 mm; stěnu změníte od 0,8 do 4 mm, dno od 0,8 do 5 mm. Stažený STL je plné těleso, takže ho ve sliceru můžete tisknout i ve vázovém režimu jednou stěnou; u nás tiskneme stěnu podle návrhu s výplní, která lépe drží vodu.'],
    ],
    'examples' => [
        'Spirálová váza s úzkým hrdlem, výška 180 mm, horní průměr 62 mm, 18 žeber: 80 g, 5 h 7 min, 280 Kč.',
        'Hladká váza s úzkým hrdlem, výška 160 mm, horní průměr 70 mm: 76 g, 1 h 57 min, 190 Kč.',
        'Žebrovaný obal na květináč s podmiskou, výška 120 mm, horní průměr 130 mm: 99 g bez podmisky, 3 h 45 min, 260 Kč.',
        'Váza se dvěma zúženími a kulatými vlnkami, výška 200 mm, horní průměr 70 mm.',
    ],
];
