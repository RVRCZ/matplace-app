<?php

// Stránka pro dotaz „světelný nápis 3d tisk“ (docs/AP.md §9). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'Světelný nápis na 3D tisk s LED páskem: logo nebo text',
    'heading' => 'Světelný nápis na 3D tisk s LED páskem',
    'description' => 'Napíšete nápis nebo nahrajete logo a nástroj navrhne světelnou ceduli ze čtyř tištěných dílů pro LED pásek na 5 V. Díly vytiskneme, nebo model stáhnete.',
    'h1' => 'Z čeho se světelný nápis skládá, co si pořídit a kolik stojí díly',
    'intro' => [
        'Nástroj navrhne světelný nápis ze čtyř tištěných dílů: tělo, čelní masku s vyříznutým motivem, difuzor, který rozptýlí světlo, a zadní kryt. Motivem je nápis do dvou řádků po 16 znacích, nebo nahrané SVG či jednoduchý obrázek; vnitřky písmen v masce podrží úzké můstky. Tvar může být obdélníkový, nebo kulatý s rovnou patou, aby stál na polici. Šířku nastavíte od 80 do 300 mm a hloubku od 25 do 80 mm, výška obdélníku se dopočítá podle motivu a okraje. Světelný zdroj zvolíte předem: LED pásek šířky 8 nebo 10 mm, nebo LED modul s ovladačem; podle něj nástroj hlídá nejmenší hloubku těla a vypíše, kolik metrů pásku se vejde po vnitřním obvodu. Tiskneme jen plastové díly, LED pásek na 5 V, USB napájení a oboustrannou pásku si pořídíte zvlášť.',
        'Příklad z kalkulace na produkci (28. 10. 2026, PLA): obdélníkový nápis OPEN široký 180 mm a hluboký 37 mm, všechny čtyři díly vedle sebe, má 97 g, tiskne se 2 h 17 min a stojí u nás 180 Kč v jedné barvě, bez dopravy (kalkulace /c/haqwdtiseafu). Čelní maska a tělo mají být tmavé, aby světlo procházelo jen motivem, difuzor bílý nebo průsvitný; jak barvy rozdělit mezi díly, říká otázka níže. Sestavení je na vás: pásek nalepíte po obvodu, kabel protáhnete otvorem, difuzor a masku vložíte zepředu. Díly si stáhnete zdarma jako STL nebo projekt pro svou tiskárnu.',
    ],
    'steps' => [
        ['name' => 'Napište nápis nebo nahrajte logo', 'text' => 'Nápis napíšete do jednoho nebo dvou řádků, nebo nahrajete SVG či obrázek s tmavým motivem na světlém pozadí. Vnitřky písmen v masce podrží úzké můstky.'],
        ['name' => 'Vyberte tvar a světelný zdroj', 'text' => 'Zvolíte obdélníkový, nebo kulatý tvar a světelný zdroj: LED pásek šířky 8 mm, 10 mm, nebo LED modul s ovladačem. Podle zdroje nástroj hlídá nejmenší hloubku těla.'],
        ['name' => 'Nastavte rozměry', 'text' => 'Zadáte šířku a hloubku. U obdélníkového tvaru se výška dopočítá podle motivu a okraje kolem něj.'],
        ['name' => 'Prohlédněte si díly', 'text' => 'Náhled ukazuje sestavu roztaženou po vrstvách a lze ho přepnout na jednotlivé díly. Pod ním je seznam věcí, které nejsou součástí výtisku.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Tisk objednáte z naší farmy. Nebo si díly zdarma stáhnete jako soubory STL či hotový projekt pro svou tiskárnu.'],
    ],
    'faq' => [
        ['q' => 'Je LED pásek součástí výtisku?', 'a' => 'Ne. Tiskneme čtyři plastové díly. LED pásek nebo modul, USB kabel s napájením a oboustrannou pásku či lepidlo si pořídíte sami; nástroj pod náhledem vypíše, co a kolik.'],
        ['q' => 'Jaký LED pásek se do nápisu hodí?', 'a' => 'Pásek na 5 V široký 8 nebo 10 mm, případně LED modul nebo pásek s ovladačem do rozměru 18 × 8 mm. Potřebnou délku pásku nástroj vypíše pod náhledem. Kabel vede otvorem v těle o průměru 3 až 10 mm; na 5 V stačí USB nabíječka.'],
        ['q' => 'V jakých barvách díly tisknout?', 'a' => 'Tělo, čelní maska a zadní kryt mají být tmavé, aby světlo procházelo jen motivem, a difuzor bílý nebo průsvitný. Jedna zakázka se u nás tiskne jednou barvou. Difuzor si proto stáhněte jako samostatný díl a nahrajte ho jako vlastní model do druhé zakázky, nebo ho vytiskněte doma z bílého PLA.'],
        ['q' => 'Jak nápis sestavím?', 'a' => 'LED pásek nalepíte po vnitřním obvodu těla a kabel protáhnete otvorem. Difuzor a čelní maska se zepředu vloží na osazení v těle, zadní kryt se nasadí zezadu. Nic se nešroubuje; kdo chce, díly zajistí kapkou lepidla.'],
        ['q' => 'Jak velký může nápis být?', 'a' => 'Šířka je 80 až 300 mm a výška obdélníkového nápisu vychází z motivu, nejvýše 300 mm. Kulatý tvar je vysoký devět desetin šířky. Každý díl se musí vejít na tiskovou podložku, na což upozorní kalkulace v dalším kroku.'],
        ['q' => 'Dá se nápis pověsit na zeď nebo do výlohy?', 'a' => 'Tělo má rovnou zadní stranu, takže ho přilepíte oboustrannou páskou nebo zavěsíte na háček za zadní kryt; kabel vede dolů nebo dozadu. Do výlohy na slunce zvolte PETG, PLA vedle rozpáleného skla měkne.'],
    ],
    'examples' => [
        'Obdélníkový světelný nápis OPEN, 180 × 64 mm, hloubka 37 mm, pro LED pásek: 97 g, 2 h 17 min, 180 Kč.',
        'Kulatý světelný nápis BAR s rovnou patou, 160 × 144 mm, stojí na polici.',
        'Dvouřádkový světelný nápis CAFÉ LUNA, 240 × 172 mm, na pult nebo do výlohy.',
    ],
];
