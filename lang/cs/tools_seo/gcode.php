<?php

return [
    'title' => 'Model z G‑kódu: z G‑kódu zpět STL nebo 3MF, i po barvách',
    'description' => 'Nahrajte .gcode do 150 MB a z drah vnějších stěn vznikne vodotěsný model: STL, díly po barvách nebo 3MF projekt. Spočítáte cenu a necháte vytisknout. Zdarma.',
    'h1' => 'Z G‑kódu zpět model: tiskněte znovu, v jiné velikosti i barvách',
    'intro' => [
        'Máte jen G‑kód a původní model se ztratil? Nástroj přečte dráhy tiskové hlavy a z vnějších stěn každé vrstvy postaví zpět plný, vodotěsný model. Povrch leží tam, kde byla vnější stěna výtisku; díry a ostrůvky zůstávají, vnitřní stěny, výplň, podpěry ani límec tvar neovlivní. Rozumí G‑kódu z OrcaSliceru, Bambu Studia, Anycubic Sliceru, PrusaSliceru i Cury, včetně oblouků a změn nástroje.',
        'Z hlavičky souboru vyčte slicer, tiskárnu, výšku vrstvy, čas tisku a spotřebu filamentu. Barevný tisk zachová: více nástrojů v jedné vrstvě dá díl na každou barvu a 3MF projekt s barvami, střídání barev po výšce zůstane jako změny filamentu. Nový model se otevře jako každý jiný soubor: spočítáte cenu, změníte velikost či barvy a necháte vytisknout u nás, nebo si ho stáhnete.',
    ],
    'steps' => [
        ['name' => 'Nahrajte G‑kód', 'text' => 'Přetáhněte soubor .gcode do pole nebo klepněte a vyberte ho. Do 150 MB a 1,5 milionu řádků; nic se nenastavuje.'],
        ['name' => 'Počkejte pár vteřin', 'text' => 'Nástroj najde smyčky vnějších stěn každé vrstvy, poskládá je podle vnoření a vytáhne do tělesa. Běžný výtisk trvá tři až deset vteřin.'],
        ['name' => 'Prohlédněte výsledek', 'text' => 'Náhled ukáže model; zpráva pod ním slicer, tiskárnu, výšku vrstvy, čas, gramy a barvy. Díly po barvách jsou v náhledu obarvené.'],
        ['name' => 'Objednejte tisk, nebo stáhněte', 'text' => 'Spočítejte cenu a nechte model vytisknout u nás, nebo stáhněte STL, díly po barvách či 3MF projekt pro svůj slicer.'],
    ],
    'faq' => [
        ['q' => 'Jak přesný je model z G‑kódu?', 'a' => 'Povrch je tam, kde byl střed vnější stěny plus půl šířky linky, tedy kde byl povrch výtisku. Na známém tělese vyšel objem o 0,8 % větší, půdorys na setiny milimetru, výška zaokrouhlená na vrstvy. Rohy jsou zaoblené na půl linky a schodky vrstev zůstávají, jako na skutečném výtisku; detaily menší než šířka linky chybí.'],
        ['q' => 'Které G‑kódy nástroj přečte?', 'a' => 'G‑kód pro FDM tiskárny se značkami typů drah: OrcaSlicer, Bambu Studio, Anycubic Slicer Next, PrusaSlicer, Cura. Nečte balíčky .gcode.3mf, binární .bgcode, G‑kód bez značek typů (starý Cura, Simplify3D) ani soubory pro pryskyřici či CNC; u takových řekne, že nenašel stěny.'],
        ['q' => 'Co se stane s barvami?', 'a' => 'Více nástrojů v jedné vrstvě (třeba písmeno vložené do destičky) dá díl na každou barvu s barvou filamentu z hlavičky a k tomu 3MF projekt s barvami. Střídání barev po výšce zůstane jako změny filamentu v daných výškách. Jeden filament dá jeden díl.'],
        ['q' => 'Můžu model zvětšit nebo dál upravit?', 'a' => 'Ano, vzniklý model je obyčejný STL: zvětšíte ho v kalkulaci, vydutíte, rozdělíte nebo z něj uděláte formu v dalších nástrojích. Zvětšením se ale zvětší i schodky vrstev, které v modelu zůstaly.'],
        ['q' => 'Kolik to stojí a jak výtisk dostanu?', 'a' => 'Převod je zdarma. Platíte jen tisk, pokud si ho u nás objednáte, z předplaceného kreditu. Výtisk pošleme přes Packetu (Zásilkovnu) na výdejní místo či na adresu v EU.'],
    ],
    'examples' => [],
];
