<?php

// Stránka pro dotaz „klíčenka se jménem 3d tisk“ (docs/AP.md §9). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Klíčenka se jménem na 3D tisk: tvar, jméno a obrázek, zdarma',
    'heading' => 'Klíčenka se jménem na 3D tisk',
    'description' => 'Poskládáte tvar, jméno a obrázek, každou vrstvu svou barvou, a přidáte očko na kroužek. Model stáhnete zdarma, nebo ji vytiskneme od 99 Kč.',
    'h1' => 'Jak klíčenku poskládat z vrstev, jaký otvor na kroužek a kolik stojí',
    'intro' => [
        'Klíčenku si tu poskládáte z vrstev. Dole leží tvar (kost, srdce, kruh, štítek, zaoblený obdélník a další), na něm jméno v jednom ze třiceti písem a třeba obrázek z knihovny siluet nebo váš vlastní. Každá vrstva má svou barvu z filamentů, které máme na farmě, a leží o krok výš než ta pod ní; vrstev může být až dvanáct. Vrstvu vyberete klepnutím v náhledu, tažením ji posunete, rohem rámečku zvětšíte a kolečkem otočíte. Nahoře je očko s otvorem 2 až 8 mm. Barvy leží nad sebou, takže tiskárně stačí ve správné výšce vyměnit filament; na naší farmě vytiskneme až čtyři barvy v jednom tisku. Klíčenku z barevného obrázku, třeba z loga, dělá rychlý formulář, na který stránka odkazuje.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): kost široká 70 mm se jménem Rex ve dvou barvách má 3,8 g a tiskne se 10 minut. Jedna stojí 99 Kč, což je nejnižší cena zakázky; deset stejných stojí 100 Kč, tedy 10 Kč za kus, protože příprava zakázky se platí jednou (kalkulace /c/fwgocmbyzfx6 a /c/teyrxpsrhi4k). Pro další jména uděláte další návrh: rozložení zůstane, přepíšete jen jméno. Kroužek není součástí výtisku. Model i projekt pro slicer s výměnami filamentu stáhnete zdarma a bez registrace.',
    ],
    'steps' => [
        ['name' => 'Začněte od klíčenky', 'text' => 'Stránka se otevře s kostí a jménem Rex. Přepište jméno, změňte tvar, nebo vyberte jinou předvolbu, třeba srdce se jménem.'],
        ['name' => 'Upravte vrstvy', 'text' => 'Klepněte na vrstvu v seznamu nebo v náhledu a změňte text, písmo, tvar, obrázek a barvu. V náhledu ji tažením posunete, rohem rámečku zvětšíte a kolečkem otočíte.'],
        ['name' => 'Přidejte další a nastavte očko', 'text' => 'Tlačítky přidáte text, obrázek nebo tvar, nejvýš dvanáct vrstev. V rozměrech nastavíte tloušťku a otvor očka podle kroužku.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Vedle náhledu vidíte rozměry, přesnou cenu a dobu tisku ukáže kalkulace po „Pokračovat“. Model i projekt pro slicer s výměnami filamentu stáhnete zdarma a bez registrace.'],
    ],
    'faq' => [
        ['q' => 'Jak velký otvor potřebuje kroužek na klíče?', 'a' => 'Na běžný kroužek o průměru 25 až 30 mm stačí otvor 4 až 5 mm; přednastavené jsou 4 mm. Na karabinu nebo tlustší kroužek ho zvětšete až na 8 mm. Kroužek není součástí výtisku.'],
        ['q' => 'Jak velká má klíčenka být?', 'a' => 'Na klíče 40 až 70 mm a tloušťka 3 mm: klíčenka pak váží 3 až 5 g a v kapse nepřekáží. Na batoh nebo tašku může mít 80 až 100 mm, aby bylo jméno čitelné z dálky.'],
        ['q' => 'Z čeho se klíčenka tiskne a vydrží v kapse?', 'a' => 'Doporučujeme PLA nebo pevnější PETG. Při tloušťce 3 mm klíčenka běžné nošení vydrží; pro klíče od auta, které leží na slunci, zvolte PETG, PLA měkne kolem 55 °C.'],
        ['q' => 'Kolik barev může klíčenka mít?', 'a' => 'Každá vrstva má svou barvu a vrstev může být dvanáct. Na naší farmě vytiskneme až čtyři barvy v jednom tisku; návrh s více barvami si stáhnete s projektem, který výměny filamentu obsahuje.'],
        ['q' => 'Mohu použít vlastní obrázek nebo logo?', 'a' => 'Ano. Jako vrstvu nahrajete SVG, PNG nebo JPG a zůstane z něj silueta v jedné barvě. Logo ve více barvách zpracuje rychlý formulář, na který stránka odkazuje.'],
        ['q' => 'Co když prvky leží vedle sebe a nedotýkají se?', 'a' => 'Nástroj je ve spodní vrstvě spojí krátkým můstkem a upozorní na to, takže klíčenka drží pohromadě.'],
    ],
    'examples' => [
        'Kost 70 × 32 mm se jménem Rex a očkem na kroužek, dvě barvy: 3,8 g, 10 minut, 99 Kč.',
        'Srdce 60 mm se jménem Ema v písmu Lobster, očko nahoře.',
        'Kulatá klíčenka 45 mm s tlapkou, černá a žlutá.',
    ],
];
