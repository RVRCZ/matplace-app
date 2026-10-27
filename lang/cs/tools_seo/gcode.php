<?php

// Stránka pro dotaz „gcode do stl“ (docs/AP.md §1, §4). 'heading' = H1 stránky, 'h1' = nadpis obsahu pod nástrojem.
return [
    'title' => 'G‑kód do STL online zdarma: z G‑kódu zpět model po barvách',
    'heading' => 'Převod G‑kódu zpět na STL',
    'description' => 'Nahrajete .gcode do 150 MB a z drah vnějších stěn vznikne uzavřený model: STL, díly po barvách nebo 3MF projekt. Spočítáte cenu a vytisknete. Zdarma.',
    'h1' => 'Co z G‑kódu vznikne, jak přesně a co se ztratí',
    'intro' => [
        'Máte jen G‑kód a původní model se ztratil, nebo jste ho dostali bez zdroje? Nástroj přečte dráhy tiskové hlavy a z vnějších stěn každé vrstvy postaví zpět plný, uzavřený model. Povrch leží tam, kde byla vnější stěna výtisku; díry a ostrůvky zůstávají, vnitřní stěny, výplň, podpěry, límec ani čisticí věž tvar neovlivní. Rozumí G‑kódu z OrcaSliceru, Bambu Studia, Anycubic Sliceru, PrusaSliceru i Cury, včetně oblouků a změn nástroje, do 150 MB a 1,5 milionu řádků. Z hlavičky vyčte slicer, tiskárnu, výšku vrstvy, čas tisku a spotřebu filamentu. Barvy zachová: více nástrojů v jedné vrstvě dá díl na každou barvu a 3MF projekt s barvami, střídání barev po výšce zůstane jako změny filamentu.',
        'Příklad z 27. 10. 2026 (spuštěno na našem počítači, soubory z naší tiskárny): G‑kód z Anycubic Slicer Next pro Kobra S1 o 5,6 MB a 191 tisících řádků, 165 vrstev po 0,2 mm, tisk 36 minut a 3,6 g, dal za 12 vteřin model 91 × 57 × 16,5 mm s 522 tisíci trojúhelníky. Dvoubarevný G‑kód o 14 MB a 545 tisících řádků (39 vrstev po 0,12 mm, 5 h 16 min, 81 g) dal za 9 vteřin dva díly po barvách, černý 49 g a žlutý Silk 33 g, s barvami z hlavičky. Na známém tělese vyšel objem o 0,8 % větší než původní model. Nový model se otevře jako každý jiný soubor: spočítáte cenu, změníte velikost či barvy a necháte vytisknout u nás, nebo si stáhnete STL, díly po barvách či 3MF projekt.',
    ],
    'steps' => [
        ['name' => 'Nahrajte G‑kód', 'text' => 'Přetáhněte soubor .gcode do pole nebo klepněte a vyberte ho. Do 150 MB a 1,5 milionu řádků; nic se nenastavuje.'],
        ['name' => 'Počkejte pár vteřin', 'text' => 'Nástroj najde smyčky vnějších stěn každé vrstvy, poskládá je podle vnoření a vytáhne do tělesa. Běžný výtisk trvá tři až dvanáct vteřin.'],
        ['name' => 'Prohlédněte výsledek', 'text' => 'Náhled ukáže model; zpráva pod ním slicer, tiskárnu, výšku vrstvy, čas, gramy a barvy. Díly po barvách jsou v náhledu obarvené.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Spočítejte cenu a nechte model vytisknout u nás, nebo stáhněte STL, díly po barvách či 3MF projekt pro svůj slicer.'],
    ],
    'faq' => [
        ['q' => 'Jak přesný je model z G‑kódu?', 'a' => 'Povrch je tam, kde byl střed vnější stěny plus půl šířky linky, tedy kde byl povrch výtisku. Na známém tělese vyšel objem o 0,8 % větší, půdorys na setiny milimetru, výška zaokrouhlená na vrstvy. Rohy jsou zaoblené na půl linky a schodky vrstev zůstávají, jako na skutečném výtisku.'],
        ['q' => 'Které G‑kódy nástroj přečte?', 'a' => 'G‑kód pro FDM tiskárny se značkami typů drah: OrcaSlicer, Bambu Studio, Anycubic Slicer Next, PrusaSlicer, Cura. Nečte binární .bgcode, G‑kód bez značek typů (starý Cura, Simplify3D) ani soubory pro pryskyřici či CNC; u takových řekne, že nenašel stěny. Balíček .gcode.3mf z Bambu Studia je zip: rozbalte ho a nahrajte soubor .gcode z jeho složky Metadata.'],
        ['q' => 'Co se stane s barvami?', 'a' => 'Více nástrojů v jedné vrstvě (třeba písmeno vložené do destičky) dá díl na každou barvu s barvou filamentu z hlavičky a k tomu 3MF projekt s barvami. Střídání barev po výšce zůstane jako změny filamentu v daných výškách. Jeden filament dá jeden díl.'],
        ['q' => 'Proč v modelu chybí drobné detaily a text?', 'a' => 'Model vzniká jen z vnějších stěn: co je užší než jedna linka (0,4 mm), slicer nikdy netiskl, a tak to v G‑kódu není. Výplň, horní a spodní vrstvy tvar nedělají. Reliéfní text nebo jemný vzor z původního modelu se proto vrátí jen tak hrubý, jak ho tiskárna opravdu tiskla.'],
        ['q' => 'Můžu model zvětšit nebo dál upravit?', 'a' => 'Ano, vzniklý model je obyčejný STL: zvětšíte ho v kalkulaci, vydutíte, rozdělíte na díly nebo z něj uděláte formu. Zvětšením se ale zvětší i schodky vrstev, které v modelu zůstaly; pro dvojnásobek a víc je lepší povrch přebrousit.'],
        ['q' => 'Co s G‑kódem tištěným ve vázovém režimu?', 'a' => 'Spirála nemá uzavřené smyčky, nástroj proto každou vrstvu uzavře tam, kde spirála prochází její výškou. Model vyjde plný; chcete‑li ho zase tisknout jako vázu, zapněte vázový režim ve sliceru znovu.'],
    ],
    'examples' => [],
];
