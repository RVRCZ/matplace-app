<?php

// Stránka pro dotaz „stojánek na telefon 3d tisk“ (docs/AP.md §9). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Stojánek na telefon na míru k 3D tisku: šest typů, zdarma',
    'heading' => 'Stojánek na telefon na míru k 3D tisku',
    'description' => 'Vyberete jeden ze šesti typů, zadáte tloušťku telefonu s obalem a sklon a hned vidíte náhled. Model stáhnete zdarma, nebo stojánek vytiskneme od 120 Kč.',
    'h1' => 'Jaké stojánky nástroj umí, jak změřit telefon a kolik stojí tisk',
    'intro' => [
        'Nástroj nabízí šest typů držáku: desku s háčky, vlnku, A‑rám s poličkou, nízký klín, kapsu na zeď a klip do auta na mřížku ventilace; k tomu provedení s vyříznutým tvarem v opěrce, vlnitý plech, plochou desku se zásuvnou nohou, stuhu do Z nebo siluetu zvířete ze sedlem. Stojánek se počítá podle tloušťky telefonu i s obalem, kterou zadáte od 7 do 20 mm, a šířky; u stolních typů nastavíte sklon a výšku opěrky až 200 mm, šířku až 260 mm, takže klín a široké typy poslouží i tabletu. Podle typu přidáte výřez na nabíjecí kabel, odlehčovací okno v opěrce nebo otvory na šrouby. Sklon je daný návrhem a po vytištění se nedá měnit; stojánek telefon jen drží, nabíječku v sobě nemá.',
        'Příklad z kalkulace na produkci (18. 10. 2026, PLA+): nízký klín široký 80 mm se sklonem 60° má 39 g, tiskne se 1 h 8 min a stojí u nás 120 Kč; A‑rám s poličkou široký 75 mm se sklonem 65° má 65 g, tiskne se 1 h 45 min a stojí 170 Kč, obojí bez dopravy. Na stůl a na zeď stačí PLA, klip do auta tiskněte z PETG, které lépe snáší teplo za sklem. Model si stáhnete zdarma jako STL nebo projekt pro svou tiskárnu (na výběr je asi 270 tiskáren), nebo ho necháte vytisknout u nás; stojánek na telefon je po medaili náš nejčastěji objednávaný nástroj.',
    ],
    'steps' => [
        ['name' => 'Vyberte typ držáku', 'text' => 'Na stůl se hodí deska s háčky, vlnka, A‑rám s poličkou nebo nízký klín. Kapsa patří na zeď a klip na mřížku ventilace v autě.'],
        ['name' => 'Změřte telefon s obalem', 'text' => 'Zadejte tloušťku telefonu i s obalem, od 7 do 20 mm, a šířku. Nástroj přidá malou rezervu sám.'],
        ['name' => 'Upravte sklon a doplňky', 'text' => 'Nastavte sklon a výšku opěrky. Podle typu přidáte výřez na nabíjecí kabel, odlehčovací okno v opěrce nebo upevnění na šrouby.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Zkontrolujte náhled a tlačítkem „Pokračovat“ přejděte k přesné ceně. Nebo si model stáhněte zdarma pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Jaký sklon zvolit?', 'a' => 'Na sledování videa na stole 60° (nízký klín), na psaní a odemykání obličejem 65 až 70° (A‑rám nebo vlnka), na noční stolek méně, aby na displej nebylo vidět z postele. Sklon je v modelu napevno, po vytištění se nemění; když si nejste jistí, vytiskněte dva.'],
        ['q' => 'Vejde se do stojánku telefon s obalem?', 'a' => 'Ano, když zadáte tloušťku telefonu i s obalem. Nástroj počítá s tloušťkou od 7 do 20 mm a malou rezervu přidá sám; moc velká rezerva by telefon nechala viklat.'],
        ['q' => 'Mohu telefon ve stojánku nabíjet?', 'a' => 'Ano. Vlnka, A‑rám, klín, kapsa na zeď i klip do auta mohou mít výřez na nabíjecí kabel a ve výchozím nastavení ho mají. Deska s háčky výřez nemá, telefon v ní stojí na dvou háčcích.'],
        ['q' => 'Hodí se stojánek i pro tablet?', 'a' => 'Šířku lze nastavit až na 260 mm a výšku opěrky až na 200 mm, takže klín a široké stolní typy tabletu poslouží. Zařízení s obalem ale může být silné nejvýše 20 mm.'],
        ['q' => 'Jak se upevní kapsa na zeď a klip do auta?', 'a' => 'Kapsa se lepí oboustrannou páskou, nebo ji po zapnutí volby se šrouby přišroubujete čtyřmi šrouby. Klip se nasadí na lamelu ventilace, jejíž tloušťku zadáte od 1 do 4 mm. Páska ani šrouby nejsou součástí výtisku.'],
        ['q' => 'Z jakého materiálu stojánek tisknout?', 'a' => 'Na stůl a na zeď stačí PLA; matný filament skryje vrstvy. Klip do auta a stojánek na palubní desku tiskněte z PETG, protože PLA za sklem na slunci měkne už kolem 55 °C.'],
    ],
    'examples' => [
        'Stolní A‑rám s poličkou, šířka 75 mm, sklon opěrky 65°, výřez na kabel: 65 g, 1 h 45 min, 170 Kč.',
        'Nízký klín na sledování videa, šířka 80 mm, hloubka 70 mm, sklon 60°: 39 g, 1 h 8 min, 120 Kč.',
        'Kapsa na zeď pro telefon široký 80 mm, čtyři otvory na šrouby.',
        'Stolní A‑rám s vyříznutou kočkou v opěrce, šířka 80 mm; tvar z knihovny nebo vlastní.',
        'Vlnitý plech do S, šířka 70 mm, sklon 70°, žebra každých 6 mm.',
        'Plochá deska se srdíčky a zásuvnou nohou, šířka 70 mm, dva díly naplocho.',
        'Stuha do Z, pás 10 mm s proužkem druhé barvy, šířka 70 mm.',
        'Silueta psa z knihovny, výška 100 mm, sedlo na telefon vpředu.',
    ],
];
